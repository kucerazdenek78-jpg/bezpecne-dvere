<?php
// Kartotéka poptávek a objednávek. Z firemní sítě: uživatel + heslo z config.php.
// Z internetu (přes tunel) JEN na hostiteli kartoteka_host a JEN po přihlášení Cloudflare Access
// (hlavička Cf-Access-Jwt-Assertion); jinak 404. Heslo z config.php platí vždy navíc.
// 30. 10. 2026: jméno zákazníka (v detailu i v seznamu) vede na jeho kartu v Zákaznících (zakaznici.php?k=…, klíč zk_klic).
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
$pdo = db(); require_once __DIR__ . '/lib/stav.php'; bd_stavy_sync($pdo);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '–' : number_format((float)$n, 0, ',', ' ') . ' Kč';

$STAVY = ['nove' => 'Nové', 'rozpracovano' => 'Rozpracováno', 'potvrzena' => 'Potvrzená', 'objednano' => 'Objednáno u výrobce', 'termin' => 'Domluven termín', 'skladem_bez' => 'Skladem – bez termínu', 'skladem_s' => 'Skladem – s termínem',
  'namontovano' => 'Namontováno', 'vyrizeno' => 'Vyřízeno', 'storno' => 'Storno', 'chyba_mailu' => 'E-mail se neodeslal'];
$TYPY = ['poptavka' => 'Poptávka', 'nabidka' => 'Nabídka', 'objednavka_interni' => 'Objednávka'];

// ── pomocné ──
function dj($r) { $d = json_decode((string)($r['data_json'] ?? ''), true); return is_array($d) ? $d : []; }
function radek($r) { // vybere údaje pro tabulku z data_json
  $d = dj($r);
  return [
    'cislo' => $d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo'],
    'jmeno' => trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')),
    'mesto' => $d['mesto'] ?? '', 'telefon' => $d['telefon'] ?? '', 'email' => $d['email'] ?? $r['email'],
    'model' => $d['model'] ?? '', 'cena' => $d['cena_celkem'] ?? null, 'zaloha' => $d['zaloha'] ?? null,
    'prodavac' => $d['prodavac'] ?? '', 'termin' => $d['termin'] ?? '', 'd' => $d,
  ];
}
function hledany_text($r, $x) { return mb_strtolower(implode(' ', [$r['predmet'], $r['email'], $x['cislo'], $x['jmeno'], $x['mesto'], $x['telefon'], $x['model'], $x['prodavac']])); }
function odstran_diakritiku($s) { return strtr($s, ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z']); }

require_once __DIR__ . '/lib/zamek.php'; zamek_init($pdo); zamek_obsluz($pdo, $cfg); zamek_hlidej($pdo); // zámek a historie
require_once __DIR__ . '/lib/foto.php'; foto_obsluz($pdo); // fotky u záznamu
require_once __DIR__ . '/lib/zamereni.php'; zam_obsluz($pdo); // zaměření
require_once __DIR__ . '/lib/zakaznici.php'; // proklik na kartu zákazníka (zk_klic)
$zkOdkaz = function (array $r, array $d, string $text, string $styl = '') use ($e) { $k = zk_klic($d, $r); return $k === '' ? $e($text) : '<a href="zakaznici.php?k=' . $e(rawurlencode($k)) . '" title="Karta zákazníka – historie, servis, poznámky"' . ($styl !== '' ? ' style="' . $styl . '"' : '') . '>' . $e($text) . '</a>'; };
// ── stažení přílohy ──
if (isset($_GET['soubor'])) {
  $jm = basename($_GET['soubor']); $cesta = $DATA . '/ulozeno/' . $jm;
  if (is_file($cesta)) {
    $ext = strtolower(pathinfo($jm, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
    header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . preg_replace('/^\d+_[0-9a-f]{8}_/', '', $jm) . '"');
    header('X-Content-Type-Options: nosniff'); readfile($cesta); exit;
  }
  http_response_code(404); exit('Nenalezeno');
}

// ── potvrzení přijaté zálohy ──
$zpr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'zaloha' && isset($_POST['id'])) {
  require_once __DIR__ . '/lib/posta.php';
  $idz = (int)$_POST['id'];
  $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$idz]); $rz = $st->fetch(PDO::FETCH_ASSOC);
  if ($rz) {
    if (zamceno($pdo, $idz)) { header('Location: kartoteka.php?id=' . $idz . '&zprava=zamceno'); exit; }
    if (isset($_POST['zrusit'])) hist_pis($pdo, $idz, 'evidence zálohy zrušena', '');
    if (isset($_POST['zrusit'])) { $pdo->prepare('UPDATE zadosti SET zaloha_prijata_dne=NULL, zaloha_prijata_kc=NULL WHERE id=?')->execute([$idz]); header('Location: kartoteka.php?id=' . $idz . '&ulozeno=1'); exit; }
    $kcz = (float)str_replace([' ', ','], ['', '.'], (string)($_POST['castka'] ?? '0'));
    $dne = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['datum'] ?? '') ? $_POST['datum'] : date('Y-m-d');
    if ($kcz <= 0) { header('Location: kartoteka.php?id=' . $idz . '&zprava=castka'); exit; }
    $pdo->prepare('UPDATE zadosti SET zaloha_prijata_dne=?, zaloha_prijata_kc=? WHERE id=?')->execute([$dne, $kcz, $idz]); hist_pis($pdo, $idz, 'záloha přijata', number_format($kcz, 0, ',', ' ') . ' Kč, ' . $dne);
    bd_stavy_sync($pdo, true);   // Nové / Rozpracováno → Potvrzená
    $xz = radek($rz); $vysl = 'ulozeno';
    if (!empty($_POST['poslat'])) {
      $em = filter_var(trim((string)$xz['email']), FILTER_VALIDATE_EMAIL);
      if (!$em) $vysl = 'bez_emailu';
      else {
        $cisloz = $xz['cislo'];
        $telo = "Dobrý den,\n\nděkujeme, evidujeme přijetí zálohy (rezervačního poplatku) ve výši " . number_format($kcz, 0, ',', ' ') . " Kč k objednávce č. $cisloz, připsané " . date('j. n. Y', strtotime($dne)) . ".\n"
          . "Vaše objednávka bezpečnostních dveří je tímto potvrzena a bude dále zpracovávána. O termínu montáže Vás budeme informovat.\n\n"
          . "Děkujeme za důvěru.\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
        $ok = posli_mail($cfg, $DATA, [$em], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], "Potvrzení přijaté zálohy – objednávka $cisloz", $telo);
        $vysl = $ok ? 'odeslano' : 'mail_chyba';
      }
    }
    header('Location: kartoteka.php?id=' . $idz . '&zprava=' . $vysl); exit;
  }
}

