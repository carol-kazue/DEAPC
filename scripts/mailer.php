<?php
// ══════════════════════════════════════════════════════════════════════════════
//  Configuração SMTP — edite estes valores antes de usar
//
//  Gmail: ative 2FA em myaccount.google.com e gere uma "App Password" de 16
//  caracteres em Segurança → Como inicia sessão → Palavras-passe de apps
// ══════════════════════════════════════════════════════════════════════════════
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'seuemail@gmail.com');       // ← altere
define('SMTP_PASS',      'xxxx xxxx xxxx xxxx');      // ← App Password
define('SMTP_FROM_NAME', 'Casa da Música');

// ── Verificação rápida de configuração ────────────────────────────────────────
function smtpConfigurado(): bool {
    return SMTP_USER !== 'seuemail@gmail.com' && SMTP_PASS !== 'xxxx xxxx xxxx xxxx';
}

// ── Cliente SMTP mínimo (STARTTLS, AUTH LOGIN) ────────────────────────────────
function smtpEnviar(string $para, string $nomeDestinatario, string $assunto, string $htmlCorpo): array
{
    if (!smtpConfigurado()) {
        return ['ok' => false, 'erro' => 'Serviço de email não configurado. Edite scripts/mailer.php.'];
    }

    $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
    if (!$fp) {
        return ['ok' => false, 'erro' => "Não foi possível ligar ao servidor de email ($errstr)."];
    }
    stream_set_timeout($fp, 15);

    // Lê uma resposta multi-linha (continua enquanto o 4.º char for '-')
    $ler = function () use ($fp): string {
        $r = '';
        do {
            $linha = fgets($fp, 512);
            if ($linha === false) break;
            $r .= $linha;
        } while (strlen($linha) >= 4 && $linha[3] === '-');
        return $r;
    };

    // Envia um comando e lê a resposta
    $cmd = function (string $c) use ($fp, $ler): string {
        fwrite($fp, $c . "\r\n");
        return $ler();
    };

    $abort = static function (string $msg) use ($fp): array {
        @fclose($fp);
        return ['ok' => false, 'erro' => $msg];
    };

    $ler(); // banner 220

    // EHLO
    $r = $cmd('EHLO localhost');
    if ((int)$r < 200 || (int)$r >= 300) return $abort("EHLO recusado.");

    // STARTTLS
    $r = $cmd('STARTTLS');
    if ((int)substr($r, 0, 3) !== 220) return $abort("STARTTLS não suportado pelo servidor.");

    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        return $abort("Falha ao estabelecer TLS.");
    }

    $cmd('EHLO localhost'); // EHLO após TLS

    // AUTH LOGIN
    $cmd('AUTH LOGIN');
    $cmd(base64_encode(SMTP_USER));
    $r = $cmd(base64_encode(SMTP_PASS));
    if ((int)substr($r, 0, 3) !== 235) {
        return $abort("Autenticação SMTP falhou. Verifique as credenciais em mailer.php.");
    }

    $cmd('MAIL FROM:<' . SMTP_USER . '>');

    $r = $cmd('RCPT TO:<' . $para . '>');
    if ((int)substr($r, 0, 3) >= 400) return $abort("Destinatário recusado: $para");

    $cmd('DATA');

    // ── Montar mensagem MIME ─────────────────────────────────────────────────
    $nomeTo    = $nomeDestinatario ? '"' . str_replace(['"', '\\'], '', $nomeDestinatario) . '" ' : '';
    $assuntoQ  = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $corpoB64  = chunk_split(base64_encode($htmlCorpo));

    $mensagem  = "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n"
               . "To: {$nomeTo}<{$para}>\r\n"
               . "Subject: {$assuntoQ}\r\n"
               . "MIME-Version: 1.0\r\n"
               . "Content-Type: text/html; charset=UTF-8\r\n"
               . "Content-Transfer-Encoding: base64\r\n"
               . "\r\n"
               . $corpoB64;

    // Linha solitária com ponto → escapar com ponto duplo (RFC 5321)
    $mensagem = preg_replace('/^\.$/m', '..', $mensagem);

    fwrite($fp, $mensagem . "\r\n.\r\n");
    $r = $ler();
    $cmd('QUIT');
    fclose($fp);

    if ((int)substr($r, 0, 3) !== 250) {
        return ['ok' => false, 'erro' => "Envio recusado pelo servidor: " . trim($r)];
    }
    return ['ok' => true];
}

