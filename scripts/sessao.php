<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['utilizador_id']);
}

function requireLogin(string $redirect = '../login.html'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function requirePerfil(string $perfil, string $redirect = '../login.html'): void {
    requireLogin($redirect);
    if ($_SESSION['perfil'] !== $perfil) {
        header('Location: ' . $redirect);
        exit;
    }
}

function getUtilizador(): array {
    return [
        'id'     => $_SESSION['utilizador_id'] ?? null,
        'nome'   => $_SESSION['nome']           ?? '',
        'email'  => $_SESSION['email']          ?? '',
        'perfil' => $_SESSION['perfil']         ?? '',
    ];
}
