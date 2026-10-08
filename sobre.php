<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Carattere&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Belleza&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Qwitcher+Grypen:wght@700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Sobre</title>
    <link rel="stylesheet" href="./css/bia.css">
</head>

<body>
    <?php require_once "./partials/header.php"; ?>

    <main>
        <div class="container-foto">
            <img src="./imagens/sobre.png" class="ftsobre" alt="Home">
            <h1 class="texto-sobre">Nossa História</h1>
        </div>
        <div class="bck-pink"></div>

        <section>
            <h4 class="litleTitle">Como tudo começou</h4>
            <h1 class="titleSobre">Um doce que nasceu<strong class="subTitle"> do carinho</strong></h1>
            <p class="txt-sobre">A Doce Maria começou bem devagarzinho, na cozinha da vovó Maria. Era domingo à tarde, a
                casa cheia, e
                aquele cheirinho de brigadeiro de panela tomando conta de tudo. A receita, escrita à mão num caderninho
                meio amarelado, ninguém sabia explicar — só sabiam repetir de olhos fechados.
                Foi dessa cozinha que saiu a nossa primeira caixinha. Um mimo simples, feito pra agradecer alguém
                querido. E foi aí que a gente entendeu: doce bom não é só sobremesa, é jeito de dizer "gosto de você".
                Mais de dez anos depois, a gente continua com o mesmo caderninho na cabeceira e a mesma vontade de
                adoçar o seu dia. Muda o tamanho da cozinha, mas o carinho na medida é sempre o mesmo.</p>

            <div class="pink-sbr">
                <img src="./imagens/aspas.png" alt="aspas" class="aspas">
                <p class="txt-fdr">
                    "Se é feito com pressa, não tem graça. Doce bom precisa de tempo e de gente que gosta." <Strong
                        class="txt-fd"> — Maria,
                        fundadora</Strong>
                </p>
            </div>

            <div class="box-pink">
                <div class="box-item">
                    <h1 class="txt-pq">12 anos</h1>
                    <strong class="txt-normal">De história e muito carinho</strong>
                </div>

                <div class="box-item">
                    <h1 class="txt-pq">48 sabores</h1>
                    <strong class="txt-normal">Criados à mão todos os dias</strong>
                </div>

                <div class="box-item">
                    <h1 class="txt-pq">3 ateliers</h1>
                    <strong class="txt-normal">Espalhados pela cidade</strong>
                </div>

                <div class="box-item">
                    <h1 class="txt-pq">9k+ mimos</h1>
                    <strong class="txt-normal">Entregues com sorriso</strong>
                </div>
            </div>

            <div class="timeline">

                <div class="historia">
                    <span class="ano">2012</span>
                    <h2>Uma cozinha, um sonho</h2>
                    <p>
                        Tudo começou na cozinha da vovó Aurora, com uma receita de brigadeiro de panela que atravessou
                        gerações e conquistou a vizinhança inteira.
                    </p>
                </div>

                <div class="historia">
                    <span class="ano">2015</span>
                    <h2>O primeiro atelier</h2>
                    <p>
                        Abrimos nossa primeira lojinha na Rua das Flores. Uma vitrine cor-de-rosa, um cheirinho de
                        chocolate quente e a certeza de que estávamos no caminho certo.
                    </p>
                </div>

                <div class="historia">
                    <span class="ano">2019</span>
                    <h2> Doce Mimo ganha o mundo
                    </h2>
                    <p>
                        Começamos a entregar pelo Brasil inteiro e cada caixinha virou um abraço que viaja longe, sempre
                        com um mimo extra dentro.

                    </p>
                </div>

                <div class="historia">
                    <span class="ano">2024</span>
                    <h2>Nossa casa nova
                    </h2>
                    <p>
                        Inauguramos o Atelier Jardim, um espaço para oficinas de chocolate, tardes de chá e muitas
                        histórias compartilhadas ao redor de uma mesa.

                    </p>
                </div>
            </div>

            <img src="./imagens/ftsobre.png" class="ftsobre">


            <div class="container-fotos">
                <img src="./imagens/exterior.png" class="fthome">
                <h4 class="txt-fotos"> Atelier Jardim
                </h4>
                <h1 class="texto-fotos">
                    Um café quentinho e um doce te esperam
                    Venha nos visitar, experimentar sabores novos e brincar de fazer chocolate nas nossas tardes de
                    oficina. Vai ser um mimo.
                </h1>
                <a href="https://www.google.com/maps/@-23.6446254,-46.5614187,14z?entry=ttu&g_ep=EgoyMDI2MDkyOC4wIKXMDSoASAFQAw%3D%3D"
                    target="_blank"><button type="button" class="btn-fotos">Como chegar</button></a>
            </div>

            <?php
            require_once './partials/footer.php'
                ?>

        </section>
    </main>
</body>

</html>