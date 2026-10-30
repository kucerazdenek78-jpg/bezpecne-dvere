<?php
// Zákazníci: seznam a karty zákazníků sestavené automaticky z poptávek a objednávek v kartotéce
// (stejný zákazník = stejný telefon, jinak stejný e-mail, jinak stejné jméno a město). Ke každému zákazníkovi jdou
// přidat štítky, poznámka a datum dalšího kontaktu. Stejná ochrana jako kartoteka.php.
// 29. 10. 2026: úprava kontaktů (přepisuje jen zobrazení, záznamy v kartotéce zůstávají), smazání (skrytí ze seznamu – vrátí se,
// když přijde nový záznam), ruční zákazník, export do telefonu (vCard .vcf) a import kontaktů z telefonu (.vcf, s výběrem).
// 30. 10. 2026: karta zákazníka jako rozcestník (odkaz sem vede z Google Kontaktů v telefonu): rychlé akce (zavolat, nová nabídka
// s vyplněným zákazníkem, servis), rozpracované nabídky zákazníka, servis u každé objednávky a rychlý zápis do poznámky s datem a jménem.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$pdo = db();
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '–' : number_format((float)$n, 0, ',', ' ') . ' Kč';
require_once __DIR__ . '/lib/zakaznici.php'; zk_tabulka($pdo); $ZK_POLE = zk_pole();   // společné funkce (i pro synchronizaci s Google Kontakty)

$TYPY = ['poptavka' => 'Poptávka', 'objednavka_interni' => 'Objednávka', 'objednavka_zakaznik' => 'Objednávka (zákazník)', 'nabidka' => 'Nabídka'];
$STAVY = ['nove' => 'Nové', 'rozpracovano' => 'Rozpracováno', 'objednano' => 'Objednáno u výrobce', 'termin' => 'Domluven termín',
  'namontovano' => 'Namontováno', 'vyrizeno' => 'Vyřízeno', 'storno' => 'Storno', 'chyba_mailu' => 'E-mail se neodeslal'];

function zk_vcard(array $c): string {   // vCard 3.0 (Android i iPhone ji umí importovat)
  $x = fn($s) => str_replace(["\\", "\n", ',', ';'], ["\\\\", '\\n', '\\,', '\\;'], (string)$s);
  $cs = preg_split('/\s+/u', trim($c['jmeno'])); $prij = count($cs) > 1 ? array_pop($cs) : ''; $jm = implode(' ', $cs);
  $v = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:" . $x($c['jmeno'] ?: ($c['firma'] ?: $c['telefon'])) . "\r\nN:" . $x($prij) . ';' . $x($jm) . ";;;\r\n";
  if ($c['firma'] !== '') $v .= 'ORG:' . $x($c['firma']) . "\r\n";
  if ($c['telefon'] !== '') $v .= 'TEL;TYPE=CELL:' . $x($c['telefon']) . "\r\n";
  if ($c['email'] !== '') $v .= 'EMAIL;TYPE=INTERNET:' . $x($c['email']) . "\r\n";
  if ($c['ulice'] . $c['mesto'] . $c['psc'] !== '') $v .= 'ADR;TYPE=HOME:;;' . $x($c['ulice']) . ';' . $x($c['mesto']) . ';;' . $x($c['psc']) . ";CZ\r\n";
  $cisla = array_values(array_filter(array_column($c['zaznamy'], 'cislo')));
  $v .= 'NOTE:' . $x('Zákazník Bezpečné dveře' . ($cisla ? ' – ' . implode(', ', array_slice(array_reverse($cisla), 0, 8)) : '')) . "\r\n";
  $v .= "CATEGORIES:Zákazníci Bezpečné dveře\r\nEND:VCARD\r\n";
  return $v;
}

