<?php
if (!isset($_SESSION['autenticado'])) {
    return;
}

$cartScriptPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (basename($cartScriptPath) === 'carrinhopag.php') {
    return;
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../php/crud.php';
}
require_once __DIR__ . '/../php/product_images.php';

$cartRootPath = strpos($cartScriptPath, '/user/') !== false || strpos($cartScriptPath, '/admin/') !== false ? '../' : './';
$cartNotice = $_SESSION['cart_notice'] ?? null;
unset($_SESSION['cart_notice']);
$cartSession = $_SESSION['carrinho'] ?? [];
$cartSession = is_array($cartSession) ? $cartSession : [];
$cartQuantities = [];

foreach ($cartSession as $productId => $quantity) {
    $productId = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($productId !== false && $quantity !== false) {
        $cartQuantities[$productId] = $quantity;
    }
}

$cartProducts = [];
$cartTotalCents = 0;
$cartItemCount = 0;
if ($cartQuantities !== []) {
    $placeholders = implode(',', array_fill(0, count($cartQuantities), '?'));
    $cartStatement = $pdo->prepare(
        "SELECT id_produto, nome_produto, img_produto, preco FROM produtos WHERE id_produto IN ({$placeholders})"
    );
    $cartStatement->execute(array_keys($cartQuantities));

    foreach ($cartStatement->fetchAll(PDO::FETCH_ASSOC) as $cartProduct) {
        $productId = (int) $cartProduct['id_produto'];
        $quantity = $cartQuantities[$productId];
        $cartProduct['quantidade'] = $quantity;
        $cartProduct['imagem_url'] = product_image_url($cartProduct['img_produto'] ?? null);
        $cartProducts[] = $cartProduct;
        $cartTotalCents += (int) round((float) $cartProduct['preco'] * 100) * $quantity;
        $cartItemCount += $quantity;
    }
}

$cartMoney = static function (int $cents): string {
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
};
?>
<button class="site-cart-trigger" type="button" aria-label="Abrir carrinho" aria-controls="site-cart-overlay" aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 1.9-1.4L22 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
    <?php if ($cartItemCount > 0): ?>
        <span class="site-cart-count"><?= $cartItemCount ?></span>
    <?php endif; ?>
</button>

