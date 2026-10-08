<?php
session_start();

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../php/crud.php';

if (!isset($_SESSION['autenticado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminDashboardUrl = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/pedidos.php'))), '/') . '/dashboard.php';
$statusPermitidos = ['Pendente', 'Em preparo', 'Pronto', 'Em entrega', 'Entregue', 'Cancelado'];
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['admin_order_notice'])) {
    $adminOrderNotice = $_SESSION['admin_order_notice'];
    unset($_SESSION['admin_order_notice']);
} else {
    $adminOrderNotice = null;
}

$filtro_status = $_GET['status'] ?? 'Todos';
$filtrosPermitidos = array_merge(['Todos'], $statusPermitidos);
if (!is_string($filtro_status) || !in_array($filtro_status, $filtrosPermitidos, true)) {
    $filtro_status = 'Todos';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $orderId = filter_var($_POST['id_pedido'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = $_POST['status_pedido'] ?? '';
    $returnStatus = $_POST['filtro_status'] ?? 'Todos';
    $returnTo = $_POST['return_to'] ?? 'pedidos';
    if (!is_string($returnStatus) || !in_array($returnStatus, $filtrosPermitidos, true)) {
        $returnStatus = 'Todos';
    }
    if (!is_string($returnTo) || !in_array($returnTo, ['pedidos', 'estoque'], true)) {
        $returnTo = 'pedidos';
    }

    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['admin_order_notice'] = ['type' => 'error', 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    } elseif ($orderId === false || !is_string($newStatus) || !in_array($newStatus, $statusPermitidos, true)) {
        $_SESSION['admin_order_notice'] = ['type' => 'error', 'message' => 'Pedido ou status inválido.'];
    } else {
        try {
            $orderExists = $pdo->prepare('SELECT id_pedido FROM pedidos WHERE id_pedido = ?');
            $orderExists->execute([$orderId]);
            if (!$orderExists->fetchColumn()) {
                $_SESSION['admin_order_notice'] = ['type' => 'error', 'message' => 'Esse pedido não foi encontrado.'];
            } else {
                $updateStatus = $pdo->prepare('UPDATE pedidos SET status_pedido = ? WHERE id_pedido = ?');
                $updateStatus->execute([$newStatus, $orderId]);
                $_SESSION['admin_order_notice'] = ['type' => 'success', 'message' => 'Status do pedido atualizado para ' . $newStatus . '.'];
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $_SESSION['admin_order_notice'] = ['type' => 'error', 'message' => 'Não foi possível atualizar o pedido. Tente novamente.'];
        }
    }

    $redirectUrl = $returnTo === 'estoque' ? 'estoque.php' : 'pedidos.php';
    if ($returnTo === 'pedidos' && $returnStatus !== 'Todos') {
        $redirectUrl .= '?status=' . rawurlencode($returnStatus);
    }
    header('Location: ' . $redirectUrl);
    exit();
}

$sql = "SELECT p.id_pedido, u.nome AS nome_cliente, p.data_pedido, p.status_pedido, p.valor_total 
        FROM pedidos p 
        INNER JOIN usuarios u ON p.id_cliente = u.id_user";

if ($filtro_status !== 'Todos') {
    $sql .= " WHERE p.status_pedido = :status";
}
$sql .= " ORDER BY p.data_pedido DESC";

$stmt = $pdo->prepare($sql);
if ($filtro_status !== 'Todos') {
    $stmt->bindParam(':status', $filtro_status);
}
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itensPorPedido = [];
if ($pedidos) {
    $placeholders = implode(',', array_fill(0, count($pedidos), '?'));
    $itensStatement = $pdo->prepare(
        "SELECT ip.id_pedido, ip.quantidade, ip.preco_unitario,
                COALESCE(pr.nome_produto, ip.nome_produto, 'Produto removido') AS nome_produto
         FROM itens_pedido ip
         LEFT JOIN produtos pr ON pr.id_produto = ip.id_produto
         WHERE ip.id_pedido IN ($placeholders)
         ORDER BY ip.id_pedido, ip.id_item"
    );
    $itensStatement->execute(array_column($pedidos, 'id_pedido'));
    foreach ($itensStatement->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itensPorPedido[(int) $item['id_pedido']][] = $item;
    }
}

function formatarData($dataHora) {
    $data = new DateTime($dataHora);
    return $data->format('d/m/Y - H:i');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Pedidos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #FAFAFA;
            margin: 0;
            padding: 0 24px 40px;
            color: #333;
        }
        .admin-site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 78px;
            margin: 0 -24px 32px;
            background: #eaa4b1;
            box-shadow: 0 4px 15px rgba(98, 56, 34, .1);
        }
        .admin-menu {
            display: flex;
            align-items: center;
            gap: 24px;
            height: 100%;
            margin: 0;
            padding: 0 5%;
            list-style: none;
        }
        .admin-menu li:first-child { margin-right: auto; }
        .admin-menu a {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: color .2s ease;
        }
        .admin-menu a:hover,
        .admin-menu a[aria-current="page"] { color: #623822; }
        .admin-logo { display: block; width: 126px; height: auto; }
        .admin-menu form { margin: 0; }
        .logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 23px;
            border: 1px solid #623822;
            border-radius: 30px;
            background: #623822;
            color: #fff;
            font: 600 13px 'Montserrat', sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .admin-menu a.logout-button { color: #fff; }
        .admin-menu a.logout-button:hover { background: #4b2a1a; color: #fff; }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        p.subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 40px;
        }
        .tabs {
            display: flex;
            gap: 30px;
            border-bottom: 1px solid #eee;
            margin-bottom: 30px;
        }
        .tabs a {
            text-decoration: none;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            padding-bottom: 15px;
            position: relative;
        }
        .tabs a.active {
            color: #333;
            font-weight: 600;
        }
        .tabs a.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #F07FA6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            text-align: left;
            font-weight: 500;
            color: #666;
            font-size: 14px;
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
        }
        td {
            padding: 20px 10px;
            font-size: 14px;
            border-bottom: 1px solid #f9f9f9;
            color: #333;
        }
        .pedido-id {
            color: #F07FA6;
            font-weight: 600;
        }
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-pendente { background-color: #FFF3E0; color: #E65100; }
        .badge-preparo { background-color: #FFE0B2; color: #E65100; }
        .badge-pronto { background-color: #E3F2FD; color: #1565C0; }
        .badge-em-entrega { background-color: #EDE7F6; color: #5E35B1; }
        .badge-entregue { background-color: #E8F5E9; color: #2E7D32; }
        .badge-cancelado { background-color: #F5F5F5; color: #616161; }
        .order-items { min-width: 220px; }
        .order-items ul { margin: 0; padding-left: 18px; }
        .order-items li { margin: 0 0 5px; line-height: 1.45; }
        .order-items li:last-child { margin-bottom: 0; }
        .order-item-price { color: #777; font-size: 12px; white-space: nowrap; }
        .status-update-form { display: flex; align-items: center; gap: 7px; min-width: 220px; }
        .status-update-form select {
            min-width: 130px;
            padding: 8px 9px;
            border: 1px solid #eadde1;
            border-radius: 8px;
            background: #fff;
            color: #49383b;
            font: 500 12px 'Montserrat', sans-serif;
        }
        .status-update-form button {
            padding: 8px 10px;
            border: 0;
            border-radius: 8px;
            background: #eaa4b1;
            color: #fff;
            font: 600 11px 'Montserrat', sans-serif;
            cursor: pointer;
        }
        .status-update-form button:hover { background: #d96888; }
        .order-notice { margin: 0 0 20px; padding: 12px 15px; border-radius: 9px; font-size: 13px; }
        .order-notice-success { background: #edf7ed; color: #27632a; }
        .order-notice-error { background: #fff0f0; color: #a12626; }
        
        .btn-view {
            background-color: #FFF0F5;
            color: #333;
            border: none;
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-view:hover { background-color: #FCE8EF; }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
        }
        .page-numbers {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .page-numbers a, .page-numbers span {
            text-decoration: none;
            color: #333;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        .page-numbers .active {
            background-color: #F07FA6;
            color: #fff;
            font-weight: 600;
        }
        .page-numbers a:hover:not(.active) {
            background-color: #eee;
        }

        .header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header-title h1 {
    margin-bottom: 8px;
}

.header-title p.subtitle {
    margin-bottom: 0;
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
    transition: background-color 0.2s, color 0.2s;
}

.btn-voltar:hover {
    background-color: #FCE8EF;
    color: #F07FA6;
}
@media (max-width: 760px) {
    body { padding-right: 14px; padding-left: 14px; }
    .admin-site-header { height: auto; min-height: 68px; margin-right: -14px; margin-left: -14px; }
    .admin-menu { flex-wrap: wrap; gap: 12px; padding: 12px 16px; }
    .admin-menu li:first-child { margin-right: auto; }
    .admin-logo { width: 98px; }
    .admin-menu a { font-size: 12px; }
    .container { padding: 22px 16px; }
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
            <li><a href="<?php echo htmlspecialchars($adminDashboardUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Abrir dashboard administrativo">Dashboard</a></li>
            <li><a href="estoque.php">Estoque</a></li>
            <li><a class="logout-button" href="../logout.php" aria-label="Sair da conta">Sair</a></li>
        </ul>
    </header>

    <div class="container">
        <h1>Pedidos</h1>   <a href="estoque.php" class="btn-voltar">
            <span class="material-symbols-outlined">arrow_back</span>
            Voltar ao Estoque
        </a>
        <p class="subtitle">Acompanhe e gerencie todos os pedidos da sua loja.</p>

        <?php if (is_array($adminOrderNotice) && isset($adminOrderNotice['message'])): ?>
            <p class="order-notice order-notice-<?php echo ($adminOrderNotice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="<?php echo ($adminOrderNotice['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>">
                <?php echo htmlspecialchars((string) $adminOrderNotice['message'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <div class="tabs">
            <?php
            $abas = $filtrosPermitidos;
            foreach ($abas as $aba): 
                $activeClass = ($filtro_status === $aba) ? 'active' : '';
                $url = '?status=' . urlencode($aba);
                $labelAba = $aba === 'Pendente' ? 'Pendentes' : ($aba === 'Pronto' ? 'Prontos' : ($aba === 'Entregue' ? 'Entregues' : ($aba === 'Cancelado' ? 'Cancelados' : $aba)));
            ?>
                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $activeClass; ?>"><?php echo htmlspecialchars($labelAba, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Produtos comprados</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Atualizar status</th>

                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pedidos)): ?>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td class="pedido-id">#<?php echo htmlspecialchars((string) $pedido['id_pedido'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($pedido['nome_cliente'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo formatarData($pedido['data_pedido']); ?></td>
                            <td class="order-items">
                                <?php $itensPedido = $itensPorPedido[(int) $pedido['id_pedido']] ?? []; ?>
                                <?php if ($itensPedido): ?>
                                    <ul>
                                        <?php foreach ($itensPedido as $item): ?>
                                            <li>
                                                <?php echo (int) $item['quantidade']; ?> ×
                                                <?php echo htmlspecialchars($item['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>
                                                <span class="order-item-price">(R$ <?php echo number_format((float) $item['preco_unitario'], 2, ',', '.'); ?> cada)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span>Sem itens registrados</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = '';
                                switch ($pedido['status_pedido']) {
                                    case 'Pendente': $statusClass = 'badge-pendente'; break;
                                    case 'Em preparo': $statusClass = 'badge-preparo'; break;
                                    case 'Pronto': $statusClass = 'badge-pronto'; break;
                                    case 'Em entrega': $statusClass = 'badge-em-entrega'; break;
                                    case 'Entregue': $statusClass = 'badge-entregue'; break;
                                    case 'Cancelado': $statusClass = 'badge-cancelado'; break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($pedido['status_pedido'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>R$ <?php echo number_format((float) $pedido['valor_total'], 2, ',', '.'); ?></td>
                            <td>
                                <form class="status-update-form" method="POST" action="pedidos.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_pedido" value="<?php echo (int) $pedido['id_pedido']; ?>">
                                    <input type="hidden" name="filtro_status" value="<?php echo htmlspecialchars($filtro_status, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="return_to" value="pedidos">
                                    <select name="status_pedido" aria-label="Novo status do pedido <?php echo (int) $pedido['id_pedido']; ?>">
                                        <?php foreach ($statusPermitidos as $status): ?>
                                            <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $pedido['status_pedido'] === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999;">Nenhum pedido encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <span>Mostrando 1 a <?php echo count($pedidos); ?> de <?php echo count($pedidos); ?> pedidos</span>
            <div class="page-numbers">
                <a href="#"><span class="material-symbols-outlined" style="font-size: 18px;">chevron_left</span></a>
                <span class="active">1</span>
                <a href="#">2</a>
                <a href="#">3</a>
                <span>...</span>
                <a href="#">5</a>
                <a href="#"><span class="material-symbols-outlined" style="font-size: 18px;">chevron_right</span></a>
            </div>
        </div>
    </div>
</body>
</html>