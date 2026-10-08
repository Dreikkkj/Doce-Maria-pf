<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/php/crud.php';
require_once __DIR__ . '/php/product_images.php';

$id_produto = filter_input(INPUT_GET, 'id_produto', FILTER_VALIDATE_INT);
if (!$id_produto || $id_produto < 1) {
    header('Location: produtos.php');
    exit;
}

$produto = read($pdo, 'produtos', 'id_produto = :id_produto', [':id_produto' => $id_produto]);
if (!$produto) {
    http_response_code(404);
    exit('Produto não encontrado.');
}

$statement = $pdo->prepare('SELECT * FROM avaliacoes WHERE produto_id = :produto_id ORDER BY id DESC LIMIT 10');
$statement->execute([':produto_id' => $id_produto]);
$avaliacoes = $statement->fetchAll(PDO::FETCH_ASSOC);
$imagemUrl = product_image_url($produto['img_produto'] ?? null);

date_default_timezone_set('America/Sao_Paulo');
$nome = $produto['nome_produto'] ?? 'Produto sem nome';
$preco = $produto['preco'] ?? 0;
$descricao = $produto['descricao'] ?? 'Sem descrição disponível para este produto.';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nome); ?> - Detalhes</title>
    <link rel="stylesheet" href="produtos.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        .container-detalhes {
            max-width: 900px;
            margin: auto;
            margin-top: 130px;
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            display: flex;
            gap: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .detalhes-img {
            width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
        }

        .detalhes-info {
            flex-grow: 1;
        }

        .btn-voltar {
            display: inline-block;
            margin-bottom: 20px;
            color: #d1a07d;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-avaliar {
            background-color: #d1a07d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
        }

        .btn-avaliar:hover {
            background-color: #b07a5a;
        }

        .container-avaliar {
            max-width: 900px;
            margin: 20px auto;
            background: #ffffff;
            padding: 50px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .box-avaliacoes {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }

        .avaliacao-cliente {
            font-weight: bold;
            margin-bottom: 10px;
            font-family: "Montserrat", sans-serif;
            color: #df70dfcc;
        }

        .avaliacao-data {
            font-family: "Montserrat", sans-serif;
            color: black;
        }

        .avaliacao-nota {
            font-weight: bold;
            font-family: "Montserrat", sans-serif;
            color: black;
        }

        .avaliacao-comentario {
            font-family: "Montserrat", sans-serif;
            color: black;
        }

        .avaliacao-item + .avaliacao-item {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eadde1;
        }
    </style>
</head>

<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="container-detalhes">
        <div class="detalhes-foto">
            <?php if ($imagemUrl !== null): ?>
                <img src="<?= htmlspecialchars($imagemUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>" class="detalhes-img">
            <?php endif; ?>
        </div>

        <div class="detalhes-info">
            <a href="produtos.php" class="btn-voltar">← Voltar para a lista</a>
            <h1><?php echo htmlspecialchars($nome); ?></h1>
            <h2 style="color: #d1a07d;">R$ <?php echo number_format((float) $preco, 2, ',', '.'); ?></h2>

            <p><strong>Descrição:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($descricao)); ?></p>

            <form method="POST" action="adicionar_carrinho.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_produto" value="<?= (int) $id_produto ?>">
                <input type="hidden" name="quantidade" value="1">
                <input type="hidden" name="return_to" value="details">
                <button class="btn-compre" style="margin-top: 20px; padding: 10px 20px; font-size: 16px;" type="submit">Adicionar ao Carrinho</button>
            </form>
        </div>

    </main>

    <section class="container-avaliar" id="avaliacoes">
        <h2>Avaliações</h2>
        <div class="box-avaliacoes">
            <?php if ($avaliacoes === []): ?>
                <p>Nenhuma avaliação encontrada.</p>
            <?php else: ?>
                <?php foreach ($avaliacoes as $avaliacao): ?>
                    <?php $dataHora = new DateTime($avaliacao['data_avaliacao']); ?>
                    <article class="avaliacao-item">
                        <p class="avaliacao-cliente"><?= htmlspecialchars($avaliacao['nome'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="avaliacao-data">Data da avaliação: <?= $dataHora->format('d/m/Y \à\s H:i') ?></p>
                        <p class="avaliacao-nota">Nota: <?= (int) $avaliacao['nota'] ?>/5</p>
                        <p class="avaliacao-comentario"><?= nl2br(htmlspecialchars($avaliacao['comentario'], ENT_QUOTES, 'UTF-8')) ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="avaliar.php?id_produto=<?= (int) $id_produto ?>">
            <button type="button" class="btn-avaliar">Avaliar</button>
        </a>

    </section>

</body>

</html>