<div class="site-cart-overlay" id="site-cart-overlay" aria-hidden="true">
    <section class="site-cart-modal" role="dialog" aria-modal="true" aria-labelledby="site-cart-title">
        <button class="site-cart-close" type="button" aria-label="Fechar carrinho">&times;</button>
        <h2 id="site-cart-title">Meu carrinho</h2>
        <?php if (is_array($cartNotice) && isset($cartNotice['message'])): ?>
            <p class="site-cart-notice site-cart-notice-<?= ($cartNotice['type'] ?? '') === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars((string) $cartNotice['message'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($cartProducts === []): ?>
            <p class="site-cart-empty">Seu carrinho está vazio.</p>
        <?php else: ?>
            <div class="site-cart-items">
                <?php foreach ($cartProducts as $cartProduct): ?>
                    <article class="site-cart-item">
                        <?php if ($cartProduct['imagem_url'] !== null): ?>
                            <img src="<?= htmlspecialchars($cartRootPath . ltrim($cartProduct['imagem_url'], './'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($cartProduct['nome_produto'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <div>
                            <h3><?= htmlspecialchars($cartProduct['nome_produto'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= (int) $cartProduct['quantidade'] ?> × <?= htmlspecialchars($cartMoney((int) round((float) $cartProduct['preco'] * 100)), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <strong><?= htmlspecialchars($cartMoney((int) round((float) $cartProduct['preco'] * 100) * (int) $cartProduct['quantidade']), ENT_QUOTES, 'UTF-8') ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="site-cart-subtotal">
                <span>Subtotal</span>
                <strong><?= htmlspecialchars($cartMoney($cartTotalCents), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>

        <a class="site-cart-checkout" href="<?= htmlspecialchars($cartRootPath, ENT_QUOTES, 'UTF-8') ?>carrinhopag.php">Ir para o carrinho</a>
    </section>
</div>

<style>
    .site-cart-trigger {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 998;
        display: grid;
        place-items: center;
        width: 58px;
        height: 58px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: #e91e63;
        color: #fff;
        box-shadow: 0 4px 15px rgba(0, 0, 0, .2);
        cursor: pointer;
        transition: transform .2s ease, background-color .2s ease;
    }

    .site-cart-trigger:hover {
        transform: scale(1.08);
        background: #d81b60;
    }

    .site-cart-trigger svg {
        width: 27px;
        height: 27px;
        fill: none;
        stroke: currentColor;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-width: 1.8;
    }

    .site-cart-count {
        position: absolute;
        top: -3px;
        right: -3px;
        display: grid;
        place-items: center;
        min-width: 21px;
        height: 21px;
        padding: 0 5px;
        border: 2px solid #fff;
        border-radius: 12px;
        background: #623822;
        color: #fff;
        font: 600 11px/1 Arial, sans-serif;
    }

    .site-cart-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        justify-content: flex-end;
        background: rgba(15, 23, 42, .48);
        opacity: 0;
        visibility: hidden;
        transition: opacity .2s ease, visibility .2s ease;
    }

    .site-cart-overlay.is-open {
        opacity: 1;
        visibility: visible;
    }

    .site-cart-modal {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 14px;
        box-sizing: border-box;
        width: min(100%, 420px);
        height: 100vh;
        height: 100dvh;
        padding: 26px 22px;
        border-radius: 16px 0 0 16px;
        overflow-y: auto;
        background: #fff;
        box-shadow: -8px 0 28px rgba(0, 0, 0, .2);
        transform: translateX(24px);
        transition: transform .2s ease;
    }

    .site-cart-overlay.is-open .site-cart-modal {
        transform: translateX(0);
    }

    .site-cart-close {
        position: absolute;
        top: 18px;
        right: 20px;
        border: 0;
        background: transparent;
        color: #623822;
        font-size: 30px;
        line-height: 1;
        cursor: pointer;
    }

    .site-cart-modal h2 {
        margin: 0;
        color: #623822;
        font: 700 24px/1.2 'Montserrat', sans-serif;
    }

    .site-cart-items {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .site-cart-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f0e6ea;
        color: #333;
        font-family: 'Montserrat', sans-serif;
    }

    .site-cart-item img {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border-radius: 10px;
        object-fit: cover;
    }

    .site-cart-item div {
        min-width: 0;
        flex: 1;
    }

    .site-cart-item h3 {
        margin: 0 0 4px;
        font-size: 13px;
    }

    .site-cart-item p {
        margin: 0;
        color: #777;
        font-size: 12px;
    }

    .site-cart-item strong {
        white-space: nowrap;
        font-size: 13px;
    }

    .site-cart-empty {
        margin: 0;
        padding: 24px 0;
        color: #777;
        text-align: center;
        font-family: 'Montserrat', sans-serif;
    }

    .site-cart-notice {
        margin: 12px 0;
        padding: 10px 12px;
        border-radius: 8px;
        font: 500 14px 'Montserrat', sans-serif;
    }

    .site-cart-notice-success {
        background: #edf7ed;
        color: #27632a;
    }

    .site-cart-notice-error {
        background: #fff0f0;
        color: #a12626;
    }

    .site-cart-subtotal {
        display: flex;
        justify-content: space-between;
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid #eadde1;
        color: #623822;
        font: 600 16px 'Montserrat', sans-serif;
    }

    .site-cart-checkout {
        display: block;
        padding: 13px 18px;
        border-radius: 24px;
        background: #623822;
        color: #fff;
        text-align: center;
        text-decoration: none;
        font: 600 14px 'Montserrat', sans-serif;
    }

    .site-cart-checkout:hover {
        background: #4b2a1a;
    }

    @media (max-width: 480px) {
        .site-cart-modal {
            width: min(100%, 420px);
        }
    }
</style>

<script>
    (() => {
        const trigger = document.querySelector('.site-cart-trigger');
        const overlay = document.getElementById('site-cart-overlay');
        const closeButton = document.querySelector('.site-cart-close');
        if (!trigger || !overlay || !closeButton) return;

        const closeCart = () => {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            trigger.setAttribute('aria-expanded', 'false');
        };
        trigger.addEventListener('click', () => {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            trigger.setAttribute('aria-expanded', 'true');
            closeButton.focus();
        });
        closeButton.addEventListener('click', closeCart);
        overlay.addEventListener('click', event => {
            if (event.target === overlay) closeCart();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeCart();
                trigger.focus();
            }
        });
        <?php if (is_array($cartNotice)): ?>
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        <?php endif; ?>
    })();
</script>
