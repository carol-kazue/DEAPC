<?php
require_once 'db.php';
require_once 'sessao.php';

requirePerfil('administrador', '../login.html');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/eventos.php');
    exit;
}

$evento_id = (int)($_POST['evento_id'] ?? 0);
if ($evento_id === 0) {
    header('Location: ../admin/eventos.php');
    exit;
}

$db  = getDB();
$upd = $db->prepare("UPDATE eventos SET estado = 'cancelado' WHERE id = :id");
$upd->bindValue(':id', $evento_id, SQLITE3_INTEGER);
$upd->execute();

header('Location: ../admin/eventos.php?sucesso=cancelado');
exit;
