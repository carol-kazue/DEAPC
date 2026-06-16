<?php
require_once 'db.php';
require_once 'sessao.php';

requirePerfil('administrador', '../login.html');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/eventos.php');
    exit;
}

$evento_id           = (int)($_POST['evento_id']           ?? 0);
$nome                = trim($_POST['nome']                 ?? '');
$data                = trim($_POST['data']                 ?? '');
$hora                = trim($_POST['hora']                 ?? '');
$sala                = trim($_POST['sala']                 ?? '');
$categoria           = trim($_POST['categoria']            ?? '');
$classificacao_etaria = trim($_POST['classificacao_etaria'] ?? 'Livre');
$descricao           = trim($_POST['descricao']            ?? '');
$capacidade          = (int)($_POST['capacidade']          ?? 0);
$estado              = trim($_POST['estado']               ?? 'rascunho');
$preco_normal        = (float)($_POST['preco_normal']      ?? 0);
$preco_jovem         = (float)($_POST['preco_jovem']       ?? 0);
$preco_senior        = (float)($_POST['preco_senior']      ?? 0);

if ($evento_id === 0 || $nome === '' || $data === '' || $hora === '' || $sala === '' || $categoria === '') {
    header('Location: ../admin/evento-editar.php?id=' . $evento_id . '&erro=campos_obrigatorios');
    exit;
}
if ($capacidade <= 0) {
    header('Location: ../admin/evento-editar.php?id=' . $evento_id . '&erro=capacidade_invalida');
    exit;
}
if (!in_array($estado, ['publicado', 'rascunho', 'cancelado'])) {
    $estado = 'rascunho';
}

// ==========================================
// ADICIONADO: LOGICA DE UPLOAD DA IMAGEM
// ==========================================
$caminho_imagem_db = null; 

if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
    // Como este script roda dentro da pasta "scripts/", a pasta uploads fica um nível acima
    $pasta_destino = '../uploads/'; 
    
    if (!is_dir($pasta_destino)) {
        mkdir($pasta_destino, 0755, true);
    }

    $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
    
    if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
        $novo_nome_arquivo = 'evento_' . $evento_id . '_' . time() . '.' . $extensao;
        $caminho_completo = $pasta_destino . $novo_nome_arquivo;

        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo)) {
            // Caminho relativo salvo no banco (utilizado para carregar a imagem na interface)
            $caminho_imagem_db = 'uploads/' . $novo_nome_arquivo;
        }
    } else {
        header('Location: ../admin/evento-editar.php?id=' . $evento_id . '&erro=imagem_invalida');
        exit;
    }
}
// ==========================================

$db = getDB();
$db->exec('BEGIN');

// ADICIONADO: Se houver uma imagem nova, inclui o campo no UPDATE. Caso contrário, mantém a query original.
if ($caminho_imagem_db !== null) {
    $upd = $db->prepare(
        'UPDATE eventos SET nome = :nome, descricao = :desc, data = :data, hora = :hora,
         sala = :sala, categoria = :cat, classificacao_etaria = :clas,
         capacidade = :cap, estado = :estado, imagem = :imagem
         WHERE id = :id'
    );
    $upd->bindValue(':imagem', $caminho_imagem_db, SQLITE3_TEXT);
} else {
    $upd = $db->prepare(
        'UPDATE eventos SET nome = :nome, descricao = :desc, data = :data, hora = :hora,
         sala = :sala, categoria = :cat, classificacao_etaria = :clas,
         capacidade = :cap, estado = :estado
         WHERE id = :id'
    );
}

$upd->bindValue(':nome',   $nome,                 SQLITE3_TEXT);
$upd->bindValue(':desc',   $descricao ?: null,     $descricao ? SQLITE3_TEXT : SQLITE3_NULL);
$upd->bindValue(':data',   $data,                 SQLITE3_TEXT);
$upd->bindValue(':hora',   $hora,                 SQLITE3_TEXT);
$upd->bindValue(':sala',   $sala,                 SQLITE3_TEXT);
$upd->bindValue(':cat',    $categoria,            SQLITE3_TEXT);
$upd->bindValue(':clas',   $classificacao_etaria ?: 'Livre', SQLITE3_TEXT);
$upd->bindValue(':cap',    $capacidade,           SQLITE3_INTEGER);
$upd->bindValue(':estado', $estado,               SQLITE3_TEXT);
$upd->bindValue(':id',     $evento_id,            SQLITE3_INTEGER);
$upd->execute();

// UPSERT preços
$upsert = $db->prepare(
    'INSERT INTO precos (evento_id, tipo, preco)
     VALUES (:eid, :tipo, :preco)
     ON CONFLICT(evento_id, tipo) DO UPDATE SET preco = excluded.preco'
);
foreach (['normal' => $preco_normal, 'jovem' => $preco_jovem, 'senior' => $preco_senior] as $tipo => $preco) {
    $upsert->bindValue(':eid',   $evento_id, SQLITE3_INTEGER);
    $upsert->bindValue(':tipo',  $tipo,      SQLITE3_TEXT);
    $upsert->bindValue(':preco', $preco,     SQLITE3_FLOAT);
    $upsert->execute();
    $upsert->reset();
}

$db->exec('COMMIT');

header('Location: ../admin/eventos.php?sucesso=editado');
exit;