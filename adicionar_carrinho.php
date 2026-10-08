<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}

$productId = filter_var($_POST['id_produto'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$quantity = filter_var($_POST['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$isDetailsPage = ($_POST['return_to'] ?? '') === 'details';
$redirect = $isDetailsPage && $productId !== false
    ? 'detalhes.php?id_produto=' . $productId
    : 'produtos.php';

if ($redirect === 'produtos.php' && isset($_POST['categoria']) && is_string($_POST['categoria']) && $_POST['categoria'] !== '') {
    $redirect .= '?categoria=' . rawurlencode($_POST['categoria']);
}

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    $_SESSION['cart_notice'] = ['type' => 'error', 'message' => 'Sua sessão expirou. Atualize a página e tente novamente.'];
    header('Location: ' . $redirect);
    exit;
}

if ($productId === false || $quantity === false) {
    $_SESSION['cart_notice'] = ['type' => 'error', 'message' => 'Não foi possível adicionar esse produto ao carrinho.'];
    header('Location: ' . $redirect);
    exit;
}

require_once __DIR__ . '/php/crud.php';
$statement = $pdo->prepare('SELECT nome_produto, estoque FROM produtos WHERE id_produto = :id_produto');
$statement->execute([':id_produto' => $productId]);
$product = $statement->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['cart_notice'] = ['type' => 'error', 'message' => 'Esse produto não está mais disponível.'];
} else {
    $cart = $_SESSION['carrinho'] ?? [];
    $cart = is_array($cart) ? $cart : [];
    $currentQuantity = filter_var($cart[$productId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $currentQuantity = $currentQuantity === false ? 0 : $currentQuantity;
    $newQuantity = $currentQuantity + $quantity;
    $stock = max(0, (int) $product['estoque']);

    if ($stock < 1) {
        $_SESSION['cart_notice'] = ['type' => 'error', 'message' => 'Esse produto está sem estoque no momento.'];
    } elseif ($newQuantity > $stock || $newQuantity > 99) {
        $_SESSION['cart_notice'] = [
            'type' => 'error',
            'message' => 'A quantidade solicitada de ' . (string) $product['nome_produto'] . ' excede o estoque disponível.',
        ];
    } else {
        $cart[$productId] = $newQuantity;
        $_SESSION['carrinho'] = $cart;
        $_SESSION['cart_notice'] = [
            'type' => 'success',
            'message' => (string) $product['nome_produto'] . ' adicionado ao carrinho.',
        ];
    }
}

header('Location: ' . $redirect);
exit;
