<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

if (!isLoggedIn()) {
    header('Location: login.html');
    exit;
}
$u = getUtilizador();
if (!in_array($u['perfil'], ['administrador', 'vendedor'])) {
    header('Location: index.php');
    exit;
}
$isAdmin = ($u['perfil'] === 'administrador');
$db      = getDB();

// ── Filtros ────────────────────────────────────────────────────────────────
$pesquisa = trim($_GET['pesquisa']  ?? '');
$fEvento  = (int)($_GET['evento_id'] ?? 0);
$fEstado  = trim($_GET['estado']     ?? '');
$fCanal   = trim($_GET['canal']      ?? '');
$fDataDe  = trim($_GET['data_de']    ?? '');
$fDataAte = trim($_GET['data_ate']   ?? '');
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$porPag   = 25;

$sucesso = htmlspecialchars($_GET['sucesso'] ?? '');
$erro    = htmlspecialchars($_GET['erro']    ?? '');

// Eventos para o dropdown de filtro
$todosEventos = [];
$resEvts = $db->query("SELECT id, nome, data FROM eventos ORDER BY data DESC");
while ($ev = $resEvts->fetchArray(SQLITE3_ASSOC)) {
    $todosEventos[] = $ev;
}

// ── Query ──────────────────────────────────────────────────────────────────
$where  = 'WHERE 1=1';
$params = [];

if (!$isAdmin) {
    $where .= ' AND c.vendedor_id = :uid';
    $params[':uid'] = (int)$u['id'];
}
if ($pesquisa !== '') {
    $where .= ' AND (c.referencia LIKE :pesq OR c.nome_cliente LIKE :pesq OR c.email_cliente LIKE :pesq)';
    $params[':pesq'] = '%' . $pesquisa . '%';
}
if ($fEvento > 0) {
    $where .= ' AND c.evento_id = :eid';
    $params[':eid'] = $fEvento;
}
if ($fEstado !== '') {
    $where .= ' AND c.estado = :estado';
    $params[':estado'] = $fEstado;
}
if ($fCanal !== '') {
    $where .= ' AND c.canal = :canal';
    $params[':canal'] = $fCanal;
}
if ($fDataDe !== '') {
    $where .= ' AND date(c.data_compra) >= :data_de';
    $params[':data_de'] = $fDataDe;
}
if ($fDataAte !== '') {
    $where .= ' AND date(c.data_compra) <= :data_ate';
    $params[':data_ate'] = $fDataAte;
}

function bindAll(SQLite3Stmt $stmt, array $params): void {
    foreach ($params as $k => $v) {
        $type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
        $stmt->bindValue($k, $v, $type);
    }
}

$stmtTotal = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM compras c $where");
bindAll($stmtTotal, $params);
$total  = (int)$stmtTotal->execute()->fetchArray()[0];
$totPag = max(1, (int)ceil($total / $porPag));
$pagina = min($pagina, $totPag);
$offset = ($pagina - 1) * $porPag;

$sqlC = "SELECT c.id, c.referencia, c.evento_id, e.nome AS evento_nome,
                c.nome_cliente, c.email_cliente, c.telefone_cliente, c.nif_cliente,
                c.canal, c.metodo_pagamento, c.total, c.estado,
                c.data_compra,
                COALESCE(SUM(ic.quantidade), 0) AS total_bilhetes,
                v.nome AS vendedor_nome
         FROM compras c
         JOIN eventos e   ON e.id = c.evento_id
         LEFT JOIN itens_compra ic ON ic.compra_id = c.id
         LEFT JOIN utilizadores v  ON v.id = c.vendedor_id
         $where
         GROUP BY c.id
         ORDER BY c.data_compra DESC
         LIMIT $porPag OFFSET $offset";

$stmtC = $db->prepare($sqlC);
bindAll($stmtC, $params);
$compras = [];
$resC = $stmtC->execute();
while ($row = $resC->fetchArray(SQLITE3_ASSOC)) {
    $compras[] = $row;
}

