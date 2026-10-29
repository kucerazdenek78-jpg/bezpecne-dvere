<?php
// Příjem formulářů z webu: uloží do databáze, uloží přílohy, pošle český e-mail přes SMTP.
// E-maily zákazníkům (typ objednavka_zakaznik – nabídky a objednávky) dostanou podpis: jméno prodavače (pole prodavac), logo a firemní údaje (lib/podpis.php).
require __DIR__ . '/lib/start.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $cfg['povolene_origin'], true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
}
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
function konec($kod, $ok, $zprava = '') { http_response_code($kod); echo json_encode(['success' => $ok ? 'true' : 'false', 'message' => $zprava]); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') konec(405, false, 'method');
if ($origin !== '' && !in_array($origin, $cfg['povolene_origin'], true)) konec(403, false, 'origin');

// honeypot – boti vyplní, lidé ne
if (!empty($_POST['_honey'])) konec(200, true);

$typ = $_POST['typ'] ?? '';
if (!isset($cfg['prijemci'][$typ])) konec(400, false, 'typ');
$p = $cfg['prijemci'][$typ];

function cisti($s, $max) { $s = trim((string)$s); $s = preg_replace('/[\r\n]+/', ' ', $s); return mb_substr($s, 0, $max); }
$predmet = cisti($_POST['predmet'] ?? '', 200);
$telo    = mb_substr((string)($_POST['telo'] ?? ''), 0, 60000);
$replyTo = filter_var(trim($_POST['reply_to'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
$emailZak = filter_var(trim($_POST['email_zakaznik'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '';
$prodavac = cisti($_POST['prodavac'] ?? '', 60);
if ($predmet === '' || $telo === '') konec(400, false, 'prazdne');

$to = $p['to'];
if ($typ === 'objednavka_zakaznik') { if (!$emailZak) konec(400, false, 'email'); $to = [$emailZak]; }

// databáze + omezení počtu odeslání (ochrana proti zneužití)
$pdo = null; $ip = klient_ip();
try {
  $pdo = db();
  {
    $pdo->exec('DELETE FROM pokusy WHERE cas < ' . (time() - 172800));
    // počítají se jen odeslané formuláře – ne ukládání rozpracovaných nabídek (koncept) ani stahování kontaktů (kontakty), ty mají vlastní limity
    $st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip = ? AND cas > ? AND typ NOT IN ('koncept', 'kontakty')");
    $st->execute([$ip, time() - 3600]);
    if ((int)$st->fetchColumn() >= (int)$cfg['limit_za_hodinu']) konec(429, false, 'limit');
    if ($typ === 'objednavka_zakaznik') { // e-mail jde na adresu z formuláře → celkový strop za den
      $st = $pdo->prepare('SELECT COUNT(*) FROM pokusy WHERE typ = ? AND cas > ?');
      $st->execute([$typ, time() - 86400]);
      if ((int)$st->fetchColumn() >= (int)$cfg['limit_zakaznik_za_den']) konec(429, false, 'limit');
    }
    $pdo->prepare('INSERT INTO pokusy (ip, typ, cas) VALUES (?,?,?)')->execute([$ip, $typ, time()]);
  }
} catch (Throwable $e) { error_log('DB: ' . $e->getMessage()); $pdo = null; /* e-mail je důležitější, pokračujeme */ }

// přílohy ($_FILES)
$prilohy = [];
$max = (int)$cfg['max_priloha_mb'] * 1048576; $celkem = 0;
foreach ($_FILES as $f) {
  if (is_array($f['name']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) continue;
  $celkem += $f['size']; if ($celkem > $max) konec(413, false, 'velke');
  $nazev = preg_replace('/[^A-Za-z0-9._-]/', '_', basename(str_replace(['\\','/'], '-', $f['name']))); if ($nazev === '') $nazev = 'priloha';
  $prilohy[] = ['nazev' => $nazev, 'cesta' => $f['tmp_name']];
}

// uložení do DB a příloh na disk (mimo veřejný web)
$id = null;
if ($pdo && !empty($p['uloz'])) {
  try {
    $st = $pdo->prepare('INSERT INTO zadosti (vytvoreno,typ,cislo,predmet,telo,email,data_json,prilohy,ip) VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([date('Y-m-d H:i:s'), $typ, cisti($_POST['cislo'] ?? '', 40), $predmet, $telo, $replyTo ?: $emailZak,
      mb_substr((string)($_POST['data_json'] ?? ''), 0, 500000), '', $ip]);
    $id = (int)$pdo->lastInsertId();
    $soubory = [];
    foreach ($prilohy as $a) {
      $jm = $id . '_' . bin2hex(random_bytes(4)) . '_' . $a['nazev'];
      if (copy($a['cesta'], $DATA . '/ulozeno/' . $jm)) $soubory[] = $jm;
    }
    $pdo->prepare('UPDATE zadosti SET prilohy=? WHERE id=?')->execute([implode("\n", $soubory), $id]);
  } catch (Throwable $e) { error_log('DB insert: ' . $e->getMessage()); $id = null; }
}

// e-mail (SMTP, česky UTF-8)
$odeslano = false;
try {
  // podpis jen do e-mailů zákazníkům (interní e-maily firmě bez podpisu)
  $sPodpisem = false;
  if ($typ === 'objednavka_zakaznik' && is_file(__DIR__ . '/lib/podpis.php')) { require_once __DIR__ . '/lib/podpis.php'; $sPodpisem = true; }
  $teloMail = $sPodpisem ? podpis_ocisti($telo) : $telo;
  $telo_html = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1">$1</a>', htmlspecialchars($teloMail, ENT_QUOTES, 'UTF-8'));
  $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;white-space:pre-wrap">' . $telo_html . '</div>';
  if ($sPodpisem) { $html .= podpis_html($prodavac, (string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz')); $teloMail .= podpis_text($prodavac); }
  $reply = ($typ === 'objednavka_zakaznik') ? $cfg['from'] : ($replyTo ?: $cfg['from']);

  if (($cfg['mail_rezim'] ?? 'smtp') === 'soubor') { // zkušební režim: nic se neposílá, zapíše se do /data/odeslano-test
    @mkdir($DATA . '/odeslano-test', 0775, true);
    file_put_contents($DATA . '/odeslano-test/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json',
      json_encode(['to' => $to, 'cc' => $p['cc'], 'reply_to' => $reply, 'predmet' => $predmet, 'telo' => $teloMail,
        'prilohy' => array_column($prilohy, 'nazev')], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $odeslano = true;
  } else {
    require '/var/www/vendor/autoload.php';
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    $m->CharSet = 'UTF-8'; $m->isSMTP();
    $m->Host = $cfg['smtp_host']; $m->Port = (int)$cfg['smtp_port']; $m->Timeout = 25;
    $m->SMTPAuth = ($cfg['smtp_user'] ?? '') !== '';
    $m->Username = $cfg['smtp_user'] ?? ''; $m->Password = $cfg['smtp_pass'] ?? '';
    $m->SMTPSecure = ($cfg['smtp_secure'] ?? 'tls') === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $m->setFrom($cfg['from'], 'Bezpečné dveře');
    foreach ($to as $a) $m->addAddress($a);
    foreach ($p['cc'] as $a) $m->addCC($a);
    $m->addReplyTo($reply);
    $m->isHTML(true); $m->Subject = $predmet; $m->Body = $html; $m->AltBody = $teloMail;
    foreach ($prilohy as $a) $m->addAttachment($a['cesta'], $a['nazev']);
    $m->send();
    $odeslano = true;
  }
} catch (Throwable $e) { error_log('E-mail selhal (' . $predmet . '): ' . $e->getMessage()); }

// automatické poděkování zákazníkovi za poptávku (jen typ poptavka a jen s platným e-mailem; chyba tady nikdy neblokuje přijetí poptávky)
if ($typ === 'poptavka' && $replyTo && ($odeslano || $id)) {
  try {
    $povol = true;
    if ($pdo) { // pojistka proti zneužití formuláře k rozesílání e-mailů: denní strop
      $st = $pdo->prepare('SELECT COUNT(*) FROM pokusy WHERE typ = ? AND cas > ?');
      $st->execute([$typ, time() - 86400]);
      $povol = (int)$st->fetchColumn() <= (int)($cfg['limit_zakaznik_za_den'] ?? 200);
    }
    if ($povol) {
      require_once __DIR__ . '/lib/posta.php';
      $cis = cisti($_POST['cislo'] ?? '', 40);
      $txt = "Dobrý den,\n\nděkujeme za Vaši poptávku" . ($cis !== '' ? " (č. $cis)" : '') . ". Dorazila k nám v pořádku a budeme se jí věnovat nejpozději do dvou pracovních dnů. Ozveme se Vám na telefon nebo e-mail, který jste uvedli.\n\n"
        . "Kdybyste chtěli cokoli doplnit, stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235.\n\n"
        . "Bezpečné dveře · KK SYSTEM\nObchodní 36, Most\nwww.bezpecne-dvere.cz";
      posli_mail($cfg, $DATA, [$replyTo], [], $cfg['from'], 'Děkujeme za poptávku' . ($cis !== '' ? " č. $cis" : ''), $txt);
    }
  } catch (Throwable $e) { error_log('Poděkování za poptávku: ' . $e->getMessage()); }
}

if ($odeslano) konec(200, true);
if ($id && $pdo) { // uloženo, jen e-mail nešel → žádost je v přehledu, nepřijdeme o ni
  try { $pdo->prepare("UPDATE zadosti SET stav='chyba_mailu' WHERE id=?")->execute([$id]); } catch (Throwable $e) {}
  konec(200, true, 'ulozeno_bez_mailu');
}
konec(500, false, 'mail'); // nic se neuložilo ani neodeslalo → klient použije záložní odeslání
