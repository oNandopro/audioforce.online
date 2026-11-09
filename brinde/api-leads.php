<?php
/**
 * AudioForce · API de Captura de Leads + Disparo Automático do Brinde (15 Famílias / 220 Sons)
 * Salva em leads-audioforce.json e leads-audioforce.csv (protegidos por .htaccess)
 * e envia e-mail HTML com o link da pasta do Google Drive + acesso imediato.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

$email = strtolower(trim($data['email'] ?? ''));
$nome = trim($data['nome'] ?? 'Criador(a)');
$editor = trim($data['editor'] ?? 'Não informado');
$aceita_mkt = !empty($data['aceita_marketing']);
$origem = trim($data['origem'] ?? 'ig-fb-quero');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Digite um endereço de e-mail válido.']);
    exit;
}

$drive_link = 'https://drive.google.com/drive/folders/1pwEYNaf2Y-bENCTm0pifmiWAwucCFZM8?usp=sharing';
$em = gmdate('Y-m-d\TH:i:s\Z');
$ip_hash = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . 'af_salt'), 0, 12);

$lead = [
    'id' => 'lead-' . time() . '-' . substr(md5($email), 0, 6),
    'nome' => $nome ?: 'Criador(a)',
    'email' => $email,
    'editor' => $editor,
    'aceita_marketing' => $aceita_mkt,
    'origem' => $origem,
    'ip_hash' => $ip_hash,
    'em' => $em
];

// 1. Proteger arquivos de dados com .htaccess se ainda não existir
$priv_dir = __DIR__ . '/_dados_leads';
if (!is_dir($priv_dir)) {
    @mkdir($priv_dir, 0755, true);
    @file_put_contents($priv_dir . '/.htaccess', "Deny from all\n");
}

$json_file = $priv_dir . '/leads-audioforce.json';
$csv_file = $priv_dir . '/leads-audioforce.csv';

$lista = [];
if (file_exists($json_file)) {
    $lista = json_decode(file_get_contents($json_file), true) ?: [];
}

// Atualizar se já existir o e-mail, ou adicionar novo
$achou = false;
foreach ($lista as &$item) {
    if (strtolower($item['email'] ?? '') === $email) {
        $item['nome'] = $lead['nome'];
        $item['editor'] = $lead['editor'];
        $item['aceita_marketing'] = $lead['aceita_marketing'];
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
    @file_put_contents($csv_file, "\xEF\xBB\xBFID,Data_UTC,Nome,Email,Editor_Principal,Aceita_Marketing,Origem\n");
}
if (!$achou) {
    $linha_csv = sprintf(
        "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
        $lead['id'],
        $lead['em'],
        str_replace('"', '""', $lead['nome']),
        str_replace('"', '""', $lead['email']),
        str_replace('"', '""', $lead['editor']),
        $lead['aceita_marketing'] ? 'SIM' : 'NAO',
        str_replace('"', '""', $lead['origem'])
    );
    @file_put_contents($csv_file, $linha_csv, FILE_APPEND);
}

// 2. Disparar e-mail automático com o Pack de Brinde
$assunto = "🎁 Seu Cofre Sonoro AudioForce (220 Sons + 6 Packs Bonus) esta liberado!";
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: AudioForce Studio <contato@audioforce.online>\r\n";
$headers .= "Reply-To: contato@audioforce.online\r\n";

$html_email = "
<div style='background:#080711;color:#F8F9FA;font-family:sans-serif;padding:32px 20px;max-width:600px;margin:0 auto;border-radius:16px;border:1px solid #1e1b36'>
  <div style='color:#00F5D4;font-weight:800;font-size:13px;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px'>AUDIOFORCE · SOUND DESIGN PRO</div>
  <h1 style='color:#FFFFFF;font-size:24px;margin:0 0 16px'>Fala, " . htmlspecialchars($lead['nome']) . "! Seu Cofre Sonoro foi destravado 🔓</h1>
  <p style='color:#C8C4DC;font-size:15px;line-height:1.6'>Aqui está o seu acesso vitalício às <strong>15 Famílias de Efeitos Sonoros Autorais (220 sons WAV 48kHz / 24-bit)</strong> + os <strong>6 Packs de Trilhas & Ambiências Bônus</strong> para elevar a retenção dos seus vídeos no <strong>" . htmlspecialchars($lead['editor']) . "</strong>:</p>
  <div style='margin:28px 0;text-align:center'>
    <a href='{$drive_link}' style='background:linear-gradient(135deg,#00F5D4,#00BBF9);color:#06050c;text-decoration:none;padding:16px 28px;border-radius:10px;font-weight:800;font-size:16px;display:inline-block'>📁 ACESSAR PASTA NO GOOGLE DRIVE AGORA →</a>
  </div>
  <p style='color:#948FB8;font-size:13px;line-height:1.6'>💡 <strong>Dica de Ouro:</strong> Salve este e-mail nos seus favoritos para baixar sempre que precisar no PC ou no celular. Quer conhecer nossa Biblioteca Completa com mais de 33.000 efeitos profissionais? Acesse <a href='https://audioforce.online' style='color:#00F5D4'>audioforce.online</a>.</p>
</div>";

$enviou_email = @mail($email, $assunto, $html_email, $headers);

echo json_encode([
    'ok' => true,
    'lead' => $lead,
    'email_enviado' => (bool)$enviou_email,
    'drive_link' => $drive_link,
    'total_leads' => count($lista)
]);
