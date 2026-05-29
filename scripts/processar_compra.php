<?php
require_once 'db.php';
require_once 'sessao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../eventos.html');
    exit;
}

$evento_id   = (int)($_POST['evento_id']        ?? 0);
$qty_normal  = max(0, (int)($_POST['qty_normal']  ?? 0));
$qty_jovem   = max(0, (int)($_POST['qty_jovem']   ?? 0));
$qty_senior  = max(0, (int)($_POST['qty_senior']  ?? 0));
$nome_cli    = trim($_POST['nome_cliente']         ?? '');
$email_cli   = trim($_POST['email_cliente']        ?? '');
$tel_cli     = trim($_POST['telefone_cliente']     ?? '');
$metodo_pag  = trim($_POST['metodo_pagamento']     ?? 'cartao');

if ($evento_id === 0 || ($qty_normal + $qty_jovem + $qty_senior) === 0) {
    header('Location: ../eventos.html?erro=dados_invalidos');
    exit;
}
if ($nome_cli === '' || $email_cli === '') {
    header('Location: ../eventos.html?erro=dados_cliente');
    exit;
}

$db = getDB();

// Carrega preços do evento
$stmt = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :eid');
$stmt->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$res  = $stmt->execute();
$precos = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $precos[$row['tipo']] = (float)$row['preco'];
}

// Verifica disponibilidade
$stmtCap = $db->prepare('SELECT capacidade FROM eventos WHERE id = :eid AND estado = \'publicado\'');
$stmtCap->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$resCap  = $stmtCap->execute()->fetchArray(SQLITE3_ASSOC);
if (!$resCap) {
    header('Location: ../eventos.html?erro=evento_nao_encontrado');
    exit;
}
$capacidade = (int)$resCap['capacidade'];

$stmtVend = $db->prepare(
    'SELECT COALESCE(SUM(ic.quantidade),0) as vendidos
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.evento_id = :eid AND c.estado = \'confirmado\''
);
$stmtVend->bindValue(':eid', $evento_id, SQLITE3_INTEGER);
$vendidos = (int)$stmtVend->execute()->fetchArray(SQLITE3_ASSOC)['vendidos'];

$total_pedido = $qty_normal + $qty_jovem + $qty_senior;
if (($vendidos + $total_pedido) > $capacidade) {
    header('Location: ../carrinho.html?evento_id=' . $evento_id . '&erro=sem_lugares');
    exit;
}

// Calcula total
$total = ($qty_normal * ($precos['normal'] ?? 0))
       + ($qty_jovem  * ($precos['jovem']  ?? 0))
       + ($qty_senior * ($precos['senior'] ?? 0));

// Gera referência única
$referencia = date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

$uid = isLoggedIn() ? getUtilizador()['id'] : null;

$db->exec('BEGIN');

$insCompra = $db->prepare(
    'INSERT INTO compras (referencia, evento_id, utilizador_id, nome_cliente, email_cliente,
                          telefone_cliente, canal, metodo_pagamento, total)
     VALUES (:ref, :eid, :uid, :nome, :email, :tel, \'online\', :metodo, :total)'
);
$insCompra->bindValue(':ref',    $referencia,  SQLITE3_TEXT);
$insCompra->bindValue(':eid',    $evento_id,   SQLITE3_INTEGER);
$insCompra->bindValue(':uid',    $uid,         $uid ? SQLITE3_INTEGER : SQLITE3_NULL);
$insCompra->bindValue(':nome',   $nome_cli,    SQLITE3_TEXT);
$insCompra->bindValue(':email',  $email_cli,   SQLITE3_TEXT);
$insCompra->bindValue(':tel',    $tel_cli,     SQLITE3_TEXT);
$insCompra->bindValue(':metodo', $metodo_pag,  SQLITE3_TEXT);
$insCompra->bindValue(':total',  $total,       SQLITE3_FLOAT);
$insCompra->execute();

$compra_id = $db->lastInsertRowID();

$insItem = $db->prepare(
    'INSERT INTO itens_compra (compra_id, tipo, quantidade, preco_unitario)
     VALUES (:cid, :tipo, :qty, :preco)'
);
foreach (['normal' => $qty_normal, 'jovem' => $qty_jovem, 'senior' => $qty_senior] as $tipo => $qty) {
    if ($qty > 0) {
        $insItem->bindValue(':cid',   $compra_id,         SQLITE3_INTEGER);
        $insItem->bindValue(':tipo',  $tipo,              SQLITE3_TEXT);
        $insItem->bindValue(':qty',   $qty,               SQLITE3_INTEGER);
        $insItem->bindValue(':preco', $precos[$tipo] ?? 0, SQLITE3_FLOAT);
        $insItem->execute();
        $insItem->reset();
    }
}

$db->exec('COMMIT');

header('Location: ../confirmacao.php?ref=' . urlencode($referencia));
exit;
