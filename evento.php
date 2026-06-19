<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: eventos.php');
    exit;
}

$db   = getDB();
// ATUALIZADO: Incluído o campo 'imagem' no SELECT da tabela eventos
$stmt = $db->prepare(
    'SELECT id, nome, descricao, data, hora, sala, categoria, classificacao_etaria, capacidade, imagem
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
      <a href="index.php" class="nav-brand">Casa da Música</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <?php if (isLoggedIn()): $u = getUtilizador(); ?>
        <a href="<?= $u['perfil'] === 'administrador' ? 'admin.php' : ($u['perfil'] === 'vendedor' ? 'vendedor.php' : 'cliente.php') ?>" class="btn-pill">
          <?= htmlspecialchars($u['nome']) ?>
        </a>
        <a href="scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
      <?php else: ?>
        <a href="login.html" class="btn-pill">Entrar</a>
      <?php endif; ?>
    </div>
  </nav>

  <main class="page">

    <p class="text-sm mb-2"><a href="eventos.php">← Eventos</a></p>

    <div class="img-box img-box-lg mb-2">
      <?php if (!empty($ev['imagem'])): ?>
        <img src="<?= htmlspecialchars($ev['imagem']) ?>" alt="<?= htmlspecialchars($ev['nome']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 6px;" />
      <?php else: ?>
        <div style="width: 100%; height: 100%; background: #2a2a3a; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #666;">Sem imagem de capa</div>
      <?php endif; ?>
    </div>

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
            <?php
            $destino = 'carrinho.php?evento_id=' . (int)$ev['id'];
            $href    = isLoggedIn() ? $destino : ('login.html?next=' . urlencode($destino));
            ?>
            <a href="<?= $href ?>" class="btn btn-full mt-3">Selecionar Bilhetes</a>
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