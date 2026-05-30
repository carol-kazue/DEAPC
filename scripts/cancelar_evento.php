<?php
require_once 'db.php';
require_once 'sessao.php';
requirePerfil('administrador', '../login.html');
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if ($id === 0) {
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('UPDATE eventos SET estado = \'cancelado\' WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);

if ($stmt->execute()) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['erro' => $db->lastErrorMsg()]);
}