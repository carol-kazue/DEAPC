<?php require_once 'scripts/sessao.php'; ?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pagamento — Casa da Música</title>
  <link rel="stylesheet" href="styles/pagamento.css" />
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
      <div class="step-dot active"></div>
      <div class="step-dot"></div>
    </div>
    <p class="text-center text-sm mb-2">Passo 2 de 3 — Pagamento</p>

    <div class="order-box" id="resumo">
      <p class="text-bold mb-1">Resumo da compra</p>
      <div id="resumo-linhas"></div>
      <div class="order-row order-total"><span>Total</span><span id="resumo-total">€0,00</span></div>
    </div>

    <form action="scripts/processar_compra.php" method="POST">
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

  <script src="scripts/validacao.js"></script>
  <script>
    // Preenche campos a partir do sessionStorage
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
      ['qty_normal','qty_jovem','qty_senior'].forEach(k => {
        if (carrinho[k] > 0) {
          const d = document.createElement('div');
          d.className = 'order-row';
          d.innerHTML = '<span>' + carrinho[k] + '× ' + labels[k] + '</span>';
          linhas.appendChild(d);
        }
      });
    }

    // Formatação automática do número do cartão
    document.getElementById('numero').addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').substring(0, 16);
      this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });

    // Formatação automática da validade
    document.getElementById('validade').addEventListener('input', function () {
      let v = this.value.replace(/\D/g, '').substring(0, 4);
      if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
      this.value = v;
    });

    // Validação ao submeter
    document.querySelector('form').addEventListener('submit', function (e) {
      let ok = true;

      const fNome  = document.getElementById('nome_cliente');
      const fEmail = document.getElementById('email_cliente');
      const fTel   = document.getElementById('telefone_cliente');
      const fNum   = document.getElementById('numero');
      const fVal   = document.getElementById('validade');
      const fCvv   = document.getElementById('cvv');

      if (!fNome.value.trim()) {
        marcaErro(fNome, 'O nome é obrigatório.'); ok = false;
      } else limpaErro(fNome);

      if (!fEmail.value.trim()) {
        marcaErro(fEmail, 'O email é obrigatório.'); ok = false;
      } else if (!emailValido(fEmail.value)) {
        marcaErro(fEmail, 'Formato de email inválido.'); ok = false;
      } else limpaErro(fEmail);

      if (!telefoneValido(fTel.value)) {
        marcaErro(fTel, 'Telefone inválido (9 a 15 dígitos).'); ok = false;
      } else limpaErro(fTel);

      const numLimpo = fNum.value.replace(/\s/g, '');
      if (numLimpo && !/^\d{16}$/.test(numLimpo)) {
        marcaErro(fNum, 'O número do cartão deve ter 16 dígitos.'); ok = false;
      } else limpaErro(fNum);

      if (fVal.value && !/^(0[1-9]|1[0-2])\/\d{2}$/.test(fVal.value)) {
        marcaErro(fVal, 'Formato inválido (MM/AA).'); ok = false;
      } else limpaErro(fVal);

      if (fCvv.value && !/^\d{3}$/.test(fCvv.value)) {
        marcaErro(fCvv, 'O CVV deve ter 3 dígitos.'); ok = false;
      } else limpaErro(fCvv);

      if (!ok) e.preventDefault();
    });
  </script>
  </main>

</body>
</html>
