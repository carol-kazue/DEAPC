<?php
require_once 'db.php';

$db = getDB();

// Lista utilizadores existentes
$res = $db->query('SELECT id, nome, apelido, email, perfil FROM utilizadores');
echo '<h2>Utilizadores na BD:</h2><pre>';
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    print_r($row);
}
echo '</pre>';
/*
// Cria administrador
$nome     = 'Admin';
$apelido  = 'Principal';
$email    = 'admin_Pedro@casaMusica.pt';
$password = '123456';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    'INSERT INTO utilizadores (nome, apelido, email, password_hash, perfil)
     VALUES (:nome, :apelido, :email, :hash, \'administrador\')'
);
$stmt->bindValue(':nome',    $nome,    SQLITE3_TEXT);
$stmt->bindValue(':apelido', $apelido, SQLITE3_TEXT);
$stmt->bindValue(':email',   $email,   SQLITE3_TEXT);
$stmt->bindValue(':hash',    $hash,    SQLITE3_TEXT);

if ($stmt->execute()) {
    echo 'Administrador criado com sucesso!<br>';
    echo 'Email: ' . $email . '<br>';
    echo 'Password: ' . $password;
} else {
    echo 'Erro: ' . $db->lastErrorMsg();
}
*/