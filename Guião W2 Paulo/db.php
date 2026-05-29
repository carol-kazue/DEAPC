<?php
function getDB(): SQLite3 {
    $caminho = __DIR__ . '/../data/casaMusica.db';
    $db = new SQLite3($caminho);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}
