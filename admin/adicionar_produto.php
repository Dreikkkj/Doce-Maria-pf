<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../php/crud.php';
require_once __DIR__ . '/../php/product_categories.php';

if (!isset($_SESSION['autenticado']) || ($_SESSION['tipo_usuario'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$erro = "";
$sucesso = "";
$categorias = product_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = filter_var($_POST['preco'] ?? null, FILTER_VALIDATE_FLOAT);
    $estoque = filter_var($_POST['estoque'] ?? null, FILTER_VALIDATE_INT);
    $categoria = $_POST['categoria'] ?? '';
    $imagem = $_FILES['imagem'] ?? null;

    if ($nome === '' || strlen($nome) > 100 || $preco === false || $preco <= 0 || $preco > 99999999.99 || $estoque === false || $estoque < 0) {
        $erro = "Preencha todos os campos obrigatórios!";
    } elseif (!is_string($categoria) || !in_array($categoria, $categorias, true)) {
        $erro = "A categoria selecionada não existe no banco de dados.";
    } elseif ($imagem === null || $imagem['error'] === UPLOAD_ERR_NO_FILE) {
        $erro = "Selecione uma imagem para o produto.";
    } elseif ($imagem['error'] !== UPLOAD_ERR_OK) {
        $erro = "Não foi possível receber a imagem. Tente selecioná-la novamente.";
    } elseif ($imagem['size'] > 5 * 1024 * 1024) {
        $erro = "A imagem deve ter no máximo 5 MB.";
    } else {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($imagem['tmp_name']);
        $imageInfo = @getimagesize($imagem['tmp_name']);
        $extensoesPermitidas = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensoesPermitidas[$mimeType]) || $imageInfo === false) {
            $erro = "Envie uma imagem válida no formato JPG, PNG ou WEBP.";
        } else {
            $diretorioUploads = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
            $nomeArquivo = 'produto_' . bin2hex(random_bytes(12)) . '.' . $extensoesPermitidas[$mimeType];
            $caminhoImagem = $diretorioUploads . DIRECTORY_SEPARATOR . $nomeArquivo;

            if (!is_dir($diretorioUploads) || !is_writable($diretorioUploads)) {
                $erro = "A pasta de imagens não está disponível para gravação.";
            } elseif (!move_uploaded_file($imagem['tmp_name'], $caminhoImagem)) {
                $erro = "Não foi possível salvar a imagem. Tente novamente.";
            } else {
                $novoProduto = [
                    'img_produto' => $nomeArquivo,
                    'nome_produto' => $nome,
                    'descricao' => $descricao,
                    'preco' => $preco,
                    'estoque' => $estoque,
                    'categoria' => $categoria,
                    'status_estoque' => $estoque === 0 ? 'Esgotado' : ($estoque <= 10 ? 'Estoque baixo' : 'Em estoque')
                ];

                try {
                    create($pdo, 'produtos', $novoProduto);
                    header("Location: estoque.php");
                    exit();
                } catch (Throwable $exception) {
                    if (is_file($caminhoImagem)) {
                        unlink($caminhoImagem);
                    }
                    error_log($exception->getMessage());
                    $erro = "Não foi possível adicionar o produto. Tente novamente.";
                }
            }
        }
    }

    if ($erro !== '') {
        http_response_code(422);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="add_produto.css">
    <link rel="icon" type="image/png" href="imagens/logo.png">
    <title>Adicionar Produto - DoceMaria Admin</title>
</head>

<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="icon-topo">
                <i class="fa-solid fa-plus"></i>
            </div>
            <h1>Novo Produto</h1>
            <p>Cadastre um novo item para o seu estoque.</p>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nome do Produto *</label>
                <div class="input-wrapper">
                    <span class="input-icon"><i class="fa-solid fa-cake-candles"></i></span>
                    <input type="text" name="nome" placeholder="Ex: Brigadeiro Gourmet" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half">
                    <label>Preço (R$) *</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-tag"></i></span>
                        <input type="number" step="0.01" name="preco" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-group half">
                    <label>Qtd. Inicial *</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-cubes"></i></span>
                        <input type="number" name="estoque" placeholder="0" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Categoria</label>
                <div class="input-wrapper">
                    <span class="input-icon"><i class="fa-solid fa-list"></i></span>
                    <select name="categoria">
                        <?php foreach ($categorias as $categoriaDisponivel): ?>
                            <option value="<?php echo htmlspecialchars($categoriaDisponivel, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($_POST['categoria'] ?? '') === $categoriaDisponivel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoriaDisponivel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <div class="input-wrapper textarea-wrapper">
                    <span class="input-icon"><i class="fa-solid fa-align-left"></i></span>
                    <textarea name="descricao" rows="4" placeholder="Detalhes dos ingredientes, peso, etc."></textarea>
                </div>
            </div>

            <div class="form-group">
                <label for="imagem">Imagem do Produto *</label>
                <input class="image-upload" type="file" id="imagem" name="imagem" accept="image/png,image/jpeg,image/webp" required>
                <small class="image-help">JPG, PNG ou WEBP, até 5 MB.</small>
            </div>

            <div class="form-actions">
                <a href="estoque.php" class="btn-cancel">Cancelar</a>
                <button type="submit" class="btn-submit">Adicionar Produto</button>
            </div>
        </form>
    </div>
</body>

</html>