// jednoduchý čtenář vCard (2.1 / 3.0 / 4.0): FN, N, ORG, TEL, EMAIL, ADR; quoted-printable a rozdělené řádky
function zk_vcf_cti(string $t): array {
  $t = preg_replace("/\r\n|\r/", "\n", $t);
  $t = preg_replace("/=\n/", '', $t);              // měkké zalomení quoted-printable
  $t = preg_replace("/\n[ \t]/", '', $t);          // pokračování řádku
  $out = [];
  foreach (preg_split('/BEGIN:VCARD/i', $t) as $blk) {
    if (stripos($blk, 'END:VCARD') === false) continue;
    $c = ['jmeno' => '', 'firma' => '', 'telefon' => '', 'email' => '', 'ulice' => '', 'mesto' => '', 'psc' => '', 'n' => ''];
    foreach (explode("\n", $blk) as $l) {
      if (!preg_match('/^(?:[\w-]+\.)?([A-Z-]+)((?:;[^:]*)?):(.*)$/i', trim($l), $m)) continue;
      $k = strtoupper($m[1]); $par = strtoupper($m[2]); $val = $m[3];
      if (strpos($par, 'QUOTED-PRINTABLE') !== false) $val = quoted_printable_decode($val);
      if (preg_match('/CHARSET=([\w-]+)/', $par, $ch) && strtoupper($ch[1]) !== 'UTF-8') $val = @mb_convert_encoding($val, 'UTF-8', $ch[1]) ?: $val;
      $un = fn($s) => trim(str_replace(['\\n', '\\,', '\\;', '\\\\'], [' ', ',', ';', '\\'], $s));
      if ($k === 'FN' && $c['jmeno'] === '') $c['jmeno'] = $un($val);
      elseif ($k === 'N') { $p = array_map($un, explode(';', $val)); $c['n'] = trim(($p[1] ?? '') . ' ' . ($p[2] ?? '') . ' ' . ($p[0] ?? '')); }
      elseif ($k === 'ORG' && $c['firma'] === '') $c['firma'] = $un(str_replace(';', ' ', $val));
      elseif ($k === 'TEL' && ($c['telefon'] === '' || strpos($par, 'CELL') !== false)) $c['telefon'] = $un($val);
      elseif ($k === 'EMAIL' && $c['email'] === '') $c['email'] = $un($val);
      elseif ($k === 'ADR' && $c['ulice'] . $c['mesto'] === '') { $p = array_map($un, explode(';', $val)); $c['ulice'] = $p[2] ?? ''; $c['mesto'] = $p[3] ?? ''; $c['psc'] = $p[5] ?? ''; }
    }
    if ($c['jmeno'] === '') $c['jmeno'] = preg_replace('/\s+/', ' ', $c['n']);
    unset($c['n']);
    foreach ($c as $f => $v) $c[$f] = mb_substr((string)$v, 0, 150);
    if ($c['jmeno'] . $c['telefon'] . $c['email'] !== '') $out[] = $c;
  }
  return $out;
}
function zk_klic_kontakt(array $c): string {   // stejné pravidlo jako zk_klic (telefon → e-mail → jméno+obec)
  $cs = preg_split('/\s+/u', trim($c['jmeno'])); $prij = count($cs) > 1 ? array_pop($cs) : '';
  return zk_klic(['telefon' => $c['telefon'], 'email' => $c['email'], 'jmeno' => implode(' ', $cs), 'prijmeni' => $prij, 'mesto' => $c['mesto']], []);
}
function zk_google_nast(): array { global $DATA; $f = $DATA . '/kontakty-sync.json'; return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
$zk_uloz_pole = function (string $k, array $pole, array $navic = []) use ($pdo) {   // zapíše vybraná pole do zakaznik_info (založí řádek, když chybí)
  $pdo->prepare('INSERT OR IGNORE INTO zakaznik_info (klic) VALUES (?)')->execute([$k]);
  $pole = array_merge($pole, $navic, ['upraveno' => date('Y-m-d H:i')]);
  $set = implode(', ', array_map(fn($f) => $f . '=?', array_keys($pole)));
  $pdo->prepare("UPDATE zakaznik_info SET $set WHERE klic=?")->execute(array_merge(array_values($pole), [$k]));
};

// ── akce ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $akce = (string)($_POST['akce'] ?? ''); $k = (string)($_POST['klic'] ?? ''); $info = zk_info($pdo);
  $vse = zk_vsichni($pdo, $info, true);
  if ($akce === 'zk_uloz' && isset($vse[$k])) {   // štítky / poznámka / další kontakt
    $stitky = implode(', ', array_slice(array_values(array_unique(array_filter(array_map(fn($s) => mb_substr(trim($s), 0, 30), explode(',', (string)($_POST['stitky'] ?? '')))))), 0, 12));
    $dk = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['dalsi_kontakt'] ?? '')) ? $_POST['dalsi_kontakt'] : '';
    $zk_uloz_pole($k, ['stitky' => $stitky, 'poznamka' => mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 8000), 'dalsi_kontakt' => $dk]);
    header('Location: zakaznici.php?k=' . urlencode($k) . '&ulozeno=1'); exit;
  }
  if ($akce === 'zk_zapis' && isset($vse[$k])) {   // rychlý zápis (telefonát, servis…) – přidá se navrch poznámky s datem a jménem
    $t = trim((string)($_POST['zapis'] ?? ''));
    if ($t !== '') {
      $kdo = function_exists('uziv_jmeno') ? trim((string)uziv_jmeno()) : '';
      $druh = in_array($_POST['druh'] ?? '', ['Telefonát', 'Servis', 'Schůzka', 'Poznámka'], true) ? $_POST['druh'] : 'Poznámka';
      $stara = (string)(zk_info($pdo)[$k]['poznamka'] ?? '');
      $nova = date('j. n. Y H:i') . ($kdo !== '' ? ' ' . $kdo : '') . ' – ' . $druh . ': ' . mb_substr(preg_replace('/\s+/u', ' ', $t), 0, 1000);
      $zk_uloz_pole($k, ['poznamka' => mb_substr($nova . ($stara !== '' ? "\n" . $stara : ''), 0, 8000)]);
    }
    header('Location: zakaznici.php?k=' . urlencode($k) . '&ulozeno=1#zapis'); exit;
  }
  if ($akce === 'zk_kontakt' && isset($vse[$k])) {   // úprava kontaktních údajů (ukládá se jen to, co se liší od záznamů)
    $zaklad = zk_vsichni($pdo, array_map(fn($i) => array_diff_key($i, $ZK_POLE), $info), true)[$k] ?? [];
    $pole = [];
    foreach (array_keys($ZK_POLE) as $f) { $v = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST[$f] ?? ''))), 0, 150); $pole[$f] = ($v === (string)($zaklad[$f] ?? '')) ? null : $v; }
    $zk_uloz_pole($k, $pole);
    header('Location: zakaznici.php?k=' . urlencode($k) . '&ulozeno=1'); exit;
  }
  if ($akce === 'zk_smazat' && isset($vse[$k])) {
    if (!$vse[$k]['zaznamy']) $pdo->prepare('DELETE FROM zakaznik_info WHERE klic=?')->execute([$k]);   // ruční zákazník bez záznamů → pryč úplně
    else $zk_uloz_pole($k, ['smazano' => date('Y-m-d H:i:s')]);
    header('Location: zakaznici.php?smazano=1'); exit;
  }
  if ($akce === 'zk_obnovit' && isset($vse[$k])) { $zk_uloz_pole($k, ['smazano' => null]); header('Location: zakaznici.php?k=' . urlencode($k)); exit; }
  if ($akce === 'zk_novy') {
    $c = []; foreach (array_keys($ZK_POLE) as $f) $c[$f] = mb_substr(trim((string)($_POST[$f] ?? '')), 0, 150);
    $k2 = zk_klic_kontakt($c);
    if ($k2 === '') { header('Location: zakaznici.php?novy=1&chyba=1'); exit; }
    $zk_uloz_pole($k2, array_filter($c, fn($v) => $v !== ''), isset($vse[$k2]) ? ['smazano' => null] : ['rucne' => '1', 'zdroj' => 'ručně', 'smazano' => null]);
    header('Location: zakaznici.php?k=' . urlencode($k2) . '&ulozeno=1'); exit;
  }
  if ($akce === 'vcf_nahled' && is_uploaded_file($_FILES['vcf']['tmp_name'] ?? '')) {   // import z telefonu – 1. krok: přečíst a ukázat
    $txt = (string)file_get_contents($_FILES['vcf']['tmp_name'], false, null, 0, 8 * 1048576);
    $GLOBALS['IMPORT'] = array_slice(zk_vcf_cti($txt), 0, 3000);
  }
  if ($akce === 'google_klic') {   // Google Kontakty: založit / změnit tajný klíč (starý přestane platit)
    $nast = zk_google_nast(); $nast['token'] = bin2hex(random_bytes(20)); $nast['zalozeno'] = date('Y-m-d H:i'); unset($nast['stazeno'], $nast['pocet']);
    @file_put_contents($DATA . '/kontakty-sync.json', json_encode($nast, JSON_UNESCAPED_UNICODE), LOCK_EX);
    header('Location: zakaznici.php?google=1&novy_klic=1'); exit;
  }
  if ($akce === 'vcf_import') {   // 2. krok: vybrané kontakty uložit
    $data = json_decode((string)base64_decode((string)($_POST['data'] ?? ''), true), true) ?: [];
    $vyb = array_map('intval', (array)($_POST['vyber'] ?? [])); $nove = $dopl = 0;
    foreach ($vyb as $ix) {
      $c = $data[$ix] ?? null; if (!is_array($c)) continue;
      $c = array_intersect_key(array_map(fn($v) => mb_substr(trim((string)$v), 0, 150), $c), $ZK_POLE);
      $k2 = zk_klic_kontakt($c + array_fill_keys(array_keys($ZK_POLE), '')); if ($k2 === '') continue;
      if (isset($vse[$k2])) {   // existující zákazník: doplnit jen prázdné údaje, nic nepřepisovat
        $pole = []; foreach ($c as $f => $v) if ($v !== '' && trim((string)$vse[$k2][$f]) === '') $pole[$f] = $v;
        if ($pole) { $zk_uloz_pole($k2, $pole); $dopl++; }
      } else { $zk_uloz_pole($k2, array_filter($c, fn($v) => $v !== ''), ['rucne' => '1', 'zdroj' => 'telefon', 'smazano' => null]); $nove++; $vse[$k2] = ['zaznamy' => []] + $c; }
    }
    header('Location: zakaznici.php?import=' . $nove . '-' . $dopl); exit;
  }
}

