<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once './CRUD/crud.php';
require_once __DIR__ . '/php/product_images.php';

date_default_timezone_set('America/Sao_Paulo');

$sqlprodutos = "
    SELECT 
        p.id_produto AS id,
        p.nome_produto AS nome,
        p.preco,
        p.img_produto AS imagem,
        COALESCE(AVG(a.nota), 0) AS media_nota,
        COUNT(a.id) AS total_avaliacoes
    FROM produtos p
    LEFT JOIN avaliacoes a ON a.produto_id = p.id_produto
    GROUP BY p.id_produto, p.nome_produto, p.preco, p.img_produto
    ORDER BY p.id_produto DESC
";

$stmt = $pdo->prepare($sqlprodutos);
$stmt->execute();
$produtosRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$produtosNew = [];
foreach ($produtosRecentes as $produtoRecente) {
    $imagemUrl = product_image_url($produtoRecente['imagem'] ?? null);
    if ($imagemUrl === null) {
        continue;
    }

    $produtoRecente['imagem_url'] = $imagemUrl;
    $produtosNew[] = $produtoRecente;
    if (count($produtosNew) === 4) {
        break;
    }
}

$totalAvaliacoes = readAll($pdo, 'avaliacoes');

if (!empty($totalAvaliacoes)) {
    $mediaGeralNota = array_sum(array_column($totalAvaliacoes, 'nota')) / count($totalAvaliacoes);
} else {
    $mediaGeralNota = 0;
}
$mediaGeralArredondada = round($mediaGeralNota, 1);

$avaliacao = readAll($pdo, 'avaliacoes', '1 ORDER BY id DESC LIMIT 3');
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Belleza&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Qwitcher+Grypen:wght@700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria</title>
    <link rel="stylesheet" href="./css/bia.css?v=6">
</head>

<body>
    <?php require_once "./partials/header.php"; ?>

    <main>
        <div class="container-foto">
            <img src="./imagens/homeft.png" class="fthome" alt="Home">
            <h1 class="texto-foto">Felicidade em cada pedaço</h1>
            <a href="sobre.php"><button type="button" class="btn-foto">Descubra sua Felicidade</button></a>
        </div>
        <div class="bck-pink"></div>

        <section class="container-creme">
            <br>
            <h1 class="title">Ultimos <strong class="destaque">Lançamentos</strong></h1>
            <div class="grid-best">

                <?php if (empty($produtosNew)): ?>
                    <p>Nenhum produto encontrado.</p>
                <?php else: ?>
                    <?php foreach ($produtosNew as $item): ?>
                        <?php $notaIndividual = round($item['media_nota']); ?>

                        <div class="grid-seller">
                            <div class="img-seller">
                                <img src="<?= htmlspecialchars($item['imagem_url'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($item['nome']) ?>" />
                                <p class="destaque-best">Novo</p>
                            </div>
                            <h4 class="nomeProduto"><?= htmlspecialchars($item['nome']) ?></h4>

                            <div class="avaliacoes">
                                <?php if ($notaIndividual == 0): ?>
                                    <p class="none">Nenhuma Avaliação Disponível</p>
                                <?php else: ?>
                                    <?php for ($i = 1; $i <= $notaIndividual; $i++): ?>
                                        <img src="./imagens/star.png" class="stars" alt="Estrela" />
                                    <?php endfor; ?>
                                <?php endif; ?>
                            </div>

                            <p class="price">R$ <?= number_format($item['preco'], 2, ',', '.') ?></p>
                            <a href="./detalhes.php?id_produto=<?= (int) $item['id'] ?>" class="btn-add">Ver Produto</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section>

        <section>
            <br>
            <h1 class="titlepts">Cadastre-se, faça seus<br> pedidos e acumule<strong class="destaque">pontos</strong>
            </h1>
            <h1 class="cfn">Como funciona?</h1>
            <div class="grid-pts">
                <div class="box-cfn">
                    <img src="./imagens/acumule.png" width="230px" alt="Acumule Doces">
                    <strong class="txtpt">Acumule Doces</strong>
                    <p class="txtpts">Ganhe doces a cada compra realizada, sem pagar nenhuma taxa para participar.</p>
                </div>
                <div class="box-cfn">
                    <img src="./imagens/progresso.png" width="230px" alt="Progresso">
                    <strong class="txtpt">Acompanhe o Progresso</strong>
                    <p class="txtpts">Fique de olho no seu saldo e veja sua evolução rumo aos 255 doces.</p>
                </div>
                <div class="box-cfn">
                    <img src="./imagens/descontos.png" width="230px" alt="Descontos">
                    <strong class="txtpt">Desbloqueie Descontos</strong>
                    <p class="txtpts">Completou a meta? Resgate um super desconto exclusivo para o seu próximo pedido.
                    </p>
                </div>
                <div class="box-cfn">
                    <img src="./imagens/pecaMais.png" width="230px" alt="Peça Mais">
                    <strong class="txtpt">Peça e Ganhe Mais</strong>
                    <p class="txtpts">Quanto mais você pede, mais rápido alcança suas recompensas!</p>
                </div>
            </div>
            <a href="cadastro.php"><button class="btn-conta">Criar Conta e Ganhar Pontos</button></a>
        </section>

        <article class="pink-bgd">
            <h1 class="title-avalia">Últimas Avaliações</h1>
            <h2 class="txt-clt">O que nossos clientes estão dizendo sobre a <strong class="pinkStrong">gente!</strong>
            </h2>
            <h2 class="txt-cls">Classificação de <?= $mediaGeralArredondada ?>/5 estrelas baseadas em avaliações de
                clientes!</h2>

            <div class="grid-avalia">
                <?php if (empty($avaliacao)): ?>
                    <p>Nenhuma avaliação disponível.</p>
                <?php else: ?>
                    <?php foreach ($avaliacao as $avaliacoes): ?>
                        <?php
                        $dataHora = new DateTime($avaliacoes['data_avaliacao']);
                        $notaComentario = isset($avaliacoes['nota']) ? round($avaliacoes['nota']) : (int) $mediaGeralArredondada;
                        ?>
                        <div class="box-avalia">
                            <div class="topo-avalia">
                                <img src="./imagens/<?= htmlspecialchars($avaliacoes['imagem']) ?>" alt="Foto de perfil"
                                    class="foto-perfil">
                                <div class="info-usuario">
                                    <h1><?= htmlspecialchars($avaliacoes['nome']) ?></h1>
                                    <div class="estrelas">
                                        <?php if ($notaComentario == 0): ?>
                                            ☆
                                        <?php else: ?>
                                            <?php for ($i = 1; $i <= $notaComentario; $i++): ?>
                                                <img src="./imagens/estrela.png" alt="Estrela" />
                                            <?php endfor; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="texto-avalia"><?= htmlspecialchars($avaliacoes['comentario']) ?></p>
                            <div class="data-badge">
                                <img src="./imagens/calendar.png" width="17px" alt="Calendário">
                                <h4><?= $dataHora->format('d-m-Y') ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>
    </main>

    <?php require_once "./partials/footer.php"; ?>
</body>

</html>