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
      <a href="index.php" class="nav-brand">Casa da Música</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
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
      <a href="eventos.php" class="btn">Mais Eventos</a>
    </div>

    <div style="margin-top:1.5rem; background:#12121f; border:1px solid #252535;
                border-radius:4px; padding:1rem 1.2rem; max-width:420px; margin-left:auto; margin-right:auto;">
      <p style="font-size:.78rem; color:#9e9080; text-transform:uppercase;
                letter-spacing:.05em; margin:0 0 .6rem;">Enviar comprovativo por email</p>
      <div style="display:flex; gap:.5rem; align-items:center;">
        <input type="email" id="email-conf" placeholder="email@exemplo.com"
          value="<?= htmlspecialchars($compra['email_cliente']) ?>"
          style="flex:1; border:1px solid #2a2a40; padding:.45rem .8rem; background:#0f0f20;
                 color:#f0ece4; border-radius:3px; font-size:.88rem; outline:none; font-family:inherit;"
          onfocus="this.style.borderColor='#c9a83c'" onblur="this.style.borderColor='#2a2a40'" />
        <button type="button" onclick="enviarEmailConf()"
          style="background:transparent; border:1px solid #3a5a8a; color:#7ab0e0;
                 border-radius:999px; padding:.42rem 1rem; cursor:pointer;
                 font-family:inherit; font-size:.85rem; white-space:nowrap;
                 transition:background .15s;"
          onmouseover="this.style.background='#1a2535'" onmouseout="this.style.background='transparent'">
          ✉ Enviar
        </button>
      </div>
      <div id="email-conf-status" style="display:none; font-size:.82rem; margin-top:.4rem;"></div>
    </div>

    <script>
    function enviarEmailConf() {
      const email  = document.getElementById('email-conf').value.trim();
      const status = document.getElementById('email-conf-status');
      if (!email) return;
      status.style.display = 'block';
      status.style.color   = '#9e9080';
      status.textContent   = 'A enviar…';
      fetch('scripts/enviar_bilhete.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams({
          referencia: '<?= htmlspecialchars(addslashes($compra['referencia'])) ?>',
          email: email
        })
      })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.ok) { status.style.color = '#52b37a'; status.textContent = '✓ Comprovativo enviado para ' + email; }
        else       { status.style.color = '#e05252'; status.textContent = d.erro || 'Erro ao enviar.'; }
      })
      .catch(function(){ status.style.color='#e05252'; status.textContent='Erro de ligação.'; });
    }
    </script>

    <?php endif; ?>
  </main>

</body>
</html>
