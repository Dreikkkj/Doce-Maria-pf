<?php
require_once __DIR__ . '/php/crud.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.php');
    exit();
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($nome === '' || strlen($nome) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || $senha === '') {
    http_response_code(422);
    exit('Informe nome, e-mail e senha válidos.');
}

if (read($pdo, 'usuarios', 'email = :email', [':email' => $email])) {
    http_response_code(422);
    exit('Este e-mail já está cadastrado.');
}

try {
    create($pdo, 'usuarios', [
        'nome' => $nome,
        'email' => $email,
        'senha' => password_hash($senha, PASSWORD_DEFAULT),
        'tipo_usuario' => 'cliente',
        'pontos_fidelidade' => 0
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        http_response_code(422);
        exit('Este e-mail já está cadastrado.');
    }
    throw $exception;
}

header('Location: login.php');
exit();
