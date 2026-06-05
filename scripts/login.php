<?php
require_once 'db.php';
require_once 'sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.html');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header('Location: ../login.html?erro=campos_vazios');
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, nome, apelido, email, password_hash, perfil FROM utilizadores WHERE email = :email AND estado = \'ativo\'');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$res  = $stmt->execute();
$row  = $res->fetchArray(SQLITE3_ASSOC);

if (!$row || !password_verify($password, $row['password_hash'])) {
    header('Location: ../login.html?erro=credenciais_invalidas');
    exit;
}

// Regista acesso
$ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$ins  = $db->prepare('INSERT INTO acessos (utilizador_id, ip) VALUES (:uid, :ip)');
$ins->bindValue(':uid', $row['id'], SQLITE3_INTEGER);
$ins->bindValue(':ip',  $ip,        SQLITE3_TEXT);
$ins->execute();

// Atualiza último acesso
$upd = $db->prepare('UPDATE utilizadores SET ultimo_acesso = datetime(\'now\') WHERE id = :id');
$upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
$upd->execute();

$_SESSION['utilizador_id'] = $row['id'];
$_SESSION['nome']           = $row['nome'] . ' ' . $row['apelido'];
$_SESSION['email']          = $row['email'];
$_SESSION['perfil']         = $row['perfil'];

$next = trim($_POST['next'] ?? '');
if ($next !== '' && !preg_match('/^https?:\/\//i', $next)) {
    header('Location: ../' . ltrim($next, '/'));
    exit;
}

switch ($row['perfil']) {
    case 'administrador': header('Location: ../admin.php');     break;
    case 'vendedor':      header('Location: ../vendedor.php');  break;
    default:              header('Location: ../cliente.php');   break;
}
exit;
