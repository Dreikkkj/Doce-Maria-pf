<?php
session_start();
require_once __DIR__ . '/../php/crud.php';
require_once __DIR__ . '/../php/loyalty.php';
require_once __DIR__ . '/../php/product_images.php';

if (!isset($_SESSION['autenticado'])) {
    header("Location: ../login.php");
    exit();
}

$usuario = read(
    $pdo,
    'usuarios',
    'id_user = :id_user',
    [':id_user' => (int) $_SESSION['id_user']]
);

if (!$usuario) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

loyalty_ensure_redemptions_table($pdo);
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$availableStatement = $pdo->query(
    'SELECT id_produto, nome_produto, img_produto, categoria, estoque
     FROM produtos
     WHERE estoque > 0
     ORDER BY id_produto'
);
$availableProducts = $availableStatement->fetchAll(PDO::FETCH_ASSOC);
$recompensas = [];
$seenCategories = [];
foreach ($availableProducts as $product) {
    $imageUrl = product_image_url($product['img_produto'] ?? null);
    if ($imageUrl === null || isset($seenCategories[$product['categoria']])) {
        continue;
    }
    $product['imagem_url'] = '../' . substr($imageUrl, 2);
    $recompensas[] = $product;
    $seenCategories[$product['categoria']] = true;
    if (count($recompensas) === 5) {
        break;
    }
}
if (count($recompensas) < 5) {
    foreach ($availableProducts as $product) {
        if (count($recompensas) === 5) {
            break;
        }
        $imageUrl = product_image_url($product['img_produto'] ?? null);
        if ($imageUrl === null) {
            continue;
        }
        $alreadyListed = false;
        foreach ($recompensas as $reward) {
            if ((int) $reward['id_produto'] === (int) $product['id_produto']) {
                $alreadyListed = true;
                break;
            }
        }
        if (!$alreadyListed) {
            $product['imagem_url'] = '../' . substr($imageUrl, 2);
            $recompensas[] = $product;
        }
    }
}
$recompensasPorId = [];
foreach ($recompensas as $reward) {
    $recompensasPorId[(int) $reward['id_produto']] = $reward;
}