// ── Template HTML do bilhete ──────────────────────────────────────────────────
function templateEmailBilhete(array $c, array $itens): string
{
    $tipos   = ['normal' => 'Normal', 'jovem' => 'Jovem (≤30)', 'senior' => 'Sénior (+65)'];
    $metodos = ['cartao' => 'Cartão de Crédito', 'multibanco' => 'Multibanco',
                'mbway'  => 'MB Way',            'dinheiro'   => 'Dinheiro'];

    $dataEvento = date('d M Y', strtotime($c['data']));
    $hora       = substr($c['hora'], 0, 5);
    $canal      = $c['canal'] === 'presencial' ? 'Presencial (Bilheteira)' : 'Online';
    $metodo     = $metodos[$c['metodo_pagamento']] ?? htmlspecialchars($c['metodo_pagamento']);

    $linhasBilhetes = '';
    foreach ($itens as $it) {
        $sub  = number_format((float)$it['quantidade'] * (float)$it['preco_unitario'], 2, ',', '.');
        $unit = number_format((float)$it['preco_unitario'], 2, ',', '.');
        $tipo = $tipos[$it['tipo']] ?? ucfirst($it['tipo']);
        $linhasBilhetes .= "
        <tr>
          <td style='padding:9px 0;color:#c8c0b4;font-size:14px;border-bottom:1px solid #1e1e30;'>"
            . (int)$it['quantidade'] . '&times; ' . htmlspecialchars($tipo) . "
          </td>
          <td style='padding:9px 0;color:#9e9080;font-size:13px;text-align:right;border-bottom:1px solid #1e1e30;'>€$unit</td>
          <td style='padding:9px 0;color:#f0ece4;font-size:14px;text-align:right;border-bottom:1px solid #1e1e30;'>€$sub</td>
        </tr>";
    }

    $total = number_format((float)$c['total'], 2, ',', '.');
    $ref   = htmlspecialchars($c['referencia']);

    return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Comprovativo — Casa da Música</title>
</head>
<body style="margin:0;padding:24px 16px;background:#0b0b14;font-family:Arial,Helvetica,sans-serif;color:#f0ece4;">
<div style="max-width:520px;margin:0 auto;">

  <!-- Cabeçalho -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#0a0805;border-radius:8px 8px 0 0;border:1px solid #c9a83c;border-bottom:none;">
    <tr>
      <td style="padding:20px 24px;">
        <p style="margin:0;font-size:20px;color:#c9a83c;letter-spacing:.05em;font-family:Georgia,serif;">Casa da Música</p>
        <p style="margin:4px 0 0;font-size:12px;color:#9e9080;">Porto &middot; Comprovativo de Bilhete</p>
      </td>
      <td style="padding:20px 24px;text-align:right;vertical-align:top;">
        <p style="margin:0;font-size:10px;color:#5a5550;text-transform:uppercase;letter-spacing:.06em;">Referência</p>
        <p style="margin:4px 0 0;font-family:monospace;font-size:14px;color:#c9a83c;">#$ref</p>
      </td>
    </tr>
  </table>

  <!-- Confirmação -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#0c1f12;border-left:1px solid #c9a83c;border-right:1px solid #c9a83c;border-bottom:1px solid #1a4028;">
    <tr>
      <td style="padding:14px 24px;">
        <span style="color:#52b37a;font-size:22px;vertical-align:middle;">&#10003;</span>
        <span style="color:#52b37a;font-size:15px;font-weight:bold;margin-left:8px;vertical-align:middle;">Compra Confirmada</span>
      </td>
    </tr>
  </table>

  <!-- Evento -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#12121f;border-left:1px solid #c9a83c;border-right:1px solid #c9a83c;border-bottom:1px solid #252535;">
    <tr>
      <td style="padding:18px 24px;">
        <p style="margin:0;font-size:18px;font-weight:bold;color:#f0ece4;">{$c['evento']}</p>
        <p style="margin:6px 0 0;font-size:13px;color:#9e9080;">$dataEvento &middot; $hora &middot; {$c['sala']}</p>
      </td>
    </tr>
  </table>

  <!-- Bilhetes -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#0f0f20;border-left:1px solid #c9a83c;border-right:1px solid #c9a83c;border-bottom:1px solid #252535;">
    <tr><td style="padding:16px 24px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <thead>
          <tr>
            <th style="text-align:left;font-size:10px;color:#c9a83c;text-transform:uppercase;letter-spacing:.06em;padding-bottom:8px;border-bottom:2px solid #252535;">Bilhete</th>
            <th style="text-align:right;font-size:10px;color:#c9a83c;text-transform:uppercase;letter-spacing:.06em;padding-bottom:8px;border-bottom:2px solid #252535;">Unit.</th>
            <th style="text-align:right;font-size:10px;color:#c9a83c;text-transform:uppercase;letter-spacing:.06em;padding-bottom:8px;border-bottom:2px solid #252535;">Subtotal</th>
          </tr>
        </thead>
        <tbody>$linhasBilhetes</tbody>
        <tfoot>
          <tr>
            <td colspan="2" style="padding-top:10px;font-weight:bold;font-size:15px;color:#c9a83c;">Total</td>
            <td style="padding-top:10px;text-align:right;font-weight:bold;font-size:15px;color:#c9a83c;">€$total</td>
          </tr>
        </tfoot>
      </table>
    </td></tr>
  </table>

  <!-- Dados do cliente -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#12121f;border-left:1px solid #c9a83c;border-right:1px solid #c9a83c;border-bottom:1px solid #252535;">
    <tr>
      <td style="padding:12px 24px;">
        <p style="margin:0;font-size:13px;color:#c8c0b4;">
          <strong>{$c['nome_cliente']}</strong> &middot; {$c['email_cliente']}
        </p>
        <p style="margin:4px 0 0;font-size:12px;color:#5a5550;">$metodo &middot; $canal</p>
      </td>
    </tr>
  </table>

  <!-- Código de barras / referência -->
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
    style="background:#08080f;border:1px solid #c9a83c;border-top:none;border-radius:0 0 8px 8px;">
    <tr>
      <td style="padding:18px 24px;text-align:center;">
        <p style="margin:0;font-family:monospace;font-size:9px;color:#2a2a40;letter-spacing:.18em;line-height:1;">
          &#x2595;&#x2581;&#x2595;&#x2581;&#x2581;&#x2595;&#x2581;&#x2595;&#x2595;&#x2581;&#x2581;&#x2595;&#x2595;&#x2581;&#x2595;&#x2581;&#x2581;&#x2595;&#x2581;&#x2595;&#x2595;&#x2581;&#x2595;&#x2581;&#x2595;&#x2581;&#x2581;&#x2595;
        </p>
        <p style="margin:8px 0 4px;font-family:monospace;font-size:14px;color:#c9a83c;letter-spacing:.1em;">#$ref</p>
        <p style="margin:0;font-size:11px;color:#3a3a55;">Apresente este email na entrada do evento.</p>
      </td>
    </tr>
  </table>

  <p style="text-align:center;font-size:11px;color:#3a3a55;margin-top:16px;">
    Este email foi gerado automaticamente. N&atilde;o responda a esta mensagem.
  </p>

</div>
</body>
</html>
HTML;
}
