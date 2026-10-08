<?php
session_start();
require_once __DIR__ . '/../php/crud.php';

if (!isset($_SESSION['autenticado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$postedToken = (string) ($_POST['csrf_token'] ?? '');
$productId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
    $_SESSION['admin_order_notice'] = [
        'type' => 'error',
        'message' => 'Sua sessão expirou. Atualize a página e tente novamente.',
    ];
    header('Location: estoque.php');
    exit();
}

if ($productId === false || $productId === null) {
    $_SESSION['admin_order_notice'] = [
        'type' => 'error',
        'message' => 'Produto inválido.',
    ];
    header('Location: estoque.php');
    exit();
}

try {
    $pdo->beginTransaction();

    $productStatement = $pdo->prepare(
        'SELECT nome_produto, img_produto FROM produtos WHERE id_produto = ? FOR UPDATE'
    );
    $productStatement->execute([$productId]);
    $product = $productStatement->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $pdo->rollBack();
        $_SESSION['admin_order_notice'] = [
            'type' => 'error',
            'message' => 'Esse produto não foi encontrado.',
        ];
        header('Location: estoque.php');
        exit();
    }

    $snapshotItems = $pdo->prepare(
        'UPDATE itens_pedido
         SET nome_produto = ?, img_produto = ?
         WHERE id_produto = ?'
    );
    $snapshotItems->execute([$product['nome_produto'], $product['img_produto'], $productId]);

    $deleteProduct = $pdo->prepare('DELETE FROM produtos WHERE id_produto = ?');
    $deleteProduct->execute([$productId]);
    $pdo->commit();

    $_SESSION['admin_order_notice'] = [
        'type' => 'success',
        'message' => 'Produto excluído do estoque. O histórico dos pedidos foi preservado.',
    ];
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    $_SESSION['admin_order_notice'] = [
        'type' => 'error',
        'message' => 'Não foi possível excluir o produto. Tente novamente.',
    ];
}

header('Location: estoque.php');
exit();
