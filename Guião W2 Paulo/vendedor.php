<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

requirePerfil('vendedor');

$vendedor = getUtilizador();
$db       = getDB();

// Mensagens de feedback
$erro   = htmlspecialchars($_GET['erro']   ?? '');
$sucesso = htmlspecialchars($_GET['sucesso'] ?? '');
$ref     = htmlspecialchars($_GET['ref']    ?? '');

// Lista de eventos publicados com disponibilidade
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
$resE   = $stmtE->execute();
$eventos = [];
while ($row = $resE->fetchArray(SQLITE3_ASSOC)) {
    $eventos[] = $row;
}

// Preços do evento selecionado (via GET para preencher o formulário)
$evento_sel = null;
$precos_sel = [];
$evento_id_form = (int)($_GET['evento_id'] ?? 0);
if ($evento_id_form > 0) {
    $stmtSel = $db->prepare('SELECT id, nome, data, hora, sala, capacidade FROM eventos WHERE id = :id');
    $stmtSel->bindValue(':id', $evento_id_form, SQLITE3_INTEGER);
    $evento_sel = $stmtSel->execute()->fetchArray(SQLITE3_ASSOC);

    $stmtP = $db->prepare('SELECT tipo, preco FROM precos WHERE evento_id = :id');
    $stmtP->bindValue(':id', $evento_id_form, SQLITE3_INTEGER);
    $resP = $stmtP->execute();
    while ($p = $resP->fetchArray(SQLITE3_ASSOC)) {
        $precos_sel[$p['tipo']] = (float)$p['preco'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vendedor — Casa da Música</title>
  <link rel="stylesheet" href="styles/admin.css" />
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.html" class="nav-brand">Casa da Música</a>
    </div>
    <div class="nav-right">
      <span class="text-sm" style="margin-right:1rem;">Olá, <?= htmlspecialchars($vendedor['nome']) ?></span>
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
      Erro: <?= $erro ?>
    </div>
    <?php endif; ?>

    <!-- Tabela de disponibilidade -->
    <h2 style="font-size:1rem; font-weight:bold; margin-bottom:0.8rem;">Disponibilidade de Eventos</h2>
    <div class="table-wrap mb-3">
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
              <a href="vendedor.php?evento_id=<?= $ev['id'] ?>" class="btn btn-sm">Emitir Bilhete</a>
              <?php else: ?>
              <span class="text-sm">Esgotado</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Formulário de venda presencial -->
    <?php if ($evento_sel): ?>
    <h2 style="font-size:1rem; font-weight:bold; margin-bottom:0.8rem;">
      Emitir Bilhete — <?= htmlspecialchars($evento_sel['nome']) ?>
      (<?= htmlspecialchars(date('d M Y', strtotime($evento_sel['data']))) ?>)
    </h2>

    <div class="form-card" style="max-width:600px;">
      <form action="scripts/vender_bilhete.php" method="POST">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_sel['id'] ?>" />

        <p class="text-bold mb-2">Dados do Cliente</p>
        <div class="form-group">
          <label for="nome_cliente">Nome completo *</label>
          <input type="text" id="nome_cliente" name="nome_cliente" required placeholder="Nome Apelido" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="email_cliente">Email *</label>
            <input type="email" id="email_cliente" name="email_cliente" required placeholder="email@exemplo.com" />
          </div>
          <div class="form-group">
            <label for="telefone_cliente">Telefone</label>
            <input type="text" id="telefone_cliente" name="telefone_cliente" placeholder="9xxxxxxxx" />
          </div>
        </div>
        <div class="form-group">
          <label for="nif_cliente">NIF</label>
          <input type="text" id="nif_cliente" name="nif_cliente" placeholder="000000000" maxlength="9" />
        </div>

        <hr style="margin:1rem 0;" />
        <p class="text-bold mb-2">Bilhetes</p>

        <div class="table-wrap mb-2">
          <table>
            <thead>
              <tr><th>Tipo</th><th>Preço</th><th>Quantidade</th></tr>
            </thead>
            <tbody>
              <?php foreach (['normal' => 'Normal', 'jovem' => 'Jovem (até 30)', 'senior' => 'Sénior (+65)'] as $tipo => $label): ?>
              <?php if (isset($precos_sel[$tipo])): ?>
              <tr>
                <td><?= $label ?></td>
                <td>€<?= number_format($precos_sel[$tipo], 2, ',', '.') ?></td>
                <td>
                  <input type="number" name="qty_<?= $tipo ?>" value="0" min="0" max="20"
                         style="width:70px; padding:4px 8px;" />
                </td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="form-group">
          <label for="metodo_pagamento">Método de pagamento</label>
          <select id="metodo_pagamento" name="metodo_pagamento">
            <option value="dinheiro">Dinheiro</option>
            <option value="multibanco">Multibanco</option>
            <option value="mbway">MB Way</option>
            <option value="cartao">Cartão</option>
          </select>
        </div>

        <div style="margin-top:1.5rem;">
          <button type="submit" class="btn">Confirmar Venda</button>
          <a href="vendedor.php" class="btn btn-pill-light" style="margin-left:1rem;">Cancelar</a>
        </div>
      </form>
    </div>
    <?php endif; ?>

  </main>

</body>
</html>
