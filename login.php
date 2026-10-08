<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/php/crud.php';

if (isset($_SESSION['autenticado'])) {
    redirecionarPorPerfil($_SESSION['tipo_usuario']);
}

$erro = "";

if (isset($_POST['usuario']) && isset($_POST['senha'])) {

    $usuario_digitado = trim($_POST['usuario']);
    $senha_digitada   = trim($_POST['senha']);

    if (empty($usuario_digitado) || empty($senha_digitada)) {
        $erro = "Preencha todos os campos!";
    } else {
        $resultado = read($pdo, 'usuarios', 'email = :email', [':email' => $usuario_digitado]);

        if ($resultado) {
            $usuario_banco = $resultado;
            $senhaArmazenada = $usuario_banco['senha'];
            $senhaValida = password_get_info($senhaArmazenada)['algo'] !== null
                ? password_verify($senha_digitada, $senhaArmazenada)
                : hash_equals($senhaArmazenada, $senha_digitada);

            if ($senhaValida) {
                if (password_get_info($senhaArmazenada)['algo'] === null) {
                    update(
                        $pdo,
                        'usuarios',
                        ['senha' => password_hash($senha_digitada, PASSWORD_DEFAULT)],
                        'id_user = ?',
                        [(int) $usuario_banco['id_user']]
                    );
                }

                $_SESSION['autenticado']       = true;
                $_SESSION['id_user']           = $usuario_banco['id_user'];
                $_SESSION['nome']              = $usuario_banco['nome'];
                $_SESSION['tipo_usuario']      = $usuario_banco['tipo_usuario'];
                $_SESSION['tel']               = $usuario_banco['telefone'];
                $_SESSION['email']             = $usuario_banco['email'];
                $_SESSION['pontos_fidelidade'] = $usuario_banco['pontos_fidelidade'];

                redirecionarPorPerfil($_SESSION['tipo_usuario']);
                exit();
            } else {
                $erro = "Acesso negado! Dados incorretos.";
            }
        } else {
            $erro = "Acesso negado! Dados incorretos.";
        }
    }
}

function redirecionarPorPerfil($tipo)
{
    switch ($tipo) {
        case 'admin':
            header("Location: ./admin/dashboard.php");
            break;
        case 'cliente':
            header("Location: ./user/minha_conta.php");
            break;
        default:
            header("Location: ./login.php");
            break;
    }
    exit();
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
    <link rel="stylesheet" href="cssEloah/identificacao.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="imagens/logo.png">
    
    <title>Página de Login - DoceMaria</title>
</head>


<body>
    <div class="login-container">
        <div class="login-header">
            <img src="imagens/logo.png" class="logo-form" alt="Logo Doce Maria">
            <h1>Bem-vindo de volta! <i class="fa-solid fa-heart color-pink"></i></h1>
            <p>Faça login para acessar sua conta</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div style="background-color: #FCE8EF; color: #DC2626; border: 1px solid #F07FA6; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 600;">
                <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="usuario">E-mail</label>
                <input
                    type="email"
                    id="usuario"
                    name="usuario"
                    class="input-control"
                    placeholder="exemplo@maria.com"
                    required>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    class="input-control"
                    placeholder="••••••••"
                    required>
            </div>

            <button type="submit" class="btn-submit">Entrar</button>
        </form>

        <div class="divisor">
            <span>ou</span>
        </div>

        <div class="login-footer">
            <p>
                Não tem uma conta? <a href="cadastro.php">Cadastre-se aqui</a>
            </p>
        </div>
    </div>
</body>

</html>