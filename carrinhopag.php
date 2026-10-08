<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/php/crud.php';
require_once __DIR__ . '/php/product_images.php';

$checkoutError = '';
$checkoutSuccess = (string) ($_SESSION['checkout_success'] ?? '');
unset($_SESSION['checkout_success']);

$cart = $_SESSION['carrinho'] ?? [];
$cart = is_array($cart) ? $cart : [];
$cartItems = [];
foreach ($cart as $productId => $quantity) {
    $productId = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($productId !== false && $quantity !== false) {
        $cartItems[$productId] = $quantity;
    }
}

$products = [];
$subtotalCents = 0;
if ($cartItems !== []) {
    $placeholders = implode(',', array_fill(0, count($cartItems), '?'));
    $statement = $pdo->prepare(
        "SELECT id_produto, img_produto, nome_produto, preco, estoque FROM produtos WHERE id_produto IN ({$placeholders})"
    );
    $statement->execute(array_keys($cartItems));
    foreach ($statement->fetchAll() as $product) {
        $productId = (int) $product['id_produto'];
        $products[$productId] = $product;
        $subtotalCents += (int) round((float) $product['preco'] * 100) * $cartItems[$productId];
    }
    if (count($products) !== count($cartItems)) {
        $checkoutError = 'Um produto do carrinho não está mais disponível.';
    }
}

