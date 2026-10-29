<?php
// Zákazníci: seznam a karty zákazníků sestavené automaticky z poptávek a objednávek v kartotéce
// (stejný zákazník = stejný telefon, jinak stejný e-mail, jinak stejné jméno a město). Ke každému zákazníkovi jdou
// přidat štítky, poznámka a datum dalšího kontaktu. Stejná ochrana jako kartoteka.php.
// 29. 10. 2026: úprava kontaktů (přepisuje jen zobrazení, záznamy v kartotéce zůstávají), smazání (skrytí ze seznamu – vrátí se,
// když přijde nový záznam), ruční zákazník, export do telefonu (vCard .vcf) a import kontaktů z telefonu (.vcf, s výběrem).
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
$pdo->exec('CREATE TABLE IF NOT EXISTS zakaznik_info (klic TEXT PRIMARY KEY, stitky TEXT, poznamka TEXT, dalsi_kontakt TEXT, upraveno TEXT)');
// doplňkové sloupce (úpravy kontaktů, smazání, ruční zákazník)
$ZK_POLE = ['jmeno' => 'Jméno a příjmení', 'firma' => 'Firma', 'telefon' => 'Telefon', 'email' => 'E-mail', 'ulice' => 'Ulice a číslo', 'mesto' => 'Obec', 'psc' => 'PSČ'];
$sloupce = array_column($pdo->query('PRAGMA table_info(zakaznik_info)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (array_merge(array_keys($ZK_POLE), ['smazano', 'rucne', 'zdroj']) as $c) if (!in_array($c, $sloupce, true)) $pdo->exec('ALTER TABLE zakaznik_info ADD COLUMN ' . $c . ' TEXT');

$TYPY = ['poptavka' => 'Poptávka', 'objednavka_interni' => 'Objednávka', 'objednavka_zakaznik' => 'Objednávka (zákazník)', 'nabidka' => 'Nabídka'];
$STAVY = ['nove' => 'Nové', 'rozpracovano' => 'Rozpracováno', 'objednano' => 'Objednáno u výrobce', 'termin' => 'Domluven termín',
  'namontovano' => 'Namontováno', 'vyrizeno' => 'Vyřízeno', 'storno' => 'Storno', 'chyba_mailu' => 'E-mail se neodeslal'];

function zk_klic(array $d, array $r): string {
  $tel = preg_replace('/\D+/', '', (string)($d['telefon'] ?? ''));
  if (strlen($tel) >= 9) return 't' . substr($tel, -9);
  $em = strtolower(trim((string)($d['email'] ?? $r['email'] ?? '')));
  if ($em !== '') return 'e' . $em;
  $jm = mb_strtolower(trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')));
  if ($jm !== '') return 'j' . md5($jm . '|' . mb_strtolower((string)($d['mesto'] ?? '')));
  return '';
}

// všichni zákazníci sestavení ze záznamů (nejnovější záznam určuje zobrazené kontakty)
function zk_nacti(PDO $pdo): array {
  $z = [];
  $q = $pdo->query('SELECT id, vytvoreno, typ, cislo, predmet, email, data_json, stav, zaloha_prijata_dne, predano_dne, zaruka_od, zaruka_do FROM zadosti ORDER BY vytvoreno ASC, id ASC');
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)($r['data_json'] ?? ''), true); if (!is_array($d)) $d = [];
    $k = zk_klic($d, $r); if ($k === '') continue;
    $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''));
    $zz = &$z[$k];
    if (!isset($zz)) $zz = ['klic' => $k, 'jmeno' => '', 'firma' => '', 'telefon' => '', 'email' => '', 'mesto' => '', 'ulice' => '', 'psc' => '', 'zaznamy' => []];
    foreach (['telefon' => $d['telefon'] ?? '', 'email' => $d['email'] ?? $r['email'] ?? '', 'mesto' => $d['mesto'] ?? '', 'ulice' => $d['ulice'] ?? '', 'psc' => $d['psc'] ?? '', 'firma' => $d['fak_jmeno'] ?? ''] as $f => $v) {
      if (trim((string)$v) !== '') $zz[$f] = trim((string)$v);
    }
    if ($jmeno !== '') $zz['jmeno'] = $jmeno;
    $zz['zaznamy'][] = ['id' => (int)$r['id'], 'datum' => $r['vytvoreno'], 'typ' => $r['typ'], 'cislo' => (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']),
      'predmet' => (string)$r['predmet'], 'cena' => $d['cena_celkem'] ?? null, 'stav' => (string)$r['stav'], 'zaloha' => $r['zaloha_prijata_dne'],
      'predano' => $r['predano_dne'], 'zaruka_od' => $r['zaruka_od'], 'zaruka_do' => $r['zaruka_do']];
    unset($zz);
  }
  return $z;
}