$info = zk_info($pdo);
// ── export do telefonu (vCard) ──
if (isset($_GET['vcf'])) {
  $vse = zk_vsichni($pdo, $info);
  $kl = (string)$_GET['vcf'];
  $sel = $kl === 'vse' ? $vse : (isset($vse[$kl]) ? [$vse[$kl]] : []);
  if (!$sel) { http_response_code(404); exit('Nenalezeno'); }
  $jm = $kl === 'vse' ? 'zakaznici-bezpecne-dvere-' . date('Y-m-d') : preg_replace('/[^\w-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', reset($sel)['jmeno'] ?: 'zakaznik'));
  header('Content-Type: text/vcard; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $jm . '.vcf"');
  foreach ($sel as $c) echo zk_vcard($c);
  exit;
}

$vse = zk_vsichni($pdo, $info, isset($_GET['k']));
$detail = isset($_GET['k']) ? ($vse[(string)$_GET['k']] ?? null) : null;
$datum = fn($s) => $s ? date('j. n. Y', strtotime((string)$s)) : '–';
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><link rel="apple-touch-icon" href="kartoteka-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Kartotéka"><title>Zákazníci – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
main{max-width:1100px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e4e8ee;text-align:left;vertical-align:top}th{font-size:13px;color:#5a6676}
.tw{overflow-x:auto}
.btn,button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:9px 14px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}
.btn.sec,button.sec{background:#e6ebf3;color:#1b2430}.btn.del,button.del{background:#fff;color:#a11;border:1px solid #e3b7b7}.mut{color:#6b7787;font-size:13px}
.chip{border-radius:999px;padding:2px 9px;font-size:12px;background:#e6ebf3;display:inline-block;margin:1px 2px 1px 0}.chip.ok{background:#d9f2e0;color:#14653a}.chip.warn{background:#fff0cc;color:#7a5200}.chip.tel{background:#e8e2fb;color:#4c2d9b}
input,textarea,select{font:inherit;padding:7px 9px;border:1px solid #c9d1dc;border-radius:8px;box-sizing:border-box;max-width:100%}
textarea{width:100%}.ok{background:#d9f2e0;border-radius:8px;padding:8px 12px;margin-bottom:10px}.err{background:#fde2e2;color:#9b1c1c;border-radius:8px;padding:8px 12px;margin-bottom:10px}
h1{font-size:22px;margin:4px 0 12px}h3{margin:0 0 8px}
a{color:#1f5fbf}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.fg{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.fg label{display:flex;flex-direction:column;gap:3px;font-size:13px;color:#5a6676}.fg input{width:100%}
details>summary{cursor:pointer;font-weight:600}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('zakaznici'); ?><main>
<?php if (isset($_GET['google'])): $gn = zk_google_nast();
  $feed = rtrim((string)($cfg['formulare_url'] ?? 'https://formulare.dvere.app'), '/') . '/kontakty-sync.php?t=' . ($gn['token'] ?? '');
  $skript = str_replace('__FEED__', $feed, (string)@file_get_contents(__DIR__ . '/lib/kontakty-skript.gs')); ?>
  <p><a href="zakaznici.php">← Zákazníci</a></p>
  <h1>🔄 Zákazníci do Google Kontaktů</h1>
  <?php if (isset($_GET['novy_klic'])): ?><div class="ok">Nový klíč je založený<?= !empty($gn['stazeno']) ? '' : ' – teď vložte skript níže do Google (starý skript s předchozím klíčem už nefunguje)' ?>.</div><?php endif; ?>
  <div class="box"><h3>Stav</h3>
    <?php if (empty($gn['token'])): ?><p>Synchronizace zatím není zapnutá.</p>
      <form method="post"><input type="hidden" name="akce" value="google_klic"><button>Zapnout – vytvořit tajný klíč</button></form>
    <?php else: ?><p><?= !empty($gn['stazeno']) ? '✅ Google si zákazníky naposledy stáhl <b>' . $e(date('j. n. Y H:i', strtotime($gn['stazeno']))) . '</b> (' . (int)($gn['pocet'] ?? 0) . ' zákazníků s telefonem nebo e-mailem).' : '⏳ Google si zákazníky zatím nestáhl – vložte a spusťte skript podle návodu.' ?></p>
      <form method="post" onsubmit="return confirm('Vytvořit nový klíč? Starý skript v Google přestane fungovat a bude potřeba vložit nový.')"><input type="hidden" name="akce" value="google_klic"><button class="sec">Vytvořit nový klíč (když unikl / pro jiný účet)</button> <span class="mut">klíč založen <?= $e($gn['zalozeno'] ?? '') ?></span></form><?php endif; ?></div>
  <?php if (!empty($gn['token'])): ?>
  <div class="box"><h3>Návod (jednou, asi 5 minut, ideálně na počítači)</h3><ol>
    <li>Přihlaste se do Google účtu, který máte v telefonu, a otevřete <a href="https://script.google.com/home/projects/create" target="_blank" rel="noopener">script.google.com → Nový projekt</a>. Projekt pojmenujte např. „Bezpečné dveře – kontakty“.</li>
    <li>Smažte, co je v editoru, a vložte celý skript níže (tlačítko <b>Zkopírovat skript</b>).</li>
    <li>Vlevo u <b>Služby</b> klikněte na <b>+</b>, vyberte <b>People API</b> a dejte <b>Přidat</b>.</li>
    <li>Uložte (ikona diskety), nahoře vyberte funkci <b>nastavit</b> a klikněte <b>Spustit</b>.</li>
    <li>Google se zeptá na oprávnění: <b>Zkontrolovat oprávnění</b> → vyberte účet → „Google tuto aplikaci neověřil“ → <b>Rozšířené</b> → <b>Přejít na … (nebezpečné)</b> → <b>Povolit</b>. (Je to váš vlastní skript, proto ho Google neověřoval.)</li>
    <li>Hotovo – skript se teď spouští sám každých 30 minut. Zákazníci se objeví v Kontaktech ve skupině „Zákazníci Bezpečné dveře“ a do telefonu se dostanou běžnou synchronizací Google účtu.</li></ol>
    <p class="mut">Zákazníka, kterého už v telefonu máte se stejným číslem, skript nepřepíše – jen ho zařadí do skupiny. Nic nemaže. Adresa ve skriptu obsahuje tajný klíč – nikomu ji neposílejte; při podezření dejte „Vytvořit nový klíč“.</p></div>
  <div class="box"><h3>Skript</h3><p><button type="button" id="gsKop">📋 Zkopírovat skript</button> <span class="mut" id="gsInfo"></span></p>
    <textarea id="gsKod" rows="16" readonly style="font:12px/1.4 ui-monospace,Menlo,Consolas,monospace;white-space:pre"><?= $e($skript) ?></textarea></div>
  <script>document.getElementById('gsKop').addEventListener('click', function () { var t = document.getElementById('gsKod'), i = document.getElementById('gsInfo');
    function ok() { i.textContent = '✓ zkopírováno – vložte do editoru Google Apps Script'; }
    if (navigator.clipboard) navigator.clipboard.writeText(t.value).then(ok, function () { t.select(); document.execCommand('copy'); ok(); }); else { t.select(); document.execCommand('copy'); ok(); } });</script>
  <?php endif; ?>
<?php elseif (!empty($GLOBALS['IMPORT']) || (($_POST['akce'] ?? '') === 'vcf_nahled')): $imp = $GLOBALS['IMPORT'] ?? [];
  $vseI = zk_vsichni($pdo, $info, true); ?>
  <p><a href="zakaznici.php">← Zákazníci</a></p>
  <h1>Import kontaktů z telefonu</h1>
  <?php if (!$imp): ?><div class="err">V souboru jsem nenašel žádné kontakty (čekám soubor .vcf z telefonu).</div><?php else: ?>
  <div class="box"><p>Nalezeno <b><?= count($imp) ?></b> kontaktů. Zaškrtněte, které jsou zákazníci. <b>Předvybraní</b> jsou ti, kteří už v kartotéce jsou (doplní se jim jen chybějící údaje – nic se nepřepíše). Ostatní se přidají jako noví zákazníci se štítkem „z telefonu“.</p>
    <form method="post"><input type="hidden" name="akce" value="vcf_import"><input type="hidden" name="data" value="<?= $e(base64_encode(json_encode($imp, JSON_UNESCAPED_UNICODE))) ?>">
    <p class="row"><input type="search" id="impHledat" placeholder="hledat v seznamu…" size="30"> <label><input type="checkbox" id="impVse"> vybrat vše zobrazené</label> <button>Importovat vybrané</button></p>
    <div class="tw"><table id="impTab"><tr><th></th><th>Jméno</th><th>Telefon</th><th>E-mail</th><th>Adresa</th><th>V kartotéce</th></tr>
    <?php foreach ($imp as $ix => $c): $k2 = zk_klic_kontakt($c); $je = $k2 !== '' && isset($vseI[$k2]); ?><tr>
      <td><input type="checkbox" name="vyber[]" value="<?= (int)$ix ?>"<?= $je ? ' checked' : '' ?>></td><td><b><?= $e($c['jmeno']) ?></b><?= $c['firma'] ? '<div class="mut">' . $e($c['firma']) . '</div>' : '' ?></td>
      <td><?= $e($c['telefon']) ?></td><td><?= $e($c['email']) ?></td><td class="mut"><?= $e(trim(implode(', ', array_filter([$c['ulice'], trim($c['psc'] . ' ' . $c['mesto'])])))) ?></td>
      <td><?= $je ? '<span class="chip ok">ano – ' . $e($vseI[$k2]['jmeno']) . '</span>' : '<span class="mut">ne</span>' ?></td></tr><?php endforeach; ?></table></div>
    <p><button>Importovat vybrané</button></p></form></div>
  <script>(function () {
    var h = document.getElementById('impHledat'), rows = [].slice.call(document.querySelectorAll('#impTab tr')).slice(1);
    h.addEventListener('input', function () { var q = h.value.toLowerCase(); rows.forEach(function (r) { r.style.display = !q || r.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none'; }); });
    document.getElementById('impVse').addEventListener('change', function () { var on = this.checked; rows.forEach(function (r) { if (r.style.display !== 'none') r.querySelector('input').checked = on; }); });
  })();</script>
  <?php endif; ?>
<?php elseif ($detail): $k = $detail['klic']; $i = $info[$k] ?? []; $zz = array_reverse($detail['zaznamy']); ?>
  <p><a href="zakaznici.php">← Všichni zákazníci</a></p>
  <h1><?= $e($detail['jmeno'] ?: '(bez jména)') ?><?= $detail['rucne'] ? ' <span class="chip tel">' . $e($detail['zdroj'] === 'telefon' ? 'z telefonu' : 'přidán ručně') . '</span>' : '' ?></h1>
  <?php if (isset($_GET['ulozeno'])): ?><div class="ok">Uloženo.</div><?php endif; ?>
  <?php
    $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
    $csj = preg_split('/\s+/u', trim($detail['jmeno'])); $prj = count($csj) > 1 ? array_pop($csj) : '';
    $pre = array_filter(['jmeno' => implode(' ', $csj), 'prijmeni' => $prj, 'telefon' => $detail['telefon'], 'email' => $detail['email'], 'ulice' => $detail['ulice'], 'mesto' => $detail['mesto'], 'psc' => $detail['psc']], fn($v) => trim((string)$v) !== '');
    $nabUrl = $wu . '/nabidka-nova.html?sys=1#d=' . rtrim(strtr(base64_encode(json_encode($pre, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    if (function_exists('bd_prod_url')) $nabUrl = bd_prod_url($nabUrl);
    $objednavky = array_values(array_filter($zz, fn($x) => $x['typ'] === 'objednavka_interni'));
    // rozpracované (neodeslané) nabídky a objednávky tohoto zákazníka – podle telefonu, e-mailu nebo jména
    $konc = [];
    if (is_file(__DIR__ . '/lib/koncepty.php')) { try {
      require_once __DIR__ . '/lib/koncepty.php'; konc_tabulka($pdo);
      $t9 = substr(preg_replace('/\D+/', '', $detail['telefon']), -9); $em = mb_strtolower(trim($detail['email'])); $jm = mb_strtolower(trim($detail['jmeno']));
      foreach ($pdo->query("SELECT * FROM koncepty WHERE stav='rozpracovano' ORDER BY upraveno DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) as $kk) {
        $f = (json_decode((string)$kk['data_json'], true) ?: [])['f'] ?? [];
        $kt = substr(preg_replace('/\D+/', '', (string)($f['telefon'] ?? '')), -9); $ke = mb_strtolower(trim((string)($f['email'] ?? '')));
        if ((strlen($t9) === 9 && $kt === $t9) || ($em !== '' && $ke === $em) || ($jm !== '' && mb_strtolower(trim((string)$kk['zakaznik'])) === $jm)) $konc[] = $kk;
      }
    } catch (Throwable $x) { $konc = []; } }
  ?>
  <div class="box" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <?php if ($detail['telefon']): ?><a class="btn" href="tel:<?= $e(preg_replace('/[^\d+]/', '', $detail['telefon'])) ?>">📞 Zavolat</a><?php endif; ?>
    <a class="btn" href="<?= $e($nabUrl) ?>">➕ Nová nabídka</a>
    <a class="btn sec" href="#zapis">📝 Zapsat telefonát / poznámku</a>
    <a class="btn sec" href="#servis">🛠 Servis<?= $objednavky ? '' : ' (bez objednávky)' ?></a>
    <a class="btn sec" href="#historie">📋 Poptávky a objednávky (<?= count($zz) ?>)</a>
  </div>
  <?php if ($konc): ?><div class="box" style="border:2px solid #e0b100;background:#fffbea"><h3 style="color:#7a5b00">📝 Rozpracované – neodeslané (<?= count($konc) ?>)</h3><div class="tw"><table>
    <tr><th>Uloženo</th><th>Druh</th><th>Číslo</th><th>Cena</th><th></th></tr>
    <?php foreach ($konc as $kk): $ku = konc_url($cfg, $kk); if (function_exists('bd_prod_url')) $ku = bd_prod_url($ku); ?><tr><td><?= $e(date('j. n. Y H:i', strtotime((string)$kk['upraveno']))) ?></td>
      <td><?= $e($kk['druh'] === 'objednavka-vice' ? 'Objednávka' : 'Nabídka') ?><?= $kk['kategorie'] ? ' <span class="mut">' . $e($kk['kategorie']) . '</span>' : '' ?></td><td><?= $e($kk['cislo'] ?: '–') ?></td><td><?= $e($kc($kk['cena'])) ?></td>
      <td style="white-space:nowrap"><a class="btn sec" style="padding:4px 9px;font-size:13px" href="<?= $e($ku) ?>">Otevřít</a> <a class="btn sec" style="padding:4px 9px;font-size:13px" href="<?= $e($ku) ?>&pdf=1" target="_blank">PDF</a></td></tr><?php endforeach; ?></table></div></div><?php endif; ?>
  <div class="box" id="zapis"><h3>Rychlý zápis</h3>
    <form method="post"><input type="hidden" name="akce" value="zk_zapis"><input type="hidden" name="klic" value="<?= $e($k) ?>">
      <p class="row" style="display:flex;flex-wrap:wrap;gap:8px"><?php foreach (['Telefonát', 'Servis', 'Schůzka', 'Poznámka'] as $ix => $dr): ?><label><input type="radio" name="druh" value="<?= $e($dr) ?>"<?= $ix === 0 ? ' checked' : '' ?>> <?= $e($dr) ?></label><?php endforeach; ?></p>
      <p><textarea name="zapis" rows="2" style="width:100%" placeholder="Co se domluvilo, co se opravilo…" required></textarea></p>
      <button>Zapsat</button> <span class="mut">uloží se navrch poznámky zákazníka s datem a vaším jménem</span></form>
    <?php if (trim((string)($i['poznamka'] ?? '')) !== ''): ?><div style="margin-top:10px;white-space:pre-wrap;font-size:14px;max-height:260px;overflow:auto;border-top:1px solid #e1e6ee;padding-top:8px"><?= $e($i['poznamka']) ?></div><?php endif; ?></div>
  <?php if ($detail['smazany']): ?><div class="err">Tento zákazník je smazaný ze seznamu (jeho záznamy v kartotéce zůstaly). <form method="post" style="display:inline"><input type="hidden" name="akce" value="zk_obnovit"><input type="hidden" name="klic" value="<?= $e($k) ?>"><button class="sec">Vrátit do seznamu</button></form></div><?php endif; ?>
  <div class="box"><h3>Kontakty a adresa</h3>
    <p>📞 <?= $detail['telefon'] ? '<a href="tel:' . $e(preg_replace('/[^\d+]/', '', $detail['telefon'])) . '">' . $e($detail['telefon']) . '</a>' : '–' ?> ·
      ✉️ <?= $detail['email'] ? '<a href="mailto:' . $e($detail['email']) . '">' . $e($detail['email']) . '</a>' : '–' ?></p>
    <p>📍 <?= $e(trim(implode(', ', array_filter([$detail['ulice'], trim($detail['psc'] . ' ' . $detail['mesto'])]))) ?: '–') ?><?= $detail['firma'] ? ' · 🏢 ' . $e($detail['firma']) : '' ?></p>
    <p><a class="btn sec" href="zakaznici.php?vcf=<?= urlencode($k) ?>">📱 Uložit do telefonu (.vcf)</a></p>
    <details<?= isset($_GET['upravit']) ? ' open' : '' ?>><summary>✏️ Upravit kontakt</summary>
      <form method="post" style="margin-top:10px"><input type="hidden" name="akce" value="zk_kontakt"><input type="hidden" name="klic" value="<?= $e($k) ?>">
        <div class="fg"><?php foreach ($ZK_POLE as $f => $t): ?><label><?= $e($t) ?><input name="<?= $e($f) ?>" value="<?= $e($detail[$f]) ?>"<?= $f === 'email' ? ' type="email"' : ($f === 'telefon' ? ' type="tel"' : '') ?>></label><?php endforeach; ?></div>
        <p class="mut">Úprava se ukáže tady v Zákaznících a v exportu do telefonu. Poptávky, nabídky a objednávky v kartotéce zůstanou, jak byly odeslané. Vymazáním pole se vrátí údaj ze záznamů.</p>
        <button>Uložit kontakt</button><?php if ($detail['upraveny']): ?> <span class="mut">obsahuje ruční úpravy</span><?php endif; ?></form></details></div>
  <div class="box" id="historie"><h3>Historie poptávek a objednávek (<?= count($zz) ?>)</h3><?php if (!$zz): ?><p class="mut">Zatím žádná poptávka, nabídka ani objednávka.</p><?php else: ?><div class="tw"><table>
    <tr><th>Datum</th><th>Druh</th><th>Číslo</th><th>Předmět</th><th>Cena</th><th>Stav</th></tr>
    <?php foreach ($zz as $x): ?><tr><td><?= $e($datum($x['datum'])) ?></td><td><?= $e($TYPY[$x['typ']] ?? $x['typ']) ?></td><td><?= $e($x['cislo']) ?></td>
      <td><a href="kartoteka.php?id=<?= (int)$x['id'] ?>"><?= $e(mb_substr($x['predmet'], 0, 90)) ?></a></td><td><?= $e($kc($x['cena'])) ?></td>
      <td><span class="chip"><?= $e($STAVY[$x['stav']] ?? $x['stav']) ?></span><?= $x['zaloha'] ? ' <span class="chip ok">záloha přijata</span>' : '' ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
  <?php
    $zar = array_values(array_filter($zz, fn($x) => !empty($x['predano']) || !empty($x['zaruka_do'])));
    $ids = array_map(fn($x) => (int)$x['id'], $zz);
    $sv = [];
    if ($ids) { $q = $pdo->prepare('SELECT zadost_id, datum, druh, poznamka, dalsi_kontrola FROM servis WHERE zadost_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY datum DESC'); $q->execute($ids); $sv = $q->fetchAll(PDO::FETCH_ASSOC); }
  ?>
  <div class="box" id="servis"><h3>Servis a záruka</h3>
    <?php if (!$zar && !$sv): ?><p class="mut">Zatím žádné předání dveří ani servisní záznam.</p><?php endif; ?>
    <?php if ($objednavky): ?><p style="display:flex;flex-wrap:wrap;gap:8px"><?php foreach ($objednavky as $x): ?><form method="post" action="kartoteka.php" style="display:inline"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><input type="hidden" name="akce" value="dvere_zaznam"><button class="sec">🛠 Zapsat servis – dveře z <?= $e($x['cislo'] ?: ('#' . $x['id'])) ?></button></form><?php endforeach; ?></p>
    <?php else: ?><p class="mut">Zákazník u nás nemá objednávku dveří – servis zapište do Rychlého zápisu výše (druh „Servis“).</p><?php endif; ?>
    <?php foreach ($zar as $x): ?><p>Dveře z <b><?= $e($x['cislo'] ?: ('#' . $x['id'])) ?></b>: předáno <?= $e($datum($x['predano'])) ?> · záruka od <?= $e($datum($x['zaruka_od'])) ?> do <b><?= $e($datum($x['zaruka_do'])) ?></b></p><?php endforeach; ?>
    <?php if ($sv): ?><div class="tw"><table><tr><th>Datum</th><th>Druh</th><th>Poznámka</th><th>Další kontrola</th></tr>
      <?php foreach ($sv as $s): ?><tr><td><?= $e($datum($s['datum'])) ?></td><td><?= $e($s['druh']) ?></td><td><?= $e($s['poznamka']) ?></td><td><?= $e($datum($s['dalsi_kontrola'])) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
  <div class="box"><h3>Štítky, poznámka a další kontakt</h3>
    <form method="post"><input type="hidden" name="akce" value="zk_uloz"><input type="hidden" name="klic" value="<?= $e($k) ?>">
      <p><label>Štítky (oddělte čárkou, např. VIP, doporučil, reklamace)<br><input name="stitky" style="width:100%;max-width:520px" value="<?= $e($i['stitky'] ?? '') ?>"></label></p>
      <p><label>Další kontakt<br><input type="date" name="dalsi_kontakt" value="<?= $e($i['dalsi_kontakt'] ?? '') ?>"></label></p>
      <p><label>Poznámka<br><textarea name="poznamka" rows="4"><?= $e($i['poznamka'] ?? '') ?></textarea></label></p>
      <button>Uložit</button><?php if (!empty($i['upraveno'])): ?> <span class="mut">naposledy upraveno <?= $e($i['upraveno']) ?></span><?php endif; ?></form></div>
  <?php if (!$detail['smazany']): ?><div class="box"><h3>Smazat zákazníka</h3>
    <form method="post" onsubmit="return confirm('<?= $zz ? 'Smazat zákazníka ze seznamu? Jeho poptávky, nabídky a objednávky v kartotéce zůstanou. Když od něj přijde nový záznam, objeví se zase.' : 'Smazat tohoto zákazníka? Nemá žádné záznamy v kartotéce, smaže se úplně.' ?>')">
      <input type="hidden" name="akce" value="zk_smazat"><input type="hidden" name="klic" value="<?= $e($k) ?>">
      <p class="mut"><?= $zz ? 'Zmizí jen ze seznamu Zákazníci – poptávky, nabídky a objednávky v kartotéce zůstanou beze změny.' : 'Zákazník nemá žádné záznamy v kartotéce – smaže se úplně.' ?></p>
      <button class="del">🗑 Smazat zákazníka</button></form></div><?php endif; ?>
<?php elseif (isset($_GET['novy'])): ?>
  <p><a href="zakaznici.php">← Zákazníci</a></p>
  <h1>Nový zákazník</h1>
  <?php if (isset($_GET['chyba'])): ?><div class="err">Vyplňte aspoň telefon, e-mail, nebo jméno.</div><?php endif; ?>
  <div class="box"><form method="post"><input type="hidden" name="akce" value="zk_novy">
    <div class="fg"><?php foreach ($ZK_POLE as $f => $t): ?><label><?= $e($t) ?><input name="<?= $e($f) ?>"<?= $f === 'email' ? ' type="email"' : ($f === 'telefon' ? ' type="tel"' : '') ?>></label><?php endforeach; ?></div>
    <p class="mut">Když zákazník se stejným telefonem (nebo e-mailem) už v kartotéce je, doplní se mu údaje, nový se nezaloží.</p>
    <button>Uložit zákazníka</button></form></div>
<?php else:
  $q = mb_strtolower(trim((string)($_GET['q'] ?? ''))); $st = trim((string)($_GET['stitek'] ?? '')); $ra = (string)($_GET['razeni'] ?? 'posledni');
  $rows = [];
  foreach ($vse as $k => $c) {
    $i = $info[$k] ?? []; $zn = $c['zaznamy']; $posl = $zn ? end($zn)['datum'] : (string)($i['upraveno'] ?? '');
    $hay = mb_strtolower(implode(' ', [$c['jmeno'], $c['firma'], $c['telefon'], $c['email'], $c['mesto'], $i['stitky'] ?? '', implode(' ', array_column($zn, 'cislo'))]));
    if ($q !== '' && mb_strpos($hay, $q) === false) continue;
    $stl = array_filter(array_map('trim', explode(',', (string)($i['stitky'] ?? ''))));
    if ($st !== '' && !in_array($st, $stl, true)) continue;
    $obj = count(array_filter($zn, fn($x) => $x['typ'] !== 'poptavka'));
    $rows[] = $c + ['posl' => $posl, 'pocet' => count($zn), 'obj' => $obj, 'stitky' => $stl, 'dk' => $i['dalsi_kontakt'] ?? ''];
  }
  usort($rows, fn($a, $b) => $ra === 'jmeno' ? strcoll($a['jmeno'], $b['jmeno']) : ($ra === 'pocet' ? $b['pocet'] <=> $a['pocet'] : strcmp((string)$b['posl'], (string)$a['posl'])));
  $vsechnyStitky = [];
  foreach ($info as $i) foreach (array_filter(array_map('trim', explode(',', (string)($i['stitky'] ?? '')))) as $s) $vsechnyStitky[$s] = true;
  ksort($vsechnyStitky);
  [$impN, $impD] = array_map('intval', explode('-', (string)($_GET['import'] ?? '') . '-0'));
?>
  <h1>Zákazníci <span class="mut">(<?= count($rows) ?> z <?= count($vse) ?>)</span></h1>
  <?php if (isset($_GET['smazano'])): ?><div class="ok">Zákazník byl smazán ze seznamu.</div><?php endif; ?>
  <?php if (isset($_GET['import'])): ?><div class="ok">Import hotový: <?= $impN ?> nových zákazníků, <?= $impD ?> doplněných.</div><?php endif; ?>
  <div class="box"><form method="get" class="row">
    <label>Hledat<br><input name="q" value="<?= $e($_GET['q'] ?? '') ?>" placeholder="jméno, telefon, e-mail, město, číslo…" size="30"></label>
    <?php if ($vsechnyStitky): ?><label>Štítek<br><select name="stitek"><option value="">všechny</option><?php foreach (array_keys($vsechnyStitky) as $s): ?><option<?= $st === $s ? ' selected' : '' ?>><?= $e($s) ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <label>Řadit<br><select name="razeni"><option value="posledni"<?= $ra === 'posledni' ? ' selected' : '' ?>>poslední aktivita</option><option value="jmeno"<?= $ra === 'jmeno' ? ' selected' : '' ?>>jméno</option><option value="pocet"<?= $ra === 'pocet' ? ' selected' : '' ?>>počet záznamů</option></select></label>
    <button>Zobrazit</button> <a class="btn sec" href="zakaznici.php">Zrušit</a></form></div>
  <?php $gn0 = zk_google_nast(); $gChip = !empty($gn0['stazeno']) ? '<span class="chip ok">Google ' . $e(date('j. n. H:i', strtotime($gn0['stazeno']))) . '</span>' : (!empty($gn0['token']) ? '<span class="chip warn">Google čeká na skript</span>' : '<span class="chip">Google vypnuto</span>'); ?>
  <details class="box"<?= isset($_GET['import']) ? ' open' : '' ?>><summary style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">📱 Telefon a kontakty <?= $gChip ?> <span class="mut">▾ export, import, Google</span> <a class="btn" href="zakaznici.php?novy=1" style="margin-left:auto;padding:5px 12px;font-size:14px" onclick="event.stopPropagation()">➕ Nový zákazník</a></summary>
    <p class="row" style="margin-top:10px"><a class="btn sec" href="zakaznici.php?vcf=vse">⬇ Export všech do telefonu (.vcf)</a>
      <a class="btn sec" href="zakaznici.php?google=1">🔄 Google Kontakty <?= $gChip ?></a></p>
    <form method="post" enctype="multipart/form-data" class="row" style="margin-top:8px"><input type="hidden" name="akce" value="vcf_nahled">
      <label>Import z telefonu (soubor .vcf)<br><input type="file" name="vcf" accept=".vcf,text/vcard,text/x-vcard" required></label><button class="sec">Načíst a vybrat</button></form>
    <p class="mut" style="margin:8px 0 0">Export: soubor otevřete v telefonu (nebo nahrajte do contacts.google.com → Importovat) – kontakty dostanou skupinu „Zákazníci Bezpečné dveře“. Import: v telefonu Kontakty → Spravovat / Exportovat → soubor .vcf, ten sem nahrajte a vyberte, kdo jsou zákazníci.</p></details>
  <div class="box tw"><table><tr><th>Zákazník</th><th>Kontakt</th><th>Město</th><th>Záznamy</th><th>Poslední</th><th>Štítky</th><th>Další kontakt</th><th></th></tr>
    <?php foreach ($rows as $c): ?><tr>
      <td><a href="zakaznici.php?k=<?= urlencode($c['klic']) ?>"><b><?= $e($c['jmeno'] ?: '(bez jména)') ?></b></a><?= $c['firma'] ? '<div class="mut">' . $e($c['firma']) . '</div>' : '' ?><?= $c['rucne'] ? ' <span class="chip tel">' . $e($c['zdroj'] === 'telefon' ? 'z telefonu' : 'ručně') . '</span>' : '' ?></td>
      <td><?= $e($c['telefon']) ?><?= $c['telefon'] && $c['email'] ? '<br>' : '' ?><span class="mut"><?= $e($c['email']) ?></span></td><td><?= $e($c['mesto']) ?></td>
      <td><?= (int)$c['pocet'] ?><?= $c['obj'] ? ' <span class="chip ok">' . (int)$c['obj'] . '× objednávka</span>' : '' ?></td>
      <td><?= $e($c['posl'] ? $datum($c['posl']) : '–') ?></td><td><?php foreach ($c['stitky'] as $s): ?><span class="chip"><?= $e($s) ?></span><?php endforeach; ?></td>
      <td><?= $c['dk'] ? ($c['dk'] <= date('Y-m-d') ? '<span class="chip warn">' . $e($datum($c['dk'])) . '</span>' : $e($datum($c['dk']))) : '' ?></td>
      <td style="white-space:nowrap"><a class="btn sec" style="padding:4px 9px;font-size:13px" href="zakaznici.php?k=<?= urlencode($c['klic']) ?>&upravit=1" title="Upravit kontakt">✏️</a>
        <form method="post" style="display:inline" onsubmit="return confirm('<?= $c['zaznamy'] ? 'Smazat zákazníka ze seznamu? Jeho poptávky, nabídky a objednávky v kartotéce zůstanou.' : 'Smazat tohoto zákazníka? Nemá žádné záznamy, smaže se úplně.' ?>')"><input type="hidden" name="akce" value="zk_smazat"><input type="hidden" name="klic" value="<?= $e($c['klic']) ?>"><button class="del" style="padding:4px 9px;font-size:13px" title="Smazat">🗑</button></form></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="mut">Nic nenalezeno.</td></tr><?php endif; ?></table></div>
<?php endif; ?>
</main></body></html>
