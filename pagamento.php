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
      <a href="cliente.php" class="nav-icon" aria-label="Conta">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0"/>
        </svg>
      </a>
      <a href="login.html" class="btn-pill">Entrar</a>
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

    <script>
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
        // We don't have prices here so we just show quantities
        ['qty_normal','qty_jovem','qty_senior'].forEach(k => {
          if (carrinho[k] > 0) {
            const d = document.createElement('div');
            d.className = 'order-row';
            d.innerHTML = '<span>' + carrinho[k] + '× ' + labels[k] + '</span>';
            linhas.appendChild(d);
          }
        });
      }
    </script>
  </main>

</body>
</html>
