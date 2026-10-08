<?php
session_start();

if (isset($_POST['logout'])) {

    session_unset();

    session_destroy();
    header('Location: ../login.php');
    exit();
}


require_once __DIR__ . '/../php/crud.php';
require_once __DIR__ . '/../php/product_images.php';

if (!isset($_SESSION['autenticado'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['autenticado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$adminDashboardUrl = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/estoque.php'))), '/') . '/dashboard.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminOrderNotice = $_SESSION['admin_order_notice'] ?? null;
unset($_SESSION['admin_order_notice']);

$produtos = readAll($pdo, 'produtos');

$valorTotalEstoque = 0;
$totalItens = 0;
$estoqueBaixoCount = 0;

if (!empty($produtos)) {
    foreach ($produtos as $prod) {
        $totalItens += $prod['estoque'];
        $valorTotalEstoque += ($prod['preco'] * $prod['estoque']);
        if ($prod['status_estoque'] === 'Estoque baixo') {
            $estoqueBaixoCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Controle de Estoque</title>

    <link rel="stylesheet" href="../admin/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="icon" type="image/png" href="imagens/logo.png">
    <link rel="stylesheet" href="../css/bia.css">

    <style>
        .admin-site-header {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            width: 100%;
            height: 82px;
            background: #eaa4b1;
            box-shadow: 0 4px 15px rgba(98, 56, 34, 0.1);
        }

        .admin-menu-superior {
            display: flex;
            align-items: center;
            gap: 24px;
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0 5%;
            list-style: none;
            box-sizing: border-box;
        }

        .admin-menu-superior li:first-child {
            margin-right: auto;
        }

        .admin-menu-superior a:not(.btn-sair) {
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }

        .admin-menu-superior a:not(.btn-sair):hover {
            color: #623822;
        }

        .admin-header-logo {
            display: block;
            width: 132px;
            height: auto;
        }

        .admin-menu-superior form {
            margin: 0;
        }

        .admin-menu-superior .btn-sair {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 24px;
            border: 1px solid #623822;
            border-radius: 36px;
            background: #623822;
            color: #fff;
            font: 600 14px 'Montserrat', sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .admin-menu-superior a.btn-sair { color: #fff; }
        .admin-menu-superior a.btn-sair:hover { background: #4b2a1a; color: #fff; }

        .admin-container {
            margin-top: 112px;
        }

        .badge-danger {
            background-color: #FCE8EF;
            color: #DC2626;
        }

        .order-notice {
            margin: 0 0 18px;
            padding: 12px 16px;
            border-radius: 9px;
            font-size: 14px;
        }

        .order-notice-success {
            background: #edf7ed;
            color: #27632a;
        }

        .order-notice-error {
            background: #fff0f0;
            color: #a12626;
        }

        .btn-icon-form {
            display: inline;
        }

        .btn-icon-form button {
            border: 0;
            background: none;
            color: inherit;
            cursor: pointer;
        }

        @media (max-width: 700px) {
            .admin-menu-superior {
                gap: 12px;
                padding: 0 16px;
            }

            .admin-header-logo {
                width: 100px;
            }

            .admin-menu-superior a {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    
<header class="admin-site-header">
    <ul class="admin-menu-superior">

        <li>
            <a href="../index.php">
                <img src="../imagens/Doce_Maria.png" class="admin-header-logo" alt="Doce Maria">
            </a>
        </li>

        <li>
            <a href="../index.php">Início</a>
        </li>

        <li>
            <a href="../sobre.php">Sobre nós</a>
        </li>

        <li>
            <a href="../produtos.php">Catálogo</a>
        </li>

        <li>
            <a href="<?php echo htmlspecialchars($adminDashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a>
        </li>

        <li><a class="btn-sair" href="../logout.php" aria-label="Sair da conta">Sair</a></li>

    </ul>
</header>


    <main class="admin-container">
        <div class="admin-header-saudacao">
            <div class="linha-saudacao">
                <h1>Olá, <?php echo htmlspecialchars($_SESSION['nome'] ?? 'Maria', ENT_QUOTES, 'UTF-8'); ?>! <i class="fa-solid fa-heart color-pink"></i></h1>
            </div>
            <p>Aqui está o controle do seu estoque.</p>
        </div>

        <section class="dashboard-cards">
            <div class="card">
                <div class="card-icon"><span class="material-symbols-outlined">payments</span></div>
                <div class="card-info">
                    <h3>Valor total do estoque</h3>
                    <div class="number">R$ <?php echo number_format($valorTotalEstoque, 2, ',', '.'); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-icon"><span class="material-symbols-outlined">inventory_2</span></div>
                <div class="card-info">
                    <h3>Itens em estoque</h3>
                    <div class="number"><?php echo $totalItens; ?></div>
                </div>
            </div>

            <div class="card card-warning">
                <div class="card-icon"><span class="material-symbols-outlined">warning</span></div>
                <div class="card-info">
                    <h3>Estoque baixo</h3>
                    <div class="number"><?php echo $estoqueBaixoCount; ?></div>
                </div>
            </div>
        </section>

        <section class="section-box">
            <?php if (is_array($adminOrderNotice) && isset($adminOrderNotice['message'])): ?>
                <p class="order-notice order-notice-<?php echo ($adminOrderNotice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="<?php echo ($adminOrderNotice['type'] ?? '') === 'success' ? 'status' : 'alert'; ?>">
                    <?php echo htmlspecialchars((string) $adminOrderNotice['message'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>
            <div class="box-header">
                <div class="box-title">
                    <span class="material-symbols-outlined box-icon">inventory_2</span>
                    <div>
                        <h2>Controle de estoque</h2>
                        <p>Gerencie seus produtos, edite quantidades e mantenha seu estoque sempre atualizado.</p>
                    </div>
                </div>

                <div class="box-actions">

                    <button class="btn-add2" style="margin-right: 10px;">
                        <a href="pedidos.php" class="btn-adicionar" style="text-decoration: none;">
                            <i class="fa-solid fa-list-check"></i> Gerenciar Pedidos
                        </a>
                    </button>

                    <button class="btn-add2">
                        <a href="adicionar_produto.php" class="btn-adicionar">
                            <i class="fa-solid fa-plus"></i> Adicionar Produto
                        </a>
                    </button>
                </div>
            </div>

            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($produtos)): ?>
                        <?php foreach ($produtos as $prod): ?>
                            <?php $imagemProduto = product_image_url($prod['img_produto'] ?? null); ?>
                            <tr>
                                <td>
                                    <div class="produto-info">
                                        <?php if ($imagemProduto !== null): ?>
                                            <img src="../<?php echo htmlspecialchars(substr($imagemProduto, 2), ENT_QUOTES, 'UTF-8'); ?>" class="produto-img" alt="<?php echo htmlspecialchars($prod['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php endif; ?>
                                        <div class="produto-textos">
                                            <span class="produto-nome"><?php echo htmlspecialchars($prod['nome_produto'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="produto-desc"><?php echo htmlspecialchars($prod['peso_tamanho'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-categoria"><?php echo htmlspecialchars($prod['categoria'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars((string) $prod['estoque'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'badge-success';
                                    if ($prod['status_estoque'] === 'Estoque baixo') {
                                        $statusClass = 'badge-warning';
                                    } elseif ($prod['status_estoque'] === 'Esgotado') {
                                        $statusClass = 'badge-danger';
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($prod['status_estoque'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td>
                                    <div class="acoes-flex">
                                        <a href="editar_produto.php?id=<?php echo (int) $prod['id_produto']; ?>" class="btn-icon"><span class="material-symbols-outlined">edit</span></a>
                                        <form class="btn-icon-form" action="excluir_produto.php" method="POST" onsubmit="return confirm('Deseja excluir este produto do estoque? Os pedidos anteriores serão preservados.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) $prod['id_produto']; ?>">
                                            <button type="submit" class="btn-icon" aria-label="Excluir <?php echo htmlspecialchars($prod['nome_produto'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #9C858D; padding: 20px;">
                                Nenhum produto cadastrado.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>

</html>