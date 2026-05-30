<?php
require_once 'db.php';
require_once 'sessao.php';
requirePerfil('administrador', '../login.html');
header('Content-Type: application/json');

$db = getDB();

$res = $db->query(
    'SELECT e.id, e.nome, e.data, e.sala, e.capacidade, e.estado,
            COALESCE(SUM(ic.quantidade), 0) AS vendidos
     FROM eventos e
     LEFT JOIN compras c ON c.evento_id = e.id AND c.estado = \'confirmado\'
     LEFT JOIN itens_compra ic ON ic.compra_id = c.id
     GROUP BY e.id
     ORDER BY e.data ASC'
);

$eventos = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

echo json_encode($eventos);