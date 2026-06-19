<?php
require_once '../scripts/db.php';
require_once '../scripts/sessao.php';

requirePerfil('administrador', '../login.html');

$db = getDB();

$totalEventos = (int)$db->querySingle(
    'SELECT COUNT(*) FROM eventos WHERE estado = \'publicado\''
);
$totalBilhetes = (int)$db->querySingle(
    'SELECT COALESCE(SUM(ic.quantidade),0)
     FROM itens_compra ic
     JOIN compras c ON c.id = ic.compra_id
     WHERE c.estado = \'confirmado\''
);
$receita = (float)$db->querySingle(
    'SELECT COALESCE(SUM(total),0) FROM compras WHERE estado = \'confirmado\''
);

$stmtE = $db->prepare(
    'SELECT e.id, e.nome, e.data, e.sala, e.capacidade, e.estado,
            COALESCE(SUM(ic.quantidade),0) AS vendidos
     FROM eventos e
     LEFT JOIN compras c ON c.evento_id = e.id AND c.estado = \'confirmado\'
     LEFT JOIN itens_compra ic ON ic.compra_id = c.id
     WHERE e.data >= date(\'now\')
     GROUP BY e.id
     ORDER BY e.data ASC
     LIMIT 10'
);
$resE   = $stmtE->execute();
$eventos = [];
while ($row = $resE->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

$admin = getUtilizador();
$estadoBadge = ['publicado' => 'badge-green', 'rascunho' => 'badge-gray', 'cancelado' => 'badge-red'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Casa da Música</title>
  <link rel="stylesheet" href="../styles/admin.css" />
</head>
<body>

  <nav class="nav-admin">
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música — Admin</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
      <a href="../bilhetes.php" class="btn-pill">Bilhetes</a>
    </div>
    <div class="nav-right">
      <span class="text-sm" style="margin-right:1rem;">Olá, <?= htmlspecialchars($admin['nome']) ?></span>
      <a href="../scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page">
    <h1 class="page-title">Dashboard</h1>

    <div class="stats-row">
      <div class="stat">
        <p class="stat-val"><?= $totalEventos ?></p>
        <p class="stat-lbl">Eventos publicados</p>
      </div>
      <div class="stat">
        <p class="stat-val"><?= number_format($totalBilhetes) ?></p>
        <p class="stat-lbl">Bilhetes vendidos</p>
      </div>
      <div class="stat">
        <p class="stat-val">€<?= number_format($receita, 0, ',', '.') ?></p>
        <p class="stat-lbl">Receita total</p>
      </div>
    </div>

    <div style="display:flex; gap:1rem; margin-bottom:2rem; flex-wrap:wrap;">
      <a href="evento-criar.php" class="btn">+ Novo Evento</a>
      <a href="eventos.php" class="btn btn-pill-light">Ver Todos os Eventos</a>
    </div>

    <div class="sec-header">
      <h2 style="font-size:1.05rem; font-weight:bold;">Próximos Eventos</h2>
      <a href="eventos.php" class="btn btn-sm">Ver todos</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Data</th>
            <th>Local</th>
            <th>Vendidos</th>
            <th>Lotação</th>
            <th>Estado</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eventos as $ev): ?>
          <tr>
            <td>#<?= (int)$ev['id'] ?></td>
            <td><?= htmlspecialchars($ev['nome']) ?></td>
            <td><?= htmlspecialchars(date('d M Y', strtotime($ev['data']))) ?></td>
            <td><?= htmlspecialchars($ev['sala']) ?></td>
            <td><?= (int)$ev['vendidos'] ?></td>
            <td><?= (int)$ev['capacidade'] ?></td>
            <td><span class="badge <?= $estadoBadge[$ev['estado']] ?? 'badge-gray' ?>"><?= ucfirst(htmlspecialchars($ev['estado'])) ?></span></td>
            <td class="col-actions">
              <a href="evento-editar.php?id=<?= (int)$ev['id'] ?>" class="btn btn-sm">Editar</a>
              <?php if ($ev['estado'] !== 'cancelado'): ?>
              <form method="POST" action="../scripts/cancelar_evento.php" style="display:inline;">
                <input type="hidden" name="evento_id" value="<?= (int)$ev['id'] ?>" />
                <button type="button" class="btn btn-sm btn-danger"
                  onclick="pedirConfirmacao(this.closest('form'), '<?= htmlspecialchars(addslashes($ev['nome'])) ?>')">Cancelar</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>


<!-- Modal de confirmação de cancelamento -->
<div id="modal-cancelar" style="display:none; position:fixed; inset:0; z-index:500;
     background:rgba(0,0,0,.75); backdrop-filter:blur(3px);
     align-items:center; justify-content:center;">
  <div style="background:#12121f; border:1px solid #c9a83c; border-radius:6px;
              padding:2rem; max-width:420px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,.7);">
    <p style="font-family:'Cinzel',Georgia,serif; font-size:1.1rem; color:#c9a83c; margin-bottom:.5rem;">Cancelar Evento</p>
    <p style="color:#c8c0b4; font-size:.92rem; margin-bottom:1.5rem;">
      Tens a certeza que queres cancelar <strong id="modal-nome" style="color:#f0ece4;"></strong>?
      Esta ação não pode ser desfeita.
    </p>
    <div style="display:flex; gap:.8rem; justify-content:flex-end;">
      <button onclick="fecharModal()" type="button"
        style="background:transparent; border:1px solid #3a3a55; color:#9e9080; border-radius:999px;
               padding:.4rem 1.2rem; cursor:pointer; font-size:.9rem; transition:background .15s;"
        onmouseover="this.style.background='#3a3a55'" onmouseout="this.style.background='transparent'">
        Voltar
      </button>
      <button id="modal-confirmar" type="button"
        style="background:#c0392b; border:none; color:#fff; border-radius:999px;
               padding:.4rem 1.3rem; cursor:pointer; font-size:.9rem; font-weight:600; transition:background .15s;"
        onmouseover="this.style.background='#96281b'" onmouseout="this.style.background='#c0392b'">
        Confirmar Cancelamento
      </button>
    </div>
  </div>
</div>

<script>
  let _formPendente = null;

  function pedirConfirmacao(form, nome) {
    _formPendente = form;
    document.getElementById('modal-nome').textContent = '«' + nome + '»';
    const modal = document.getElementById('modal-cancelar');
    modal.style.display = 'flex';
  }

  function fecharModal() {
    _formPendente = null;
    document.getElementById('modal-cancelar').style.display = 'none';
  }

  document.getElementById('modal-confirmar').addEventListener('click', function () {
    if (_formPendente) _formPendente.submit();
  });

  document.getElementById('modal-cancelar').addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
  });
</script>
</body>
</html>
