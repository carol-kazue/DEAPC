<?php
require_once 'db.php';
require_once 'sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../registo.html');
    exit;
}

$nome_completo = trim($_POST['nome']      ?? '');
$email         = trim($_POST['email']     ?? '');
$password      = trim($_POST['password']  ?? '');
$password2     = trim($_POST['password2'] ?? '');
$data_nasc     = trim($_POST['data_nasc'] ?? '');

// Validações básicas
if ($nome_completo === '' || $email === '' || $password === '') {
    header('Location: ../registo.html?erro=campos_vazios');
    exit;
}
if ($password !== $password2) {
    header('Location: ../registo.html?erro=passwords_diferentes');
    exit;
}
if (strlen($password) < 6) {
    header('Location: ../registo.html?erro=password_curta');
    exit;
}

// Separa nome e apelido
$partes  = explode(' ', $nome_completo, 2);
$nome    = $partes[0];
$apelido = $partes[1] ?? '';

$db = getDB();

// Verifica se o email já existe
$stmt = $db->prepare('SELECT id FROM utilizadores WHERE email = :email');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$res  = $stmt->execute();
if ($res->fetchArray()) {
    header('Location: ../registo.html?erro=email_existe');
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ins  = $db->prepare(
    'INSERT INTO utilizadores (nome, apelido, email, password_hash, perfil, data_nascimento)
     VALUES (:nome, :apelido, :email, :hash, \'cliente\', :data_nasc)'
);
$ins->bindValue(':nome',     $nome,     SQLITE3_TEXT);
$ins->bindValue(':apelido',  $apelido,  SQLITE3_TEXT);
$ins->bindValue(':email',    $email,    SQLITE3_TEXT);
$ins->bindValue(':hash',     $hash,     SQLITE3_TEXT);
$ins->bindValue(':data_nasc', $data_nasc ?: null, $data_nasc ? SQLITE3_TEXT : SQLITE3_NULL);
$ins->execute();

header('Location: ../login.html?sucesso=conta_criada');
exit;
