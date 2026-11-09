<?php
/**
 * AudioForce · API Oficial de Captura de Leads + Disparo SMTP (contato@audioforce.online)
 * - Salva o lead em _dados_leads/leads-audioforce.json e .csv
 * - Envia o e-mail imediato via SMTP SSL (smtp.hostinger.com:465) com o link do 1 Pack escolhido,
 *   passo a passo de como baixar/usar e convite para a Loja Oficial (https://audioforce.online).
 * - Permite que o Painel Mundo OND (módulo Destino) consulte os leads e veja/edite o modelo de e-mail.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Argon-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$priv_dir = __DIR__ . '/_dados_leads';
if (!is_dir($priv_dir)) {
    @mkdir($priv_dir, 0755, true);
}
if (!file_exists($priv_dir . '/.htaccess')) {
    @file_put_contents($priv_dir . '/.htaccess', "Deny from all\n");
}

$json_file = $priv_dir . '/leads-audioforce.json';
$csv_file  = $priv_dir . '/leads-audioforce.csv';
$tpl_file  = $priv_dir . '/email-template.json';

$PACKS_MAP = [
    'whoosh'      => ['num' => '01', 'nome' => 'Pack 01 · Whoosh',          'qtd' => 12, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Whoosh.zip'],
    'riser'       => ['num' => '02', 'nome' => 'Pack 02 · Riser',           'qtd' => 12, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Riser.zip'],
    'impacto'     => ['num' => '03', 'nome' => 'Pack 03 · Impacto',         'qtd' => 12, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Impacto.zip'],
    'glitch'      => ['num' => '04', 'nome' => 'Pack 04 · Glitch',          'qtd' => 12, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Glitch.zip'],
    'clique'      => ['num' => '05', 'nome' => 'Pack 05 · Clique & UI',     'qtd' => 12, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Clique.zip'],
    'sweep'       => ['num' => '06', 'nome' => 'Pack 06 · Sweep',           'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Sweep.zip'],
    'pop'         => ['num' => '07', 'nome' => 'Pack 07 · Pop & Bolhas',    'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Pop.zip'],
    'boom'        => ['num' => '08', 'nome' => 'Pack 08 · Boom 808',        'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Boom.zip'],
    'notificacao' => ['num' => '09', 'nome' => 'Pack 09 · Notificação',     'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Notifica%C3%A7%C3%A3o.zip'],
    'textura'     => ['num' => '10', 'nome' => 'Pack 10 · Textura & Amb.',  'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Textura.zip'],
    'transicao'   => ['num' => '11', 'nome' => 'Pack 11 · Transição',       'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Transi%C3%A7%C3%A3o.zip'],
    'digital'     => ['num' => '12', 'nome' => 'Pack 12 · Digital HUD',     'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Digital.zip'],
    'tensao'      => ['num' => '13', 'nome' => 'Pack 13 · Tensão & Pulso',  'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Tens%C3%A3o.zip'],
    'comico'      => ['num' => '14', 'nome' => 'Pack 14 · Cômico',          'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20C%C3%B4mico.zip'],
    'drop'        => ['num' => '15', 'nome' => 'Pack 15 · Sub Drop',        'qtd' => 16, 'zip' => 'https://audioforce.online/brinde/packs/AF%20Brinde%20-%20Drop.zip'],
];

function get_default_template() {
    return [
        'assunto' => '🎧 Aqui está o seu {pack_nome} Grátis · AudioForce',
        'remetente_nome' => 'AudioForce',
        'saudacao' => 'Fala, {nome}! Tudo pronto para turbinar a sua edição 🔥',
        'intro_texto' => 'Conforme prometido, aqui está o acesso imediato ao seu {pack_nome} ({pack_qtd} efeitos sonoros profissionais em WAV 48kHz / 24-bit), pronto para você usar no {editor}:',
        'texto_botao_pack' => '⬇ BAIXAR MEU {pack_nome} AGORA (.ZIP)',
        'orientacao_titulo' => '📋 Como baixar e usar o seu Pack em 3 passos rápidos:',
        'orientacao_passos' => "1. Clique no botão azul acima para baixar o arquivo .ZIP do seu pack direto para o seu computador ou celular.\n2. Extraia (descompacte) a pasta .ZIP — todos os arquivos estão em formato WAV 24-bit de alta definição, organizados e nomeados.\n3. Arraste os efeitos sonoros direto para a timeline do seu editor ({editor}) e ajuste o volume entre -6dB e -12dB para um acabamento de cinema!",
        'pitch_loja_titulo' => '🔥 Quer ter o Arsenal Completo da AudioForce na sua mão?',
        'pitch_loja_texto' => 'Esse pack gratuito é apenas uma amostra da qualidade do nosso estúdio. Na Loja Oficial AudioForce você encontra nossa Biblioteca Completa com 52 Packs de Efeitos Sonoros + 6 Packs de Trilhas & Loops (+33.000 sons organizados para qualquer nicho) com entrega imediata:',
        'texto_botao_loja' => '⚡ CONHECER A LOJA OFICIAL AUDIOFORCE →',
        'link_loja' => 'https://audioforce.online/#combos',
        'rodape' => '© 2026 AudioForce · Todos os direitos reservados.'
    ];
}

function load_template($tpl_file) {
    $def = get_default_template();
    if (file_exists($tpl_file)) {
        $saved = json_decode(file_get_contents($tpl_file), true);
        if (is_array($saved)) {
            return array_merge($def, $saved);
        }
    }
    @file_put_contents($tpl_file, json_encode($def, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $def;
}

function replace_vars($str, $vars) {
    foreach ($vars as $k => $v) {
        $str = str_replace('{' . $k . '}', $v, $str);
    }
    return $str;
}

function build_email_html($tpl, $vars) {
    $saudacao = htmlspecialchars(replace_vars($tpl['saudacao'], $vars));
    $intro = nl2br(htmlspecialchars(replace_vars($tpl['intro_texto'], $vars)));
    $btn_pack = htmlspecialchars(replace_vars($tpl['texto_botao_pack'], $vars));
    $ori_tit = htmlspecialchars(replace_vars($tpl['orientacao_titulo'], $vars));
    $ori_txt = nl2br(htmlspecialchars(replace_vars($tpl['orientacao_passos'], $vars)));
    $pitch_tit = htmlspecialchars(replace_vars($tpl['pitch_loja_titulo'], $vars));
    $pitch_txt = nl2br(htmlspecialchars(replace_vars($tpl['pitch_loja_texto'], $vars)));
    $btn_loja = htmlspecialchars(replace_vars($tpl['texto_botao_loja'], $vars));
    $rodape = htmlspecialchars(replace_vars($tpl['rodape'], $vars));
    $link_pack = $vars['link_pack'];
    $link_loja = $tpl['link_loja'] ?: 'https://audioforce.online/#combos';

    return "<!DOCTYPE html>
<html lang='pt-BR'>
<body style='margin:0;padding:24px 12px;background:#080711;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#F8F9FA;'>
  <div style='max-width:600px;margin:0 auto;background:#110F20;border:1px solid #252140;border-radius:18px;padding:32px 24px;'>
    <div style='display:inline-block;padding:5px 12px;border-radius:999px;background:rgba(0,245,212,0.12);border:1px solid rgba(0,245,212,0.3);color:#00F5D4;font-weight:800;font-size:12px;letter-spacing:1.5px;margin-bottom:16px;'>AUDIOFORCE</div>
    <h1 style='color:#FFFFFF;font-size:22px;line-height:1.3;margin:0 0 14px;'>{$saudacao}</h1>
    <p style='color:#D0CCE6;font-size:15px;line-height:1.6;margin:0 0 24px;'>{$intro}</p>

    <div style='margin:26px 0;text-align:center;'>
      <a href='{$link_pack}' style='background:linear-gradient(135deg,#00F5D4,#00BBF9);color:#06050C;text-decoration:none;padding:16px 28px;border-radius:12px;font-weight:800;font-size:15px;display:inline-block;'>{$btn_pack}</a>
    </div>

    <div style='background:#17142B;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:18px 20px;margin:24px 0;'>
      <div style='color:#CCFF00;font-weight:800;font-size:14px;margin-bottom:10px;'>{$ori_tit}</div>
      <div style='color:#D0CCE6;font-size:13px;line-height:1.65;'>{$ori_txt}</div>
    </div>

    <div style='background:linear-gradient(135deg,rgba(247,37,133,0.16),rgba(123,44,191,0.22));border:1px solid rgba(247,37,133,0.4);border-radius:16px;padding:22px 20px;margin-top:26px;text-align:center;'>
      <h3 style='color:#FFFFFF;font-size:18px;margin:0 0 8px;'>{$pitch_tit}</h3>
      <p style='color:#E2DFF0;font-size:13px;line-height:1.55;margin:0 0 16px;'>{$pitch_txt}</p>
      <a href='{$link_loja}' style='background:#F72585;color:#FFFFFF;text-decoration:none;padding:13px 24px;border-radius:10px;font-weight:800;font-size:14px;display:inline-block;'>{$btn_loja}</a>
    </div>

    <div style='text-align:center;margin-top:28px;padding-top:18px;border-top:1px solid rgba(255,255,255,0.08);color:#8A84A8;font-size:11px;'>
      {$rodape}
    </div>
  </div>
</body>
</html>";
}

function send_smtp_hostinger($to_email, $subject, $html_body, $from_name = 'AudioForce') {
    $smtp_host = 'ssl://smtp.hostinger.com';
    $smtp_port = 465;
    $smtp_user = 'contato@audioforce.online';
    $smtp_pass = 'AudioForce@2026!';

    $fp = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15);
    if (!$fp) {
        return ['ok' => false, 'err' => "Conn failed: $errstr ($errno)"];
    }

    $read_resp = function() use ($fp) {
        $out = '';
        while ($line = fgets($fp, 515)) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    };

    $send_cmd = function($cmd) use ($fp, $read_resp) {
        fwrite($fp, $cmd . "\r\n");
        return $read_resp();
    };

    $read_resp(); // banner
    $send_cmd("EHLO audioforce.online");
    $send_cmd("AUTH LOGIN");
    $send_cmd(base64_encode($smtp_user));
    $auth_res = $send_cmd(base64_encode($smtp_pass));
    if (strpos($auth_res, '235') === false) {
        fclose($fp);
        return ['ok' => false, 'err' => 'Auth failed: ' . trim($auth_res)];
    }

    $send_cmd("MAIL FROM:<{$smtp_user}>");
    $rcpt_res = $send_cmd("RCPT TO:<{$to_email}>");
    if (strpos($rcpt_res, '250') === false && strpos($rcpt_res, '251') === false) {
        fclose($fp);
        return ['ok' => false, 'err' => 'RCPT failed: ' . trim($rcpt_res)];
    }

    $send_cmd("DATA");

    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encoded_from = '=?UTF-8?B?' . base64_encode($from_name) . "?= <{$smtp_user}>";
    $msg_id = '<' . time() . '.' . substr(md5($to_email . microtime()), 0, 10) . '@audioforce.online>';
    $date_hdr = gmdate('D, d M Y H:i:s +0000');

    $headers  = "Date: {$date_hdr}\r\n";
    $headers .= "From: {$encoded_from}\r\n";
    $headers .= "Reply-To: {$smtp_user}\r\n";
    $headers .= "To: <{$to_email}>\r\n";
    $headers .= "Subject: {$encoded_subject}\r\n";
    $headers .= "Message-ID: {$msg_id}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";

    $body_b64 = chunk_split(base64_encode($html_body));
    $data_res = $send_cmd($headers . "\r\n" . $body_b64 . "\r\n.");
    $send_cmd("QUIT");
    fclose($fp);

    $ok = (strpos($data_res, '250') !== false);
    return ['ok' => $ok, 'resp' => trim($data_res)];
}

$action = $_GET['action'] ?? '';
$key = $_GET['key'] ?? ($_SERVER['HTTP_X_ARGON_KEY'] ?? '');

if ($action === 'get_template') {
    $tpl = load_template($tpl_file);
    echo json_encode(['ok' => true, 'template' => $tpl]);
    exit;
}

if ($action === 'save_template') {
    if ($key !== 'af_argon_2026') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'erro' => 'Chave inválida']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    $cur = load_template($tpl_file);
    foreach ($cur as $k => $v) {
        if (isset($body[$k]) && is_string($body[$k])) {
            $cur[$k] = $body[$k];
        }
    }
    @file_put_contents($tpl_file, json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['ok' => true, 'template' => $cur]);
    exit;
}

if ($action === 'list_leads') {
    if ($key !== 'af_argon_2026') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'erro' => 'Chave inválida']);
        exit;
    }
    $lista = [];
    if (file_exists($json_file)) {
        $lista = json_decode(file_get_contents($json_file), true) ?: [];
    }
    echo json_encode(['ok' => true, 'total' => count($lista), 'leads' => $lista]);
    exit;
}

// Captura de Lead + Disparo de E-mail
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

$email = strtolower(trim($data['email'] ?? ''));
$nome = trim($data['nome'] ?? 'Criador(a)');
$editor = trim($data['editor'] ?? 'CapCut');
$pack_id = strtolower(trim($data['pack_id'] ?? 'whoosh'));
$origem = trim($data['origem'] ?? 'ig-fb-quero');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Digite um endereço de e-mail válido.']);
    exit;
}

$pack_info = $PACKS_MAP[$pack_id] ?? $PACKS_MAP['whoosh'];
$pack_escolhido = trim($data['pack_escolhido'] ?? $pack_info['nome']);
$link_pack = $pack_info['zip'];

$em = gmdate('Y-m-d\TH:i:s\Z');
$ip_hash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . 'af_salt'), 0, 12);

$lead = [
    'id' => 'lead-' . time() . '-' . substr(md5($email), 0, 6),
    'nome' => $nome ?: 'Criador(a)',
    'email' => $email,
    'editor' => $editor,
    'pack_id' => $pack_id,
    'pack_escolhido' => $pack_escolhido,
    'link_pack' => $link_pack,
    'aceita_marketing' => true,
    'origem' => $origem,
    'ip_hash' => $ip_hash,
    'em' => $em
];

$lista = [];
if (file_exists($json_file)) {
    $lista = json_decode(file_get_contents($json_file), true) ?: [];
}

$achou = false;
foreach ($lista as &$item) {
    if (strtolower($item['email'] ?? '') === $email) {
        $item['nome'] = $lead['nome'];
        $item['editor'] = $lead['editor'];
        $item['pack_id'] = $lead['pack_id'];
        $item['pack_escolhido'] = $lead['pack_escolhido'];
        $item['link_pack'] = $lead['link_pack'];
        $item['origem'] = $lead['origem'];
        $item['atualizado_em'] = $em;
        $achou = true;
        break;
    }
}
unset($item);

if (!$achou) {
    $lista[] = $lead;
}

@file_put_contents($json_file, json_encode($lista, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if (!file_exists($csv_file)) {
    @file_put_contents($csv_file, "\xEF\xBB\xBFID,Data_UTC,Nome,Email,Editor_Principal,Pack_Escolhido,Origem\n");
}
if (!$achou) {
    $linha_csv = sprintf(
        "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
        $lead['id'],
        $lead['em'],
        str_replace('"', '""', $lead['nome']),
        str_replace('"', '""', $lead['email']),
        str_replace('"', '""', $lead['editor']),
        str_replace('"', '""', $lead['pack_escolhido']),
        str_replace('"', '""', $lead['origem'])
    );
    @file_put_contents($csv_file, $linha_csv, FILE_APPEND);
}

// Montar e disparar o e-mail pelo SMTP oficial contato@audioforce.online
$tpl = load_template($tpl_file);
$vars = [
    'nome' => $lead['nome'],
    'email' => $lead['email'],
    'editor' => $lead['editor'],
    'pack_nome' => $pack_info['nome'],
    'pack_qtd' => (string)$pack_info['qtd'],
    'link_pack' => $link_pack,
    'link_loja' => $tpl['link_loja'] ?: 'https://audioforce.online/#combos'
];

$assunto = replace_vars($tpl['assunto'], $vars);
$html_email = build_email_html($tpl, $vars);
$smtp_res = send_smtp_hostinger($email, $assunto, $html_email, $tpl['remetente_nome'] ?: 'AudioForce');

echo json_encode([
    'ok' => true,
    'lead' => $lead,
    'email_enviado' => (bool)($smtp_res['ok'] ?? false),
    'smtp_info' => $smtp_res,
    'total_leads' => count($lista)
]);
