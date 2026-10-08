<?php
session_start();
require_once __DIR__ . '/../php/crud.php';

// Verifica se o usuário está autenticado
if (!isset($_SESSION['autenticado'])) {
    header("Location: ../login.php");
    exit();
}

$id_user = (int) $_SESSION['id_user'];
$usuario = read($pdo, 'usuarios', 'id_user = :id_user', [':id_user' => $id_user]);

if (!$usuario) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$mensagem_sucesso = $_SESSION['mensagem_sucesso'] ?? null;
unset($_SESSION['mensagem_sucesso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $nova_senha = $_POST['senha'] ?? '';

    if ($nome === '' || strlen($nome) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || strlen($telefone) > 20) {
        http_response_code(422);
        exit('Informe um nome e um e-mail válidos.');
    }

    $emailExistente = read(
        $pdo,
        'usuarios',
        'email = :email AND id_user <> :id_user',
        [':email' => $email, ':id_user' => $id_user]
    );
    if ($emailExistente) {
        http_response_code(422);
        exit('Este e-mail já está cadastrado.');
    }

    $dadosAtualizados = [
        'nome' => $nome,
        'email' => $email,
        'telefone' => $telefone === '' ? null : $telefone
    ];
    if ($nova_senha !== '') {
        $dadosAtualizados['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
    }

    update($pdo, 'usuarios', $dadosAtualizados, 'id_user = ?', [$id_user]);
    $_SESSION['nome'] = $nome;
    $_SESSION['email'] = $email;
    $_SESSION['tel'] = $telefone;
    $_SESSION['mensagem_sucesso'] = 'Perfil atualizado com sucesso!';
    header("Location: editar_perfil.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doce Maria | Editar Perfil</title>

    <!-- Fontes e Ícones -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #FAF6F8;
            color: #333;
            padding-bottom: 60px;
        }

        
        .topbar-cliente {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 25px 60px;
            background-color: transparent;
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            color: #333;
            display: flex;
            align-items: center;
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -2px;
            background-color: #E53935;
            color: white;
            font-size: 10px;
            font-weight: 700;
            border-radius: 50%;
            width: 15px;
            height: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .avatar-sm {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #FCE8EF;
            color: #D81B60;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .user-name-sm {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

  
        .account-container {
            max-width: 750px;
            margin: 0 auto;
            padding: 0 20px;
        }

      
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-text h1 {
            font-size: 26px;
            font-weight: 700;
            color: #222;
            margin-bottom: 6px;
        }

        .header-text p {
            font-size: 14px;
            color: #777;
        }

        .btn-voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #FFF0F5;
            color: #333;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-voltar:hover {
            background-color: #FCE8EF;
            color: #F07FA6;
        }


        .card-box {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 35px;
            border: 1px solid #F0E6EA;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #E0D5D9;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            color: #333;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            border-color: #F07FA6;
        }

        .form-text-muted {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #888;
        }

        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            border-top: 1px solid #F0E6EA;
            padding-top: 25px;
        }

        .btn-salvar {
            background-color: #F07FA6;
            color: #FFF;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-salvar:hover {
            background-color: #D81B60;
        }

        .btn-cancelar {
            background-color: #F5F5F5;
            color: #555;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .btn-cancelar:hover {
            background-color: #EAEAEA;
        }

    
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }
    </style>
</head>

<body>

    <header class="topbar-cliente">
        <div class="user-nav">
            <button class="notification-btn" title="Notificações">
                <span class="material-symbols-outlined">notifications</span>
                <span class="notification-badge">2</span>
            </button>
            <div class="user-profile-menu">
                <div class="avatar-sm">
                    <?php echo htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <span class="user-name-sm">
                    <?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="material-symbols-outlined" style="font-size: 18px; color: #666;">expand_more</span>
            </div>
        </div>
    </header>

    <main class="account-container">
        
  
        <div class="page-header">
            <div class="header-text">
                <h1>Editar Perfil</h1>
                <p>Atualize suas informações pessoais.</p>
            </div>
            
            <a href="minha_conta.php" class="btn-voltar">
                <span class="material-symbols-outlined">arrow_back</span>
                Voltar
            </a>
        </div>

        <?php if ($mensagem_sucesso !== null): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($mensagem_sucesso, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <section class="card-box">
            <form action="editar_perfil.php" method="POST">
                
                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" class="form-control" 
                           value="<?php echo htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group">
                    <label for="senha">Nova Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="Deixe em branco para não alterar">
                    <span class="form-text-muted">Só preencha se quiser mudar a senha atual.</span>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-salvar">Salvar Alterações</button>
                    <a href="minha_conta.php" class="btn-cancelar">Cancelar</a>
                </div>

            </form>
        </section>

    </main>

    <?php require __DIR__ . '/../partials/cart_modal.php'; ?>
</body>
</html>