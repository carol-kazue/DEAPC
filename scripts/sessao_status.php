<?php
require_once 'sessao.php';
header('Content-Type: application/json');

if (isLoggedIn()) {
    echo json_encode([
        'loggedIn' => true,
        'nome'     => $_SESSION['nome'],
        'perfil'   => $_SESSION['perfil']
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}