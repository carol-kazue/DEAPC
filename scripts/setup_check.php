<?php
require_once 'db.php';

$db = getDB();

// Lista eventos
$res = $db->query('SELECT id, nome, data, hora, sala, capacidade, estado FROM eventos');
echo '<h2>Eventos na BD:</h2><pre>';
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    print_r($row);
}
echo '</pre>';

// Lista preços
$res2 = $db->query('SELECT * FROM precos');
echo '<h2>Preços na BD:</h2><pre>';
while ($row = $res2->fetchArray(SQLITE3_ASSOC)) {
    print_r($row);
}
echo '</pre>';