<?php
require_once 'scripts/db.php';
require_once 'scripts/sessao.php';

requirePerfil('cliente', 'login.html');

$u  = getUtilizador();
$db = getDB();

// Dados do utilizador
$stmt = $db->prepare('SELECT nome, apelido, email, data_registo FROM utilizadores WHERE id = :id');
$stmt->bindValue(':id', $u['id'], SQLITE3_INTEGER);
$utilizador = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

// Histórico de compras
$stmtC = $db->prepare(
    'SELECT c.referencia, e.nome AS evento, e.data, e.hora,
            c.total, c.estado, c.data_compra,
            GROUP_CONCAT(ic.quantidade || \'x \' || ic.tipo, \', \') AS bilhetes
     FROM compras c
     JOIN eventos e ON e.id = c.evento_id
     JOIN itens_compra ic ON ic.compra_id = c.id
     WHERE c.utilizador_id = :uid
     GROUP BY c.id
     ORDER BY c.data_compra DESC'
);
$stmtC->bindValue(':uid', $u['id'], SQLITE3_INTEGER);
$resC   = $stmtC->execute();
$compras = [];
while ($row = $resC->fetchArray(SQLITE3_ASSOC)) {
    $compras[] = $row;
}

$estadoBadge = ['confirmado' => 'badge-green', 'cancelado' => 'badge-red'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>A Minha Conta — Casa da Música</title>
  <link rel="stylesheet" href="styles/cliente.css" />
</head>
<body>

  <nav>
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <a href="scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page">
    <h1 class="page-title">A Minha Conta</h1>

    <div class="card mb-2" style="max-width:500px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <p class="text-bold" style="font-size:1.1rem;">
            <?= htmlspecialchars($utilizador['nome'] . ' ' . $utilizador['apelido']) ?>
          </p>
          <p class="text-sm mt-1"><?= htmlspecialchars($utilizador['email']) ?></p>
          <p class="text-sm mt-1">
            Conta criada em <?= htmlspecialchars(date('M Y', strtotime($utilizador['data_registo']))) ?>
          </p>
        </div>
      </div>
    </div>

    <hr />

    <div class="sec-header">
      <h2 style="font-size:1.1rem; font-weight:bold;">Histórico de Compras</h2>
    </div>

    <?php if (empty($compras)): ?>
      <p class="text-sm">Ainda não tem compras registadas.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Referência</th>
            <th>Evento</th>
            <th>Data do Evento</th>
            <th>Bilhetes</th>
            <th>Total</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compras as $c): ?>
          <tr>
            <td>#<?= htmlspecialchars($c['referencia']) ?></td>
            <td><?= htmlspecialchars($c['evento']) ?></td>
            <td><?= htmlspecialchars(date('d M Y', strtotime($c['data']))) ?></td>
            <td><?= htmlspecialchars($c['bilhetes']) ?></td>
            <td>€<?= number_format((float)$c['total'], 2, ',', '.') ?></td>
            <td>
              <span class="badge <?= $estadoBadge[$c['estado']] ?? 'badge-gray' ?>">
                <?= ucfirst(htmlspecialchars($c['estado'])) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </main>

</body>
</html>
