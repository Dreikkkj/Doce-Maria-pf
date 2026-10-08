<?php
session_start();

if (isset($_POST['logout'])) {

    session_unset();

    session_destroy();
    header('Location: ../login.php');
    exit();
}


require_once __DIR__ . '/../php/crud.php';
require_once __DIR__ . '/../php/loyalty.php';


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


$meta_proximo_nivel = 3000;
$pontos_atuais = loyalty_points_for_user($pdo, (int) $_SESSION['id_user']);
$pontos_restantes = max(0, $meta_proximo_nivel - $pontos_atuais);
$porcentagem_progresso = min(100, round(($pontos_atuais / $meta_proximo_nivel) * 100));
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Minha Conta</title>


    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="../css/bia.css">
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

        :root {
            --rosa-cabecalho: #eaa4b1;
            --marrom-escuro: #623822;
            --marrom-borda: #623a25;
            --branco: #ffffff;
            --fonte-principal: 'Montserrat', sans-serif;
        }

        .account-header {
            background-color: var(--rosa-cabecalho);
            padding: 10px 100px;
            position: sticky;
            top: 0;
            z-index: 1000;
            font-family: var(--fonte-principal);
            box-shadow: 0 4px 15px rgba(98, 56, 34, 0.1);
        }

        .account-header .menu-superior {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 0;
            padding: 0;
            width: 100%;
            box-sizing: border-box;
            position: static;
            background: transparent;
        }

        .account-header .menu-superior li:first-of-type {
            margin-right: auto;
            margin-left: 0;
        }

        .account-header .menu-superior a {
            text-decoration: none;
            color: var(--branco);
            font-weight: 600;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .account-header .menu-superior a:not(.btnentrar):not(.btncad):not(.btn-sair):hover {
            color: var(--marrom-escuro);
        }

        .account-header .header-logo {
            width: 132px;
            height: auto;
            display: block;
            transition: transform 0.3s ease;
        }

        .account-header .header-logo:hover {
            transform: scale(1.03);
        }

        .account-header .btn-sair,
        .account-header .btnentrar,
        .account-header .btncad {
            font-family: var(--fonte-principal);
            padding: 13px 27px;
            border-radius: 36px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .account-header .btn-sair {
            background-color: var(--branco);
            color: var(--marrom-escuro);
            border: 1px solid var(--branco);
        }

        .account-header .btn-sair:hover {
            background-color: var(--marrom-escuro);
            color: var(--branco);
            border-color: var(--marrom-escuro);
        }

        .account-header .menu-superior form {
            margin: 0;
            padding: 0;
            display: inline;
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
            margin-bottom: 30px;
            margin-top: 130px;
        }

        .page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #222;
            margin-bottom: 6px;
        }

        .page-header p {
            font-size: 14px;
            color: #777;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .heart-icon {
            color: #F07FA6;
        }


        .card-box {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 28px 32px;
            border: 1px solid #F0E6EA;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            margin-bottom: 25px;
        }


        .profile-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-info-left {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .avatar-lg {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: #FCE8EF;
            color: #D81B60;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-details h2 {
            font-size: 18px;
            font-weight: 700;
            color: #222;
            margin-bottom: 4px;
        }

        .profile-details p {
            font-size: 13px;
            color: #666;
            margin-bottom: 2px;
        }

        .btn-edit-profile {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #FCE8EF;
            color: #333;
            text-decoration: none;
            padding: 10px 22px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-edit-profile:hover {
            background-color: #FAD2E1;
            color: #D81B60;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #222;
            margin-bottom: 16px;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .quick-access-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #FFFFFF;
            border: 1px solid #F0E6EA;
            border-radius: 12px;
            padding: 18px 24px;
            text-decoration: none;
            color: #333;
            transition: all 0.2s ease;
        }

        .quick-access-item:hover {
            border-color: #F07FA6;
            box-shadow: 0 4px 12px rgba(240, 127, 166, 0.12);
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .item-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background-color: #FFF0F5;
            color: #D81B60;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .item-arrow {
            color: #999;
            font-size: 20px;
        }
    </style>
</head>

<body>

    <header class="account-header">
        <ul class="menu-superior">

            <li>
                <a href="../index.php">
                    <img src="../imagens/Doce_Maria.png" class="header-logo" alt="Doce Maria">
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
                <a href="minha_conta.php">Minha conta</a>
            </li>

            <li>
                <form method="POST" action="minha_conta.php">
                    <button type="submit" class="btn-sair" name="logout">
                        Sair
                    </button>
                </form>
            </li>

        </ul>
    </header>


    <main class="account-container">


        <div class="page-header">
            <h1>Minha conta</h1>
            <p>Gerencie suas informações e acompanhe seus pedidos. <i class="fa-solid fa-heart heart-icon"></i></p>
        </div>


        <section class="card-box profile-card">
            <div class="profile-info-left">
                <div class="avatar-lg">
                    <?php echo htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="profile-details">
                    <h2><?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><?php echo htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <a href="editar_perfil.php" class="btn-edit-profile">
                <i class="fa-solid fa-pencil" style="font-size: 12px;"></i> Editar perfil
            </a>
        </section>


        <section>
            <h2 class="section-title">Acesso rápido</h2>

            <div class="quick-access-grid">

                <a href="meus_pedidos.php" class="quick-access-item">
                    <div class="item-left">
                        <div class="item-icon">
                            <span class="material-symbols-outlined">shopping_bag</span>
                        </div>
                        <span class="item-title">Meus pedidos</span>
                    </div>
                    <span class="material-symbols-outlined item-arrow">chevron_right</span>
                </a>


                <a href="fidelidade.php" class="quick-access-item">
                    <div class="item-left">
                        <div class="item-icon">
                            <i class="fa-regular fa-heart" style="font-size: 18px;"></i>
                        </div>
                        <span class="item-title">Fidelidade</span>
                    </div>
                    <span class="material-symbols-outlined item-arrow">chevron_right</span>
                </a>
            </div>
        </section>

    </main>

    <?php require __DIR__ . '/../partials/cart_modal.php'; ?>
</body>

</html>