// Itens de cada compra desta página
$itensPorCompra = [];
if (!empty($compras)) {
    $ids   = implode(',', array_map('intval', array_column($compras, 'id')));
    $resIt = $db->query("SELECT compra_id, tipo, quantidade, preco_unitario FROM itens_compra WHERE compra_id IN ($ids) ORDER BY tipo");
    while ($it = $resIt->fetchArray(SQLITE3_ASSOC)) {
        $itensPorCompra[(int)$it['compra_id']][] = $it;
    }
}

// Sumário (só para a view do admin — total confirmado / receita)
$totalConf    = 0;
$receitaConf  = 0.0;
if ($isAdmin) {
    $stmtSum = $db->prepare("SELECT COUNT(DISTINCT c.id), SUM(c.total) FROM compras c $where AND c.estado='confirmado'");
    bindAll($stmtSum, $params);
    $rowSum = $stmtSum->execute()->fetchArray();
    $totalConf   = (int)($rowSum[0] ?? 0);
    $receitaConf = (float)($rowSum[1] ?? 0);
}

function urlPag(int $p): string {
    $q = $_GET;
    $q['pagina'] = $p;
    return 'bilhetes.php?' . http_build_query($q);
}

$tiposLabel = ['normal' => 'Normal', 'jovem' => 'Jovem', 'senior' => 'Sénior'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bilhetes — Casa da Música</title>
  <link rel="stylesheet" href="styles/<?= $isAdmin ? 'admin' : 'vendedor' ?>.css" />
  <style>
    /* Classes partilhadas não presentes em ambos os CSS */
    .badge-red    { background:#2b1414; color:#e05252; display:inline-block; padding:.18rem .6rem; border-radius:999px; font-size:.78rem; }
    .badge-gold   { background:#2a2010; color:#c9a83c; display:inline-block; padding:.18rem .6rem; border-radius:999px; font-size:.78rem; }
    .nav-vendor   { background:#090b14 !important; border-bottom-color:#3a5a8a !important; }
    .nav-vendor .nav-brand { color:#7ab0e0 !important; }
    .filter-bar {
      background:#12121f; border:1px solid #252535; border-radius:4px;
      padding:1rem 1.2rem; margin-bottom:1.5rem;
      display:flex; flex-wrap:wrap; gap:.8rem; align-items:flex-end;
    }
    .filter-bar .form-group { margin-bottom:0; flex:1; min-width:130px; }
    .filter-bar select,
    .filter-bar input { width:100%; }
    .text-right { text-align:right; }
    .col-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
    .mb-2 { margin-bottom:1rem; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    tr.detail-row td { padding:0; background:#0d0d1a; }
    tr.detail-row .inner { padding:.8rem 1rem; border-bottom:1px solid #1e1e30; }
    .alert         { padding:.9rem 1.2rem; background:#12121f; border:1px solid #252535; margin-bottom:1.5rem; border-radius:3px; }
    .alert-success { background:#0c1f16; border-left:3px solid #3a8f5c; color:#c8c0b4; }
    .alert-danger  { background:#1f0c0c; border-left:3px solid #c0392b; color:#c8c0b4; }
    .btn-ghost {
      background:transparent; border:1px solid #3a3a55; color:#9e9080;
      border-radius:999px; padding:.25rem .9rem; cursor:pointer; font-size:.8rem;
      font-family:inherit; transition:background .15s, color .15s;
    }
    .btn-ghost:hover { background:#3a3a55; color:#f0ece4; }
    .btn-danger-sm {
      background:transparent; border:1px solid #5a2020; color:#e05252;
      border-radius:999px; padding:.25rem .9rem; cursor:pointer; font-size:.8rem;
      font-family:inherit; transition:background .15s;
    }
    .btn-danger-sm:hover { background:#2b1414; }
    .btn-success-sm {
      background:transparent; border:1px solid #1a5c3a; color:#52b37a;
      border-radius:999px; padding:.25rem .9rem; cursor:pointer; font-size:.8rem;
      font-family:inherit; transition:background .15s;
    }
    .btn-success-sm:hover { background:#0c1f16; }
    .summary-row { display:flex; gap:1.5rem; margin-bottom:1.5rem; }
    .summary-card {
      background:#12121f; border:1px solid #252535; border-radius:4px;
      padding:1rem 1.5rem; flex:1;
    }
    .summary-val { font-family:'Cinzel',Georgia,serif; font-size:1.6rem; font-weight:bold; color:#c9a83c; }
    .summary-lbl { font-size:.82rem; color:#9e9080; margin-top:.2rem; }
  </style>
</head>
<body>

  <nav class="<?= $isAdmin ? 'nav-admin' : 'nav-vendor' ?>">
    <div class="nav-left">
      <a href="<?= $isAdmin ? 'admin/index.php' : 'vendedor.php' ?>" class="nav-brand">
        Casa da Música<?= $isAdmin ? ' — Admin' : '' ?>
      </a>
      <?php if ($isAdmin): ?>
        <a href="admin/eventos.php" class="btn-pill">Eventos</a>
      <?php else: ?>
        <a href="vendedor.php" class="btn-pill">Área Vendedor</a>
      <?php endif; ?>
      <a href="bilhetes.php" class="btn-pill" style="background:#c9a83c22;">Bilhetes</a>
    </div>
    <div class="nav-right">
      <span style="font-size:.88rem; color:#9e9080; margin-right:1rem;">
        Olá, <?= htmlspecialchars($u['nome']) ?>
      </span>
      <a href="scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page">
    <h1 class="page-title">Gestão de Bilhetes</h1>

    <?php if ($sucesso !== ''): ?>
    <div class="alert alert-success mb-2">
      <?= $sucesso === 'cancelado'  ? 'Bilhete cancelado com sucesso.'   : '' ?>
      <?= $sucesso === 'reativado'  ? 'Bilhete reativado com sucesso.'   : '' ?>
    </div>
    <?php endif; ?>
    <?php if ($erro !== ''): ?>
    <div class="alert alert-danger mb-2">
      <?= $erro === 'sem_permissao' ? 'Não tem permissão para esta operação.' : htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="summary-row">
      <div class="summary-card">
        <div class="summary-val"><?= $total ?></div>
        <div class="summary-lbl">Compras encontradas</div>
      </div>
      <div class="summary-card">
        <div class="summary-val"><?= $totalConf ?></div>
        <div class="summary-lbl">Confirmadas</div>
      </div>
      <div class="summary-card">
        <div class="summary-val">€<?= number_format($receitaConf, 2, ',', '.') ?></div>
        <div class="summary-lbl">Receita (confirmadas)</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="GET" action="bilhetes.php" class="filter-bar">
      <div class="form-group">
        <label>Pesquisar</label>
        <input type="text" name="pesquisa" placeholder="Referência ou cliente…"
               value="<?= htmlspecialchars($pesquisa) ?>" />
      </div>
      <div class="form-group">
        <label>Evento</label>
        <select name="evento_id">
          <option value="0">Todos os eventos</option>
          <?php foreach ($todosEventos as $ev): ?>
          <option value="<?= (int)$ev['id'] ?>" <?= $fEvento === (int)$ev['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ev['nome']) ?> (<?= htmlspecialchars(date('d/m/Y', strtotime($ev['data']))) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Estado</label>
        <select name="estado">
          <option value="">Todos</option>
          <option value="confirmado" <?= $fEstado === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
          <option value="cancelado"  <?= $fEstado === 'cancelado'  ? 'selected' : '' ?>>Cancelado</option>
        </select>
      </div>
      <?php if ($isAdmin): ?>
      <div class="form-group">
        <label>Canal</label>
        <select name="canal">
          <option value="">Todos</option>
          <option value="online"      <?= $fCanal === 'online'      ? 'selected' : '' ?>>Online</option>
          <option value="presencial"  <?= $fCanal === 'presencial'  ? 'selected' : '' ?>>Presencial</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label>Data de</label>
        <input type="date" name="data_de" value="<?= htmlspecialchars($fDataDe) ?>" />
      </div>
      <div class="form-group">
        <label>Data até</label>
        <input type="date" name="data_ate" value="<?= htmlspecialchars($fDataAte) ?>" />
      </div>
      <button type="submit" class="btn btn-sm" style="margin-bottom:0;">Filtrar</button>
      <a href="bilhetes.php" class="btn-ghost" style="margin-bottom:0;">Limpar</a>
    </form>

    <!-- Tabela -->
    <?php if (empty($compras)): ?>
      <p class="text-sm" style="margin-top:1rem;">Nenhuma compra encontrada.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Referência</th>
            <th>Evento</th>
            <th>Cliente</th>
            <?php if ($isAdmin): ?><th>Canal</th><?php endif; ?>
            <th>Bilhetes</th>
            <th>Total</th>
            <th>Estado</th>
            <th>Data</th>
            <?php if ($isAdmin): ?><th>Vendedor</th><?php endif; ?>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compras as $c): ?>
          <?php $cid = (int)$c['id']; ?>
          <tr>
            <td style="font-family:monospace; font-size:.82rem; white-space:nowrap; color:#c9a83c;">
              <?= htmlspecialchars($c['referencia']) ?>
            </td>
            <td><?= htmlspecialchars($c['evento_nome']) ?></td>
            <td>
              <span style="color:#f0ece4;"><?= htmlspecialchars($c['nome_cliente']) ?></span><br>
              <span style="color:#5a5550; font-size:.78rem;"><?= htmlspecialchars($c['email_cliente']) ?></span>
            </td>
            <?php if ($isAdmin): ?>
            <td>
              <span class="badge <?= $c['canal'] === 'presencial' ? 'badge-gold' : '' ?>">
                <?= $c['canal'] === 'presencial' ? 'Presencial' : 'Online' ?>
              </span>
            </td>
            <?php endif; ?>
            <td style="text-align:center;"><?= (int)$c['total_bilhetes'] ?></td>
            <td style="white-space:nowrap;">€<?= number_format((float)$c['total'], 2, ',', '.') ?></td>
            <td>
              <?php if ($c['estado'] === 'confirmado'): ?>
                <span class="badge badge-green">Confirmado</span>
              <?php else: ?>
                <span class="badge-red">Cancelado</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap; font-size:.82rem; color:#9e9080;">
              <?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['data_compra']))) ?>
            </td>
            <?php if ($isAdmin): ?>
            <td style="font-size:.82rem; color:#9e9080;">
              <?= $c['vendedor_nome'] !== null ? htmlspecialchars($c['vendedor_nome']) : '<em style="color:#3a3a55;">online</em>' ?>
            </td>
            <?php endif; ?>
            <td>
              <div class="col-actions">
                <button type="button" class="btn-ghost"
                  onclick="abrirDetalhes(<?= $cid ?>)">Ver</button>
                <?php if ($c['estado'] === 'confirmado'): ?>
                  <button type="button" class="btn-danger-sm"
                    onclick="pedirAcao(<?= $cid ?>, 'cancelado', <?= json_encode($c['referencia']) ?>)">
                    Cancelar
                  </button>
                <?php elseif ($isAdmin): ?>
                  <button type="button" class="btn-success-sm"
                    onclick="pedirAcao(<?= $cid ?>, 'confirmado', <?= json_encode($c['referencia']) ?>)">
                    Reativar
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <?php if ($totPag > 1): ?>
    <div style="display:flex; justify-content:center; align-items:center; gap:6px; margin:2rem 0 1rem;">
      <?php if ($pagina > 1): ?>
        <a href="<?= htmlspecialchars(urlPag($pagina - 1)) ?>" class="btn-ghost">‹</a>
      <?php endif; ?>
      <?php
      $ini = max(1, $pagina - 2);
      $fim = min($totPag, $pagina + 2);
      if ($ini > 1): ?><a href="<?= htmlspecialchars(urlPag(1)) ?>" class="btn-ghost">1</a><?php if ($ini > 2): ?><span style="color:#5a5550">…</span><?php endif; endif; ?>
      <?php for ($i = $ini; $i <= $fim; $i++): ?>
        <?php if ($i === $pagina): ?>
          <span style="background:#c9a83c; color:#0b0b14; border-radius:4px; padding:.2rem .7rem; font-size:.9rem; font-weight:bold;"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= htmlspecialchars(urlPag($i)) ?>" class="btn-ghost"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($fim < $totPag): ?><?php if ($fim < $totPag - 1): ?><span style="color:#5a5550">…</span><?php endif; ?><a href="<?= htmlspecialchars(urlPag($totPag)) ?>" class="btn-ghost"><?= $totPag ?></a><?php endif; ?>
      <?php if ($pagina < $totPag): ?>
        <a href="<?= htmlspecialchars(urlPag($pagina + 1)) ?>" class="btn-ghost">›</a>
      <?php endif; ?>
      <span style="font-size:.82rem; color:#5a5550; margin-left:.5rem;"><?= $pagina ?>/<?= $totPag ?> (<?= $total ?> compras)</span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </main>

  <!-- Modal de detalhes da compra -->
  <div id="modal-detalhes" style="display:none; position:fixed; inset:0; z-index:500;
       background:rgba(0,0,0,.78); backdrop-filter:blur(3px);
       align-items:center; justify-content:center; padding:2rem 1rem;">
    <div style="background:#12121f; border:1px solid #252535; border-radius:6px;
                padding:2rem; max-width:520px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,.7);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.2rem;">
        <div>
          <p style="font-family:'Cinzel',Georgia,serif; font-size:1rem; color:#c9a83c; margin-bottom:.2rem;">Detalhe da Compra</p>
          <p id="det-ref" style="font-family:monospace; font-size:.88rem; color:#9e9080;"></p>
        </div>
        <button type="button" onclick="fecharDetalhes()"
          style="background:none; border:none; color:#5a5550; font-size:1.4rem; cursor:pointer; line-height:1;"
          onmouseover="this.style.color='#f0ece4'" onmouseout="this.style.color='#5a5550'">✕</button>
      </div>
      <div id="det-corpo"></div>
    </div>
  </div>

  <!-- Modal de confirmação de ação -->
  <div id="modal-acao" style="display:none; position:fixed; inset:0; z-index:600;
       background:rgba(0,0,0,.82); backdrop-filter:blur(3px);
       align-items:center; justify-content:center; padding:2rem 1rem;">
    <div style="background:#12121f; border:1px solid #c9a83c; border-radius:6px;
                padding:2rem; max-width:400px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,.7);">
      <p id="acao-titulo" style="font-family:'Cinzel',Georgia,serif; font-size:1rem; color:#c9a83c; margin-bottom:.8rem;"></p>
      <p id="acao-texto" style="font-size:.9rem; color:#c8c0b4; margin-bottom:1.4rem;"></p>
      <form id="form-acao" action="scripts/atualizar_estado_bilhete.php" method="POST">
        <input type="hidden" name="compra_id" id="acao-id" value="" />
        <input type="hidden" name="novo_estado" id="acao-estado" value="" />
        <input type="hidden" name="redirect" value="bilhetes.php?<?= htmlspecialchars(http_build_query(array_diff_key($_GET, ['sucesso'=>'','erro'=>'','pagina'=>'']))) ?>&pagina=<?= $pagina ?>" />
        <div style="display:flex; gap:.8rem; justify-content:flex-end;">
          <button type="button" onclick="fecharAcao()"
            style="background:transparent; border:1px solid #3a3a55; color:#9e9080;
                   border-radius:999px; padding:.4rem 1.2rem; cursor:pointer; font-size:.9rem; font-family:inherit;">
            Voltar
          </button>
          <button type="submit" id="acao-confirmar"
            style="border:none; border-radius:999px; padding:.4rem 1.4rem;
                   cursor:pointer; font-size:.9rem; font-weight:600; font-family:inherit;">
            Confirmar
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  // ── Dados embutidos ──────────────────────────────────────────────────────
  const COMPRAS = <?php
    $dadosJS = [];
    foreach ($compras as $c) {
        $cid = (int)$c['id'];
        $dadosJS[$cid] = [
            'referencia'  => $c['referencia'],
            'evento'      => $c['evento_nome'],
            'cliente'     => $c['nome_cliente'],
            'email'       => $c['email_cliente'],
            'telefone'    => $c['telefone_cliente'] ?? '',
            'nif'         => $c['nif_cliente'] ?? '',
            'canal'       => $c['canal'],
            'metodo'      => $c['metodo_pagamento'],
            'total'       => $c['total'],
            'estado'      => $c['estado'],
            'data'        => date('d/m/Y H:i', strtotime($c['data_compra'])),
            'vendedor'    => $c['vendedor_nome'] ?? null,
            'itens'       => $itensPorCompra[$cid] ?? [],
        ];
    }
    echo json_encode($dadosJS);
  ?>;

  const TIPOS_LABEL = { normal: 'Normal', jovem: 'Jovem', senior: 'Sénior' };
  const METODOS     = { dinheiro: 'Dinheiro', multibanco: 'Multibanco', mbway: 'MB Way', cartao: 'Cartão' };

  // ── Modal de detalhes ────────────────────────────────────────────────────
  function abrirDetalhes(id) {
    const c = COMPRAS[id];
    if (!c) return;
    document.getElementById('det-ref').textContent = '#' + c.referencia;

    const estadoBadge = c.estado === 'confirmado'
      ? '<span style="background:#1a3327;color:#52b37a;padding:.15rem .5rem;border-radius:999px;font-size:.78rem;">Confirmado</span>'
      : '<span style="background:#2b1414;color:#e05252;padding:.15rem .5rem;border-radius:999px;font-size:.78rem;">Cancelado</span>';

    let itensHtml = '';
    if (c.itens && c.itens.length > 0) {
      itensHtml = '<table style="width:100%; border-collapse:collapse; margin-top:.5rem;">';
      itensHtml += '<thead><tr>'
        + '<th style="text-align:left;font-size:.72rem;color:#c9a83c;text-transform:uppercase;letter-spacing:.05em;padding:.3rem .5rem;border-bottom:1px solid #1e1e30;">Tipo</th>'
        + '<th style="text-align:center;font-size:.72rem;color:#c9a83c;text-transform:uppercase;letter-spacing:.05em;padding:.3rem .5rem;border-bottom:1px solid #1e1e30;">Qtd</th>'
        + '<th style="text-align:right;font-size:.72rem;color:#c9a83c;text-transform:uppercase;letter-spacing:.05em;padding:.3rem .5rem;border-bottom:1px solid #1e1e30;">Unit.</th>'
        + '<th style="text-align:right;font-size:.72rem;color:#c9a83c;text-transform:uppercase;letter-spacing:.05em;padding:.3rem .5rem;border-bottom:1px solid #1e1e30;">Subtotal</th>'
        + '</tr></thead><tbody>';
      c.itens.forEach(function(it) {
        const sub = (parseFloat(it.preco_unitario) * parseInt(it.quantidade)).toFixed(2).replace('.', ',');
        itensHtml += '<tr>'
          + '<td style="padding:.35rem .5rem;font-size:.88rem;color:#f0ece4;border-bottom:1px solid #1a1a28;">' + (TIPOS_LABEL[it.tipo] || it.tipo) + '</td>'
          + '<td style="padding:.35rem .5rem;font-size:.88rem;text-align:center;color:#f0ece4;border-bottom:1px solid #1a1a28;">' + it.quantidade + '</td>'
          + '<td style="padding:.35rem .5rem;font-size:.88rem;text-align:right;color:#9e9080;border-bottom:1px solid #1a1a28;">€' + parseFloat(it.preco_unitario).toFixed(2).replace('.', ',') + '</td>'
          + '<td style="padding:.35rem .5rem;font-size:.88rem;text-align:right;color:#f0ece4;border-bottom:1px solid #1a1a28;">€' + sub + '</td>'
          + '</tr>';
      });
      itensHtml += '</tbody></table>';
    } else {
      itensHtml = '<p style="color:#5a5550;font-size:.85rem;">Sem itens registados.</p>';
    }

    document.getElementById('det-corpo').innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem .8rem;margin-bottom:1rem;font-size:.85rem;">
        <div><span style="color:#5a5550;">Evento</span><br><span style="color:#f0ece4;">${esc(c.evento)}</span></div>
        <div><span style="color:#5a5550;">Data</span><br><span style="color:#f0ece4;">${esc(c.data)}</span></div>
        <div><span style="color:#5a5550;">Cliente</span><br><span style="color:#f0ece4;">${esc(c.cliente)}</span></div>
        <div><span style="color:#5a5550;">Email</span><br><span style="color:#9e9080;font-size:.8rem;">${esc(c.email)}</span></div>
        ${c.telefone ? `<div><span style="color:#5a5550;">Telefone</span><br><span style="color:#f0ece4;">${esc(c.telefone)}</span></div>` : ''}
        ${c.nif      ? `<div><span style="color:#5a5550;">NIF</span><br><span style="color:#f0ece4;">${esc(c.nif)}</span></div>` : ''}
        <div><span style="color:#5a5550;">Canal</span><br><span style="color:#f0ece4;">${c.canal === 'presencial' ? 'Presencial' : 'Online'}</span></div>
        <div><span style="color:#5a5550;">Pagamento</span><br><span style="color:#f0ece4;">${METODOS[c.metodo] || c.metodo}</span></div>
        <div><span style="color:#5a5550;">Estado</span><br>${estadoBadge}</div>
        ${c.vendedor ? `<div><span style="color:#5a5550;">Vendedor</span><br><span style="color:#f0ece4;">${esc(c.vendedor)}</span></div>` : ''}
      </div>
      <p style="font-size:.78rem;color:#c9a83c;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">Bilhetes</p>
      <div style="background:#0f0f20;border:1px solid #1e1e30;border-radius:3px;overflow:hidden;">${itensHtml}</div>
      <div style="display:flex;justify-content:flex-end;margin-top:.8rem;">
        <span style="font-size:.9rem;color:#9e9080;margin-right:.5rem;">Total</span>
        <strong style="color:#c9a83c;">€${parseFloat(c.total).toFixed(2).replace('.', ',')}</strong>
      </div>
    `;
    document.getElementById('modal-detalhes').style.display = 'flex';
  }
  function fecharDetalhes() { document.getElementById('modal-detalhes').style.display = 'none'; }

  // ── Modal de ação (cancelar / reativar) ──────────────────────────────────
  function pedirAcao(id, novoEstado, ref) {
    document.getElementById('acao-id').value     = id;
    document.getElementById('acao-estado').value = novoEstado;
    const btn = document.getElementById('acao-confirmar');
    if (novoEstado === 'cancelado') {
      document.getElementById('acao-titulo').textContent = 'Cancelar Bilhete';
      document.getElementById('acao-texto').textContent  = 'Cancelar a compra #' + ref + '? Esta operação altera o estado para cancelado.';
      btn.style.background = '#c0392b';
      btn.style.color      = '#fff';
    } else {
      document.getElementById('acao-titulo').textContent = 'Reativar Bilhete';
      document.getElementById('acao-texto').textContent  = 'Reativar a compra #' + ref + '? O estado voltará a confirmado.';
      btn.style.background = '#2e7d52';
      btn.style.color      = '#fff';
    }
    document.getElementById('modal-acao').style.display = 'flex';
  }
  function fecharAcao() { document.getElementById('modal-acao').style.display = 'none'; }

  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.getElementById('modal-detalhes').addEventListener('click', function(e){ if(e.target===this) fecharDetalhes(); });
  document.getElementById('modal-acao').addEventListener('click',     function(e){ if(e.target===this) fecharAcao(); });
  </script>

</body>
</html>
