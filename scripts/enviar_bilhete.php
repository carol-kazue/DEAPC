<?php
require_once 'db.php';
require_once 'mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$referencia    = trim($_POST['referencia']    ?? '');
$emailOverride = trim($_POST['email']         ?? '');

if ($referencia === '') {
    echo json_encode(['ok' => false, 'erro' => 'Referência em falta.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT c.referencia, c.nome_cliente, c.email_cliente,
            c.metodo_pagamento, c.total, c.canal,
            e.nome AS evento, e.data, e.hora, e.sala
     FROM compras c
     JOIN eventos e ON e.id = c.evento_id
     WHERE c.referencia = :ref AND c.estado = 'confirmado'"
);
$stmt->bindValue(':ref', $referencia, SQLITE3_TEXT);
$compra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$compra) {
    echo json_encode(['ok' => false, 'erro' => 'Compra não encontrada ou cancelada.']);
    exit;
}

$stmtI = $db->prepare(
    'SELECT ic.tipo, ic.quantidade, ic.preco_unitario
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.referencia = :ref'
);
$stmtI->bindValue(':ref', $referencia, SQLITE3_TEXT);
$resI  = $stmtI->execute();
$itens = [];
while ($row = $resI->fetchArray(SQLITE3_ASSOC)) {
    $itens[] = $row;
}

$emailDestino = ($emailOverride !== '') ? $emailOverride : $compra['email_cliente'];

if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'erro' => 'Endereço de email inválido.']);
    exit;
}

$assunto = 'Comprovativo — ' . $compra['evento'] . ' · Casa da Música';
$html    = templateEmailBilhete($compra, $itens);
$result  = smtpEnviar($emailDestino, $compra['nome_cliente'], $assunto, $html);

echo json_encode($result);
