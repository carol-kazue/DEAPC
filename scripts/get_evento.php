<?php
require_once 'db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    echo json_encode(['erro' => 'ID inválido']);
    exit;
}

$db = getDB();

// Dados do evento
$stmt = $db->prepare(
    'SELECT e.id, e.nome, e.data, e.hora, e.sala, e.categoria, e.capacidade, e.descricao,
            COALESCE(SUM(ic.quantidade), 0) AS vendidos
     FROM eventos e
     LEFT JOIN compras c ON c.evento_id = e.id AND c.estado = \'confirmado\'
     LEFT JOIN itens_compra ic ON ic.compra_id = c.id
     WHERE e.id = :id AND e.estado = \'publicado\'
     GROUP BY e.id'
);
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$evento = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$evento) {
    echo json_encode(['erro' => 'Evento não encontrado']);
    exit;
}

// Preços
$stmtP = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :id');
$stmtP->bindValue(':id', $id, SQLITE3_INTEGER);
$resP = $stmtP->execute();
$precos = [];
while ($row = $resP->fetchArray(SQLITE3_ASSOC)) {
    $precos[$row['tipo']] = (float)$row['preco'];
}

$evento['precos'] = $precos;
$evento['disponiveis'] = (int)$evento['capacidade'] - (int)$evento['vendidos'];

echo json_encode($evento);