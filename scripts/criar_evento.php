<?php
require_once 'db.php';
require_once 'sessao.php';

requirePerfil('administrador', '../login.html');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/evento-criar.php');
    exit;
}

$nome                = trim($_POST['nome']                ?? '');
$data                = trim($_POST['data']                ?? '');
$hora                = trim($_POST['hora']                ?? '');
$sala                = trim($_POST['sala']                ?? '');
$categoria           = trim($_POST['categoria']           ?? '');
$classificacao_etaria = trim($_POST['classificacao_etaria'] ?? 'Livre');
$descricao           = trim($_POST['descricao']           ?? '');
$capacidade          = (int)($_POST['capacidade']         ?? 0);
$estado              = trim($_POST['estado']              ?? 'rascunho');
$preco_normal        = (float)($_POST['preco_normal']     ?? 0);
$preco_jovem         = (float)($_POST['preco_jovem']      ?? 0);
$preco_senior        = (float)($_POST['preco_senior']     ?? 0);

if ($nome === '' || $data === '' || $hora === '' || $sala === '' || $categoria === '') {
    header('Location: ../admin/evento-criar.php?erro=campos_obrigatorios');
    exit;
}
if ($capacidade <= 0) {
    header('Location: ../admin/evento-criar.php?erro=capacidade_invalida');
    exit;
}
if (!in_array($estado, ['publicado', 'rascunho', 'cancelado'])) {
    $estado = 'rascunho';
}

$db = getDB();
$db->exec('BEGIN');

$ins = $db->prepare(
    'INSERT INTO eventos (nome, descricao, data, hora, sala, categoria, classificacao_etaria, capacidade, estado)
     VALUES (:nome, :desc, :data, :hora, :sala, :cat, :clas, :cap, :estado)'
);
$ins->bindValue(':nome',   $nome,                 SQLITE3_TEXT);
$ins->bindValue(':desc',   $descricao ?: null,     $descricao ? SQLITE3_TEXT : SQLITE3_NULL);
$ins->bindValue(':data',   $data,                 SQLITE3_TEXT);
$ins->bindValue(':hora',   $hora,                 SQLITE3_TEXT);
$ins->bindValue(':sala',   $sala,                 SQLITE3_TEXT);
$ins->bindValue(':cat',    $categoria,            SQLITE3_TEXT);
$ins->bindValue(':clas',   $classificacao_etaria ?: 'Livre', SQLITE3_TEXT);
$ins->bindValue(':cap',    $capacidade,           SQLITE3_INTEGER);
$ins->bindValue(':estado', $estado,               SQLITE3_TEXT);
$ins->execute();

$evento_id = $db->lastInsertRowID();

$insP = $db->prepare(
    'INSERT INTO precos (evento_id, tipo, preco) VALUES (:eid, :tipo, :preco)'
);
foreach (['normal' => $preco_normal, 'jovem' => $preco_jovem, 'senior' => $preco_senior] as $tipo => $preco) {
    $insP->bindValue(':eid',   $evento_id, SQLITE3_INTEGER);
    $insP->bindValue(':tipo',  $tipo,      SQLITE3_TEXT);
    $insP->bindValue(':preco', $preco,     SQLITE3_FLOAT);
    $insP->execute();
    $insP->reset();
}

$db->exec('COMMIT');

header('Location: ../admin/eventos.php?sucesso=criado');
exit;
