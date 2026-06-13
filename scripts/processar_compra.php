<?php
require_once 'db.php';
require_once 'sessao.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function sair(bool $ok, array $payload, bool $isAjax, string $redir = '') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ok ? array_merge(['ok' => true], $payload) : ['ok' => false, 'erro' => $payload['erro']]);
        exit;
    }
    header('Location: ' . $redir);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../eventos.php');
    exit;
}

$evento_id  = (int)($_POST['evento_id']       ?? 0);
$qty_normal = max(0, (int)($_POST['qty_normal']  ?? 0));
$qty_jovem  = max(0, (int)($_POST['qty_jovem']   ?? 0));
$qty_senior = max(0, (int)($_POST['qty_senior']  ?? 0));
$nome_cli   = trim($_POST['nome_cliente']         ?? '');
$email_cli  = trim($_POST['email_cliente']        ?? '');
$tel_cli    = trim($_POST['telefone_cliente']     ?? '');
$metodo_pag = trim($_POST['metodo_pagamento']     ?? 'cartao');

if ($evento_id === 0 || ($qty_normal + $qty_jovem + $qty_senior) === 0) {
    sair(false, ['erro' => 'dados_invalidos'], $isAjax, '../eventos.php?erro=dados_invalidos');
}
if ($nome_cli === '' || $email_cli === '') {
    sair(false, ['erro' => 'dados_cliente'], $isAjax, '../eventos.php?erro=dados_cliente');
}

$db = getDB();

$stmtP = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :eid');
$stmtP->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$resP   = $stmtP->execute();
$precos = [];
while ($row = $resP->fetchArray(SQLITE3_ASSOC)) {
    $precos[$row['tipo']] = (float)$row['preco'];
}

$stmtE = $db->prepare(
    "SELECT nome, data, hora, sala, capacidade FROM eventos WHERE id = :eid AND estado = 'publicado'"
);
$stmtE->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$ev = $stmtE->execute()->fetchArray(SQLITE3_ASSOC);
if (!$ev) {
    sair(false, ['erro' => 'evento_nao_encontrado'], $isAjax, '../eventos.php?erro=evento_nao_encontrado');
}

$stmtV = $db->prepare(
    "SELECT COALESCE(SUM(ic.quantidade),0) AS vendidos
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.evento_id = :eid AND c.estado = 'confirmado'"
);
$stmtV->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$vendidos     = (int)$stmtV->execute()->fetchArray(SQLITE3_ASSOC)['vendidos'];
$total_pedido = $qty_normal + $qty_jovem + $qty_senior;

if (($vendidos + $total_pedido) > (int)$ev['capacidade']) {
    sair(false, ['erro' => 'sem_lugares'], $isAjax, '../carrinho.php?evento_id=' . $evento_id . '&erro=sem_lugares');
}

$total = ($qty_normal * ($precos['normal'] ?? 0))
       + ($qty_jovem  * ($precos['jovem']  ?? 0))
       + ($qty_senior * ($precos['senior'] ?? 0));

$referencia = date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
$uid        = isLoggedIn() ? getUtilizador()['id'] : null;

$db->exec('BEGIN');

$insC = $db->prepare(
    "INSERT INTO compras (referencia, evento_id, utilizador_id, nome_cliente, email_cliente,
                          telefone_cliente, canal, metodo_pagamento, total)
     VALUES (:ref, :eid, :uid, :nome, :email, :tel, 'online', :metodo, :total)"
);
$insC->bindValue(':ref',    $referencia, SQLITE3_TEXT);
$insC->bindValue(':eid',    $evento_id,  SQLITE3_INTEGER);
$insC->bindValue(':uid',    $uid,        $uid ? SQLITE3_INTEGER : SQLITE3_NULL);
$insC->bindValue(':nome',   $nome_cli,   SQLITE3_TEXT);
$insC->bindValue(':email',  $email_cli,  SQLITE3_TEXT);
$insC->bindValue(':tel',    $tel_cli,    SQLITE3_TEXT);
$insC->bindValue(':metodo', $metodo_pag, SQLITE3_TEXT);
$insC->bindValue(':total',  $total,      SQLITE3_FLOAT);
$insC->execute();

$compra_id = $db->lastInsertRowID();

$insI = $db->prepare(
    'INSERT INTO itens_compra (compra_id, tipo, quantidade, preco_unitario)
     VALUES (:cid, :tipo, :qty, :preco)'
);
$itensJSON = [];
foreach (['normal' => $qty_normal, 'jovem' => $qty_jovem, 'senior' => $qty_senior] as $tipo => $qty) {
    if ($qty > 0) {
        $insI->bindValue(':cid',   $compra_id,          SQLITE3_INTEGER);
        $insI->bindValue(':tipo',  $tipo,               SQLITE3_TEXT);
        $insI->bindValue(':qty',   $qty,                SQLITE3_INTEGER);
        $insI->bindValue(':preco', $precos[$tipo] ?? 0, SQLITE3_FLOAT);
        $insI->execute();
        $insI->reset();
        $itensJSON[] = ['tipo' => $tipo, 'quantidade' => $qty, 'preco_unitario' => $precos[$tipo] ?? 0];
    }
}

$db->exec('COMMIT');

sair(true, [
    'referencia'  => $referencia,
    'redirect'    => 'confirmacao.php?ref=' . urlencode($referencia),
    'evento'      => $ev['nome'],
    'data_evento' => date('d M Y', strtotime($ev['data'])),
    'hora_evento' => substr($ev['hora'], 0, 5),
    'sala'        => $ev['sala'],
    'nome_cliente' => $nome_cli,
    'email_cliente'=> $email_cli,
    'total'        => $total,
    'metodo'      => $metodo_pag,
    'itens'       => $itensJSON,
], $isAjax, '../confirmacao.php?ref=' . urlencode($referencia));