if (isset($_SESSION['loyalty_notice'])) {
    $loyaltyNotice = $_SESSION['loyalty_notice'];
    unset($_SESSION['loyalty_notice']);
} else {
    $loyaltyNotice = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $productId = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $loyaltyNotice = ['type' => 'error', 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($productId === false || !isset($recompensasPorId[$productId])) {
        $loyaltyNotice = ['type' => 'error', 'message' => 'Esse produto não está disponível para resgate.'];
    } else {
        try {
            $pdo->beginTransaction();
            $userLock = $pdo->prepare('SELECT id_user FROM usuarios WHERE id_user = ? FOR UPDATE');
            $userLock->execute([(int) $_SESSION['id_user']]);
            if (!$userLock->fetchColumn()) {
                throw new DomainException('Não foi possível confirmar sua conta. Entre novamente.');
            }

            $productLock = $pdo->prepare(
                'SELECT id_produto, nome_produto, estoque FROM produtos WHERE id_produto = ? FOR UPDATE'
            );
            $productLock->execute([$productId]);
            $product = $productLock->fetch(PDO::FETCH_ASSOC);
            if (!$product || (int) $product['estoque'] < 1) {
                throw new DomainException('Esse produto acabou. Escolha outra recompensa.');
            }

            $pontosFidelidade = loyalty_points_for_user($pdo, (int) $_SESSION['id_user']);
            if ($pontosFidelidade < 5000) {
                throw new DomainException('Você precisa de 5.000 pontos para resgatar um produto.');
            }

            $stockUpdate = $pdo->prepare(
                "UPDATE produtos
                 SET status_estoque = CASE
                         WHEN estoque - 1 <= 0 THEN 'Esgotado'
                         WHEN estoque - 1 <= 5 THEN 'Estoque baixo'
                         ELSE 'Em estoque'
                     END,
                     estoque = estoque - 1
                 WHERE id_produto = ?"
            );
            $stockUpdate->execute([$productId]);
            $redemptionInsert = $pdo->prepare(
                "INSERT INTO fidelidade_resgates (id_cliente, id_produto, nome_produto, pontos_gastos)
                 VALUES (?, ?, ?, 5000)"
            );
            $redemptionInsert->execute([
                (int) $_SESSION['id_user'],
                $productId,
                $product['nome_produto'],
            ]);

            $pdo->commit();
            $_SESSION['loyalty_notice'] = [
                'type' => 'success',
                'message' => $product['nome_produto'] . ' resgatado por 5.000 pontos. Aguarde a confirmação da loja.',
            ];
            header('Location: fidelidade.php');
            exit();
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $loyaltyNotice = ['type' => 'error', 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $loyaltyNotice = ['type' => 'error', 'message' => 'Não foi possível concluir o resgate agora. Tente novamente.'];
        }
    }
}

$pontosFidelidade = loyalty_points_for_user($pdo, (int) $_SESSION['id_user']);
$redemptionStatement = $pdo->prepare(
    'SELECT nome_produto, pontos_gastos, status, data_resgate
     FROM fidelidade_resgates
     WHERE id_cliente = :id_cliente
     ORDER BY data_resgate DESC
     LIMIT 10'
);
$redemptionStatement->execute([':id_cliente' => (int) $_SESSION['id_user']]);
$resgates = $redemptionStatement->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Programa de Fidelidade</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #FAF6F8;
            color: #333;
            padding-bottom: 60px;
        }

        /* Topbar */
        .topbar-cliente {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 25px 60px;
            background-color: transparent;
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar-sm {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #FCE8EF;
            color: #D81B60;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .user-name-sm {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .account-container {
            max-width: 850px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-text h1 {
            font-size: 26px;
            font-weight: 700;
            color: #222;
            margin-bottom: 6px;
        }

        .header-text p {
            font-size: 14px;
            color: #777;
        }

        .btn-voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #FFF0F5;
            color: #333;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-voltar:hover {
            background-color: #FCE8EF;
            color: #F07FA6;
        }

        .points-banner {
            background: linear-gradient(135deg, #F07FA6 0%, #D81B60 100%);
            color: white;
            border-radius: 20px;
            padding: 35px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 20px rgba(216, 27, 96, 0.15);
            margin-bottom: 35px;
        }

        .banner-info label {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .banner-info .total-points {
            font-size: 44px;
            font-weight: 800;
            margin: 8px 0;
            line-height: 1;
        }

        .banner-info p {
            font-size: 13px;
            opacity: 0.85;
        }

        .banner-icon {
            font-size: 64px;
            opacity: 0.3;
        }

        .rules-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 25px 30px;
            border: 1px solid #F0E6EA;
            margin-bottom: 35px;
        }

        .rules-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: #222;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rules-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .rule-item {
            text-align: center;
            padding: 10px;
        }

        .rule-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #FFF0F5;
            color: #D81B60;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 22px;
        }

        .rule-item h4 {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .rule-item p {
            font-size: 12px;
            color: #777;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #222;
            margin-bottom: 20px;
        }

        .rewards-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .reward-card {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 20px 25px;
            border: 1px solid #F0E6EA;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .reward-card:hover {
            border-color: #F07FA6;
            box-shadow: 0 4px 12px rgba(240, 127, 166, 0.08);
        }

        .reward-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .reward-product-image {
            width: 58px;
            height: 58px;
            flex: 0 0 58px;
            object-fit: cover;
            border-radius: 12px;
            background-color: #FFF0F5;
        }

        .reward-details h4 {
            font-size: 15px;
            font-weight: 700;
            color: #222;
            margin-bottom: 4px;
        }

        .reward-details p {
            font-size: 12px;
            color: #777;
        }

        .reward-details {
            min-width: 0;
        }

        .reward-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .reward-points-cost {
            font-size: 14px;
            font-weight: 700;
            color: #D81B60;
        }

        .btn-resgatar {
            background-color: #F07FA6;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-resgatar:hover {
            background-color: #D81B60;
        }

        .btn-resgatar:disabled {
            background-color: #E0E0E0;
            color: #999;
            cursor: not-allowed;
        }

        .loyalty-notice {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
        }

        .loyalty-notice-success {
            background: #edf7ed;
            color: #27632a;
        }

        .loyalty-notice-error {
            background: #fff0f0;
            color: #a12626;
        }

        .redemptions-section {
            margin-top: 35px;
        }

        .redemption-list {
            display: grid;
            gap: 10px;
        }

        .redemption-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 15px 18px;
            background: #fff;
            border: 1px solid #F0E6EA;
            border-radius: 12px;
            font-size: 13px;
        }

        .redemption-row span:last-child {
            color: #D81B60;
            font-weight: 600;
            white-space: nowrap;
        }

        @media (max-width: 700px) {
            .rewards-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .rules-grid {
                grid-template-columns: 1fr;
            }

            .reward-card {
                padding: 16px;
            }

            .reward-details h4 {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

 
    <header class="topbar-cliente">
        <div class="user-nav">
            <div class="user-profile-menu">
                <div class="avatar-sm">
                    <?php echo htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <span class="user-name-sm">
                    <?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        </div>
    </header>

    <main class="account-container">
        
   
        <div class="page-header">
            <div class="header-text">
                <h1>Clube Doce Maria</h1>
                <p>Acumule pontos em suas compras e troque por gostosuras!</p>
            </div>
            
            <a href="minha_conta.php" class="btn-voltar">
                <span class="material-symbols-outlined">arrow_back</span>
                Voltar
            </a>
        </div>

        <div class="points-banner">
            <div class="banner-info">
                <label>Seu Saldo Atual</label>
                <div class="total-points"><?php echo number_format($pontosFidelidade, 0, ',', '.'); ?> Pts</div>
                <p>Você é uma cliente especial no nosso Clube de Fidelidade!</p>
            </div>
            <span class="material-symbols-outlined banner-icon">workspace_premium</span>
        </div>


        <section class="rules-card">
            <h3><span class="material-symbols-outlined" style="color: #F07FA6;">info</span> Como funciona o clube?</h3>
            <div class="rules-grid">
                <div class="rule-item">
                    <div class="rule-icon"><span class="material-symbols-outlined">shopping_cart</span></div>
                    <h4>1. Faça seus pedidos</h4>
                    <p>Cada R$ 1,00 em compras na Doce Maria equivale a 10 pontos.</p>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><span class="material-symbols-outlined">stars</span></div>
                    <h4>2. Acumule pontos</h4>
                    <p>Os pontos são creditados automaticamente quando você faz um pedido. Pedidos cancelados não acumulam pontos.</p>
                </div>
                <div class="rule-item">
                    <div class="rule-icon"><span class="material-symbols-outlined">card_giftcard</span></div>
                    <h4>3. Troque por prêmios</h4>
                    <p>Cada resgate vale 1 produto e custa 5.000 pontos. Você pode resgatar novamente quando tiver saldo.</p>
                </div>
            </div>
        </section>

        <?php if (is_array($loyaltyNotice) && isset($loyaltyNotice['message'])): ?>
            <p class="loyalty-notice loyalty-notice-<?php echo ($loyaltyNotice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="<?php echo ($loyaltyNotice['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>">
                <?php echo htmlspecialchars((string) $loyaltyNotice['message'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <section>
            <h2 class="section-title">Resgate um produto do catálogo</h2>
            <div class="rewards-list">
                <?php foreach ($recompensas as $recompensa): ?>
                    <article class="reward-card">
                        <div class="reward-left">
                            <img
                                class="reward-product-image"
                                src="<?php echo htmlspecialchars($recompensa['imagem_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($recompensa['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <div class="reward-details">
                                <h4><?php echo htmlspecialchars($recompensa['nome_produto'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p>1 unidade · estoque: <?php echo (int) $recompensa['estoque']; ?></p>
                            </div>
                        </div>
                        <div class="reward-right">
                            <span class="reward-points-cost">5.000 pontos</span>
                            <form method="POST" action="fidelidade.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id_produto" value="<?php echo (int) $recompensa['id_produto']; ?>">
                                <button class="btn-resgatar" type="submit" <?php echo $pontosFidelidade < 5000 ? 'disabled' : ''; ?>>
                                    <?php echo $pontosFidelidade < 5000 ? 'Pontos insuficientes' : 'Resgatar'; ?>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($recompensas === []): ?>
                    <p class="texto-pequeno">Não há produtos com imagem e estoque disponíveis para resgate no momento.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($resgates !== []): ?>
            <section class="redemptions-section">
                <h2 class="section-title">Meus resgates</h2>
                <div class="redemption-list">
                    <?php foreach ($resgates as $resgate): ?>
                        <div class="redemption-row">
                            <span>
                                <?php echo htmlspecialchars($resgate['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($resgate['data_resgate'])), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span><?php echo htmlspecialchars($resgate['status'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo number_format((int) $resgate['pontos_gastos'], 0, ',', '.'); ?> pts</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <?php require __DIR__ . '/../partials/cart_modal.php'; ?>
</body>
</html>