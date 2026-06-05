<?php
require_once 'scripts/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: eventos.php');
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, nome, descricao, data, hora, sala, categoria, classificacao_etaria, capacidade
     FROM eventos WHERE id = :id AND estado = \'publicado\''
);
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$ev = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$ev) {
    header('Location: eventos.php');
    exit;
}

$stmtP = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :id ORDER BY tipo');
$stmtP->bindValue(':id', $id, SQLITE3_INTEGER);
$resP   = $stmtP->execute();
$precos = [];
while ($p = $resP->fetchArray(SQLITE3_ASSOC)) {
    $precos[$p['tipo']] = (float)$p['preco'];
}

$stmtV = $db->prepare(
    'SELECT COALESCE(SUM(ic.quantidade),0) AS vendidos
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.evento_id = :id AND c.estado = \'confirmado\''
);
$stmtV->bindValue(':id', $id, SQLITE3_INTEGER);
$vendidos    = (int)$stmtV->execute()->fetchArray(SQLITE3_ASSOC)['vendidos'];
$disponiveis = (int)$ev['capacidade'] - $vendidos;

$labels = ['normal' => 'Normal', 'jovem' => 'Jovem', 'senior' => 'Sénior'];
$preco_min = $precos ? min($precos) : null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($ev['nome']) ?> — Casa da Música</title>
  <link rel="stylesheet" href="styles/evento.css" />
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.html" class="nav-brand">Casa da Música</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <a href="cliente.php" class="nav-icon" aria-label="Conta">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0"/>
        </svg>
      </a>
      <a href="login.html" class="btn-pill">Entrar</a>
    </div>
  </nav>

  <main class="page">

    <p class="text-sm mb-2"><a href="eventos.php">← Eventos</a></p>

    <div class="img-box img-box-lg mb-2"></div>

    <div class="detail-grid">

      <div>
        <h1 class="page-title"><?= htmlspecialchars($ev['nome']) ?></h1>
        <p class="detail-meta">📅 <?= htmlspecialchars(date('d \d\e F \d\e Y', strtotime($ev['data']))) ?> · <?= htmlspecialchars(substr($ev['hora'], 0, 5)) ?></p>
        <p class="detail-meta">📍 <?= htmlspecialchars($ev['sala']) ?>, Casa da Música, Porto</p>
        <p class="detail-meta">🏷 <?= htmlspecialchars($ev['categoria']) ?></p>
        <?php if ($ev['classificacao_etaria']): ?>
        <p class="detail-meta">🔞 <?= htmlspecialchars($ev['classificacao_etaria']) ?></p>
        <?php endif; ?>
        <hr />
        <?php if ($ev['descricao']): ?>
        <p class="detail-desc"><?= nl2br(htmlspecialchars($ev['descricao'])) ?></p>
        <?php endif; ?>
        <p class="detail-desc">Capacidade total: <?= (int)$ev['capacidade'] ?> lugares.</p>
      </div>

      <div>
        <div class="ticket-box">
          <p class="ticket-box-title">Selecionar Bilhetes</p>

          <?php foreach ($labels as $tipo => $label): ?>
          <?php if (isset($precos[$tipo])): ?>
          <div class="ticket-row">
            <div>
              <p class="text-bold"><?= $label ?></p>
              <p class="text-sm"><?= $tipo === 'jovem' ? 'Até 30 anos' : ($tipo === 'senior' ? 'Mais de 65 anos' : 'Tarifa geral') ?></p>
            </div>
            <span class="ticket-price">€<?= number_format($precos[$tipo], 2, ',', '.') ?></span>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>

          <?php if ($disponiveis > 0): ?>
          <a href="carrinho.php?evento_id=<?= (int)$ev['id'] ?>" class="btn btn-full mt-3">Selecionar Bilhetes</a>
          <?php else: ?>
          <button class="btn btn-full mt-3" disabled>Esgotado</button>
          <?php endif; ?>
        </div>

        <div class="alert alert-info mt-2">
          <p class="text-sm">
            <strong>Lugares disponíveis:</strong>
            <?= $disponiveis ?> / <?= (int)$ev['capacidade'] ?>
          </p>
        </div>
      </div>

    </div>
  </main>

</body>
</html>
