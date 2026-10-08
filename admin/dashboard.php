<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../php/crud.php';

if (!isset($_SESSION['autenticado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$money = static function ($value): string {
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
};

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM produtos) AS total_produtos,
        (SELECT COALESCE(SUM(estoque), 0) FROM produtos) AS unidades_estoque,
        (SELECT COUNT(*) FROM produtos WHERE estoque > 0 AND estoque <= 10) AS estoque_baixo,
        (SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'cliente') AS total_clientes,
        (SELECT COUNT(*) FROM pedidos WHERE status_pedido = 'Pendente') AS pedidos_pendentes,
        (SELECT COUNT(*) FROM pedidos WHERE data_pedido >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS pedidos_mes,
        (SELECT COALESCE(SUM(valor_total), 0) FROM pedidos
         WHERE data_pedido >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
           AND status_pedido <> 'Cancelado') AS vendas_mes,
        (SELECT COALESCE(SUM(valor_total), 0) FROM pedidos
         WHERE status_pedido <> 'Cancelado') AS vendas_total"
)->fetch(PDO::FETCH_ASSOC);

$statusRows = $pdo->query(
    'SELECT status_pedido, COUNT(*) AS total
     FROM pedidos
     GROUP BY status_pedido'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$orderStatuses = ['Pendente', 'Em preparo', 'Pronto', 'Em entrega', 'Entregue', 'Cancelado'];
$maxStatusCount = max(1, ...array_map(
    static fn(string $status): int => (int) ($statusRows[$status] ?? 0),
    $orderStatuses
));

$categories = $pdo->query(
    'SELECT categoria, COUNT(*) AS total
     FROM produtos
     GROUP BY categoria
     ORDER BY total DESC, categoria
     LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);
$maxCategoryCount = max(1, ...array_map(static fn(array $category): int => (int) $category['total'], $categories));

$lowStock = $pdo->query(
    "SELECT id_produto, nome_produto, categoria, estoque
     FROM produtos
     WHERE estoque <= 10
     ORDER BY estoque ASC, nome_produto
     LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

$recentOrders = $pdo->query(
    'SELECT p.id_pedido, p.data_pedido, p.status_pedido, p.valor_total, u.nome AS nome_cliente
     FROM pedidos p
     INNER JOIN usuarios u ON u.id_user = p.id_cliente
     ORDER BY p.data_pedido DESC
     LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);

$adminName = (string) ($_SESSION['nome'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Doce Maria</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink: #eaa4b1;
            --pink-strong: #d96888;
            --pink-soft: #fff2f5;
            --brown: #623822;
            --ink: #49383b;
            --muted: #907f82;
            --line: #f1e5e8;
            --surface: #fff;
            --page: #faf6f8;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page);
            color: var(--ink);
            font-family: 'Montserrat', sans-serif;
        }

        .admin-site-header {
            position: sticky;
            top: 0;
            z-index: 10;
            height: 78px;
            background: var(--pink);
            box-shadow: 0 4px 15px rgba(98, 56, 34, .1);
        }

        .admin-menu {
            display: flex;
            align-items: center;
            gap: 26px;
            height: 100%;
            margin: 0;
            padding: 0 5%;
            list-style: none;
        }

        .admin-menu li:first-child { margin-right: auto; margin-left: 0; }
        .admin-menu a {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: color .2s ease;
        }
        .admin-menu a:hover, .admin-menu a[aria-current="page"] { color: var(--brown); }
        .admin-logo { display: block; width: 126px; height: auto; }
        .admin-menu form { margin: 0; }
        .logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 23px;
            border: 1px solid #fff;
            border-radius: 30px;
            background: #fff;
            color: var(--brown);
            font: 600 13px 'Montserrat', sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .admin-menu a.logout-button { color: var(--brown); }
        .admin-menu a.logout-button:hover { background: #623822; color: #fff; }

        .dashboard {
            width: min(1220px, calc(100% - 48px));
            margin: 42px auto 60px;
        }

        .welcome {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: var(--pink-strong);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            color: var(--brown);
            font-size: clamp(25px, 3vw, 34px);
        }
        .welcome p:last-child {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
        }
        .date-chip {
            padding: 11px 16px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            color: var(--brown);
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card, .panel {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: 0 5px 18px rgba(98, 56, 34, .035);
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 112px;
            padding: 20px;
        }
        .stat-icon {
            display: grid;
            flex: 0 0 48px;
            width: 48px;
            height: 48px;
            place-items: center;
            border-radius: 15px;
            background: var(--pink-soft);
            color: var(--pink-strong);
            font-size: 18px;
        }
        .stat-card:nth-child(2) .stat-icon { background: #f7f0ec; color: var(--brown); }
        .stat-card:nth-child(3) .stat-icon { background: #fff5e8; color: #d68b2c; }
        .stat-card:nth-child(4) .stat-icon { background: #edf7f0; color: #40875d; }
        .stat-label { margin: 0 0 6px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .stat-value { margin: 0; color: var(--brown); font-size: 23px; font-weight: 800; }
        .stat-note { margin: 5px 0 0; color: #a69598; font-size: 11px; }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
            gap: 20px;
            align-items: start;
        }
        .panel { padding: 23px; }
        .panel + .panel { margin-top: 20px; }
        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }
        .panel-heading h2 { margin: 0; color: var(--brown); font-size: 17px; }
        .panel-heading p { margin: 5px 0 0; color: var(--muted); font-size: 12px; }
        .text-link { color: var(--pink-strong); font-size: 12px; font-weight: 700; text-decoration: none; }
        .text-link:hover { color: var(--brown); }

        .status-list, .category-list, .stock-list, .order-list { display: grid; gap: 15px; }
        .bar-label {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 12px;
            font-weight: 600;
        }
        .bar-label span:last-child { color: var(--muted); }
        .bar-track { height: 8px; overflow: hidden; border-radius: 99px; background: #f6edf0; }
        .bar-fill { height: 100%; min-width: 3px; border-radius: inherit; background: linear-gradient(90deg, #f2a0b3, #d96888); }
        .category-list .bar-fill { background: linear-gradient(90deg, #dfb7a0, var(--brown)); }

        .stock-item, .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding-bottom: 13px;
            border-bottom: 1px solid #f5edef;
        }
        .stock-item:last-child, .order-item:last-child { padding-bottom: 0; border-bottom: 0; }
        .item-main { min-width: 0; }
        .item-main strong {
            display: block;
            overflow: hidden;
            color: var(--ink);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .item-main small { display: block; margin-top: 4px; color: var(--muted); font-size: 11px; }
        .stock-count {
            flex: 0 0 auto;
            padding: 6px 10px;
            border-radius: 99px;
            background: #fff4e6;
            color: #ad6c19;
            font-size: 11px;
            font-weight: 700;
        }
        .stock-count.empty { background: #fff0f0; color: #b34040; }
        .order-total { flex: 0 0 auto; color: var(--brown); font-size: 12px; font-weight: 700; }
        .status-badge {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 8px;
            border-radius: 99px;
            background: var(--pink-soft);
            color: #b75a73;
            font-size: 10px;
            font-weight: 700;
        }
        .status-badge.cancelled { background: #f3eeee; color: #81777a; }
        .status-badge.done { background: #edf7f0; color: #40875d; }
        .empty-state { margin: 0; padding: 15px 0; color: var(--muted); font-size: 13px; }
        .quick-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            color: var(--brown);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }
        .quick-link:hover { border-color: var(--pink); background: var(--pink-soft); }

        @media (max-width: 920px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .admin-site-header { height: auto; min-height: 68px; }
            .admin-menu { flex-wrap: wrap; gap: 12px; padding: 12px 16px; }
            .admin-menu li:first-child { margin-right: auto; }
            .admin-logo { width: 98px; }
            .admin-menu a { font-size: 12px; }
            .dashboard { width: calc(100% - 28px); margin-top: 26px; }
            .welcome { align-items: flex-start; flex-direction: column; }
            .stats-grid { gap: 10px; }
            .stat-card { min-height: 98px; gap: 10px; padding: 13px; }
            .stat-icon { flex-basis: 38px; width: 38px; height: 38px; font-size: 15px; }
            .stat-value { font-size: 18px; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>
    <header class="admin-site-header">
        <ul class="admin-menu">
            <li><a href="../index.php"><img src="../imagens/Doce_Maria.png" class="admin-logo" alt="Doce Maria"></a></li>
            <li><a href="../index.php">Início</a></li>
            <li><a href="../sobre.php">Sobre nós</a></li>
            <li><a href="../produtos.php">Catálogo</a></li>
            <li><a href="dashboard.php" aria-current="page" aria-label="Você já está no dashboard">Dashboard</a></li>
            <li><a class="logout-button" href="../logout.php" aria-label="Sair da conta">Sair</a></li>
        </ul>
    </header>

    <main class="dashboard">
        <section class="welcome">
            <div>
                <p class="eyebrow">Painel administrativo</p>
                <h1>Olá, <?php echo $escape($adminName); ?> ✨</h1>
                <p>Acompanhe o movimento e cuide da sua loja em um só lugar.</p>
            </div>
            <div class="date-chip"><i class="fa-regular fa-calendar"></i> <?php echo $escape(date('d/m/Y')); ?></div>
        </section>

        <section class="stats-grid" aria-label="Indicadores da loja">
            <article class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <p class="stat-label">Vendas neste mês</p>
                    <p class="stat-value"><?php echo $escape($money($stats['vendas_mes'])); ?></p>
                    <p class="stat-note"><?php echo (int) $stats['pedidos_mes']; ?> pedidos no mês</p>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
                <div>
                    <p class="stat-label">Pedidos pendentes</p>
                    <p class="stat-value"><?php echo number_format((int) $stats['pedidos_pendentes'], 0, ',', '.'); ?></p>
                    <p class="stat-note">Aguardando atendimento</p>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    <p class="stat-label">Produtos no catálogo</p>
                    <p class="stat-value"><?php echo number_format((int) $stats['total_produtos'], 0, ',', '.'); ?></p>
                    <p class="stat-note"><?php echo number_format((int) $stats['unidades_estoque'], 0, ',', '.'); ?> unidades em estoque</p>
                </div>
            </article>
            <article class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <p class="stat-label">Clientes cadastrados</p>
                    <p class="stat-value"><?php echo number_format((int) $stats['total_clientes'], 0, ',', '.'); ?></p>
                    <p class="stat-note"><?php echo number_format((int) $stats['estoque_baixo'], 0, ',', '.'); ?> produtos com estoque baixo</p>
                </div>
            </article>
        </section>

        <div class="dashboard-grid">
            <div>
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Visão geral dos pedidos</h2>
                            <p>Distribuição dos pedidos por status</p>
                        </div>
                        <a class="text-link" href="pedidos.php">Ver pedidos <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="status-list">
                        <?php foreach ($orderStatuses as $status): ?>
                            <?php $count = (int) ($statusRows[$status] ?? 0); ?>
                            <div>
                                <div class="bar-label"><span><?php echo $escape($status); ?></span><span><?php echo $count; ?></span></div>
                                <div class="bar-track"><div class="bar-fill" style="width: <?php echo $count > 0 ? max(3, (int) round($count / $maxStatusCount * 100)) : 0; ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="quick-links">
                        <a class="quick-link" href="estoque.php"><i class="fa-solid fa-boxes-stacked"></i> Gerenciar estoque</a>
                        <a class="quick-link" href="adicionar_produto.php"><i class="fa-solid fa-plus"></i> Adicionar produto</a>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Pedidos recentes</h2>
                            <p>Últimas movimentações da loja</p>
                        </div>
                        <span class="text-link"><?php echo $escape($money($stats['vendas_total'])); ?> em vendas</span>
                    </div>
                    <div class="order-list">
                        <?php foreach ($recentOrders as $order): ?>
                            <?php
                            $statusClass = $order['status_pedido'] === 'Cancelado'
                                ? 'cancelled'
                                : ($order['status_pedido'] === 'Entregue' ? 'done' : '');
                            ?>
                            <div class="order-item">
                                <div class="item-main">
                                    <strong>Pedido #<?php echo (int) $order['id_pedido']; ?> · <?php echo $escape($order['nome_cliente']); ?></strong>
                                    <small><?php echo $escape(date('d/m/Y · H:i', strtotime($order['data_pedido']))); ?></small>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $escape($order['status_pedido']); ?></span>
                                </div>
                                <span class="order-total"><?php echo $escape($money($order['valor_total'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($recentOrders === []): ?>
                            <p class="empty-state">Ainda não há pedidos para mostrar.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div>
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Produtos por categoria</h2>
                            <p>As categorias mais presentes no catálogo</p>
                        </div>
                    </div>
                    <div class="category-list">
                        <?php foreach ($categories as $category): ?>
                            <div>
                                <div class="bar-label"><span><?php echo $escape($category['categoria']); ?></span><span><?php echo (int) $category['total']; ?> produtos</span></div>
                                <div class="bar-track"><div class="bar-fill" style="width: <?php echo max(3, (int) round((int) $category['total'] / $maxCategoryCount * 100)); ?>%"></div></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($categories === []): ?>
                            <p class="empty-state">Ainda não há produtos cadastrados.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Atenção ao estoque</h2>
                            <p>Produtos com 10 unidades ou menos</p>
                        </div>
                        <a class="text-link" href="estoque.php">Abrir estoque</a>
                    </div>
                    <div class="stock-list">
                        <?php foreach ($lowStock as $product): ?>
                            <div class="stock-item">
                                <div class="item-main">
                                    <strong><?php echo $escape($product['nome_produto']); ?></strong>
                                    <small><?php echo $escape($product['categoria']); ?></small>
                                </div>
                                <span class="stock-count <?php echo (int) $product['estoque'] === 0 ? 'empty' : ''; ?>">
                                    <?php echo (int) $product['estoque'] === 0 ? 'Esgotado' : (int) $product['estoque'] . ' un.'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($lowStock === []): ?>
                            <p class="empty-state">Tudo certo! Não há produtos com estoque baixo.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
