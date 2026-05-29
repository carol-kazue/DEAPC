<?php
require_once 'scripts/db.php';

$referencia = trim($_GET['ref'] ?? '');
$compra     = null;
$itens      = [];

if ($referencia !== '') {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT c.referencia, c.total, c.estado, c.data_compra, c.metodo_pagamento,
                c.nome_cliente, c.email_cliente,
                e.nome AS evento, e.data, e.hora, e.sala
         FROM compras c
         JOIN eventos e ON e.id = c.evento_id
         WHERE c.referencia = :ref'
    );
    $stmt->bindValue(':ref', $referencia, SQLITE3_TEXT);
    $compra = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($compra) {
        $stmtI = $db->prepare(
            'SELECT tipo, quantidade, preco_unitario FROM itens_compra WHERE compra_id =
             (SELECT id FROM compras WHERE referencia = :ref)'
        );
        $stmtI->bindValue(':ref', $referencia, SQLITE3_TEXT);
        $resI = $stmtI->execute();
        while ($row = $resI->fetchArray(SQLITE3_ASSOC)) {
            $itens[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Confirmação — Casa da Música</title>
  <link rel="stylesheet" href="styles/confirmacao.css" />
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.html" class="nav-brand">Casa da Música</a>
      <a href="eventos.html" class="btn-pill">Eventos</a>
    </div>
  </nav>

  <main class="page" style="max-width:600px; text-align:center;">

    <?php if (!$compra): ?>
      <p>Compra não encontrada.</p>
    <?php else: ?>

    <div class="steps mt-2 mb-2">
      <div class="step-dot active"></div>
      <div class="step-dot active"></div>
      <div class="step-dot active"></div>
    </div>
    <p class="text-sm mb-2">Passo 3 de 3 — Confirmação</p>

    <div style="font-size:3rem; margin:1rem 0;">✓</div>
    <h1 class="page-title">Compra Confirmada!</h1>

    <div class="order-box" style="text-align:left; margin-top:1.5rem;">
      <p class="text-bold mb-1">Detalhes da compra</p>
      <p class="text-sm"><strong>Referência:</strong> #<?= htmlspecialchars($compra['referencia']) ?></p>
      <p class="text-sm"><strong>Evento:</strong> <?= htmlspecialchars($compra['evento']) ?></p>
      <p class="text-sm">
        <strong>Data:</strong>
        <?= htmlspecialchars(date('d M Y', strtotime($compra['data']))) ?> às
        <?= htmlspecialchars(substr($compra['hora'], 0, 5)) ?>
      </p>
      <p class="text-sm"><strong>Sala:</strong> <?= htmlspecialchars($compra['sala']) ?></p>
      <hr style="margin:0.8rem 0;" />
      <?php foreach ($itens as $item): ?>
      <div class="order-row">
        <span><?= (int)$item['quantidade'] ?>× <?= ucfirst(htmlspecialchars($item['tipo'])) ?></span>
        <span>€<?= number_format((float)$item['quantidade'] * (float)$item['preco_unitario'], 2, ',', '.') ?></span>
      </div>
      <?php endforeach; ?>
      <div class="order-row order-total">
        <span>Total</span>
        <span>€<?= number_format((float)$compra['total'], 2, ',', '.') ?></span>
      </div>
    </div>

    <div style="margin-top:1.5rem; display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
      <a href="cliente.php" class="btn btn-pill-light">Ver Histórico</a>
      <a href="eventos.html" class="btn">Mais Eventos</a>
    </div>

    <?php endif; ?>
  </main>

</body>
</html>
