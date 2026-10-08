<?php
$scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
$siteBaseUrl = rtrim($scriptDirectory, '/.');
if ($siteBaseUrl === '') {
    $siteBaseUrl = '';
}
?>
<header>
    <ul class="menu-superior">

        <li>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($siteBaseUrl . '/imagens/Doce_Maria.png', ENT_QUOTES, 'UTF-8'); ?>" class="header-logo" alt="Doce Maria">
            </a>
        </li>

        <li>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Início</a>
        </li>

        <li>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/sobre.php', ENT_QUOTES, 'UTF-8'); ?>">Sobre nós</a>
        </li>

        <li>
            <a href="<?php echo htmlspecialchars($siteBaseUrl . '/produtos.php', ENT_QUOTES, 'UTF-8'); ?>">Catálogo</a>
        </li>

       
    </ul>
</header>