$tipCents = 500;
$paymentMethods = ['Cartão', 'Pix', 'Dinheiro'];
$collectionMethods = ['Retirada', 'Entrega'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id_user'])) {
        $checkoutError = 'Entre na sua conta para finalizar o pedido.';
    } elseif (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        $checkoutError = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($cartItems === [] || count($products) !== count($cartItems)) {
        $checkoutError = 'Adicione produtos disponíveis ao carrinho antes de finalizar.';
    } else {
        $collector = trim((string) ($_POST['nome_coletor'] ?? ''));
        $note = trim((string) ($_POST['nota_pedido'] ?? ''));
        $collectionMethod = (string) ($_POST['metodo_coleta'] ?? '');
        $paymentMethod = (string) ($_POST['metodo_pagamento'] ?? '');
        $tip = (string) ($_POST['gorjeta'] ?? '');
        $zipCode = trim((string) ($_POST['cep'] ?? ''));
        $deliveryAddress = trim((string) ($_POST['endereco_entrega'] ?? ''));

        if ($collector === '' || mb_strlen($collector) > 100 || mb_strlen($note) > 2000) {
            $checkoutError = 'Preencha o nome de quem receberá o pedido e confira a observação.';
        } elseif (!in_array($collectionMethod, $collectionMethods, true)) {
            $checkoutError = 'Selecione retirada ou entrega.';
        } elseif (!in_array($paymentMethod, $paymentMethods, true)) {
            $checkoutError = 'Selecione uma forma de pagamento válida.';
        } elseif (!in_array($tip, ['0', '5', '7', '9', '0.00', '5.00', '7.00', '9.00'], true)) {
            $checkoutError = 'Selecione uma gorjeta válida.';
        } elseif ($collectionMethod === 'Entrega' && ($zipCode === '' || mb_strlen($zipCode) > 10 || $deliveryAddress === '' || mb_strlen($deliveryAddress) > 500)) {
            $checkoutError = 'Informe um CEP válido e um endereço de até 500 caracteres.';
        } else {
            $tipCents = (int) round((float) $tip * 100);
            $freightCents = 0;

            try {
                $connection = $pdo;
                $connection->beginTransaction();
                $lockedProducts = [];
                $subtotalCents = 0;

                foreach ($cartItems as $productId => $quantity) {
                    $statement = $connection->prepare(
                        'SELECT id_produto, nome_produto, preco, estoque FROM produtos WHERE id_produto = ? FOR UPDATE'
                    );
                    $statement->execute([$productId]);
                    $product = $statement->fetch();

                    if (!$product || (int) $product['estoque'] < $quantity) {
                        throw new DomainException('Estoque insuficiente para um ou mais produtos do carrinho.');
                    }

                    $lockedProducts[$productId] = $product;
                    $subtotalCents += (int) round((float) $product['preco'] * 100) * $quantity;
                }

                $totalCents = $subtotalCents + $tipCents + $freightCents;
                $statement = $connection->prepare(
                    'INSERT INTO pedidos (id_cliente, metodo_coleta, nome_coletor, nota_pedido, cep, endereco_entrega, subtotal, valor_frete, gorjeta, valor_total, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    (int) $_SESSION['id_user'],
                    $collectionMethod,
                    $collector,
                    $note,
                    $collectionMethod === 'Entrega' ? $zipCode : null,
                    $collectionMethod === 'Entrega' ? $deliveryAddress : null,
                    number_format($subtotalCents / 100, 2, '.', ''),
                    number_format($freightCents / 100, 2, '.', ''),
                    number_format($tipCents / 100, 2, '.', ''),
                    number_format($totalCents / 100, 2, '.', ''),
                    $paymentMethod,
                ]);
                $orderId = (int) $connection->lastInsertId();

                $itemStatement = $connection->prepare(
                    'INSERT INTO itens_pedido (id_pedido, id_produto, nome_produto, img_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stockStatement = $connection->prepare(
                    "UPDATE produtos SET status_estoque = CASE WHEN estoque - ? <= 0 THEN 'Esgotado' WHEN estoque - ? <= 5 THEN 'Estoque baixo' ELSE 'Em estoque' END, estoque = estoque - ? WHERE id_produto = ?"
                );

                foreach ($cartItems as $productId => $quantity) {
                    $price = number_format((float) $lockedProducts[$productId]['preco'], 2, '.', '');
                    $itemStatement->execute([
                        $orderId,
                        $productId,
                        $lockedProducts[$productId]['nome_produto'],
                        $lockedProducts[$productId]['img_produto'],
                        $quantity,
                        $price,
                    ]);
                    $stockStatement->execute([$quantity, $quantity, $quantity, $productId]);
                }

                $connection->commit();
                unset($_SESSION['carrinho']);
                $_SESSION['checkout_success'] = "Pedido #{$orderId} registrado com sucesso.";
                header('Location: carrinhopag.php');
                exit;
            } catch (DomainException $exception) {
                if (isset($connection) && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                $checkoutError = $exception->getMessage();
            } catch (Throwable $exception) {
                if (isset($connection) && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log($exception->getMessage());
                $checkoutError = 'Não foi possível registrar o pedido agora. Tente novamente.';
            }
        }
    }
}

function format_brl(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function escape_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Doce Maria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./andreicss/carrinhopag.css">
    <link rel="stylesheet" href="./css/bia.css">

    <style>

        #botaoTop {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e91e63;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 998;
            transition: transform 0.2s ease, background-color 0.2s ease;
            padding: 0;
        }

        #botaoTop:hover {
            transform: scale(1.08);
            background-color: #d81b60;
        }

        #botaoTop img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }


        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 9999;
            padding: 15px;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .cart-modal {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            color: #333;
        }

        .modal-overlay.active .cart-modal {
            transform: translateY(0);
        }

        .close-modal-btn {
            position: absolute;
            top: 18px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
            line-height: 1;
        }

        .close-modal-btn:hover {
            color: #333;
        }

        .cart-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .cart-items-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .cart-item-block {
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 16px;
        }

        .cart-item-block:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cart-item {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .item-img-container {
            width: 65px;
            height: 65px;
            border-radius: 12px;
            overflow: hidden;
            background: #f9f9f9;
            flex-shrink: 0;
        }

        .item-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4px;
        }

        .item-title {
            font-size: 15px;
            font-weight: 600;
            color: #222;
            margin: 0;
        }

        .item-price-small {
            font-size: 13px;
            color: #777;
        }

        .item-description {
            font-size: 12px;
            color: #888;
            margin: 0;
        }

        .quantity-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background-color: #f5f5f7;
            border-radius: 20px;
            padding: 4px 8px;
            gap: 10px;
        }

        .btn-qty {
            background: none;
            border: none;
            color: #555;
            cursor: pointer;
            font-size: 13px;
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s;
        }

        .btn-qty:hover {
            background: #e0e0e0;
        }

        .qty-number {
            font-size: 14px;
            font-weight: 600;
            min-width: 16px;
            text-align: center;
        }

        .item-price-total {
            font-weight: 700;
            font-size: 15px;
            color: #e91e63;
        }

        .divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 18px 0;
            position: relative;
        }

        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px dashed #e0e0e0;
        }

        .divider-icon {
            margin: 0 10px;
            color: #ffb74d;
            font-size: 12px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 12px;
        }

        .cross-sell-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .cross-sell-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .cross-sell-img-wrapper {
            position: relative;
            width: 55px;
            height: 55px;
            border-radius: 12px;
            background: #f8f8f8;
            margin-bottom: 6px;
        }

        .cross-sell-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .add-btn {
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }

        .add-btn:hover {
            transform: scale(1.15);
        }

        .cross-sell-title {
            font-size: 11px;
            color: #333;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60px;
        }

        .cross-sell-price {
            font-size: 11px;
            color: #888;
            font-weight: 600;
        }

        .subtotal-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 14px;
            color: #222;
        }

        .loyalty-banner {
            background: #fff8e1;
            color: #f57f17;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
        }

        .checkout-btn {
            width: 100%;
            background: #2e7d32;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .checkout-btn:hover {
            background: #1b5e20;
        }

        .empty-cart-msg {
            text-align: center;
            color: #888;
            padding: 15px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
<?php
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
    <form method="post" id="checkout-form">
    <input type="hidden" name="csrf_token" value="<?= escape_html($_SESSION['csrf_token']) ?>">
    <main class="container">
        <?php if ($checkoutError !== ''): ?>
            <p class="checkout-feedback erro" role="alert"><?= escape_html($checkoutError) ?></p>
        <?php elseif ($checkoutSuccess !== ''): ?>
            <p class="checkout-feedback sucesso" role="status"><?= escape_html($checkoutSuccess) ?></p>
        <?php endif; ?>
        
        <div class="coluna-esq">
            
            <section class="cartao">
                <h2 class="titulo">Finalizar pedido</h2>
                
                <div class="grid-info">
                    <div class="bloco-info">
                        <h3>Método de Coleta</h3>
                        <div class="grupo-botoes">
                            <button class="botao-opcao ativo" type="button" data-metodo="Retirada">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                Retirada
                            </button>
                            <button class="botao-opcao" type="button" data-metodo="Entrega">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h.01"/><path d="M17 7h.01"/><path d="M7 17h.01"/><path d="M17 17h.01"/></svg>
                                Entrega
                            </button>
                        </div>
                        <input type="hidden" name="metodo_coleta" id="metodo-coleta" value="Retirada">
                        <p class="texto-pequeno" id="texto-coleta">Retire seu pedido no balcão de coleta</p>
                    </div>

                    <div class="icone-texto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <div>
                            <p class="texto-destaque">Pedido pendente</p>
                            <p class="texto-pequeno">A loja confirmará o preparo</p>
                        </div>
                    </div>

                    <div class="icone-texto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <div id="campos-entrega" hidden>
                            <div class="campo-grupo">
                                <label for="cep">CEP</label>
                                <input class="input-texto" id="cep" name="cep" maxlength="10" autocomplete="postal-code">
                            </div>
                            <div class="campo-grupo">
                                <label for="endereco-entrega">Endereço</label>
                                <input class="input-texto" id="endereco-entrega" name="endereco_entrega" maxlength="500" autocomplete="street-address">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="label-input" for="nome-coletor">Quem receberá o pedido?</label>
                    <input type="text" class="input-texto" id="nome-coletor" name="nome_coletor" maxlength="100" placeholder="Nome e sobrenome" required>
                </div>
            </section>

            <section class="cartao">
                <h2 class="titulo">Minha Sacola</h2>
                
                <label class="texto-pequeno" for="nota-pedido" style="display: block; margin-bottom: 8px;">Adicione uma nota para o pedido (opcional)</label>
                <div class="input-com-contador">
                    <input type="text" class="input-texto" id="nota-pedido" name="nota_pedido" maxlength="2000" placeholder="Observação">
                    <span class="contador">2000</span>
                </div>

                <?php if ($cartItems === []): ?>
                    <p class="texto-pequeno">Seu carrinho está vazio. Adicione produtos antes de finalizar.</p>
                <?php else: ?>
                    <?php foreach ($cartItems as $productId => $quantity): ?>
                        <?php if (!isset($products[$productId])) { continue; } ?>
                        <?php $product = $products[$productId]; ?>
                        <?php $productImageUrl = product_image_url($product['img_produto'] ?? null); ?>
                        <?php $lineCents = (int) round((float) $product['preco'] * 100) * $quantity; ?>
                        <div class="produto">
                            <?php if ($productImageUrl !== null): ?>
                                <img src="<?= escape_html($productImageUrl) ?>" alt="<?= escape_html($product['nome_produto']) ?>" class="produto-imagem">
                            <?php endif; ?>
                            <div class="produto-detalhes">
                                <div class="produto-cabecalho">
                                    <h3 class="produto-titulo"><?= escape_html($product['nome_produto']) ?></h3>
                                    <span class="produto-titulo"><?= format_brl($lineCents) ?></span>
                                </div>
                                <p class="texto-pequeno">Quantidade: <?= $quantity ?> · <?= format_brl((int) round((float) $product['preco'] * 100)) ?> cada</p>
                                <p class="texto-pequeno">Estoque disponível: <?= (int) $product['estoque'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="rodapé-sacola">
                        <span>Quantidade: <?= array_sum($cartItems) ?></span>
                        <span><?= format_brl($subtotalCents) ?></span>
                    </div>
                <?php endif; ?>
            </section>

        </div>

        <aside class="coluna-dir">
            
            <section class="cartao">
                <h2 class="titulo-menor" style="margin-top: 0;">Detalhes do Pedido</h2>
                
                <div class="linha-valor">
                    <span>Subtotal</span>
                    <span id="valor-subtotal"><?= format_brl($subtotalCents) ?></span>
                </div>
                <div class="linha-valor">
                    <span>Gorjeta</span>
                    <span id="valor-gorjeta"><?= format_brl($tipCents) ?></span>
                </div>

                <div class="grid-gorjetas">
                    <button class="botao-gorjeta ativo" type="button" data-valor="5.00">R$ 5</button>
                    <button class="botao-gorjeta" type="button" data-valor="7.00">R$ 7</button>
                    <button class="botao-gorjeta" type="button" data-valor="9.00">R$ 9</button>
                    <button class="botao-gorjeta" type="button" data-valor="0.00">Sem gorjeta</button>
                </div>
                <input type="hidden" name="gorjeta" id="gorjeta" value="5.00">
                <p class="texto-pequeno" style="margin-bottom: 16px;">100% das gorjetas vão para os padeiros</p>

                <div class="total-pedido">
                    <span>Total</span>
                    <span id="valor-total"><?= format_brl($subtotalCents + $tipCents) ?></span>
                </div>

                <h2 class="titulo-menor">Pagamento</h2>
                
                <div class="box-pagamento">
                    <div class="aba-cartao">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        Forma de pagamento
                    </div>
                    <div class="form-cartao">
                        <div class="campo-grupo">
                            <label for="metodo-pagamento">Selecione como vai pagar</label>
                            <select class="input-texto" id="metodo-pagamento" name="metodo_pagamento" required>
                                <option value="Cartão">Cartão</option>
                                <option value="Pix">Pix</option>
                                <option value="Dinheiro">Dinheiro</option>
                            </select>
                        </div>
                    </div>
                </div>

                <p class="aviso-legal">
                    O pedido será registrado como pendente. O pagamento não é processado nesta página; não informe número ou código do cartão.
                </p>

                <button class="botao-cupom" type="button">
                    <span>+</span> Cartão de Presente, Voucher ou Código Promocional
                </button>

                <p class="termos">
                    Ao prosseguir, você concorda com nossos <a href="#">Termos e Condições</a> e confirma que leu e compreende nossa <a href="#">Política de Privacidade</a>.
                </p>
                <?php if (isset($_SESSION['id_user'])): ?>
                    <button class="botao-finalizar" type="submit" <?= $cartItems === [] || count($products) !== count($cartItems) ? 'disabled' : '' ?>>Confirmar pedido</button>
                <?php else: ?>
                    <a class="botao-finalizar" href="login.php">Entre para finalizar o pedido</a>
                <?php endif; ?>
            </section>

        </aside>

        <button id="botaoTop" type="button" title="Abrir Carrinho"><img src="./imagens/carticon.png" alt="Carrinho"></button>

    </main>
    </form>

                <!--modal-->
    <div class="modal-overlay" id="modal-carrinho-overlay">
        <div class="cart-modal">
            <button class="close-modal-btn" id="close-cart-modal" type="button">&times;</button>

            <h2 class="cart-title">Meu carrinho</h2>

            <div class="cart-items-list" id="modal-cart-items">
                <?php if ($cartItems === []): ?>
                    <p class="empty-cart-msg">Seu carrinho está vazio.</p>
                <?php else: ?>
                    <?php foreach ($cartItems as $productId => $quantity): ?>
                        <?php if (!isset($products[$productId])) continue; ?>
                        <?php $product = $products[$productId]; ?>
                        <?php $productImageUrl = product_image_url($product['img_produto'] ?? null); ?>
                        <?php $unitPrice = (float) $product['preco']; ?>
                        <?php $lineCents = (int) round($unitPrice * 100) * $quantity; ?>
                        
                        <div class="cart-item-block" data-product-id="<?= $productId ?>" data-price="<?= $unitPrice ?>">
                            <div class="cart-item">
                                <div class="item-img-container">
                                    <?php if ($productImageUrl !== null): ?>
                                        <img src="<?= escape_html($productImageUrl) ?>" alt="<?= escape_html($product['nome_produto']) ?>" class="item-img">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/100" alt="Produto" class="item-img">
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <div class="item-header">
                                        <h3 class="item-title"><?= escape_html($product['nome_produto']) ?></h3>
                                        <span class="item-price-small"><?= format_brl((int) round($unitPrice * 100)) ?></span>
                                    </div>
                                    <p class="item-description">Estoque disponível: <?= (int) $product['estoque'] ?></p>
                                </div>
                            </div>

                            <div class="quantity-price-row">
                                <div class="quantity-control">
                                    <button class="btn-qty btn-minus" type="button">
                                        <i class="<?= $quantity === 1 ? 'fa-regular fa-trash-can' : 'fa-solid fa-minus' ?>"></i>
                                    </button>
                                    <span class="qty-number"><?= $quantity ?></span>
                                    <button class="btn-qty btn-plus" type="button">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <span class="item-price-total"><?= format_brl($lineCents) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="divider">
                <i class="fa-solid fa-sparkles divider-icon">✦</i>
            </div>

            <h3 class="section-title">Deseja adicionar algo?</h3>
            
            <div class="cross-sell-grid">
                <div class="cross-sell-card">
                    <div class="cross-sell-img-wrapper">
                        <img src="https://via.placeholder.com/60" alt="Cortador">
                        <button class="add-btn" type="button" data-title="Cortador" data-price="4.99">+</button>
                    </div>
                    <span class="cross-sell-title">Cortador</span>
                    <span class="cross-sell-price">R$ 4,99</span>
                </div>

                <div class="cross-sell-card">
                    <div class="cross-sell-img-wrapper">
                        <img src="https://via.placeholder.com/60" alt="Confeites">
                        <button class="add-btn" type="button" data-title="Confeites" data-price="3.50">+</button>
                    </div>
                    <span class="cross-sell-title">Confeites</span>
                    <span class="cross-sell-price">R$ 3,50</span>
                </div>

                <div class="cross-sell-card">
                    <div class="cross-sell-img-wrapper">
                        <img src="https://via.placeholder.com/60" alt="Embalagem">
                        <button class="add-btn" type="button" data-title="Embalagem" data-price="2.00">+</button>
                    </div>
                    <span class="cross-sell-title">Embalagem</span>
                    <span class="cross-sell-price">R$ 2,00</span>
                </div>

                <div class="cross-sell-card">
                    <div class="cross-sell-img-wrapper">
                        <img src="https://via.placeholder.com/60" alt="Vela Fofa">
                        <button class="add-btn" type="button" data-title="Vela Fofa" data-price="5.00">+</button>
                    </div>
                    <span class="cross-sell-title">Vela Fofa</span>
                    <span class="cross-sell-price">R$ 5,00</span>
                </div>
            </div>

            <div class="divider">
                <i class="fa-solid fa-sparkles divider-icon">✦</i>
            </div>

            <div class="subtotal-row">
                <span class="subtotal-label">Subtotal</span>
                <span class="subtotal-value" id="modal-subtotal-val"><?= format_brl($subtotalCents) ?></span>
            </div>

            <div class="loyalty-banner">
                <i class="fa-solid fa-trophy"></i>
                <span>Registre-se para ganhar pontos de fidelidade</span>
            </div>

            <button class="checkout-btn" id="modal-checkout-btn" type="button">Finalizar Pedido</button>

        </div>
    </div>

   
    <script>
        document.addEventListener('DOMContentLoaded', () => {
       
            const botoesColeta = document.querySelectorAll('.botao-opcao');
            const campoMetodoColeta = document.getElementById('metodo-coleta');
            const camposEntrega = document.getElementById('campos-entrega');
            const cep = document.getElementById('cep');
            const enderecoEntrega = document.getElementById('endereco-entrega');
            const textoColeta = document.getElementById('texto-coleta');

            botoesColeta.forEach(botao => {
                botao.addEventListener('click', function() {
                    botoesColeta.forEach(b => b.classList.remove('ativo'));
                    this.classList.add('ativo');
                    campoMetodoColeta.value = this.dataset.metodo;

                    const entregaSelecionada = this.dataset.metodo === 'Entrega';
                    camposEntrega.hidden = !entregaSelecionada;
                    cep.required = entregaSelecionada;
                    enderecoEntrega.required = entregaSelecionada;
                    textoColeta.textContent = entregaSelecionada
                        ? 'Informe onde o pedido deve ser entregue'
                        : 'Retire seu pedido no balcão de coleta';
                });
            });

            const subtotalCentavos = <?= (int) $subtotalCents ?>;
            const campoGorjeta = document.getElementById('gorjeta');
            const elementoGorjeta = document.getElementById('valor-gorjeta');
            const elementoTotal = document.getElementById('valor-total');
            const formatarReais = centavos => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(centavos / 100);

            const botoesGorjeta = document.querySelectorAll('.botao-gorjeta');
            botoesGorjeta.forEach(botao => {
                botao.addEventListener('click', function() {
                    botoesGorjeta.forEach(b => b.classList.remove('ativo'));
                    this.classList.add('ativo');

                    const gorjetaCentavos = Math.round(parseFloat(this.dataset.valor) * 100);
                    campoGorjeta.value = (gorjetaCentavos / 100).toFixed(2);
                    elementoGorjeta.textContent = formatarReais(gorjetaCentavos);
                    elementoTotal.textContent = formatarReais(subtotalCentavos + gorjetaCentavos);
                });
            });

     
            const botaoTop = document.getElementById('botaoTop');
            const modalOverlay = document.getElementById('modal-carrinho-overlay');
            const closeCartModal = document.getElementById('close-cart-modal');
            const modalCheckoutBtn = document.getElementById('modal-checkout-btn');

       
            if (botaoTop && modalOverlay) {
                botaoTop.addEventListener('click', () => {
                    modalOverlay.classList.add('active');
                });
            }

   
            if (closeCartModal && modalOverlay) {
                closeCartModal.addEventListener('click', () => {
                    modalOverlay.classList.remove('active');
                });
            }

    
            if (modalOverlay) {
                modalOverlay.addEventListener('click', (e) => {
                    if (e.target === modalOverlay) {
                        modalOverlay.classList.remove('active');
                    }
                });
            }

        
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
                    modalOverlay.classList.remove('active');
                }
            });

   
            if (modalCheckoutBtn && modalOverlay) {
                modalCheckoutBtn.addEventListener('click', () => {
                    modalOverlay.classList.remove('active');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }

            const modalCartItems = document.getElementById('modal-cart-items');
            const modalSubtotalVal = document.getElementById('modal-subtotal-val');

            function updateModalSubtotal() {
                let totalCents = 0;
                const itemBlocks = modalCartItems.querySelectorAll('.cart-item-block');

                itemBlocks.forEach(block => {
                    const price = parseFloat(block.dataset.price) || 0;
                    const qty = parseInt(block.querySelector('.qty-number').textContent, 10) || 0;
                    totalCents += Math.round(price * 100) * qty;
                });

                if (modalSubtotalVal) {
                    modalSubtotalVal.textContent = formatarReais(totalCents);
                }

                if (itemBlocks.length === 0) {
                    modalCartItems.innerHTML = '<p class="empty-cart-msg">Seu carrinho está vazio.</p>';
                }
            }

            if (modalCartItems) {
                modalCartItems.addEventListener('click', (e) => {
                    const btnMinus = e.target.closest('.btn-minus');
                    const btnPlus = e.target.closest('.btn-plus');

                    if (btnMinus) {
                        const itemBlock = btnMinus.closest('.cart-item-block');
                        const qtySpan = itemBlock.querySelector('.qty-number');
                        const totalSpan = itemBlock.querySelector('.item-price-total');
                        const price = parseFloat(itemBlock.dataset.price) || 0;
                        let qty = parseInt(qtySpan.textContent, 10);

                        qty -= 1;
                        if (qty <= 0) {
                            itemBlock.remove();
                        } else {
                            qtySpan.textContent = qty;
                            totalSpan.textContent = formatarReais(Math.round(price * 100) * qty);
                            
                            if (qty === 1) {
                                btnMinus.innerHTML = '<i class="fa-regular fa-trash-can"></i>';
                            }
                        }
                        updateModalSubtotal();
                    }

                    if (btnPlus) {
                        const itemBlock = btnPlus.closest('.cart-item-block');
                        const qtySpan = itemBlock.querySelector('.qty-number');
                        const totalSpan = itemBlock.querySelector('.item-price-total');
                        const btnMinus = itemBlock.querySelector('.btn-minus');
                        const price = parseFloat(itemBlock.dataset.price) || 0;
                        let qty = parseInt(qtySpan.textContent, 10);

                        qty += 1;
                        qtySpan.textContent = qty;
                        totalSpan.textContent = formatarReais(Math.round(price * 100) * qty);

                        if (qty > 1 && btnMinus) {
                            btnMinus.innerHTML = '<i class="fa-solid fa-minus"></i>';
                        }
                        updateModalSubtotal();
                    }
                });
            }

            const crossSellBtns = document.querySelectorAll('.cross-sell-card .add-btn');
            crossSellBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const title = this.dataset.title;
                    const price = parseFloat(this.dataset.price);

                    const emptyMsg = modalCartItems.querySelector('.empty-cart-msg');
                    if (emptyMsg) {
                        emptyMsg.remove();
                    }

                    const newBlock = document.createElement('div');
                    newBlock.className = 'cart-item-block';
                    newBlock.dataset.price = price;
                    newBlock.innerHTML = `
                        <div class="cart-item">
                            <div class="item-img-container">
                                <img src="https://via.placeholder.com/100" alt="${title}" class="item-img">
                            </div>
                            <div class="item-details">
                                <div class="item-header">
                                    <h3 class="item-title">${title}</h3>
                                    <span class="item-price-small">${formatarReais(Math.round(price * 100))}</span>
                                </div>
                                <p class="item-description">Adicionado das sugestões</p>
                            </div>
                        </div>
                        <div class="quantity-price-row">
                            <div class="quantity-control">
                                <button class="btn-qty btn-minus" type="button"><i class="fa-regular fa-trash-can"></i></button>
                                <span class="qty-number">1</span>
                                <button class="btn-qty btn-plus" type="button"><i class="fa-solid fa-plus"></i></button>
                            </div>
                            <span class="item-price-total">${formatarReais(Math.round(price * 100))}</span>
                        </div>
                    `;

                    modalCartItems.appendChild(newBlock);
                    updateModalSubtotal();
                });
            });
        });
    </script>
</body>
</html>