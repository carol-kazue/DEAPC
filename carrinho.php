<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

$evento_id = (int)($_GET['evento_id'] ?? 0);
if ($evento_id === 0) {
    header('Location: eventos.php');
    exit;
}

if (!isLoggedIn()) {
    $next = 'carrinho.php?evento_id=' . $evento_id;
    header('Location: login.html?next=' . urlencode($next));
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, nome, data, hora, sala, capacidade
     FROM eventos WHERE id = :id AND estado = \'publicado\''
);
$stmt->bindValue(':id', $evento_id, SQLITE3_INTEGER);
$ev = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$ev) {
    header('Location: eventos.php');
    exit;
}

$stmtP = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :id');
$stmtP->bindValue(':id', $evento_id, SQLITE3_INTEGER);
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
$stmtV->bindValue(':id', $evento_id, SQLITE3_INTEGER);
$vendidos    = (int)$stmtV->execute()->fetchArray(SQLITE3_ASSOC)['vendidos'];
$disponiveis = (int)$ev['capacidade'] - $vendidos;

$erro = $_GET['erro'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Selecionar Bilhetes — Casa da Música</title>
  <link rel="stylesheet" href="styles/carrinho.css" />
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.html" class="nav-brand">Casa da Música</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <?php if (isLoggedIn()): $u = getUtilizador(); ?>
        <a href="cliente.php" class="btn-pill"><?= htmlspecialchars($u['nome']) ?></a>
        <a href="scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
      <?php else: ?>
        <a href="login.html" class="btn-pill">Entrar</a>
      <?php endif; ?>
    </div>
  </nav>

  <main class="page" style="max-width:700px;">

    <div class="steps mt-2 mb-2">
      <div class="step-dot active"></div>
      <div class="step-dot"></div>
      <div class="step-dot"></div>
    </div>
    <p class="text-center text-sm mb-2">Passo 1 de 3 — Selecionar Bilhetes</p>

    <?php if ($erro === 'sem_lugares'): ?>
    <div class="alert alert-danger mb-2">Não há lugares suficientes disponíveis.</div>
    <?php endif; ?>

    <div class="order-box">
      <p class="text-bold"><?= htmlspecialchars($ev['nome']) ?></p>
      <p class="text-sm">
        <?= htmlspecialchars(date('d M Y', strtotime($ev['data']))) ?>
        · <?= htmlspecialchars(substr($ev['hora'], 0, 5)) ?>
        · <?= htmlspecialchars($ev['sala']) ?>
      </p>
      <p class="text-sm">Lugares disponíveis: <strong><?= $disponiveis ?></strong></p>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Descrição</th>
            <th>Preço unit.</th>
            <th>Quantidade</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $tipoInfo = [
              'normal' => 'Tarifa geral',
              'jovem'  => 'Até 30 anos',
              'senior' => 'Mais de 65 anos',
          ];
          $tipoLabel = ['normal' => 'Normal', 'jovem' => 'Jovem', 'senior' => 'Sénior'];
          foreach ($tipoInfo as $tipo => $desc):
              if (!isset($precos[$tipo])) continue;
          ?>
          <tr>
            <td class="text-bold"><?= $tipoLabel[$tipo] ?></td>
            <td class="text-sm"><?= $desc ?></td>
            <td>€<?= number_format($precos[$tipo], 2, ',', '.') ?></td>
            <td>
              <div class="qty-ctrl">
                <button type="button" class="qty-btn" onclick="change('<?= $tipo ?>',-1)">−</button>
                <span class="qty-val" id="qty-<?= $tipo ?>">0</span>
                <button type="button" class="qty-btn" onclick="change('<?= $tipo ?>',1)">+</button>
              </div>
            </td>
            <td id="sub-<?= $tipo ?>">€0,00</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="order-box mt-2">
      <div class="order-row order-total">
        <span>Total</span>
        <span id="total">€0,00</span>
      </div>
    </div>

    <div style="display:flex; justify-content:space-between; margin-top:1.5rem;">
      <a href="evento.php?id=<?= (int)$evento_id ?>" class="btn btn-pill-light">← Voltar</a>
      <button type="button" class="btn" id="btn-continuar" onclick="continuar()">Continuar →</button>
    </div>
  </main>

  <script>
    const EVENTO_ID = <?= (int)$evento_id ?>;
    const prices = {
      <?php foreach ($precos as $tipo => $preco): ?>
      '<?= $tipo ?>': <?= $preco ?>,
      <?php endforeach; ?>
    };
    const qty = {};
    Object.keys(prices).forEach(t => qty[t] = 0);

    function fmt(v) { return '€' + v.toFixed(2).replace('.', ','); }

    function change(tipo, delta) {
      qty[tipo] = Math.max(0, (qty[tipo] || 0) + delta);
      document.getElementById('qty-' + tipo).textContent = qty[tipo];
      const sub = qty[tipo] * prices[tipo];
      document.getElementById('sub-' + tipo).textContent = fmt(sub);
      const total = Object.keys(qty).reduce((s, k) => s + (qty[k] || 0) * (prices[k] || 0), 0);
      document.getElementById('total').textContent = fmt(total);
    }

    function continuar() {
      const total = Object.keys(qty).reduce((s, k) => s + (qty[k] || 0) * (prices[k] || 0), 0);
      if (total === 0) { alert('Selecione pelo menos um bilhete.'); return; }
      sessionStorage.setItem('carrinho', JSON.stringify({
        evento_id:  EVENTO_ID,
        qty_normal: qty['normal'] || 0,
        qty_jovem:  qty['jovem']  || 0,
        qty_senior: qty['senior'] || 0,
        total:      total
      }));
      window.location.href = 'pagamento.php';
    }
  </script>

</body>
</html>