function zk_info(PDO $pdo): array {
  $o = [];
  foreach ($pdo->query('SELECT * FROM zakaznik_info')->fetchAll(PDO::FETCH_ASSOC) as $r) $o[$r['klic']] = $r;
  return $o;
}

// Zákazníci ze záznamů + ruční (z telefonu / přidaní ručně), s úpravami z zakaznik_info. Smazaní se vynechají,
// pokud od smazání nepřišel nový záznam ($i['smazano'] = datum a čas smazání).
function zk_vsichni(PDO $pdo, array $info, bool $vcSmazanych = false): array {
  global $ZK_POLE;
  $vse = zk_nacti($pdo);
  foreach ($info as $k => $i) if (!isset($vse[$k]) && !empty($i['rucne'])) $vse[$k] = ['klic' => $k, 'jmeno' => '', 'firma' => '', 'telefon' => '', 'email' => '', 'mesto' => '', 'ulice' => '', 'psc' => '', 'zaznamy' => []];
  foreach ($vse as $k => &$c) {
    $i = $info[$k] ?? [];
    foreach (array_keys($ZK_POLE) as $f) if (isset($i[$f]) && $i[$f] !== null && $i[$f] !== '') $c[$f] = (string)$i[$f];
    $c['upraveny'] = (bool)array_filter(array_keys($ZK_POLE), fn($f) => ($i[$f] ?? '') !== '');
    $c['rucne'] = !empty($i['rucne']); $c['zdroj'] = (string)($i['zdroj'] ?? '');
    $posl = $c['zaznamy'] ? end($c['zaznamy'])['datum'] : '';
    $c['smazany'] = !empty($i['smazano']) && ($posl === '' || strcmp((string)$posl, (string)$i['smazano']) <= 0);
    if ($c['smazany'] && !$vcSmazanych) unset($vse[$k]);
  }
  unset($c);
  return $vse;
}

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
    $zk_uloz_pole($k, ['stitky' => $stitky, 'poznamka' => mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 2000), 'dalsi_kontakt' => $dk]);
    header('Location: zakaznici.php?k=' . urlencode($k) . '&ulozeno=1'); exit;
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
<?php if (!empty($GLOBALS['IMPORT']) || (($_POST['akce'] ?? '') === 'vcf_nahled')): $imp = $GLOBALS['IMPORT'] ?? [];
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
  <div class="box"><h3>Historie poptávek a objednávek (<?= count($zz) ?>)</h3><?php if (!$zz): ?><p class="mut">Zatím žádná poptávka, nabídka ani objednávka.</p><?php else: ?><div class="tw"><table>
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
  <div class="box"><h3>Servis a záruka</h3>
    <?php if (!$zar && !$sv): ?><p class="mut">Zatím žádné předání dveří ani servisní záznam.</p><?php endif; ?>
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
  <div class="box"><h3>📱 Telefon a kontakty</h3>
    <p class="row"><a class="btn" href="zakaznici.php?novy=1">➕ Nový zákazník</a>
      <a class="btn sec" href="zakaznici.php?vcf=vse">⬇ Export všech do telefonu (.vcf)</a></p>
    <form method="post" enctype="multipart/form-data" class="row" style="margin-top:8px"><input type="hidden" name="akce" value="vcf_nahled">
      <label>Import z telefonu (soubor .vcf)<br><input type="file" name="vcf" accept=".vcf,text/vcard,text/x-vcard" required></label><button class="sec">Načíst a vybrat</button></form>
    <p class="mut" style="margin:8px 0 0">Export: soubor otevřete v telefonu (nebo nahrajte do contacts.google.com → Importovat) – kontakty dostanou skupinu „Zákazníci Bezpečné dveře“. Import: v telefonu Kontakty → Spravovat / Exportovat → soubor .vcf, ten sem nahrajte a vyberte, kdo jsou zákazníci.</p></div>
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
