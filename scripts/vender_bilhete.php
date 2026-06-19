<?php
require_once 'db.php';
require_once 'sessao.php';

requirePerfil('vendedor', '../login.html');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
          && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function sairV(bool $ok, array $payload, bool $isAjax, string $redir = '') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($ok ? array_merge(['ok' => true], $payload) : ['ok' => false, 'erro' => $payload['erro']]);
        exit;
    }
    header('Location: ' . $redir);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vendedor.php');
    exit;
}

$evento_id  = (int)($_POST['evento_id']       ?? 0);
$qty_normal = max(0, (int)($_POST['qty_normal']  ?? 0));
$qty_jovem  = max(0, (int)($_POST['qty_jovem']   ?? 0));
$qty_senior = max(0, (int)($_POST['qty_senior']  ?? 0));
$nome_cli   = trim($_POST['nome_cliente']         ?? '');
$email_cli  = trim($_POST['email_cliente']        ?? '');
$tel_cli    = trim($_POST['telefone_cliente']     ?? '');
$nif_cli    = trim($_POST['nif_cliente']          ?? '');
$metodo_pag = trim($_POST['metodo_pagamento']     ?? 'dinheiro');

if ($evento_id === 0 || ($qty_normal + $qty_jovem + $qty_senior) === 0 || $nome_cli === '' || $email_cli === '') {
    sairV(false, ['erro' => 'dados_invalidos'], $isAjax, '../vendedor.php?erro=dados_invalidos');
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
    sairV(false, ['erro' => 'evento_nao_encontrado'], $isAjax, '../vendedor.php?erro=evento_nao_encontrado');
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
    sairV(false, ['erro' => 'sem_lugares'], $isAjax, '../vendedor.php?erro=sem_lugares&evento_id=' . $evento_id);
}

$total      = ($qty_normal * ($precos['normal'] ?? 0))
            + ($qty_jovem  * ($precos['jovem']  ?? 0))
            + ($qty_senior * ($precos['senior'] ?? 0));
$referencia = date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
$vendedor   = getUtilizador();

$db->exec('BEGIN');

$insC = $db->prepare(
    "INSERT INTO compras (referencia, evento_id, utilizador_id, nome_cliente, email_cliente,
                          telefone_cliente, nif_cliente, canal, vendedor_id, metodo_pagamento, total)
     VALUES (:ref, :eid, NULL, :nome, :email, :tel, :nif, 'presencial', :vid, :metodo, :total)"
);
$insC->bindValue(':ref',    $referencia,     SQLITE3_TEXT);
$insC->bindValue(':eid',    $evento_id,      SQLITE3_INTEGER);
$insC->bindValue(':nome',   $nome_cli,       SQLITE3_TEXT);
$insC->bindValue(':email',  $email_cli,      SQLITE3_TEXT);
$insC->bindValue(':tel',    $tel_cli,        SQLITE3_TEXT);
$insC->bindValue(':nif',    $nif_cli,        SQLITE3_TEXT);
$insC->bindValue(':vid',    $vendedor['id'], SQLITE3_INTEGER);
$insC->bindValue(':metodo', $metodo_pag,     SQLITE3_TEXT);
$insC->bindValue(':total',  $total,          SQLITE3_FLOAT);
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

sairV(true, [
    'referencia'  => $referencia,
    'evento'      => $ev['nome'],
    'data_evento' => date('d M Y', strtotime($ev['data'])),
    'hora_evento' => substr($ev['hora'], 0, 5),
    'sala'        => $ev['sala'],
    'nome_cliente' => $nome_cli,
    'email_cliente'=> $email_cli,
    'total'        => $total,
    'metodo'      => $metodo_pag,
    'itens'       => $itensJSON,
], $isAjax, '../vendedor.php?sucesso=venda_registada&ref=' . urlencode($referencia));
