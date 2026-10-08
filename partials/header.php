<head>
    <?php
    $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    if (preg_match('~/(?:user|admin|php|CRUD)$~i', $scriptDirectory)) {
        $scriptDirectory = dirname($scriptDirectory);
    }
    $siteBaseUrl = rtrim($scriptDirectory, '/.');
    $siteBaseUrl = $siteBaseUrl === '' ? '' : $siteBaseUrl;
    $siteHomeUrl = $siteBaseUrl . '/index.php';
    $siteAboutUrl = $siteBaseUrl . '/sobre.php';
    $siteCatalogUrl = $siteBaseUrl . '/produtos.php';
    $isAdmin = ($_SESSION['tipo_usuario'] ?? '') === 'admin';
    $accountUrl = $siteBaseUrl . ($isAdmin ? '/admin/dashboard.php' : '/user/minha_conta.php');
    $logoutUrl = $siteBaseUrl . '/logout.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Belleza&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Qwitcher+Grypen:wght@700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($siteBaseUrl, ENT_QUOTES, 'UTF-8') ?>/css/bia.css?v=6">
</head>
<header class="<?= isset($_SESSION['autenticado']) ? 'account-header' : '' ?>">
    <nav>
        <ul class="menu-superior">
            <li>
                <a href="<?= htmlspecialchars($siteHomeUrl, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($siteBaseUrl, ENT_QUOTES, 'UTF-8') ?>/imagens/Doce_Maria.png" alt="Doce Maria Logo" class="header-logo"></a>
            </li>
            <li><a href="<?= htmlspecialchars($siteHomeUrl, ENT_QUOTES, 'UTF-8') ?>">Início</a></li>
            <li><a href="<?= htmlspecialchars($siteAboutUrl, ENT_QUOTES, 'UTF-8') ?>">Sobre nós</a></li>
            <li><a href="<?= htmlspecialchars($siteCatalogUrl, ENT_QUOTES, 'UTF-8') ?>">Catálogo</a></li>

            <?php if (isset($_SESSION['autenticado'])): ?>
                <li>
                    <?php if ($isAdmin): ?>
                        <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Abrir dashboard administrativo">Dashboard</a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Minha conta</a>
                    <?php endif; ?>
                </li>
                <li>
                    <a class="btn-sair" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Sair da conta">Sair</a>
                </li>
            <?php else: ?>
                <li>
                    <a class="btnentrar" href="<?= htmlspecialchars($siteBaseUrl, ENT_QUOTES, 'UTF-8') ?>/login.php">Entrar</a>
                </li>
                <li>
                    <a class="btncad" href="<?= htmlspecialchars($siteBaseUrl, ENT_QUOTES, 'UTF-8') ?>/cadastro.php">Cadastre-se</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<?php require __DIR__ . '/cart_modal.php'; ?>
