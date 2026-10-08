<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/php/product_images.php';

$host = 'localhost';
$usuario = 'root';
$senha = '';
$banco = 'db_docemaria';

$conn = new mysqli($host, $usuario, $senha, $banco);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$categoria_selecionada = $_GET['categoria'] ?? '';

// Adicionado GROUP BY id_produto para evitar registros duplicados
if (!empty($categoria_selecionada)) {
    $categoria_safe = $conn->real_escape_string($categoria_selecionada);
    $sql = "SELECT * FROM produtos WHERE categoria = '$categoria_safe' GROUP BY id_produto ORDER BY nome_produto";
} else {
    $sql = "SELECT * FROM produtos GROUP BY id_produto ORDER BY nome_produto";
}

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Todos</title>
    <link rel="stylesheet" href="produtos.css?v=3">
</head>
<body>
    <?php require_once './partials/header.php'; ?>

    <main>
        <h1>Ver todos</h1>

        <div class="layout-conteudo">
            <aside class="sidebar-categorias">
                <h2>Categorias</h2>
                <form method="GET" action="" id="form-filtro">
                    <ul>
                        <?php 
                        $categorias = ['Brigadeiro', 'Brownies', 'Bolos', 'Cookies','Cupcakes', 'Donuts', 'Macaron', 'Pirulitos'];
                        foreach ($categorias as $cat): 
                            $checked = ($categoria_selecionada === $cat) ? 'checked' : '';
                        ?>
                            <li>
                                <label>
                                    <input type="radio" name="categoria" value="<?php echo $cat; ?>" <?php echo $checked; ?> onchange="this.form.submit()">
                                    <?php echo $cat; ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if (!empty($categoria_selecionada)): ?>
                        <a href="produtos.php" style="display:inline-block; margin-top:10px; font-size:13px; color:#d9534f; text-decoration:none; font-weight:bold;">
                            ✕ Limpar filtro
                        </a>
                    <?php endif; ?>
                </form>
            </aside>

            <section class="grid-produtos">
                <?php 
                if ($resultado && $resultado->num_rows > 0) {
                    while ($produto = $resultado->fetch_assoc()) { 
                        $id_produto = $produto['id_produto'];
                        $nome   = $produto['nome_produto'] ?? $produto['nome'] ?? 'Produto';
                        $preco  = $produto['preco'] ?? 0;
                        $imagem = product_image_url($produto['img_produto'] ?? $produto['imagem'] ?? null);
                        if ($imagem === null) {
                            continue;
                        }
                ?>
                        <div class="card-produtos">
                            <img src="<?php echo htmlspecialchars($imagem, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" class="img-produto">
                            
                            <!-- Nome -->
                            <p class="titulo-produto"><?php echo htmlspecialchars($nome); ?></p>
                            
                            <!-- Preço -->
                            <p class="preco-produto">R$ <?php echo number_format((float)$preco, 2, ',', '.'); ?></p>

                            <!-- Botões de Ação -->
                            <div class="acoes-card">
                                <form class="adicionar-carrinho-form" method="POST" action="adicionar_carrinho.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_produto" value="<?php echo (int) $id_produto; ?>">
                                    <input type="hidden" name="quantidade" value="1" class="quantidade-carrinho">
                                    <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoria_selecionada, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="btn-adicionar-carrinho">Adicionar</button>
                                    <div class="contador-qtd">
                                        <button type="button" class="btn-menos" onclick="alterarQtd(this, -1)">-</button>
                                        <span class="qtd-valor">1</span>
                                        <button type="button" class="btn-mais" onclick="alterarQtd(this, 1)">+</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                <?php 
                    }
                } else {
                    echo "<p>Nenhum produto encontrado.</p>";
                }
                ?>
            </section>
        </div>
    </main>

    <script>
    function alterarQtd(btn, delta) {
        const container = btn.closest('.contador-qtd');
        const spanQtd = container.querySelector('.qtd-valor');
        let qtdAtual = parseInt(spanQtd.textContent) || 1;

        qtdAtual += delta;

        if (qtdAtual < 1) {
            qtdAtual = 1;
        }

        spanQtd.textContent = qtdAtual;
        container.closest('form').querySelector('.quantidade-carrinho').value = qtdAtual;
    }
    </script>

</body>
</html>