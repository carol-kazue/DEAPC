<?php
require_once '../scripts/db.php';
require_once '../scripts/sessao.php';

requirePerfil('administrador', '../login.html');

$erro = htmlspecialchars($_GET['erro'] ?? '');
$msgs_erro = [
    'campos_obrigatorios' => 'Preencha todos os campos obrigatórios.',
    'data_invalida'       => 'Data ou hora inválidas.',
    'capacidade_invalida' => 'Capacidade tem de ser um número positivo.',
];
$admin = getUtilizador();
$salas      = ['Sala Suggia', 'Sala 2', 'Grande Auditório', 'Terraço', 'Outro'];
$categorias = ['Sinfónico', 'Música Clássica', 'Música de Câmara', 'Jazz', 'World Music', 'Música Popular', 'Música Contemporânea', 'Música Coral', 'Ópera', 'Hip-Hop', 'Rock', 'Fado', 'Outro'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Criar Evento — Casa da Música</title>
  <link rel="stylesheet" href="../styles/admin-evento-editar-criar.css" />
</head>
<body>

  <nav class="nav-admin">
    <div class="nav-left">
      <a href="index.php" class="nav-brand">Casa da Música — Admin</a>
      <a href="eventos.php" class="btn-pill">Eventos</a>
    </div>
    <div class="nav-right">
      <span class="text-sm" style="margin-right:1rem;">Olá, <?= htmlspecialchars($admin['nome']) ?></span>
      <a href="../scripts/logout.php" class="btn-pill btn-pill-light">Sair</a>
    </div>
  </nav>

  <main class="page" style="max-width:800px;">
    <p class="text-sm mb-2"><a href="eventos.php">← Eventos</a></p>
    <h1 class="page-title">Criar Evento</h1>

    <?php if ($erro && isset($msgs_erro[$erro])): ?>
    <div class="alert alert-danger mb-2"><?= $msgs_erro[$erro] ?></div>
    <?php endif; ?>

    <form action="../scripts/criar_evento.php" method="POST">
    <div class="form-card">

      <p class="text-bold mb-2">Informações Gerais</p>

      <div class="form-group">
        <label for="nome">Título do evento *</label>
        <input type="text" id="nome" name="nome" required placeholder="Nome do evento" />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="data">Data *</label>
          <input type="date" id="data" name="data" required />
        </div>
        <div class="form-group">
          <label for="hora">Hora *</label>
          <input type="time" id="hora" name="hora" required />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="sala">Local *</label>
          <select id="sala" name="sala" required>
            <?php foreach ($salas as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="categoria">Categoria *</label>
          <select id="categoria" name="categoria" required>
            <?php foreach ($categorias as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="classificacao_etaria">Classificação etária</label>
          <input type="text" id="classificacao_etaria" name="classificacao_etaria" placeholder="ex: Livre, M/6, M/12" />
        </div>
        <div class="form-group">
          <label for="capacidade">Lotação total *</label>
          <input type="number" id="capacidade" name="capacidade" min="1" required placeholder="ex: 1238" />
        </div>
      </div>

      <div class="form-group">
        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="4" placeholder="Descrição do evento..."></textarea>
      </div>

      <hr />

      <p class="text-bold mb-2">Tarifários</p>
      <div class="table-wrap mb-2">
        <table>
          <thead>
            <tr><th>Tipo</th><th>Preço (€)</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Normal</td>
              <td><input type="number" name="preco_normal" min="0" step="0.5" value="0" required /></td>
            </tr>
            <tr>
              <td>Jovem (até 30 anos)</td>
              <td><input type="number" name="preco_jovem" min="0" step="0.5" value="0" required /></td>
            </tr>
            <tr>
              <td>Sénior (+65 anos)</td>
              <td><input type="number" name="preco_senior" min="0" step="0.5" value="0" required /></td>
            </tr>
          </tbody>
        </table>
      </div>

      <hr />

      <div class="form-group">
        <label for="estado">Estado de publicação</label>
        <select id="estado" name="estado">
          <option value="rascunho">Rascunho</option>
          <option value="publicado">Publicado</option>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-success">Criar Evento</button>
        <a href="eventos.php" class="btn btn-pill-light">Cancelar</a>
      </div>
    </div>
    </form>
  </main>

  <script src="../scripts/validacao.js"></script>
  <script>
    document.querySelector('form').addEventListener('submit', function (e) {
      let ok = true;

      const campos = [
        { id: 'nome',       msg: 'O título é obrigatório.' },
        { id: 'data',       msg: 'A data é obrigatória.' },
        { id: 'hora',       msg: 'A hora é obrigatória.' },
      ];
      campos.forEach(function (c) {
        const f = document.getElementById(c.id);
        if (!f.value.trim()) { marcaErro(f, c.msg); ok = false; }
        else limpaErro(f);
      });

      const fCap = document.getElementById('capacidade');
      if (!fCap.value || parseInt(fCap.value) <= 0) {
        marcaErro(fCap, 'A lotação deve ser um número positivo.'); ok = false;
      } else limpaErro(fCap);

      const fData = document.getElementById('data');
      if (fData.value && new Date(fData.value) < new Date(new Date().toDateString())) {
        marcaErro(fData, 'A data do evento não pode ser no passado.'); ok = false;
      }

      ['preco_normal','preco_jovem','preco_senior'].forEach(function (nm) {
        const f = document.querySelector('[name="' + nm + '"]');
        if (f && parseFloat(f.value) < 0) {
          marcaErro(f, 'O preço não pode ser negativo.'); ok = false;
        } else if (f) limpaErro(f);
      });

      if (!ok) e.preventDefault();
    });
  </script>
</body>
</html>