// ── záznam dveří (servis, předání, záruka): odkaz na stránku dveří, u starších objednávek se tajný kód vytvoří ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'dvere_zaznam' && isset($_POST['id'])) {
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE id=? AND typ='objednavka_interni'"); $st->execute([(int)$_POST['id']]); $rz = $st->fetch(PDO::FETCH_ASSOC);
  if ($rz) {
    $dz = json_decode((string)$rz['data_json'], true) ?: [];
    if (empty($dz['predani_token'])) { $dz['predani_token'] = bin2hex(random_bytes(12)); $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($dz, JSON_UNESCAPED_UNICODE), $rz['id']]); }
    header('Location: predani.php?t=' . $dz['predani_token']); exit;
  }
}

// ── uložení stavu / poznámky ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
  $stav = array_key_exists($_POST['stav'] ?? '', $STAVY) ? $_POST['stav'] : 'nove';
  $pdo->prepare('UPDATE zadosti SET stav=?, poznamka=? WHERE id=?')->execute([$stav, mb_substr((string)($_POST['poznamka'] ?? ''), 0, 5000), (int)$_POST['id']]);
  header('Location: kartoteka.php?id=' . (int)$_POST['id'] . '&ulozeno=1'); exit;
}

// ── načtení + filtry ──
$typ = $_GET['typ'] ?? 'objednavka_interni'; if ($typ !== '' && !isset($TYPY[$typ])) $typ = '';
$fStav = $_GET['stav'] ?? ''; $fModel = $_GET['model'] ?? ''; $fProd = $_GET['prodavac'] ?? '';
$q = trim($_GET['q'] ?? '');
$od = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['od'] ?? '') ? $_GET['od'] : ''; $do = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['do'] ?? '') ? $_GET['do'] : '';

function nacti($pdo, $typ, $fStav, $od, $do) {
  $sql = 'SELECT * FROM zadosti WHERE typ IN (\'poptavka\',\'nabidka\',\'objednavka_interni\')'; $par = [];
  if ($typ !== '') { $sql .= ' AND typ=?'; $par[] = $typ; }
  if ($fStav !== '') { $sql .= ' AND stav=?'; $par[] = $fStav; }
  if ($od !== '') { $sql .= ' AND vytvoreno>=?'; $par[] = $od . ' 00:00:00'; }
  if ($do !== '') { $sql .= ' AND vytvoreno<=?'; $par[] = $do . ' 23:59:59'; }
  $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 3000'); $st->execute($par);
  $out = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $r['x'] = radek($r); $out[] = $r; }
  return $out;
}

