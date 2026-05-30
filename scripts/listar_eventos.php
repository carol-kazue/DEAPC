<?php
require_once 'db.php';
header('Content-Type: application/json');

$db = getDB();

$stmt = $db->prepare(
    'SELECT e.id, e.nome, e.data, e.hora, e.sala, e.categoria,
            MIN(p.preco) AS preco_minimo
     FROM eventos e
     LEFT JOIN precos p ON p.evento_id = e.id
     WHERE e.estado = \'publicado\'
     GROUP BY e.id
     ORDER BY e.data ASC'
);

$eventos = [];
$res = $stmt->execute();
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

echo json_encode($eventos);