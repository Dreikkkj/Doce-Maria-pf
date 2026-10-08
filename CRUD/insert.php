<?php
require_once 'crud.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['titulo']))

    $novaAvaliacao = [
        'id_user' => $_POST['id_user'],
        'id_produto' => $_POST['id_produto'],
        'nome_produto' => $_POST['nome_produto'],
        'titulo' => $_POST['titulo'],
        'nota' => $_POST['nota'],
        'comentario' => $_POST['comentario']
    ];

$idAvaliacaoNova = create($pdo, 'avaliacoes', $novaAvaliacao);

header('Location: ../detalhe.php?id=' . $_POST['id_produto']);
exit;


