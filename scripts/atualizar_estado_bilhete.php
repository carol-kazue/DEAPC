<?php
require_once 'db.php';
require_once 'sessao.php';

if (!isLoggedIn()) {
    header('Location: ../login.html');
    exit;
}
$u = getUtilizador();
if (!in_array($u['perfil'], ['administrador', 'vendedor'])) {
    header('Location: ../index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../bilhetes.php');
    exit;
}

$isAdmin   = ($u['perfil'] === 'administrador');
$compraId  = (int)($_POST['compra_id']   ?? 0);
$novoEst   = trim($_POST['novo_estado']  ?? '');
$redirect  = trim($_POST['redirect']     ?? '../bilhetes.php');

// Sanitiza redirect para evitar open redirect
if (!preg_match('/^[a-zA-Z0-9_.?\-=&\/]+$/', $redirect)) {
    $redirect = '../bilhetes.php';
}
$redirect = '../' . ltrim($redirect, '/');

if ($compraId === 0 || !in_array($novoEst, ['confirmado', 'cancelado'])) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'erro=dados_invalidos');
    exit;
}

// Reativar só para admin
if ($novoEst === 'confirmado' && !$isAdmin) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'erro=sem_permissao');
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, vendedor_id, estado FROM compras WHERE id = :id');
$stmt->bindValue(':id', $compraId, SQLITE3_INTEGER);
$compra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$compra) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'erro=nao_encontrado');
    exit;
}

// Vendedor só pode cancelar as suas próprias compras
if (!$isAdmin && (int)$compra['vendedor_id'] !== (int)$u['id']) {
    header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'erro=sem_permissao');
    exit;
}

$upd = $db->prepare('UPDATE compras SET estado = :estado WHERE id = :id');
$upd->bindValue(':estado', $novoEst,  SQLITE3_TEXT);
$upd->bindValue(':id',     $compraId, SQLITE3_INTEGER);
$upd->execute();

$msg = $novoEst === 'cancelado' ? 'cancelado' : 'reativado';
header('Location: ' . $redirect . (strpos($redirect, '?') !== false ? '&' : '?') . 'sucesso=' . $msg);
exit;
