<?php require_once 'scripts/sessao.php'; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pagamento — Casa da Música</title>
  <link rel="stylesheet" href="styles/pagamento.css" />
  <style>
    /* ── Simulação de API ─────────────────────────────── */
    @keyframes spin  { to { transform: rotate(360deg); } }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    @keyframes fadein{ from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }

    #modal-sim {
      display:none; position:fixed; inset:0; z-index:900;
      background:rgba(0,0,0,.88); backdrop-filter:blur(4px);
      align-items:center; justify-content:center; padding:1.5rem;
    }
    .sim-card {
      background:#0f0f1c; border:1px solid #2a2a40; border-radius:8px;
      width:100%; max-width:480px; overflow:hidden;
      box-shadow:0 12px 48px rgba(0,0,0,.8);
    }

    /* Painel de processamento */
    .sim-top {
      background:#0b0b14; padding:1.4rem 1.6rem .8rem;
      border-bottom:1px solid #1e1e30;
    }
    .sim-logo-row {
      display:flex; align-items:center; gap:.9rem; margin-bottom:.9rem;
    }
    .sim-spinner-ring {
      width:36px; height:36px; border-radius:50%;
      border:3px solid #252535; border-top-color:#c9a83c;
      animation:spin .9s linear infinite; flex-shrink:0;
    }
    .sim-titulo  { font-family:'Cinzel',Georgia,serif; color:#c9a83c; font-size:1rem; }
    .sim-api-tag {
      font-family:monospace; font-size:.72rem; color:#3a5a8a;
      background:#0a0f18; border:1px solid #1e2a3a; border-radius:3px;
      padding:.15rem .5rem; display:inline-block; margin-top:.2rem;
    }
    .sim-steps { padding:.4rem 1.6rem 1.2rem; }
    .sim-step  {
      display:flex; align-items:center; gap:.7rem;
      padding:.4rem 0; font-size:.88rem; color:#5a5550;
      transition:color .2s;
      animation:fadein .3s ease;
    }
    .sim-step[data-s="active"]  { color:#c8c0b4; }
    .sim-step[data-s="done"]    { color:#52b37a; }
    .step-ic {
      width:18px; height:18px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-size:.8rem; font-weight:bold;
      border:1.5px solid #3a3a55; color:#3a3a55;
      transition:border-color .2s, color .2s, background .2s;
    }
    .sim-step[data-s="active"] .step-ic {
      border-color:#c9a83c; color:#c9a83c;
      border-top-color:transparent;
      animation:spin .8s linear infinite;
    }
    .sim-step[data-s="done"] .step-ic {
      border-color:#52b37a; background:#52b37a; color:#0b0b14;
    }

    /* ── Comprovativo / Bilhete ───────────────────────── */
    #fase-recibo { display:none; }
    .ticket-wrap { padding:1.4rem 1.6rem; }
    .ticket {
      background:#0b0b14; border:1px solid #c9a83c33;
      border-radius:6px; overflow:hidden;
      font-size:.88rem;
    }
    .tkt-head {
      background:#12121f; padding:.9rem 1.2rem;
      display:flex; justify-content:space-between; align-items:flex-start;
    }
    .tkt-check { font-size:1.6rem; color:#52b37a; line-height:1; }
    .tkt-title { font-family:'Cinzel',Georgia,serif; color:#52b37a; font-size:.9rem; font-weight:600; }
    .tkt-brand { font-size:.75rem; color:#9e9080; margin-top:.1rem; }
    .tkt-ref   { font-family:monospace; font-size:.8rem; color:#c9a83c; text-align:right; }
    .tkt-ref-lbl { font-size:.65rem; color:#5a5550; text-transform:uppercase; letter-spacing:.04em; }
    .tkt-event { padding:.9rem 1.2rem .7rem; border-bottom:1px solid #1e1e30; }
    .tkt-event-name {
      font-family:'Cinzel',Georgia,serif; color:#f0ece4;
      font-size:1rem; font-weight:600; margin-bottom:.25rem;
    }
    .tkt-event-meta { color:#9e9080; font-size:.8rem; }
    .tkt-perf {
      position:relative; margin:0; height:0;
      border:none; border-top:1.5px dashed #252535;
    }
    .tkt-perf::before, .tkt-perf::after {
      content:''; position:absolute; top:-7px;
      width:13px; height:13px; border-radius:50%;
      background:#0b0b14; border:1.5px solid #252535;
    }
    .tkt-perf::before { left:-7px; }
    .tkt-perf::after  { right:-7px; }
    .tkt-items { padding:.8rem 1.2rem; }
    .tkt-row {
      display:flex; justify-content:space-between;
      padding:.22rem 0; color:#c8c0b4;
    }
    .tkt-row.total {
      border-top:1px solid #252535; margin-top:.4rem; padding-top:.5rem;
      font-weight:600; color:#c9a83c; font-size:.92rem;
    }
    .tkt-foot {
      background:#08080f; padding:.7rem 1.2rem;
      display:flex; flex-direction:column; align-items:center; gap:.4rem;
    }
    .tkt-client-line { font-size:.78rem; color:#9e9080; }
    .barcode-strip {
      height:44px; width:240px;
      background: repeating-linear-gradient(
        90deg,
        #f0ece4 0, #f0ece4 1px, transparent 1px, transparent 3px,
        #f0ece4 3px, #f0ece4 5px, transparent 5px, transparent 9px,
        #f0ece4 9px, #f0ece4 10px, transparent 10px, transparent 12px,
        #f0ece4 12px, #f0ece4 15px, transparent 15px, transparent 17px,
        #f0ece4 17px, #f0ece4 18px, transparent 18px, transparent 22px,
        #f0ece4 22px, #f0ece4 24px, transparent 24px, transparent 26px,
        #f0ece4 26px, #f0ece4 27px, transparent 27px, transparent 31px
      );
      border-radius:2px;
      margin:.2rem 0;
    }
    .barcode-num { font-family:monospace; font-size:.7rem; color:#5a5550; letter-spacing:.08em; }

    .sim-actions {
      padding:1rem 1.6rem 1.4rem;
      display:flex; gap:.8rem; justify-content:flex-end;
      border-top:1px solid #1e1e30;
    }
    .sim-btn-print {
      background:transparent; border:1px solid #3a3a55; color:#9e9080;
      border-radius:999px; padding:.4rem 1.2rem; cursor:pointer;
      font-family:inherit; font-size:.88rem; transition:background .15s;
    }
    .sim-btn-print:hover { background:#3a3a55; color:#f0ece4; }
    .sim-btn-ok {
      background:#c9a83c; border:none; color:#0b0b14;
      border-radius:999px; padding:.4rem 1.4rem; cursor:pointer;
      font-family:inherit; font-size:.88rem; font-weight:600;
      transition:background .15s;
    }
    .sim-btn-ok:hover { background:#dfc060; }

    /* Erro inline */
    #sim-erro {
      padding:.8rem 1.6rem;
      color:#e05252; font-size:.88rem; display:none;
      border-top:1px solid #2b1414;
    }
    .sim-btn-fechar {
      background:transparent; border:1px solid #5a2020; color:#e05252;
      border-radius:999px; padding:.3rem 1rem; cursor:pointer;
      font-family:inherit; font-size:.82rem; margin-top:.5rem;
    }

    /* Print */
    @media print {
      body > *:not(#modal-sim) { display:none !important; }
      #modal-sim {
        position:static !important; background:none !important;
        padding:0 !important; display:block !important;
      }
      .sim-card { box-shadow:none; border:none; }
      #fase-processar { display:none !important; }
      #fase-recibo    { display:block !important; }
      .sim-actions, .sim-logo-row { display:none !important; }
    }
  </style>
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música</a>
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
      <div class="step-dot active"></div>
      <div class="step-dot"></div>
    </div>
    <p class="text-center text-sm mb-2">Passo 2 de 3 — Pagamento</p>

    <div id="msg-erro" style="display:none; padding:.8rem 1.2rem; background:#1f0c0c;
         border-left:3px solid #c0392b; border-radius:3px; color:#c8c0b4;
         font-size:.88rem; margin-bottom:1rem;"></div>

    <div class="order-box" id="resumo">
      <p class="text-bold mb-1">Resumo da compra</p>
      <div id="resumo-linhas"></div>
      <div class="order-row order-total"><span>Total</span><span id="resumo-total">€0,00</span></div>
    </div>

    <form id="form-pagamento" action="scripts/processar_compra.php" method="POST">
      <input type="hidden" id="hid-evento-id"   name="evento_id"        value="" />
      <input type="hidden" id="hid-qty-normal"  name="qty_normal"       value="0" />
      <input type="hidden" id="hid-qty-jovem"   name="qty_jovem"        value="0" />
      <input type="hidden" id="hid-qty-senior"  name="qty_senior"       value="0" />
      <input type="hidden"                       name="metodo_pagamento" value="cartao" />

      <div class="form-card">
        <p class="text-bold mb-2">Dados do cliente</p>
        <div class="form-group">
          <label for="nome_cliente">Nome completo</label>
          <input type="text" id="nome_cliente" name="nome_cliente" placeholder="Nome Apelido" required />
        </div>
        <div class="form-group">
          <label for="email_cliente">Email</label>
          <input type="email" id="email_cliente" name="email_cliente" placeholder="email@exemplo.com" required />
        </div>
        <div class="form-group">
          <label for="telefone_cliente">Telefone</label>
          <input type="text" id="telefone_cliente" name="telefone_cliente" placeholder="9xxxxxxxx" />
        </div>
      </div>

      <div class="form-card" style="margin-top:1rem;">
        <p class="text-bold mb-2">Dados de pagamento</p>
        <div class="form-group">
          <label for="titular">Nome do titular</label>
          <input type="text" id="titular" placeholder="Nome como no cartão" />
        </div>
        <div class="form-group">
          <label for="numero">Número do cartão</label>
          <input type="text" id="numero" placeholder="0000 0000 0000 0000" maxlength="19" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="validade">Validade</label>
            <input type="text" id="validade" placeholder="MM/AA" maxlength="5" />
          </div>
          <div class="form-group">
            <label for="cvv">CVV</label>
            <input type="text" id="cvv" placeholder="•••" maxlength="3" />
          </div>
        </div>
      </div>

      <div style="display:flex; justify-content:space-between; margin-top:1.5rem;">
        <a id="btn-voltar" href="eventos.php" class="btn btn-pill-light">← Voltar</a>
        <button type="submit" class="btn" id="btn-pagar">Pagar €0,00</button>
      </div>
    </form>

  </main>

  <!-- ── Modal de simulação de pagamento ────────────────────────────────── -->
  <div id="modal-sim">
    <div class="sim-card">

      <!-- FASE 1: Processamento -->
      <div id="fase-processar">
        <div class="sim-top">
          <div class="sim-logo-row">
            <div class="sim-spinner-ring"></div>
            <div>
              <p class="sim-titulo">A processar pagamento</p>
              <span class="sim-api-tag">POST /api/v1/payment/authorize</span>
            </div>
          </div>
        </div>
        <div class="sim-steps" id="sim-steps-wrap">
          <div class="sim-step" id="s0" data-s="active">
            <div class="step-ic"></div>
            <span>Ligação segura estabelecida</span>
          </div>
          <div class="sim-step" id="s1" data-s="waiting">
            <div class="step-ic"></div>
            <span>Dados do cartão verificados</span>
          </div>
          <div class="sim-step" id="s2" data-s="waiting">
            <div class="step-ic"></div>
            <span>Pagamento autorizado pelo emissor</span>
          </div>
          <div class="sim-step" id="s3" data-s="waiting">
            <div class="step-ic"></div>
            <span>Bilhetes emitidos e reservados</span>
          </div>
        </div>
      </div>

      <!-- FASE 2: Comprovativo -->
      <div id="fase-recibo">
        <div class="ticket-wrap">
          <div class="ticket" id="ticket-area">
            <!-- preenchido por JS -->
          </div>
        </div>
        <div class="sim-actions">
          <button class="sim-btn-print" onclick="window.print()">Imprimir</button>
          <button class="sim-btn-ok"    id="btn-continuar">Continuar →</button>
        </div>
      </div>

      <!-- Erro -->
      <div id="sim-erro">
        <p id="sim-erro-msg"></p>
        <button class="sim-btn-fechar" onclick="fecharSim()">Fechar</button>
      </div>

    </div>
  </div>

  <script src="scripts/validacao.js"></script>
  <script>
  // ── Carrinho ──────────────────────────────────────────────────────────
  const carrinho = JSON.parse(sessionStorage.getItem('carrinho') || '{}');
  if (!carrinho.evento_id) {
    window.location.href = 'eventos.php';
  } else {
    document.getElementById('hid-evento-id').value  = carrinho.evento_id;
    document.getElementById('hid-qty-normal').value = carrinho.qty_normal || 0;
    document.getElementById('hid-qty-jovem').value  = carrinho.qty_jovem  || 0;
    document.getElementById('hid-qty-senior').value = carrinho.qty_senior || 0;
    document.getElementById('btn-voltar').href = 'carrinho.php?evento_id=' + carrinho.evento_id;

    const total = parseFloat(carrinho.total) || 0;
    document.getElementById('btn-pagar').textContent = 'Pagar €' + total.toFixed(2).replace('.', ',');
    document.getElementById('resumo-total').textContent = '€' + total.toFixed(2).replace('.', ',');

    const linhas = document.getElementById('resumo-linhas');
    const labels = { qty_normal: 'Normal', qty_jovem: 'Jovem', qty_senior: 'Sénior' };
    ['qty_normal','qty_jovem','qty_senior'].forEach(function(k) {
      if (carrinho[k] > 0) {
        const d = document.createElement('div');
        d.className = 'order-row';
        d.innerHTML = '<span>' + carrinho[k] + '× ' + labels[k] + '</span>';
        linhas.appendChild(d);
      }
    });
  }

  // ── Formatação do cartão ──────────────────────────────────────────────
  document.getElementById('numero').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 16);
    this.value = v.replace(/(.{4})/g, '$1 ').trim();
  });
  document.getElementById('validade').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
    this.value = v;
  });

  // ── Submit: validar + simulação ───────────────────────────────────────
  document.getElementById('form-pagamento').addEventListener('submit', function (e) {
    e.preventDefault();

    let ok = true;
    const fNome  = document.getElementById('nome_cliente');
    const fEmail = document.getElementById('email_cliente');
    const fTel   = document.getElementById('telefone_cliente');
    const fNum   = document.getElementById('numero');
    const fVal   = document.getElementById('validade');
    const fCvv   = document.getElementById('cvv');

    if (!fNome.value.trim())  { marcaErro(fNome, 'O nome é obrigatório.'); ok = false; } else limpaErro(fNome);
    if (!fEmail.value.trim()) { marcaErro(fEmail, 'O email é obrigatório.'); ok = false; }
    else if (!emailValido(fEmail.value)) { marcaErro(fEmail, 'Formato de email inválido.'); ok = false; }
    else limpaErro(fEmail);

    if (!telefoneValido(fTel.value)) { marcaErro(fTel, 'Telefone inválido (9 a 15 dígitos).'); ok = false; } else limpaErro(fTel);

    const numLimpo = fNum.value.replace(/\s/g, '');
    if (numLimpo && !/^\d{16}$/.test(numLimpo)) { marcaErro(fNum, 'O número do cartão deve ter 16 dígitos.'); ok = false; } else limpaErro(fNum);
    if (fVal.value && !/^(0[1-9]|1[0-2])\/\d{2}$/.test(fVal.value)) { marcaErro(fVal, 'Formato inválido (MM/AA).'); ok = false; } else limpaErro(fVal);
    if (fCvv.value && !/^\d{3}$/.test(fCvv.value)) { marcaErro(fCvv, 'O CVV deve ter 3 dígitos.'); ok = false; } else limpaErro(fCvv);

    if (!ok) return;

    document.getElementById('msg-erro').style.display = 'none';
    iniciarSimulacao(new FormData(this));
  });

  // ── Simulação de API ──────────────────────────────────────────────────
  const STEPS_DELAYS = [550, 650, 700, 600]; // ms por step
  let fetchData = null;
  let animDone  = false;
  let redirectUrl = '';

  function iniciarSimulacao(formData) {
    fetchData = null;
    animDone  = false;

    // Reset steps
    for (let i = 0; i < 4; i++) {
      document.getElementById('s' + i).setAttribute('data-s', i === 0 ? 'active' : 'waiting');
      document.getElementById('s' + i).querySelector('.step-ic').textContent = '';
    }
    document.getElementById('fase-processar').style.display = '';
    document.getElementById('fase-recibo').style.display    = 'none';
    document.getElementById('sim-erro').style.display       = 'none';
    document.getElementById('modal-sim').style.display      = 'flex';

    // Fetch
    fetch('scripts/processar_compra.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams(formData)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { fetchData = data; tryMostrar(); })
    .catch(function() { fetchData = { ok: false, erro: 'network' }; tryMostrar(); });

    // Animação
    let acc = 0;
    STEPS_DELAYS.forEach(function(delay, i) {
      acc += delay;
      setTimeout(function() { marcarDone(i); }, acc);
    });
    setTimeout(function() { animDone = true; tryMostrar(); }, acc + 150);
  }

  function marcarDone(i) {
    const el = document.getElementById('s' + i);
    el.setAttribute('data-s', 'done');
    el.querySelector('.step-ic').textContent = '✓';
    const next = document.getElementById('s' + (i + 1));
    if (next) next.setAttribute('data-s', 'active');
  }

  function tryMostrar() {
    if (!fetchData || !animDone) return;
    if (fetchData.ok) {
      redirectUrl = fetchData.redirect || 'confirmacao.php';
      sessionStorage.removeItem('carrinho');
      mostrarRecibo(fetchData);
    } else {
      mostrarErroPagamento(fetchData.erro);
    }
  }

  function mostrarErroPagamento(codigo) {
    const msgs = {
      sem_lugares:        'Já não existem lugares disponíveis para este evento.',
      dados_invalidos:    'Dados inválidos. Por favor tente novamente.',
      dados_cliente:      'Preencha o nome e o email do cliente.',
      evento_nao_encontrado: 'O evento não foi encontrado.',
      network:            'Erro de ligação. Verifique a sua internet e tente novamente.',
    };
    document.getElementById('sim-erro-msg').textContent = msgs[codigo] || 'Ocorreu um erro. Por favor tente novamente.';
    document.getElementById('fase-processar').style.display = 'none';
    document.getElementById('sim-erro').style.display = 'block';
  }

  function fecharSim() {
    document.getElementById('modal-sim').style.display = 'none';
  }

  // ── Comprovativo ──────────────────────────────────────────────────────
  const METODOS = { cartao:'Cartão de Crédito', multibanco:'Multibanco', mbway:'MB Way', dinheiro:'Dinheiro' };
  const TIPOS   = { normal:'Normal', jovem:'Jovem (≤30)', senior:'Sénior (+65)' };

  function mostrarRecibo(d) {
    let itensHtml = '';
    let total = 0;
    (d.itens || []).forEach(function(it) {
      const sub = parseFloat(it.preco_unitario) * parseInt(it.quantidade);
      total += sub;
      itensHtml += '<div class="tkt-row"><span>' + parseInt(it.quantidade) + '× ' + (TIPOS[it.tipo] || it.tipo)
        + '</span><span>€' + sub.toFixed(2).replace('.', ',') + '</span></div>';
    });

    document.getElementById('ticket-area').innerHTML = `
      <div class="tkt-head">
        <div style="display:flex;align-items:center;gap:.7rem;">
          <span class="tkt-check">✓</span>
          <div>
            <p class="tkt-title">Bilhete Confirmado</p>
            <p class="tkt-brand">Casa da Música · Porto</p>
          </div>
        </div>
        <div style="text-align:right;">
          <p class="tkt-ref-lbl">Referência</p>
          <p class="tkt-ref">#${esc(d.referencia)}</p>
        </div>
      </div>
      <div class="tkt-event">
        <p class="tkt-event-name">${esc(d.evento)}</p>
        <p class="tkt-event-meta">${esc(d.data_evento)} · ${esc(d.hora_evento)} · ${esc(d.sala)}</p>
      </div>
      <hr class="tkt-perf" />
      <div class="tkt-items">
        ${itensHtml}
        <div class="tkt-row total"><span>Total</span><span>€${parseFloat(d.total).toFixed(2).replace('.', ',')}</span></div>
      </div>
      <hr class="tkt-perf" />
      <div class="tkt-foot">
        <p class="tkt-client-line">${esc(d.nome_cliente)} · ${METODOS[d.metodo] || d.metodo}</p>
        <div class="barcode-strip"></div>
        <p class="barcode-num">${esc(d.referencia)}</p>
      </div>
    `;

    document.getElementById('fase-processar').style.display = 'none';
    document.getElementById('fase-recibo').style.display    = 'block';
    document.getElementById('btn-continuar').onclick = function() {
      window.location.href = redirectUrl;
    };
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  </script>

</body>
</html>
