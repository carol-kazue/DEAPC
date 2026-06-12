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

    document.getElementById('form-bilhete').addEventListener('submit', function(e) {
      const nome  = document.getElementById('inp-nome').value.trim();
      const email = document.getElementById('inp-email').value.trim();
      const total = ['normal','jovem','senior'].reduce(function(s,t){ return s + (qtds[t]||0); }, 0);
      if (!nome || !email || total === 0) {
        e.preventDefault();
        alert('Preencha o nome, email e selecione pelo menos um bilhete.');
      }
    });
  </script>

</body>
</html>
