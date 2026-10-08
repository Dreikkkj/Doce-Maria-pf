<?php
session_start();
require_once __DIR__ . '/../php/crud.php';
require_once __DIR__ . '/../php/product_images.php';


if (!isset($_SESSION['autenticado'])) {
    header("Location: ../login.php");
    exit();
}

$id_user = (int) $_SESSION['id_user'];
$usuario = read($pdo, 'usuarios', 'id_user = :id_user', [':id_user' => $id_user]);
if (!$usuario) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT p.id_pedido, p.data_pedido, p.status_pedido, p.metodo_coleta,
               p.metodo_pagamento, p.valor_total, ip.id_item, ip.quantidade,
               ip.preco_unitario, COALESCE(pr.nome_produto, ip.nome_produto) AS nome_produto,
               COALESCE(pr.img_produto, ip.img_produto) AS img_produto
        FROM pedidos p
        LEFT JOIN itens_pedido ip ON ip.id_pedido = p.id_pedido
        LEFT JOIN produtos pr ON pr.id_produto = ip.id_produto
        WHERE p.id_cliente = :id_cliente
        ORDER BY p.data_pedido DESC, ip.id_item";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id_cliente' => $id_user]);
$linhasPedido = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pedidos = [];

foreach ($linhasPedido as $linha) {
    $idPedido = (int) $linha['id_pedido'];
    if (!isset($pedidos[$idPedido])) {
        $pedidos[$idPedido] = [
            'id_pedido' => $idPedido,
            'data_pedido' => $linha['data_pedido'],
            'status_pedido' => $linha['status_pedido'],
            'metodo_coleta' => $linha['metodo_coleta'],
            'metodo_pagamento' => $linha['metodo_pagamento'],
            'valor_total' => $linha['valor_total'],
            'itens' => []
        ];
    }

    if ($linha['id_item'] !== null) {
        $pedidos[$idPedido]['itens'][] = [
            'nome_produto' => $linha['nome_produto'],
            'img_produto' => $linha['img_produto'],
            'quantidade' => $linha['quantidade'],
            'preco_unitario' => $linha['preco_unitario']
        ];
    }
}
$pedidos = array_values($pedidos);