function css() { return <<<CSS
:root{--bg:#f3f5f8;--card:#fff;--ink:#16233a;--mut:#64708a;--line:#e1e6ee;--acc:#1d5fb8;--acc2:#e8f0fb}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);font-size:15px}
header{background:#16233a;color:#fff;padding:12px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}header b{font-size:17px}header a{color:#cfe0ff;text-decoration:none}
main{max-width:1250px;margin:0 auto;padding:16px}a{color:var(--acc)}
.tabs{display:flex;gap:6px;margin-bottom:12px}.tabs a{padding:8px 14px;border-radius:8px;background:#e6ebf3;color:var(--ink);text-decoration:none;font-weight:600}.tabs a.on{background:var(--acc);color:#fff}
.box{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:14px}
form.f{display:flex;gap:8px;flex-wrap:wrap;align-items:end}form.f label{font-size:12px;color:var(--mut);display:flex;flex-direction:column;gap:3px}
input,select,textarea,button{font:inherit;padding:7px 9px;border:1px solid #c9d1de;border-radius:7px;background:#fff;color:var(--ink)}button,.btn{background:var(--acc);color:#fff;border-color:var(--acc);cursor:pointer;text-decoration:none;display:inline-block;padding:7px 14px;border-radius:7px}
.btn.sec{background:#fff;color:var(--acc)}textarea{width:100%}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--line);border-radius:10px;overflow:hidden}th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{background:#eef2f8;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--mut)}tr:hover td{background:#fafcff}
.num{text-align:right;white-space:nowrap}.mut{color:var(--mut);font-size:13px}.chip{display:inline-block;padding:2px 9px;border-radius:99px;font-size:12px;font-weight:700;background:#e6ebf3}
.s-nove{background:#ffe8d1;color:#9a3b00}.s-rozpracovano{background:#fff3c4;color:#7a5b00}.s-potvrzena{background:#ede9fe;color:#5b21b6}.s-skladem_bez{background:#fef3c7;color:#92400e}.s-skladem_s{background:#d1fae5;color:#065f46}.s-objednano,.s-termin{background:#dbeafe;color:#1e40af}.s-namontovano,.s-vyrizeno{background:#d9f2e0;color:#14653a}.s-storno{background:#eee;color:#666}.s-chyba_mailu{background:#fde0e0;color:#a11}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px}dl{margin:0;display:grid;grid-template-columns:150px 1fr;gap:5px 12px}dt{color:var(--mut)}dd{margin:0}
h2{margin:0 0 8px;font-size:19px}h3{margin:0 0 8px;font-size:14px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut)}
.err{background:#fde2e2;color:#9b1c1c;padding:8px 12px;border-radius:8px;margin-bottom:12px}
.ok{background:#d9f2e0;color:#14653a;padding:8px 12px;border-radius:8px;margin-bottom:12px}
@media(max-width:800px){.hide-m{display:none}dl{grid-template-columns:110px 1fr}}
CSS; }

// ── export CSV (Excel, česky: středník + BOM) ──
if (isset($_GET['export'])) {
  $rows = nacti($pdo, $typ, $fStav, $od, $do);
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="kartoteka-' . date('Y-m-d') . '.csv"');
  echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
  fputcsv($o, ['Vytvořeno', 'Typ', 'Číslo', 'Stav', 'Jméno', 'Telefon', 'E-mail', 'Město', 'Model', 'Cena celkem', 'Záloha', 'Prodavač', 'Termín', 'Poznámka'], ';');
  foreach ($rows as $r) { $x = $r['x'];
    if ($fModel !== '' && $x['model'] !== $fModel) continue; if ($fProd !== '' && $x['prodavac'] !== $fProd) continue;
    if ($q !== '' && mb_strpos(odstran_diakritiku(hledany_text($r, $x)), odstran_diakritiku(mb_strtolower($q))) === false) continue;
    fputcsv($o, [$r['vytvoreno'], $TYPY[$r['typ']] ?? $r['typ'], $x['cislo'], $STAVY[$r['stav']] ?? $r['stav'], $x['jmeno'], $x['telefon'], $x['email'], $x['mesto'], $x['model'], $x['cena'], $x['zaloha'], $x['prodavac'], $x['termin'], $r['poznamka']], ';'); }
  fclose($o); exit;
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="manifest" href="kartoteka-app-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><link rel="apple-touch-icon" href="kartoteka-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Kartotéka"><title>Kartotéka – Bezpečné dveře</title><style><?= css() ?></style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('kartoteka'); ?><main>
<?php
// ══════════ DETAIL ══════════
if (isset($_GET['id'])):
  $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([(int)$_GET['id']]); $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) { echo '<p>Záznam nenalezen. <a href="kartoteka.php">Zpět</a></p>'; }
  else { $x = radek($r); $d = $x['d'];
    $link = ''; if (preg_match('~Vytvořit objednávku:?\s*(https?://\S+)~u', (string)$r['telo'], $m)) $link = $m[1];
    $adresa = implode(', ', array_filter([$d['ulice'] ?? '', trim(($d['psc'] ?? '') . ' ' . ($d['mesto'] ?? ''))]));
    $patro = !isset($d['patro']) ? '' : ((int)$d['patro'] === 0 ? 'přízemí' : $d['patro'] . '. patro, ' . (($d['vytah'] ?? '') === 'Ano' ? 's výtahem' : 'bez výtahu'));
    $doprava = !isset($d['doprava_km']) ? '' : (str_replace('.', ',', (string)$d['doprava_km']) . ' km – ' . (($d['doprava_cena'] ?? null) === null ? 'upřesníme' : ($d['doprava_cena'] ? $kc($d['doprava_cena']) : 'zdarma')));
    $dl = function (array $pary) use ($e) { echo '<dl>'; foreach ($pary as $k => $v) if ($v !== '' && $v !== null) echo '<dt>' . $e($k) . '</dt><dd>' . $e($v) . '</dd>'; echo '</dl>'; };
?>
  <p><a href="kartoteka.php?typ=<?= $e($r['typ']) ?>">← zpět na seznam</a></p>
  <?php if (isset($_GET['ulozeno'])): ?><div class="ok">Uloženo.</div><?php endif; ?>
  <?php $zm = ['ulozeno' => 'Záloha zaevidována.', 'odeslano' => 'Záloha zaevidována a potvrzení odesláno zákazníkovi' . (($cfg['mail_rezim'] ?? '') === 'soubor' ? ' (zkušební režim – e-mail se jen uložil do souboru).' : '.'),
    'bez_emailu' => 'Záloha zaevidována, ale zákazník nemá platný e-mail – potvrzení se neodeslalo.', 'mail_chyba' => 'Záloha zaevidována, ale potvrzení se nepodařilo odeslat.', 'castka' => 'Zadejte částku větší než 0.'];
    if (isset($zm[$_GET['zprava'] ?? ''])): ?><div class="<?= ($_GET['zprava'] === 'castka' || $_GET['zprava'] === 'mail_chyba') ? 'err' : 'ok' ?>"><?= $e($zm[$_GET['zprava']]) ?></div><?php endif; ?>
  <div class="box"><h2><?= $e($TYPY[$r['typ']] ?? $r['typ']) ?> <?= $e($x['cislo']) ?> · <?= $zkOdkaz($r, $d, $x['jmeno'], 'color:inherit') ?> <span class="chip s-<?= $e($r['stav']) ?>"><?= $e($STAVY[$r['stav']] ?? $r['stav']) ?></span></h2>
    <div class="mut">Přijato <?= $e($r['vytvoreno']) ?><?= $x['prodavac'] ? ' · prodavač ' . $e($x['prodavac']) : '' ?></div>
    <p><?php [$lObj, $lNab] = bd_odkazy($r, $d); if ($r['typ'] === 'poptavka'): ?><a class="btn" href="<?= $e(bd_prod_url($lObj)) ?>">Vytvořit objednávku z této poptávky</a> <a class="btn sec" href="<?= $e(bd_prod_url($lNab)) ?>">Vytvořit nabídku z této poptávky</a> <?php endif; if ($r['typ'] === 'nabidka'): ?><a class="btn" href="<?= $e(bd_prod_url($lObj)) ?>">Vytvořit objednávku z této nabídky</a> <?php endif; ?>
      
      <?php if ($r['typ'] === 'nabidka' && !empty($d['polozky'])): foreach ($d['polozky'] as $pl): if (empty($pl['model_kod'])) continue;
        $o = array_filter(['cislo' => $d['obj_cislo'] ?? '', 'model_kod' => $pl['model_kod'], 'odstin' => $pl['odstin'] ?? '', 'jmeno' => $d['jmeno'] ?? '', 'prijmeni' => $d['prijmeni'] ?? '', 'telefon' => $d['telefon'] ?? '', 'email' => $d['email'] ?? '', 'ulice' => $d['ulice'] ?? '', 'mesto' => $d['mesto'] ?? '', 'psc' => $d['psc'] ?? ''], fn($v) => $v !== '' && $v !== null);
        $o = array_filter($o + (is_array($pl['cfg'] ?? null) ? $pl['cfg'] : []), fn($v) => $v !== '' && $v !== null);
        $u = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/') . '/objednavka-nova.html#d=' . rtrim(strtr(base64_encode(json_encode($o, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '='); ?>
        <a class="btn" href="<?= $e(bd_prod_url($u)) ?>">Objednávka: <?= $e($pl['co']) ?></a>
      <?php endforeach; endif; ?>
      <a class="btn sec" href="#puvodni">Původní zpráva</a> <?php if ($r['typ'] === 'objednavka_interni'): ?><form method="post" style="display:inline"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="akce" value="dvere_zaznam"><button class="btn sec">Záznam dveří / servis</button></form><?php endif; ?></p></div>
  <?php // rozpočet z jiného programu (PDF) a návaznost nabídka ↔ objednávka (z_nabidky)
  $vazby = []; $rz = is_array($d['rozpocet'] ?? null) ? $d['rozpocet'] : null;
  $dd2 = fn($k) => !empty($r[$k]) ? date('j. n. Y', strtotime((string)$r[$k])) : '';   // objednávka u dodavatele (stránka Dodavatel)
  $dodInfo = ($r['typ'] === 'objednavka_interni' && (!empty($r['dodavatel']) || !empty($r['dod_objednano']) || !empty($r['dod_termin']))) ? array_filter(['Dodavatel' => (string)($r['dodavatel'] ?? ''), 'č. objednávky u dodavatele' => (string)($r['dodavatel_ref'] ?? ''), 'objednáno' => $dd2('dod_objednano'), 'termín dodání' => $dd2('dod_termin'), 'dodáno k nám' => $dd2('dod_dodano')]) : [];
  try {
    if (!empty($d['z_nabidky'])) {
      $q2 = $pdo->prepare("SELECT id, cislo FROM zadosti WHERE typ='nabidka' AND cislo=? ORDER BY id DESC LIMIT 1"); $q2->execute([(string)$d['z_nabidky']]);
      $v2 = $q2->fetch(PDO::FETCH_ASSOC); $vazby[] = $v2 ? ['Nabídka ' . $v2['cislo'], (int)$v2['id']] : ['Nabídka ' . $d['z_nabidky'] . ' (v kartotéce nenalezena)', 0];
    }
    if ($r['typ'] === 'nabidka' && (string)$r['cislo'] !== '') {
      $q2 = $pdo->prepare("SELECT id, cislo FROM zadosti WHERE typ='objednavka_interni' AND data_json LIKE ? ORDER BY id");
      $q2->execute(['%"z_nabidky":' . json_encode((string)$r['cislo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '%']);
      foreach ($q2->fetchAll(PDO::FETCH_ASSOC) as $v2) $vazby[] = ['Objednávka ' . $v2['cislo'], (int)$v2['id']];
    }
  } catch (Throwable $ex) {}
  if ($vazby || $rz || $dodInfo): ?>
  <div class="box"><h3>Rozpočet, dodavatel a návaznosti</h3>
    <?php if ($rz): ?><p>📄 Rozpočet z jiného programu<?= !empty($rz['cislo']) ? ' č. ' . $e($rz['cislo']) : '' ?><?= !empty($rz['popis']) ? ' – ' . $e($rz['popis']) : '' ?><?= !empty($rz['soubor']) ? ' <span class="mut">(soubor ' . $e($rz['soubor']) . ' – je připojený v PDF v přílohách níže)</span>' : '' ?></p><?php endif; ?>
    <?php foreach ($vazby as [$t2, $id2]): ?><p>🔗 <?= $id2 ? '<a href="?id=' . $id2 . '">' . $e($t2) . '</a>' : $e($t2) ?></p><?php endforeach; ?>
    <?php if ($dodInfo): ?><p>🚚 <?= $e(implode(' · ', array_map(fn($k, $x) => $k . ': ' . $x, array_keys($dodInfo), $dodInfo))) ?> · <a href="dodavatel.php">stránka Dodavatel</a></p><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="grid">
    <div class="box"><h3>Zákazník</h3><?php $dl(['Jméno' => $x['jmeno'], 'Telefon' => $x['telefon'], 'E-mail' => $x['email'], 'Adresa montáže' => $adresa, 'Patro' => $patro, 'Doprava' => $doprava,
      'Fakturace' => $d['fakturace'] ?? '', 'Objednatel' => $d['fak_jmeno'] ?? '', 'Fakturační adresa' => $d['fak_adresa'] ?? '', 'IČ' => ($d['ic'] ?? '') . (!empty($d['platce_dph']) ? ' (plátce DPH)' : ''), 'Číslo OP' => $d['op'] ?? '']); ?><?php $zkK = zk_klic($d, $r); if ($zkK !== ''): ?><p style="margin:10px 0 0"><a class="btn sec" href="zakaznici.php?k=<?= $e(rawurlencode($zkK)) ?>">👤 Karta zákazníka</a> <span class="mut">historie, rozpracované, servis, poznámky</span></p><?php endif; ?></div>
    <div class="box"><h3>Dveře</h3><?php $dl(['Model' => $x['model'], 'Barva' => $d['odstin'] ?? '', 'Zárubeň' => $d['zarubne'] ?? '', 'Tl. zárubně' => $d['tl_zarubne'] ?? '', 'Kování' => $d['kovani'] ?? '', 'Kukátko' => $d['kukatko'] ?? '',
      'Vložky' => $d['vlozky'] ?? '', 'Otevírání' => $d['otevirani'] ?? '', 'Šířka' => $d['sire'] ?? '', 'Záruka' => $d['zaruka'] ?? '', 'Zeď' => trim(($d['zed'] ?? '') . (!empty($d['tl_zdi']) ? ', ' . $d['tl_zdi'] . ' cm' : ''), ', '),
      'Zvonek' => $d['zvonek'] ?? '', 'Termín montáže' => $d['termin'] ?? '']); ?></div>
    <div class="box"><h3>Cena</h3>
      <?php if (!empty($d['polozky'])): ?><table><?php foreach ($d['polozky'] as $l): ?><tr><td><?= $e(($l['co'] ?? '') . ': ' . ($l['volba'] ?? '')) ?></td><td class="num"><?= $e($l['priplatek'] ?? '') ?></td></tr><?php endforeach; ?></table><?php endif; ?>
      <p><b>Celkem: <?= $e($kc($x['cena'])) ?></b><?php if ($x['zaloha'] !== null): ?><br>Záloha: <?= $e($kc($x['zaloha'])) ?> · doplatek: <?= $e($kc($d['doplatek'] ?? null)) ?><?php endif; ?></p></div>
    <?php if (!empty($r['predano_dne'])): require_once __DIR__ . '/lib/bonus.php'; bonus_tabulky($pdo); $r = bonus_zadost($pdo, (int)$r['id']) ?: $r; $zefDo = zaruka_efektivni_do($pdo, $r); $bUd = bonus_udrzba($pdo, $r); $bMes = bonus_mesicu_celkem($r); ?><div class="box"><h3>Předání a záruka</h3><p><span class="chip s-vyrizeno">Předáno</span> <?= $e(date('j. n. Y', strtotime($r['predano_dne']))) ?></p><p>Záruka od <?= $e(date('j. n. Y', strtotime($r['zaruka_od']))) ?><?= !empty($zefDo) ? ' do ' . $e(date('j. n. Y', strtotime($zefDo))) : ' (délka záruky neurčena)' ?></p>
      <?php if ($bMes > 0 && empty($r['zaruka_prodl_let'])): ?><p class="mut">Bonus +<?= (int)$bMes ?> měs. (registrace<?= !empty($r['bonus_rec_schv_dne']) ? ' + recenze' : '' ?>): <?php if ($bUd['aktivni']): ?><b style="color:#14653a">platí</b> – další zápis o údržbě nejpozději do <?= $e(date('j. n. Y', strtotime($bUd['do']))) ?><?php else: ?><b style="color:#a11">ZANIKL</b> – zápis o údržbě nebyl proveden do <?= $e(date('j. n. Y', strtotime($bUd['do']))) ?>; bonusové měsíce (<?= (int)$bMes ?>) se do konce záruky už nezapočítávají<?php endif; ?><?= $bUd['posledni'] ? ' · poslední zápis ' . $e(date('j. n. Y', strtotime($bUd['posledni']))) : ' · zatím bez zápisu o údržbě' ?></p><?php endif; ?></div><?php endif; ?>
    <?php if ($r['typ'] === 'objednavka_interni'): ?><div class="box"><h3>Platba zálohy</h3>
      <p style="font-size:.9em;color:#555;margin:.2em 0 .6em">Platba převodem se spáruje sama podle VS <?= $e(preg_replace('/\D+/', '', (string)$x['cislo'])) ?>. Tady potvrzuj jen hotovost nebo ruční platbu.</p>
      <?php if (!empty($r['zaloha_prijata_dne'])): ?><p><span class="chip s-vyrizeno">Záloha přijata</span> <?= $e($kc($r['zaloha_prijata_kc'])) ?> · <?= $e(date('j. n. Y', strtotime($r['zaloha_prijata_dne']))) ?></p>
      <?php else: ?><p><span class="chip s-nove">Záloha nezaplacena</span><?= $x['zaloha'] ? ' · požadováno ' . $e($kc($x['zaloha'])) . ', VS ' . $e(preg_replace('/\D+/', '', (string)$x['cislo'])) : '' ?></p><?php endif; ?>
      <form method="post"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="akce" value="zaloha">
        <p><label>Přijatá částka (Kč) <input name="castka" size="9" value="<?= $e($r['zaloha_prijata_kc'] ?? $x['zaloha'] ?? '') ?>"></label>
        <label>Datum <input type="date" name="datum" value="<?= $e($r['zaloha_prijata_dne'] ?? date('Y-m-d')) ?>"></label></p>
        <p><label><input type="checkbox" name="poslat" value="1"<?= empty($r['zaloha_prijata_dne']) ? ' checked' : '' ?>> poslat zákazníkovi potvrzení e-mailem (<?= $e($x['email'] ?: 'e-mail chybí') ?>)</label></p>
        <button>Záloha přijata hotově</button> <?php if (!empty($r['zaloha_prijata_dne'])): ?><button name="zrusit" value="1" class="sec" onclick="return confirm('Zrušit evidenci přijaté zálohy?')">Zrušit evidenci</button><?php endif; ?></form></div><?php endif; ?>
    <div class="box"><h3>Poznámky</h3><?php $dl(['Pro zákazníka' => $d['poznamka'] ?? '', 'Interní (z formuláře)' => $d['interni'] ?? '', 'Z poptávky' => $d['z_poptavky'] ?? '']); ?>
      <form method="post"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <p><label>Stav: <select name="stav"><?php foreach ($STAVY as $k => $v): ?><option value="<?= $e($k) ?>"<?= $r['stav'] === $k ? ' selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></label></p>
        <p><textarea name="poznamka" rows="4" placeholder="Interní poznámka kartotéky (kdo volal, co se domluvilo…)"><?= $e($r['poznamka']) ?></textarea></p><button>Uložit</button></form></div>
  </div>
  <?php zamek_box($pdo, $r); ?>
  <?php zam_box($r); ?>
  <?php foto_box($r); ?>
  <?php $prilohy = array_filter(explode("\n", (string)$r['prilohy'])); if ($prilohy): ?><div class="box"><h3>Přílohy</h3>
    <?php foreach ($prilohy as $s): ?><p>📎 <a href="?soubor=<?= urlencode($s) ?>" target="_blank"><?= $e(preg_replace('/^\d+_[0-9a-f]{8}_/', '', $s)) ?></a></p><?php endforeach; ?></div><?php endif; ?>
  <div class="box" id="puvodni"><h3>Původní zpráva (<?= $e($r['predmet']) ?>)</h3><pre style="white-space:pre-wrap;font-family:inherit;margin:0"><?= $e(preg_replace('~\ndata_json[^\n]*~u', '', (string)$r['telo'])) ?></pre></div>
<?php } else:
// ══════════ SEZNAM ══════════
  $vse = nacti($pdo, $typ, $fStav, $od, $do);
  $modely = $prodavaci = [];
  foreach ($vse as $r) { if ($r['x']['model'] !== '') $modely[$r['x']['model']] = 1; if ($r['x']['prodavac'] !== '') $prodavaci[$r['x']['prodavac']] = 1; }
  $qn = odstran_diakritiku(mb_strtolower($q)); $rows = [];
  foreach ($vse as $r) { $x = $r['x'];
    if ($fModel !== '' && $x['model'] !== $fModel) continue; if ($fProd !== '' && $x['prodavac'] !== $fProd) continue;
    if ($qn !== '' && mb_strpos(odstran_diakritiku(hledany_text($r, $x)), $qn) === false) continue;
    $rows[] = $r; }
  $suma = 0; foreach ($rows as $r) if (is_numeric($r['x']['cena'])) $suma += $r['x']['cena'];
  $qs = fn($a) => '?' . http_build_query(array_filter(array_merge(['typ' => $typ, 'stav' => $fStav, 'model' => $fModel, 'prodavac' => $fProd, 'q' => $q, 'od' => $od, 'do' => $do], $a), fn($v) => $v !== ''));
?>
  <?php $webU = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/'); ?><p class="nove" style="display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 .8rem"><a class="btn" href="<?= $e($webU) ?>/objednavka-nova.html?sys=1<?= $e(bd_prod_p()) ?>">➕ Přidat objednávku</a><a class="btn" href="<?= $e($webU) ?>/poptavka-nova.html?sys=1<?= $e(bd_prod_p()) ?>">➕ Přidat poptávku</a><a class="btn" href="<?= $e($webU) ?>/nabidka-nova.html?sys=1<?= $e(bd_prod_p()) ?>">➕ Přidat nabídku</a></p>
  <div class="tabs"><a class="<?= $typ === 'objednavka_interni' ? 'on' : '' ?>" href="kartoteka.php?typ=objednavka_interni">Objednávky</a><a class="<?= $typ === 'poptavka' ? 'on' : '' ?>" href="kartoteka.php?typ=poptavka">Poptávky</a><a class="<?= $typ === 'nabidka' ? 'on' : '' ?>" href="kartoteka.php?typ=nabidka">Nabídky</a><a class="<?= $typ === '' ? 'on' : '' ?>" href="kartoteka.php?typ=">Vše</a></div>
  <form class="box f" method="get"><input type="hidden" name="typ" value="<?= $e($typ) ?>">
    <label>Hledat<input name="q" value="<?= $e($q) ?>" placeholder="jméno, město, telefon, číslo…" size="28"></label>
    <label>Stav<select name="stav"><option value="">všechny</option><?php foreach ($STAVY as $k => $v): ?><option value="<?= $e($k) ?>"<?= $fStav === $k ? ' selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></label>
    <label>Model<select name="model"><option value="">všechny</option><?php foreach (array_keys($modely) as $m): ?><option<?= $fModel === $m ? ' selected' : '' ?>><?= $e($m) ?></option><?php endforeach; ?></select></label>
    <label>Prodavač<select name="prodavac"><option value="">všichni</option><?php foreach (array_keys($prodavaci) as $m): ?><option<?= $fProd === $m ? ' selected' : '' ?>><?= $e($m) ?></option><?php endforeach; ?></select></label>
    <label>Od<input type="date" name="od" value="<?= $e($od) ?>"></label><label>Do<input type="date" name="do" value="<?= $e($do) ?>"></label>
    <button>Filtrovat</button><a class="btn sec" href="kartoteka.php?typ=<?= $e($typ) ?>">Zrušit</a><a class="btn sec" href="<?= $e($qs(['export' => '1'])) ?>">Export do Excelu (CSV)</a></form>
  <p class="mut"><?= count($rows) ?> záznamů<?= $suma ? ' · součet cen ' . $e($kc($suma)) : '' ?></p>
  <table><tr><th>Přijato</th><th>Číslo</th><th>Zákazník</th><th class="hide-m">Město</th><th>Model</th><th class="num">Cena</th><th class="hide-m">Prodavač</th><th>Stav</th><th>Akce</th></tr>
  <?php foreach ($rows as $r): $x = $r['x']; ?><tr>
    <td class="mut"><?= $e(substr($r['vytvoreno'], 0, 10)) ?></td>
    <td><a href="?id=<?= (int)$r['id'] ?>"><?= $e($x['cislo'] ?: '(bez čísla)') ?></a><?= $typ === '' ? '<div class="mut">' . $e($TYPY[$r['typ']] ?? '') . '</div>' : '' ?></td>
    <td><?= $zkOdkaz($r, $x['d'], $x['jmeno'] ?: $r['predmet']) ?><div class="mut"><?= $e($x['telefon']) ?></div></td><td class="hide-m"><?= $e($x['mesto']) ?></td><td><?= $e($x['model']) ?></td>
    <td class="num"><?= $e($kc($x['cena'])) ?></td><td class="hide-m"><?= $e($x['prodavac']) ?></td><td><span class="chip s-<?= $e($r['stav']) ?>"><?= $e($STAVY[$r['stav']] ?? $r['stav']) ?></span></td><td style="white-space:nowrap"><?php if (in_array($r['typ'], ['poptavka','nabidka'], true)) { [$lo, $ln] = bd_odkazy($r, $x['d']); ?><a class="btn sec" style="padding:4px 8px;font-size:13px" href="<?= $e($lo) ?>">Objednávka</a><?php if ($r['typ'] === 'poptavka'): ?> <a class="btn sec" style="padding:4px 8px;font-size:13px" href="<?= $e($ln) ?>">Nabídka</a><?php endif; } ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="mut">Nic nenalezeno.</td></tr><?php endif; ?></table>
<?php endif; ?></main><div id="instalace" style="display:none;max-width:760px;margin:20px auto;padding:14px 16px;background:#fff;border:1px solid #dbe3ee;border-radius:12px;font-family:system-ui,Arial,sans-serif">
  <button type="button" id="instalovat" style="width:100%;padding:14px;border:0;border-radius:10px;background:#123d27;color:#fff;font:600 17px system-ui,Arial,sans-serif;cursor:pointer">📲 Nainstalovat aplikaci Kartotéka</button>
  <p id="instalace-text" style="margin:8px 0 0;color:#475569;font-size:14px"></p>
</div>
<script>
(function () {
  var box = document.getElementById('instalace'), btn = document.getElementById('instalovat'), txt = document.getElementById('instalace-text'), ev = null;
  var hotovo = function () { box.style.display = ''; btn.style.display = 'none'; txt.textContent = 'Hotovo – ikona „Kartotéka“ je na ploše.'; };
  if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true) return;
  var ios = /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (ios) { box.style.display = ''; btn.style.display = 'none'; txt.innerHTML = '<b>Aplikace Kartotéka na iPhonu:</b> otevřete tuto stránku v Safari → tlačítko Sdílet (čtvereček se šipkou) → <b>Přidat na plochu</b>.'; return; }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; box.style.display = ''; btn.style.display = ''; txt.textContent = 'Kartotéka se přidá na plochu jako ikona „Kartotéka“ a otevírá se bez lišty prohlížeče a bez horního menu.'; });
  btn.addEventListener('click', function () {
    if (!ev) return;
    ev.prompt();
    ev.userChoice.then(function (r) { ev = null; if (r && r.outcome === 'accepted') hotovo(); else box.style.display = 'none'; });
  });
  window.addEventListener('appinstalled', hotovo);
})();
</script>
</body></html>
