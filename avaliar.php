<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/php/crud.php';

$idProduto = filter_input(INPUT_GET, 'id_produto', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
}

if (!$idProduto || $idProduto < 1) {
    http_response_code(400);
    exit('Produto inválido.');
}

$produto = read($pdo, 'produtos', 'id_produto = :id_produto', [':id_produto' => $idProduto]);
if (!$produto) {
    http_response_code(404);
    exit('Produto não encontrado.');
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT);
    $comentario = trim((string) ($_POST['comentario'] ?? ''));

    if (!isset($_SESSION['autenticado'], $_SESSION['id_user'])) {
        $erro = 'Entre na sua conta para enviar uma avaliação.';
    } elseif (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($nota === false || $nota < 1 || $nota > 5 || $comentario === '') {
        $erro = 'Informe uma nota de 1 a 5 e escreva um comentário.';
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO avaliacoes (produto_id, nome, nota, comentario) VALUES (:produto_id, :nome, :nota, :comentario)'
        );
        $statement->execute([
            ':produto_id' => $idProduto,
            ':nome' => (string) $_SESSION['nome'],
            ':nota' => $nota,
            ':comentario' => $comentario,
        ]);

        header('Location: detalhes.php?id_produto=' . $idProduto . '#avaliacoes');
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$nomeProduto = (string) $produto['nome_produto'];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar <?= htmlspecialchars($nomeProduto, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="./css/bia.css">
    <link rel="stylesheet" href="./avaliar.css">
    <style>
        .avaliacao-mensagem {
            max-width: 650px;
            margin: 20px auto;
            color: #8b2f3c;
            text-align: center;
        }

        .avaliacao-produto {
            color: #623822;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .pagina-principal {
                margin: 40px 16px;
            }

            .avaliacao {
                width: 100%;
                padding: 24px;
            }

            .avaliacao input,
            .avaliacao textarea,
            .avaliacao button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="pagina-principal">
        <form action="avaliar.php?id_produto=<?= (int) $idProduto ?>" method="POST" class="avaliacao">
            <h1>Avaliar produto</h1>
            <?php if ($erro !== ''): ?>
                <p class="avaliacao-mensagem" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <p>Produto</p>
            <input class="avaliacao-produto" type="text" value="<?= htmlspecialchars($nomeProduto, ENT_QUOTES, 'UTF-8') ?>" readonly>

            <?php if (!isset($_SESSION['autenticado'], $_SESSION['id_user'])): ?>
                <p>Entre na sua conta para enviar uma avaliação.</p>
                <a href="./login.php">Entrar</a>
            <?php else: ?>
                <p>Nota da avaliação</p>
                <input type="number" name="nota" min="1" max="5" placeholder="Entre 1 e 5" required>

                <p>Comentário sobre o produto</p>
                <textarea name="comentario" rows="5" required></textarea>

                <input type="hidden" name="id_produto" value="<?= (int) $idProduto ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Enviar avaliação</button>
            <?php endif; ?>
        </form>
    </main>
</body>

</html>