function formatarData($dataHora) {
    $data = new DateTime($dataHora);
    return $data->format('d/m/Y \à\s H:i');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Meus Pedidos</title>


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

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
            display: flex;
            align-items: center;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -2px;
            background-color: #E53935;
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 50%;
            width: 15px;
            height: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
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


        .order-card {
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #F0E6EA;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background-color: #FAFAFA;
            border-bottom: 1px solid #F0E6EA;
        }

        .order-id-date h3 {
            font-size: 16px;
            font-weight: 700;
            color: #222;
            margin-bottom: 4px;
        }

        .order-id-date span {
            font-size: 13px;
            color: #777;
        }


        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pendente { background-color: #FFF3E0; color: #E65100; }
        .badge-preparo { background-color: #FFE0B2; color: #E65100; }
        .badge-pronto { background-color: #E3F2FD; color: #1565C0; }
        .badge-em-entrega { background-color: #EDE7F6; color: #5E35B1; }
        .badge-entregue { background-color: #E8F5E9; color: #2E7D32; }
        .badge-cancelado { background-color: #F5F5F5; color: #616161; }

        .order-items-list {
            padding: 20px 25px;
        }

        .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed #F0E6EA;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .item-img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            background-color: #FAF6F8;
            border: 1px solid #F0E6EA;
        }

        .item-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .item-details p {
            font-size: 12px;
            color: #777;
        }

        .item-price {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

       
         .order-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            background-color: #FFF0F5;
            border-top: 1px solid #F0E6EA;
        }

        .order-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #555;
        }

        .order-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .order-total {
            text-align: right;
        }

        .order-total label {
            font-size: 12px;
            color: #666;
            display: block;
        }

        .order-total span {
            font-size: 18px;
            font-weight: 700;
            color: #D81B60;
        }

        /* Estado Vazio */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #F0E6EA;
        }

        .empty-orders span {
            font-size: 48px;
            color: #F07FA6;
            margin-bottom: 12px;
        }

        .empty-orders h3 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
        }

        .empty-orders p {
            font-size: 14px;
            color: #777;
        }
    </style>
</head>

<body>

    <header class="topbar-cliente">
        <div class="user-nav">
            <button class="notification-btn" title="Notificações">
                <span class="material-symbols-outlined">notifications</span>
                <span class="notification-badge">2</span>
            </button>
            <div class="user-profile-menu">
                <div class="avatar-sm">
                    <?php echo htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <span class="user-name-sm">
                    <?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="material-symbols-outlined" style="font-size: 18px; color: #666;">expand_more</span>
            </div>
        </div>
    </header>

    <main class="account-container">
        
 
        <div class="page-header">
            <div class="header-text">
                <h1>Meus Pedidos</h1>
                <p>Acompanhe o histórico e o status das suas encomendas.</p>
            </div>
            
            <a href="minha_conta.php" class="btn-voltar">
                <span class="material-symbols-outlined">arrow_back</span>
                Voltar
            </a>
        </div>


        <section>
            <?php if (!empty($pedidos)): ?>
                <?php foreach ($pedidos as $pedido): ?>
                <?php
                    $classesStatus = [
                        'Pendente' => 'badge-pendente',
                        'Em preparo' => 'badge-preparo',
                        'Pronto' => 'badge-pronto',
                        'Em entrega' => 'badge-em-entrega',
                        'Entregue' => 'badge-entregue',
                        'Cancelado' => 'badge-cancelado'
                    ];
                    $classeStatus = $classesStatus[$pedido['status_pedido']] ?? '';
                ?>
                <div class="order-card">
                    <div class="order-card-header">
                        <div class="order-id-date">
                            <h3>Pedido #<?php echo (int) $pedido['id_pedido']; ?></h3>
                            <span><?php echo htmlspecialchars(formatarData($pedido['data_pedido']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <span class="badge <?php echo $classeStatus; ?>"><?php echo htmlspecialchars($pedido['status_pedido'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="order-items-list">
                        <?php foreach ($pedido['itens'] as $item): ?>
                        <?php $imagemUrl = product_image_url($item['img_produto'] ?? null); ?>
                        <div class="order-item">
                            <div class="item-info">
                                <?php if ($imagemUrl !== null): ?>
                                    <img src="../<?php echo htmlspecialchars(substr($imagemUrl, 2), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['nome_produto'] ?? 'Produto', ENT_QUOTES, 'UTF-8'); ?>" class="item-img">
                                <?php endif; ?>
                                <div class="item-details">
                                    <h4><?php echo htmlspecialchars($item['nome_produto'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p>Qtd: <?php echo (int) $item['quantidade']; ?>x R$ <?php echo number_format((float) $item['preco_unitario'], 2, ',', '.'); ?></p>
                                </div>
                            </div>
                            <span class="item-price">R$ <?php echo number_format((float) $item['preco_unitario'] * (int) $item['quantidade'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-card-footer">
                        <div class="order-meta">
                            <div class="order-meta-item">
                                <span class="material-symbols-outlined" style="font-size: 18px;">local_shipping</span>
                                <span><?php echo htmlspecialchars($pedido['metodo_coleta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="order-meta-item">
                                <span class="material-symbols-outlined" style="font-size: 18px;">pix</span>
                                <span><?php echo htmlspecialchars($pedido['metodo_pagamento'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>

                        <div class="order-total">
                            <label>Total do Pedido</label>
                            <span>R$ <?php echo number_format((float) $pedido['valor_total'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-orders">
                    <span class="material-symbols-outlined">shopping_bag</span>
                    <h3>Você ainda não fez nenhum pedido</h3>
                    <p>Que tal dar uma olhada nas nossas delícias hoje?</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <?php require __DIR__ . '/../partials/cart_modal.php'; ?>
</body>
</html>