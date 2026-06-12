<?php
require_once '../scripts/db.php';
require_once '../scripts/sessao.php';

requirePerfil('administrador', '../login.html');

$db = getDB();

$filtro_estado = trim($_GET['estado'] ?? '');

$sql = 'SELECT e.id, e.nome, e.data, e.hora, e.sala, e.categoria, e.capacidade, e.estado,
               COALESCE(SUM(ic.quantidade),0) AS vendidos
        FROM eventos e
        LEFT JOIN compras c ON c.evento_id = e.id AND c.estado = \'confirmado\'
        LEFT JOIN itens_compra ic ON ic.compra_id = c.id';
if ($filtro_estado !== '') {
    $sql .= ' WHERE e.estado = :estado';
}
$sql .= ' GROUP BY e.id ORDER BY e.data ASC';

$stmt = $db->prepare($sql);
if ($filtro_estado !== '') {
    $stmt->bindValue(':estado', $filtro_estado, SQLITE3_TEXT);
}
$res = $stmt->execute();
$eventos = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

$sucesso = htmlspecialchars($_GET['sucesso'] ?? '');
$msgs = [
    'criado'   => 'Evento criado com sucesso.',
    'editado'  => 'Evento atualizado com sucesso.',
    'cancelado'=> 'Evento cancelado.',
];
$estadoBadge = ['publicado' => 'badge-green', 'rascunho' => 'badge-gray', 'cancelado' => 'badge-red'];
$admin = getUtilizador();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Eventos — Admin — Casa da Música</title>
  <link rel="stylesheet" href="../styles/admin.css" />
</head>
<body>

  <nav class="nav-admin">
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música — Admin</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <span class="text-sm" style="margin-right:1rem;">Olá, <?= htmlspecialchars($admin['nome']) ?></span>
      <a href="../scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page">
    <div class="sec-header">
      <h1 class="page-title">Eventos</h1>
      <a href="evento-criar.php" class="btn">+ Novo Evento</a>
    </div>

    <?php if ($sucesso && isset($msgs[$sucesso])): ?>
    <div class="alert alert-success mb-2"><?= $msgs[$sucesso] ?></div>
    <?php endif; ?>

    <form method="GET" action="eventos.php" style="margin-bottom:1rem; display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap;">
      <div class="form-group" style="margin-bottom:0;">
        <label for="estado">Filtrar por estado</label>
        <select id="estado" name="estado" onchange="this.form.submit()">
          <option value="">Todos</option>
          <option value="publicado"  <?= $filtro_estado === 'publicado'  ? 'selected' : '' ?>>Publicado</option>
          <option value="rascunho"   <?= $filtro_estado === 'rascunho'   ? 'selected' : '' ?>>Rascunho</option>
          <option value="cancelado"  <?= $filtro_estado === 'cancelado'  ? 'selected' : '' ?>>Cancelado</option>
        </select>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Data</th>
            <th>Sala</th>
            <th>Categoria</th>
            <th>Vendidos / Lotação</th>
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
            <td><?= htmlspecialchars($ev['categoria']) ?></td>
            <td><?= (int)$ev['vendidos'] ?> / <?= (int)$ev['capacidade'] ?></td>
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
