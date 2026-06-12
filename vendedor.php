<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

requirePerfil('vendedor', 'login.html');

$vendedor = getUtilizador();
$db       = getDB();

$erro    = htmlspecialchars($_GET['erro']    ?? '');
$sucesso = htmlspecialchars($_GET['sucesso'] ?? '');
$ref     = htmlspecialchars($_GET['ref']     ?? '');

// Eventos publicados com disponibilidade
$stmtE = $db->prepare(
    'SELECT e.id, e.nome, e.data, e.hora, e.sala, e.capacidade,
            COALESCE(SUM(ic.quantidade), 0) AS vendidos
     FROM eventos e
     LEFT JOIN compras c ON c.evento_id = e.id AND c.estado = \'confirmado\'
     LEFT JOIN itens_compra ic ON ic.compra_id = c.id
     WHERE e.estado = \'publicado\' AND e.data >= date(\'now\')
     GROUP BY e.id
     ORDER BY e.data ASC'
);
$eventos = [];
$res = $stmtE->execute();
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

// Todos os preços, indexados por evento_id
$resP   = $db->query('SELECT evento_id, tipo, preco FROM precos');
$precos = [];
while ($p = $resP->fetchArray(SQLITE3_ASSOC)) {
    $precos[(int)$p['evento_id']][$p['tipo']] = (float)$p['preco'];
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vendedor — Casa da Música</title>
  <link rel="stylesheet" href="styles/vendedor.css" />
</head>
<body>

  <nav class="nav-vendor">
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música</a>
      <a href="bilhetes.php" class="btn-pill">Bilhetes</a>
    </div>
    <div class="nav-right">
      <span style="font-size:.88rem; color:#9e9080; margin-right:1rem;">Olá, <?= htmlspecialchars($vendedor['nome']) ?></span>
      <a href="scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page">
    <h1 class="page-title">Área do Vendedor</h1>

    <?php if ($sucesso === 'venda_registada'): ?>
    <div class="alert alert-success mb-2">
      Venda registada com sucesso! Referência: <strong>#<?= $ref ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($erro !== ''): ?>
    <div class="alert alert-danger mb-2">
      <?= $erro === 'dados_invalidos' ? 'Preencha todos os campos obrigatórios e selecione pelo menos um bilhete.' : htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Evento</th>
            <th>Data</th>
            <th>Hora</th>
            <th>Sala</th>
            <th>Disponíveis</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eventos as $ev): ?>
          <?php $disponiveis = (int)$ev['capacidade'] - (int)$ev['vendidos']; ?>
          <tr>
            <td><?= htmlspecialchars($ev['nome']) ?></td>
            <td><?= htmlspecialchars(date('d M Y', strtotime($ev['data']))) ?></td>
            <td><?= htmlspecialchars(substr($ev['hora'], 0, 5)) ?></td>
            <td><?= htmlspecialchars($ev['sala']) ?></td>
            <td>
              <span class="badge <?= $disponiveis > 0 ? 'badge-green' : 'badge-red' ?>">
                <?= $disponiveis ?> / <?= (int)$ev['capacidade'] ?>
              </span>
            </td>
            <td>
              <?php if ($disponiveis > 0): ?>
              <button type="button" class="btn btn-sm"
                onclick="abrirModal(<?= (int)$ev['id'] ?>, <?= htmlspecialchars(json_encode($ev['nome']), ENT_QUOTES) ?>, '<?= htmlspecialchars(date('d M Y', strtotime($ev['data']))) ?>', '<?= htmlspecialchars($ev['sala']) ?>')">
                Emitir Bilhete
              </button>
              <?php else: ?>
              <span class="text-sm" style="color:#5a5550;">Esgotado</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Modal de emissão de bilhete -->
  <div id="modal-bilhete" style="display:none; position:fixed; inset:0; z-index:500;
       background:rgba(0,0,0,.78); backdrop-filter:blur(3px);
       align-items:center; justify-content:center; overflow-y:auto; padding:2rem 1rem;">
    <div style="background:#12121f; border:1px solid #c9a83c; border-radius:6px;
                padding:2rem; max-width:560px; width:100%; box-shadow:0 8px 32px rgba(0,0,0,.7);">

      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.2rem;">
        <div>
          <p style="font-family:'Cinzel',Georgia,serif; font-size:1.1rem; color:#c9a83c; margin-bottom:.25rem;">Emitir Bilhete</p>
          <p id="modal-evento-nome" style="color:#f0ece4; font-weight:600; font-size:.95rem;"></p>
          <p id="modal-evento-info" style="color:#9e9080; font-size:.82rem; margin-top:.15rem;"></p>
        </div>
        <button onclick="fecharModal()" type="button"
          style="background:none; border:none; color:#5a5550; font-size:1.4rem; cursor:pointer; line-height:1; padding:.2rem .4rem;"
          onmouseover="this.style.color='#f0ece4'" onmouseout="this.style.color='#5a5550'">✕</button>
      </div>

      <form action="scripts/vender_bilhete.php" method="POST" id="form-bilhete">
        <input type="hidden" name="evento_id" id="modal-evento-id" value="" />

        <!-- Tipos de bilhete -->
        <p style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; margin-bottom:.6rem;">Bilhetes</p>
        <div id="modal-bilhetes" style="border:1px solid #252535; border-radius:3px; margin-bottom:1.2rem; overflow:hidden;"></div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
          <div>
            <label style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; display:block; margin-bottom:.35rem;">Nome completo *</label>
            <input type="text" name="nome_cliente" id="inp-nome" required placeholder="Nome Apelido"
              style="width:100%; border:1px solid #2a2a40; padding:.5rem .8rem; background:#0f0f20; color:#f0ece4; font-family:inherit; border-radius:3px; outline:none; font-size:.9rem;" />
          </div>
          <div>
            <label style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; display:block; margin-bottom:.35rem;">Email *</label>
            <input type="email" name="email_cliente" id="inp-email" required placeholder="email@exemplo.com"
              style="width:100%; border:1px solid #2a2a40; padding:.5rem .8rem; background:#0f0f20; color:#f0ece4; font-family:inherit; border-radius:3px; outline:none; font-size:.9rem;" />
          </div>
          <div>
            <label style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; display:block; margin-bottom:.35rem;">Telefone</label>
            <input type="text" name="telefone_cliente" placeholder="9xxxxxxxx"
              style="width:100%; border:1px solid #2a2a40; padding:.5rem .8rem; background:#0f0f20; color:#f0ece4; font-family:inherit; border-radius:3px; outline:none; font-size:.9rem;" />
          </div>
          <div>
            <label style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; display:block; margin-bottom:.35rem;">NIF</label>
            <input type="text" name="nif_cliente" placeholder="000000000" maxlength="9"
              style="width:100%; border:1px solid #2a2a40; padding:.5rem .8rem; background:#0f0f20; color:#f0ece4; font-family:inherit; border-radius:3px; outline:none; font-size:.9rem;" />
          </div>
        </div>

        <div style="margin-bottom:1.2rem;">
          <label style="font-size:.78rem; color:#c9a83c; text-transform:uppercase; letter-spacing:.06em; font-weight:500; display:block; margin-bottom:.35rem;">Método de pagamento</label>
          <select name="metodo_pagamento"
            style="width:100%; border:1px solid #2a2a40; padding:.5rem .8rem; background:#0f0f20; color:#f0ece4; font-family:inherit; border-radius:3px; outline:none; font-size:.9rem;">
            <option value="dinheiro">Dinheiro</option>
            <option value="multibanco">Multibanco</option>
            <option value="mbway">MB Way</option>
            <option value="cartao">Cartão</option>
          </select>
        </div>

        <!-- Total -->
        <div style="background:#0f0f20; border:1px solid #252535; border-radius:3px; padding:.7rem 1rem; margin-bottom:1.2rem; display:flex; justify-content:space-between; align-items:center;">
          <span style="font-size:.88rem; color:#9e9080;">Total</span>
          <span id="modal-total" style="font-weight:bold; color:#c9a83c; font-size:1rem;">€0,00</span>
        </div>

        <div style="display:flex; gap:.8rem; justify-content:flex-end;">
          <button onclick="fecharModal()" type="button"
            style="background:transparent; border:1px solid #3a3a55; color:#9e9080; border-radius:999px;
                   padding:.4rem 1.2rem; cursor:pointer; font-size:.9rem; transition:background .15s;"
            onmouseover="this.style.background='#3a3a55'" onmouseout="this.style.background='transparent'">
            Cancelar
          </button>
          <button type="submit"
            style="background:#c9a83c; border:none; color:#0b0b14; border-radius:999px;
                   padding:.4rem 1.4rem; cursor:pointer; font-size:.9rem; font-weight:600; transition:background .15s;"
            onmouseover="this.style.background='#dfc060'" onmouseout="this.style.background='#c9a83c'">
            Confirmar Venda
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const PRECOS = <?= json_encode($precos) ?>;
    const TIPOS  = { normal: 'Normal', jovem: 'Jovem (até 30 anos)', senior: 'Sénior (+65 anos)' };
    let qtds = {};

    function abrirModal(id, nome, data, sala) {
      document.getElementById('modal-evento-id').value   = id;
      document.getElementById('modal-evento-nome').textContent = nome;
      document.getElementById('modal-evento-info').textContent = data + ' · ' + sala;

      qtds = { normal: 0, jovem: 0, senior: 0 };

      const p = PRECOS[id] || {};
      const cont = document.getElementById('modal-bilhetes');
      cont.innerHTML = '';

      let temBilhetes = false;
      ['normal', 'jovem', 'senior'].forEach(function(tipo) {
        if (p[tipo] === undefined) return;
        temBilhetes = true;
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; justify-content:space-between; align-items:center; padding:.65rem 1rem; border-bottom:1px solid #1e1e30;';
        row.innerHTML =
          '<div>' +
            '<span style="color:#f0ece4; font-size:.9rem;">' + TIPOS[tipo] + '</span>' +
            '<span style="color:#9e9080; font-size:.8rem; margin-left:.6rem;">€' + p[tipo].toFixed(2).replace('.', ',') + '</span>' +
          '</div>' +
          '<div style="display:flex; align-items:center; gap:.5rem;">' +
            '<button type="button" onclick="changeQty(\'' + tipo + '\',-1,'+id+')"' +
              'style="width:28px;height:28px;border:1px solid #2a2a40;background:#181828;color:#f0ece4;cursor:pointer;font-size:1rem;border-radius:3px;transition:border-color .15s;"' +
              'onmouseover="this.style.borderColor=\'#c9a83c\'" onmouseout="this.style.borderColor=\'#2a2a40\'">−</button>' +
            '<span id="qty-' + tipo + '" style="width:24px;text-align:center;color:#f0ece4;font-size:.9rem;">0</span>' +
            '<button type="button" onclick="changeQty(\'' + tipo + '\',1,'+id+')"' +
              'style="width:28px;height:28px;border:1px solid #2a2a40;background:#181828;color:#f0ece4;cursor:pointer;font-size:1rem;border-radius:3px;transition:border-color .15s;"' +
              'onmouseover="this.style.borderColor=\'#c9a83c\'" onmouseout="this.style.borderColor=\'#2a2a40\'">+</button>' +
          '</div>';
        cont.appendChild(row);
      });

      if (!temBilhetes) {
        cont.innerHTML = '<p style="padding:.8rem 1rem; color:#9e9080; font-size:.88rem;">Sem tarifários definidos para este evento.</p>';
      }

      atualizarTotal(id);
      document.getElementById('modal-bilhete').style.display = 'flex';
      document.getElementById('inp-nome').focus();
    }

    function fecharModal() {
      document.getElementById('modal-bilhete').style.display = 'none';
    }

    function changeQty(tipo, delta, eventoId) {
      qtds[tipo] = Math.max(0, (qtds[tipo] || 0) + delta);
      document.getElementById('qty-' + tipo).textContent = qtds[tipo];

      // Atualiza os hidden inputs
      let inp = document.querySelector('[name="qty_' + tipo + '"]');
      if (!inp) {
        inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'qty_' + tipo;
        document.getElementById('form-bilhete').appendChild(inp);
      }
      inp.value = qtds[tipo];

      atualizarTotal(eventoId);
    }

    function atualizarTotal(eventoId) {
      const p = PRECOS[eventoId] || {};
      let total = 0;
      ['normal', 'jovem', 'senior'].forEach(function(tipo) {
        total += (qtds[tipo] || 0) * (p[tipo] || 0);
      });
      document.getElementById('modal-total').textContent = '€' + total.toFixed(2).replace('.', ',');
    }

    document.getElementById('modal-bilhete').addEventListener('click', function(e) {
      if (e.target === this) fecharModal();
    });

    // Interceta submit → simulação de API
    document.getElementById('form-bilhete').addEventListener('submit', function(e) {
      e.preventDefault();
      const nome  = document.getElementById('inp-nome').value.trim();
      const email = document.getElementById('inp-email').value.trim();
      const tot   = ['normal','jovem','senior'].reduce(function(s,t){ return s + (qtds[t]||0); }, 0);
      if (!nome || !email || tot === 0) {
        alert('Preencha o nome, email e selecione pelo menos um bilhete.');
        return;
      }
      iniciarVendaSim(new FormData(this));
    });
  </script>

  <!-- ── Overlay de simulação de venda ──────────────────────────────── -->
  <style>
    @keyframes vspin  { to { transform: rotate(360deg); } }
    @keyframes vfadin { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:none} }
    #modal-venda-sim {
      display:none; position:fixed; inset:0; z-index:700;
      background:rgba(0,0,0,.88); backdrop-filter:blur(4px);
      align-items:center; justify-content:center; padding:1.5rem;
    }
    .vsim-card {
      background:#0f0f1c; border:1px solid #3a5a8a; border-radius:8px;
      width:100%; max-width:440px; overflow:hidden;
      box-shadow:0 12px 48px rgba(0,0,0,.85);
    }
    .vsim-top {
      background:#090b14; padding:1.2rem 1.5rem .7rem;
      border-bottom:1px solid #1e1e30;
    }
    .vsim-ring {
      width:32px; height:32px; border-radius:50%;
      border:3px solid #1e2a3a; border-top-color:#7ab0e0;
      animation:vspin .9s linear infinite; flex-shrink:0;
    }
    .vsim-titulo  { font-family:'Cinzel',Georgia,serif; color:#7ab0e0; font-size:.95rem; }
    .vsim-api-tag {
      font-family:monospace; font-size:.7rem; color:#3a5a8a;
      background:#07090f; border:1px solid #1a2535; border-radius:3px;
      padding:.12rem .45rem; display:inline-block; margin-top:.2rem;
    }
    .vsim-steps { padding:.3rem 1.5rem 1.1rem; }
    .vsim-step {
      display:flex; align-items:center; gap:.65rem;
      padding:.38rem 0; font-size:.87rem; color:#5a5550;
      animation:vfadin .3s ease;
    }
    .vsim-step[data-s="active"] { color:#c8c0b4; }
    .vsim-step[data-s="done"]   { color:#52b37a; }
    .vstep-ic {
      width:17px; height:17px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-size:.76rem; font-weight:bold;
      border:1.5px solid #3a3a55; color:#3a3a55;
      transition:all .2s;
    }
    .vsim-step[data-s="active"] .vstep-ic {
      border-color:#7ab0e0; color:#7ab0e0;
      border-top-color:transparent; animation:vspin .8s linear infinite;
    }
    .vsim-step[data-s="done"] .vstep-ic {
      border-color:#52b37a; background:#52b37a; color:#0b0b14;
    }
    /* Ticket comprovativo (mesma estrutura de pagamento.php) */
    #vfase-recibo { display:none; }
    .vtkt-wrap { padding:1.3rem 1.5rem; }
    .vtkt {
      background:#0b0b14; border:1px solid #c9a83c33;
      border-radius:6px; overflow:hidden; font-size:.87rem;
    }
    .vtkt-head {
      background:#12121f; padding:.85rem 1.1rem;
      display:flex; justify-content:space-between; align-items:flex-start;
    }
    .vtkt-check { font-size:1.5rem; color:#52b37a; line-height:1; }
    .vtkt-title { font-family:'Cinzel',Georgia,serif; color:#52b37a; font-size:.88rem; font-weight:600; }
    .vtkt-brand { font-size:.73rem; color:#9e9080; }
    .vtkt-ref   { font-family:monospace; font-size:.78rem; color:#c9a83c; text-align:right; }
    .vtkt-ref-lbl { font-size:.63rem; color:#5a5550; text-transform:uppercase; letter-spacing:.04em; }
    .vtkt-event { padding:.85rem 1.1rem .65rem; border-bottom:1px solid #1e1e30; }
    .vtkt-event-name {
      font-family:'Cinzel',Georgia,serif; color:#f0ece4;
      font-size:.95rem; font-weight:600; margin-bottom:.22rem;
    }
    .vtkt-event-meta { color:#9e9080; font-size:.78rem; }
    .vtkt-perf {
      position:relative; margin:0; height:0;
      border:none; border-top:1.5px dashed #252535;
    }
    .vtkt-perf::before, .vtkt-perf::after {
      content:''; position:absolute; top:-7px;
      width:13px; height:13px; border-radius:50%;
      background:#0b0b14; border:1.5px solid #252535;
    }
    .vtkt-perf::before { left:-7px; }
    .vtkt-perf::after  { right:-7px; }
    .vtkt-items { padding:.75rem 1.1rem; }
    .vtkt-row { display:flex; justify-content:space-between; padding:.2rem 0; color:#c8c0b4; }
    .vtkt-row.total {
      border-top:1px solid #252535; margin-top:.35rem; padding-top:.45rem;
      font-weight:600; color:#c9a83c; font-size:.9rem;
    }
    .vtkt-foot {
      background:#08080f; padding:.65rem 1.1rem;
      display:flex; flex-direction:column; align-items:center; gap:.35rem;
    }
    .vtkt-client-line { font-size:.75rem; color:#9e9080; }
    .vbarcode {
      height:40px; width:220px;
      background: repeating-linear-gradient(
        90deg,
        #f0ece4 0, #f0ece4 1px, transparent 1px, transparent 3px,
        #f0ece4 3px, #f0ece4 5px, transparent 5px, transparent 9px,
        #f0ece4 9px, #f0ece4 10px, transparent 10px, transparent 12px,
        #f0ece4 12px, #f0ece4 15px, transparent 15px, transparent 17px,
        #f0ece4 17px, #f0ece4 18px, transparent 18px, transparent 22px,
        #f0ece4 22px, #f0ece4 24px, transparent 24px, transparent 26px
      );
      border-radius:2px;
    }
    .vbarcode-num { font-family:monospace; font-size:.68rem; color:#5a5550; letter-spacing:.06em; }
    .vsim-actions {
      padding:.9rem 1.5rem 1.2rem;
      display:flex; gap:.7rem; justify-content:flex-end;
      border-top:1px solid #1e1e30;
    }
    .vsim-btn-print {
      background:transparent; border:1px solid #3a3a55; color:#9e9080;
      border-radius:999px; padding:.38rem 1.1rem; cursor:pointer;
      font-family:inherit; font-size:.85rem; transition:background .15s;
    }
    .vsim-btn-print:hover { background:#3a3a55; color:#f0ece4; }
    .vsim-btn-ok {
      background:#c9a83c; border:none; color:#0b0b14;
      border-radius:999px; padding:.38rem 1.3rem; cursor:pointer;
      font-family:inherit; font-size:.85rem; font-weight:600;
    }
    .vsim-btn-ok:hover { background:#dfc060; }
    #vsim-erro { padding:.7rem 1.5rem; color:#e05252; font-size:.86rem; display:none; }
    @media print {
      body > *:not(#modal-venda-sim) { display:none !important; }
      #modal-venda-sim { position:static !important; background:none !important; display:block !important; padding:0 !important; }
      .vsim-card { box-shadow:none; border:none; }
      #vfase-processar { display:none !important; }
      #vfase-recibo    { display:block !important; }
      .vsim-actions    { display:none !important; }
    }
  </style>

  <div id="modal-venda-sim">
    <div class="vsim-card">
      <div id="vfase-processar">
        <div class="vsim-top">
          <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.7rem;">
            <div class="vsim-ring"></div>
            <div>
              <p class="vsim-titulo">A processar venda</p>
              <span class="vsim-api-tag">POST /api/v1/tickets/issue</span>
            </div>
          </div>
        </div>
        <div class="vsim-steps">
          <div class="vsim-step" id="vs0" data-s="active"><div class="vstep-ic"></div><span>A verificar disponibilidade</span></div>
          <div class="vsim-step" id="vs1" data-s="waiting"><div class="vstep-ic"></div><span>A registar compra</span></div>
          <div class="vsim-step" id="vs2" data-s="waiting"><div class="vstep-ic"></div><span>A emitir bilhetes</span></div>
        </div>
      </div>
      <div id="vfase-recibo">
        <div class="vtkt-wrap"><div class="vtkt" id="vtkt-area"></div></div>
        <div class="vsim-actions">
          <button class="vsim-btn-print" onclick="window.print()">Imprimir</button>
          <button class="vsim-btn-ok"    onclick="fecharVendaSim()">Fechar</button>
        </div>
      </div>
      <div id="vsim-erro">
        <p id="vsim-erro-msg"></p>
        <button style="background:transparent;border:1px solid #5a2020;color:#e05252;border-radius:999px;
                       padding:.3rem .9rem;cursor:pointer;font-family:inherit;font-size:.8rem;margin-top:.4rem;"
          onclick="document.getElementById('modal-venda-sim').style.display='none'">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    const VMETODOS = { dinheiro:'Dinheiro', multibanco:'Multibanco', mbway:'MB Way', cartao:'Cartão' };
    const VTIPOS   = { normal:'Normal', jovem:'Jovem (≤30)', senior:'Sénior (+65)' };
    const VDELAYS  = [500, 600, 550];
    let vFetchData = null, vAnimDone = false;

    function iniciarVendaSim(formData) {
      vFetchData = null; vAnimDone = false;
      for (let i = 0; i < 3; i++) {
        document.getElementById('vs'+i).setAttribute('data-s', i===0?'active':'waiting');
        document.getElementById('vs'+i).querySelector('.vstep-ic').textContent = '';
      }
      document.getElementById('vfase-processar').style.display = '';
      document.getElementById('vfase-recibo').style.display    = 'none';
      document.getElementById('vsim-erro').style.display       = 'none';
      document.getElementById('modal-venda-sim').style.display = 'flex';

      fetch('scripts/vender_bilhete.php', {
        method:'POST',
        headers:{'X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams(formData)
      })
      .then(function(r){ return r.json(); })
      .then(function(d){ vFetchData = d; vTryMostrar(); })
      .catch(function(){ vFetchData = {ok:false,erro:'network'}; vTryMostrar(); });

      let acc = 0;
      VDELAYS.forEach(function(delay, i) {
        acc += delay;
        setTimeout(function() {
          const el = document.getElementById('vs'+i);
          el.setAttribute('data-s','done');
          el.querySelector('.vstep-ic').textContent = '✓';
          const nx = document.getElementById('vs'+(i+1));
          if (nx) nx.setAttribute('data-s','active');
        }, acc);
      });
      setTimeout(function(){ vAnimDone = true; vTryMostrar(); }, acc + 120);
    }

    function vTryMostrar() {
      if (!vFetchData || !vAnimDone) return;
      if (vFetchData.ok) {
        vMostrarRecibo(vFetchData);
      } else {
        const msgs = {
          dados_invalidos:'Preencha todos os campos obrigatórios.',
          sem_lugares:'Sem lugares disponíveis para este evento.',
          evento_nao_encontrado:'Evento não encontrado.',
          network:'Erro de ligação. Tente novamente.'
        };
        document.getElementById('vsim-erro-msg').textContent = msgs[vFetchData.erro] || 'Ocorreu um erro.';
        document.getElementById('vfase-processar').style.display = 'none';
        document.getElementById('vsim-erro').style.display = 'block';
      }
    }

    function vMostrarRecibo(d) {
      let itensHtml = '';
      (d.itens || []).forEach(function(it) {
        const sub = parseFloat(it.preco_unitario) * parseInt(it.quantidade);
        itensHtml += '<div class="vtkt-row"><span>'+parseInt(it.quantidade)+'× '+(VTIPOS[it.tipo]||it.tipo)
          +'</span><span>€'+sub.toFixed(2).replace('.',',')+'</span></div>';
      });
      document.getElementById('vtkt-area').innerHTML = `
        <div class="vtkt-head">
          <div style="display:flex;align-items:center;gap:.65rem;">
            <span class="vtkt-check">✓</span>
            <div>
              <p class="vtkt-title">Bilhete Emitido</p>
              <p class="vtkt-brand">Casa da Música · Presencial</p>
            </div>
          </div>
          <div style="text-align:right;">
            <p class="vtkt-ref-lbl">Referência</p>
            <p class="vtkt-ref">#${vEsc(d.referencia)}</p>
          </div>
        </div>
        <div class="vtkt-event">
          <p class="vtkt-event-name">${vEsc(d.evento)}</p>
          <p class="vtkt-event-meta">${vEsc(d.data_evento)} · ${vEsc(d.hora_evento)} · ${vEsc(d.sala)}</p>
        </div>
        <hr class="vtkt-perf" />
        <div class="vtkt-items">
          ${itensHtml}
          <div class="vtkt-row total"><span>Total</span><span>€${parseFloat(d.total).toFixed(2).replace('.',',')}</span></div>
        </div>
        <hr class="vtkt-perf" />
        <div class="vtkt-foot">
          <p class="vtkt-client-line">${vEsc(d.nome_cliente)} · ${VMETODOS[d.metodo]||d.metodo}</p>
          <div class="vbarcode"></div>
          <p class="vbarcode-num">${vEsc(d.referencia)}</p>
        </div>
      `;
      document.getElementById('vfase-processar').style.display = 'none';
      document.getElementById('vfase-recibo').style.display    = 'block';
    }

    function fecharVendaSim() {
      document.getElementById('modal-venda-sim').style.display = 'none';
      document.getElementById('modal-bilhete').style.display   = 'none';
      // Recarrega a página para actualizar a tabela
      window.location.reload();
    }

    function vEsc(s) {
      return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
  </script>

</body>
</html>
