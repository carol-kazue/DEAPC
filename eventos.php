<?php
require_once 'scripts/db.php';

$db = getDB();

$pesquisa  = trim($_GET['pesquisa']  ?? '');
$data_de   = trim($_GET['data_de']   ?? '');
$data_ate  = trim($_GET['data_ate']  ?? '');
$categoria = trim($_GET['categoria'] ?? '');

$sql = 'SELECT e.id, e.nome, e.data, e.hora, e.sala, e.categoria,
               MIN(p.preco) AS preco_min
        FROM eventos e
        LEFT JOIN precos p ON p.evento_id = e.id
        WHERE e.estado = \'publicado\'';

$params = [];

if ($pesquisa !== '') {
    $sql .= ' AND e.nome LIKE :pesquisa';
    $params[':pesquisa'] = '%' . $pesquisa . '%';
}
if ($data_de !== '') {
    $sql .= ' AND e.data >= :data_de';
    $params[':data_de'] = $data_de;
}
if ($data_ate !== '') {
    $sql .= ' AND e.data <= :data_ate';
    $params[':data_ate'] = $data_ate;
}
if ($categoria !== '') {
    $sql .= ' AND e.categoria = :categoria';
    $params[':categoria'] = $categoria;
}

$sql .= ' GROUP BY e.id ORDER BY e.data ASC';

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, SQLITE3_TEXT);
}
$res = $stmt->execute();
$eventos = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

$categorias = ['Sinfónico', 'Jazz', 'Câmara', 'Contemporâneo', 'World Music', 'Fado', 'Outro'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Eventos — Casa da Música</title>
  <link rel="stylesheet" href="styles/eventos.css" />
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
    <h1 class="page-title">Eventos</h1>

    <form method="GET" action="eventos.php" class="search-bar">
      <div class="form-group">
        <label for="pesquisa">Pesquisar</label>
        <input type="text" id="pesquisa" name="pesquisa"
               placeholder="Nome do evento..."
               value="<?= htmlspecialchars($pesquisa) ?>" />
      </div>
      <div class="form-group">
        <label for="data-de">Data de</label>
        <input type="date" id="data-de" name="data_de"
               value="<?= htmlspecialchars($data_de) ?>" />
      </div>
      <div class="form-group">
        <label for="data-ate">Data até</label>
        <input type="date" id="data-ate" name="data_ate"
               value="<?= htmlspecialchars($data_ate) ?>" />
      </div>
      <div class="form-group">
        <label for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
          <option value="">Todas</option>
          <?php foreach ($categorias as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>"
            <?= $categoria === $cat ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn" style="margin-bottom:0">Pesquisar</button>
    </form>

    <?php if (empty($eventos)): ?>
      <p class="text-sm" style="margin-top:2rem;">Nenhum evento encontrado.</p>
    <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($eventos as $ev): ?>
      <div class="card">
        <div class="img-box img-box-md"></div>
        <p class="card-title"><?= htmlspecialchars($ev['nome']) ?></p>
        <p class="card-meta">
          <?= htmlspecialchars(date('d M Y', strtotime($ev['data']))) ?>
          · <?= htmlspecialchars(substr($ev['hora'], 0, 5)) ?>
          · <?= htmlspecialchars($ev['sala']) ?>
        </p>
        <p class="card-meta"><?= htmlspecialchars($ev['categoria']) ?></p>
        <div class="card-footer">
          <span class="card-price">
            <?= $ev['preco_min'] !== null
                ? 'A partir de €' . number_format((float)$ev['preco_min'], 2, ',', '.')
                : 'Consulte preços' ?>
          </span>
          <a href="evento.php?id=<?= (int)$ev['id'] ?>" class="btn btn-sm">Ver Mais</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>

</body>
</html>
