<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

$db = getDB();

$pesquisa  = trim($_GET['pesquisa']  ?? '');
$data_de   = trim($_GET['data_de']   ?? '');
$data_ate  = trim($_GET['data_ate']  ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$por_pag   = 6;

$where  = "WHERE e.estado = 'publicado'";
$params = [];

if ($pesquisa !== '') {
    $where .= ' AND e.nome LIKE :pesquisa';
    $params[':pesquisa'] = '%' . $pesquisa . '%';
}
if ($data_de !== '') {
    $where .= ' AND e.data >= :data_de';
    $params[':data_de'] = $data_de;
}
if ($data_ate !== '') {
    $where .= ' AND e.data <= :data_ate';
    $params[':data_ate'] = $data_ate;
}
if ($categoria !== '') {
    $where .= ' AND e.categoria = :categoria';
    $params[':categoria'] = $categoria;
}

// Total de eventos (para calcular páginas)
$stmtTotal = $db->prepare("SELECT COUNT(DISTINCT e.id) FROM eventos e $where");
foreach ($params as $k => $v) $stmtTotal->bindValue($k, $v, SQLITE3_TEXT);
$total       = (int)$stmtTotal->execute()->fetchArray()[0];
$total_pag   = max(1, (int)ceil($total / $por_pag));
$pagina      = min($pagina, $total_pag);
$offset      = ($pagina - 1) * $por_pag;

// Eventos da página atual
$sql = "SELECT e.id, e.nome, e.data, e.hora, e.sala, e.categoria,
               MIN(p.preco) AS preco_min
        FROM eventos e
        LEFT JOIN precos p ON p.evento_id = e.id
        $where
        GROUP BY e.id
        ORDER BY e.data ASC
        LIMIT $por_pag OFFSET $offset";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, SQLITE3_TEXT);
$res = $stmt->execute();
$eventos = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

$categorias = ['Sinfónico', 'Jazz', 'Câmara', 'Contemporâneo', 'World Music', 'Fado', 'Outro'];

function urlPag(int $p, string $pesquisa, string $data_de, string $data_ate, string $categoria): string {
    $q = ['pagina' => $p];
    if ($pesquisa)  $q['pesquisa']  = $pesquisa;
    if ($data_de)   $q['data_de']   = $data_de;
    if ($data_ate)  $q['data_ate']  = $data_ate;
    if ($categoria) $q['categoria'] = $categoria;
    return 'eventos.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Eventos — Casa da Música</title>
  <link rel="stylesheet" href="styles/eventos.css" />
  <style>
    /* Dropdown nav */
    .nav-dropdown { position:relative; }
    .nav-dropdown-toggle {
      background:none; border:1px solid currentColor; border-radius:20px;
      padding:5px 14px; cursor:pointer; font:inherit; color:inherit;
      display:flex; align-items:center; gap:6px;
    }
    .nav-dropdown-toggle:hover { background:rgba(0,0,0,.06); }
    .nav-dropdown-menu {
      display:none; position:absolute; right:0; top:calc(100% + 6px);
      background:#fff; border:1px solid #ddd; border-radius:8px;
      box-shadow:0 4px 14px rgba(0,0,0,.12); min-width:160px;
      overflow:hidden; z-index:200;
    }
    .nav-dropdown-menu a {
      display:block; padding:10px 16px; font-size:.9rem;
      color:#222; text-decoration:none; white-space:nowrap;
    }
    .nav-dropdown-menu a:hover { background:#f5f5f5; }
    .nav-dropdown-menu .menu-sep {
      border:none; border-top:1px solid #eee; margin:2px 0;
    }
    .nav-dropdown.open .nav-dropdown-menu { display:block; }

    /* Paginação */
    .paginacao {
      display:flex; justify-content:center; align-items:center;
      gap:6px; margin:2rem 0 1rem;
    }
    .pg-btn {
      min-width:36px; height:36px; padding:0 10px;
      border:1px solid #ddd; border-radius:6px; background:#fff;
      cursor:pointer; font-size:.9rem; color:#333;
      display:flex; align-items:center; justify-content:center;
    }
    .pg-btn:hover:not(:disabled) { background:#f0f0f0; }
    .pg-btn.ativo { background:#1a1a1a; color:#fff; border-color:#1a1a1a; font-weight:bold; }
    .pg-btn:disabled { opacity:.4; cursor:default; }
    .pg-btn a { color:inherit; text-decoration:none; display:block; padding:0 4px; }
    .pg-info { font-size:.85rem; color:#666; margin-left:8px; }
  </style>
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.html" class="nav-brand">Casa da Música</a>
      <a href="eventos.html" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <?php if (isLoggedIn()): $u = getUtilizador(); ?>
        <div class="nav-dropdown" id="nav-dd">
          <button class="nav-dropdown-toggle" onclick="toggleDrop()" type="button">
            <?= htmlspecialchars($u['nome']) ?> <span style="font-size:.7rem;">▾</span>
          </button>
          <div class="nav-dropdown-menu">
            <?php if ($u['perfil'] === 'administrador'): ?>
              <a href="admin.php">Dashboard</a>
              <a href="admin-eventos.php">Gerir Eventos</a>
              <hr class="menu-sep">
            <?php elseif ($u['perfil'] === 'vendedor'): ?>
              <a href="vendedor.php">Área do Vendedor</a>
              <hr class="menu-sep">
            <?php else: ?>
              <a href="cliente.php">A Minha Conta</a>
              <hr class="menu-sep">
            <?php endif; ?>
            <a href="scripts/logout.php">Sair</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.html" class="btn-pill">Entrar</a>
      <?php endif; ?>
    </div>
  </nav>

  <main class="page">
    <h1 class="page-title">Eventos</h1>

    <form method="GET" action="eventos.html" class="search-bar">
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
    <div class="cards-grid" id="grelha-eventos">
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

    <!-- Paginação -->
    <?php if ($total_pag > 1): ?>
    <div class="paginacao">
      <!-- Anterior -->
      <?php if ($pagina > 1): ?>
        <button class="pg-btn"><a href="<?= htmlspecialchars(urlPag($pagina - 1, $pesquisa, $data_de, $data_ate, $categoria)) ?>">‹</a></button>
      <?php else: ?>
        <button class="pg-btn" disabled>‹</button>
      <?php endif; ?>

      <!-- Números de página -->
      <?php
      $inicio = max(1, $pagina - 2);
      $fim    = min($total_pag, $pagina + 2);
      if ($inicio > 1): ?>
        <button class="pg-btn"><a href="<?= htmlspecialchars(urlPag(1, $pesquisa, $data_de, $data_ate, $categoria)) ?>">1</a></button>
        <?php if ($inicio > 2): ?><span style="padding:0 4px;color:#aaa;">…</span><?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $inicio; $i <= $fim; $i++): ?>
        <?php if ($i === $pagina): ?>
          <button class="pg-btn ativo"><?= $i ?></button>
        <?php else: ?>
          <button class="pg-btn"><a href="<?= htmlspecialchars(urlPag($i, $pesquisa, $data_de, $data_ate, $categoria)) ?>"><?= $i ?></a></button>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($fim < $total_pag): ?>
        <?php if ($fim < $total_pag - 1): ?><span style="padding:0 4px;color:#aaa;">…</span><?php endif; ?>
        <button class="pg-btn"><a href="<?= htmlspecialchars(urlPag($total_pag, $pesquisa, $data_de, $data_ate, $categoria)) ?>"><?= $total_pag ?></a></button>
      <?php endif; ?>

      <!-- Próxima -->
      <?php if ($pagina < $total_pag): ?>
        <button class="pg-btn"><a href="<?= htmlspecialchars(urlPag($pagina + 1, $pesquisa, $data_de, $data_ate, $categoria)) ?>">›</a></button>
      <?php else: ?>
        <button class="pg-btn" disabled>›</button>
      <?php endif; ?>

      <span class="pg-info"><?= $pagina ?> / <?= $total_pag ?> &nbsp;(<?= $total ?> eventos)</span>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </main>

  <!-- Painel de informação do projeto -->
  <button id="btn-sobre" onclick="toggleSobre()" title="Sobre o projeto"
    style="position:fixed; bottom:1.5rem; right:1.5rem; z-index:100;
           background:#1a1a1a; color:#fff; border:none; border-radius:50%;
           width:46px; height:46px; font-size:1.2rem; cursor:pointer;
           box-shadow:0 2px 8px rgba(0,0,0,.35);">ⓘ</button>

  <div id="painel-sobre"
    style="display:none; position:fixed; bottom:4.5rem; right:1.5rem; z-index:100;
           background:#fff; border:1px solid #ddd; border-radius:10px;
           box-shadow:0 4px 16px rgba(0,0,0,.15); padding:1.2rem 1.5rem;
           max-width:320px; font-size:0.88rem; line-height:1.6;">
    <p style="font-weight:bold; font-size:1rem; margin-bottom:0.5rem;">Casa da Música — Sistema de Bilhética</p>
    <p style="color:#555; margin-bottom:0.8rem;">
      Aplicação web para gestão e venda de bilhetes da Casa da Música do Porto.
      Permite a compra online de bilhetes, gestão de eventos pelo administrador
      e emissão presencial pelo vendedor.
    </p>
    <hr style="margin:0.6rem 0;" />
    <p style="font-weight:bold; margin-bottom:0.3rem;">Grupo 18 — DEAPC 2025/26</p>
    <ul style="margin:0; padding-left:1.2rem; color:#333;">
      <li>Ana Inada — 1242098</li>
      <li>Pedro Silva — 1242116</li>
      <li>Paulo Costa — 1231470</li>
    </ul>
    <p style="margin-top:0.6rem; color:#888; font-size:0.78rem;">
      <a href="https://github.com/carol-kazue/DEAPC" target="_blank" style="color:#555;">github.com/carol-kazue/DEAPC</a>
    </p>
  </div>

  <script>
    // Dropdown do nav
    function toggleDrop() {
      document.getElementById('nav-dd').classList.toggle('open');
    }
    document.addEventListener('click', function (e) {
      const dd = document.getElementById('nav-dd');
      if (dd && !dd.contains(e.target)) dd.classList.remove('open');
    });

    // Toggle painel sobre o projeto
    function toggleSobre() {
      const painel = document.getElementById('painel-sobre');
      const aberto = painel.style.display !== 'none';
      painel.style.display = aberto ? 'none' : 'block';
      document.getElementById('btn-sobre').textContent = aberto ? 'ⓘ' : '✕';
    }
    document.addEventListener('click', function (e) {
      const painel = document.getElementById('painel-sobre');
      const btn    = document.getElementById('btn-sobre');
      if (painel && painel.style.display !== 'none'
          && !painel.contains(e.target) && e.target !== btn) {
        painel.style.display = 'none';
        btn.textContent = 'ⓘ';
      }
    });
  </script>
</body>
</html>
