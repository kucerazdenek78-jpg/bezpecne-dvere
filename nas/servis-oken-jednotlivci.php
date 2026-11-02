<?php
// Servis oken – JEDNOTLIVCI (zkušební verze 2. 11. 2026). Servis u jednoho zákazníka (byt, dům): založení ručně, z poptávky
// „Servis oken“ z webu nebo z karty zákazníka; zápis v mobilu (kontroly, okna, závady, návrh opravy, cena, podpis, fotky),
// protokol v PDF (tisk / e-mail zákazníkovi) a připomenutí dalšího servisu (za 1 / 2 roky). Stejná ochrana jako kartoteka.php.
// Data: tabulka so_jed, fotky, podpis a protokoly v $DATA/servis-oken/j<id>/. Zákazník se páruje klíčem z Zákazníků (lib/zakaznici.php);
// když v Zákaznících ještě není, založí se tam jako ručně přidaný.
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
require_once __DIR__ . '/lib/menu.php';
require_once __DIR__ . '/lib/servis-oken.php';
require_once __DIR__ . '/lib/zakaznici.php';
$pdo = db(); so_tabulky($pdo); sj_tabulka($pdo); zk_tabulka($pdo);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, 0, ',', ' ') . ' Kč';
$ted = date('Y-m-d H:i:s'); $dnes = date('Y-m-d');
$P = fn($k, $max = 500) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)($_POST[$k] ?? ''))), 0, $max);
$cis = function ($v) { $v = str_replace([' ', "\u{a0}", ',', 'Kč', 'kč'], ['', '', '.', '', ''], trim((string)$v)); return $v === '' || !is_numeric($v) ? null : (float)$v; };
$web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
const SJ_STAVY = ['nova' => 'K vyřízení', 'hotovo' => 'Hotovo', 'zruseno' => 'Zrušeno'];
const SJ_OPAKOVAT = [0 => 'neopakovat', 1 => 'za 1 rok', 2 => 'za 2 roky'];

function sj_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_jed (id INTEGER PRIMARY KEY AUTOINCREMENT, klic TEXT, jmeno TEXT NOT NULL, telefon TEXT, email TEXT, ulice TEXT, mesto TEXT, psc TEXT,
    zadost_id INTEGER, predchozi_id INTEGER, popis TEXT, stav TEXT DEFAULT 'nova', termin TEXT, cas TEXT,
    k_kovani INTEGER DEFAULT 0, k_ramy INTEGER DEFAULT 0, k_tesneni INTEGER DEFAULT 0, k_zlabky INTEGER DEFAULT 0, zkratky TEXT,
    o3 INTEGER DEFAULT 0, o2 INTEGER DEFAULT 0, o1 INTEGER DEFAULT 0, bd INTEGER DEFAULT 0, vk INTEGER DEFAULT 0, dv INTEGER DEFAULT 0,
    zavada TEXT, oprava TEXT, max_cena REAL, cena REAL, opraveno_hned TEXT, podpis TEXT, podepsal TEXT, technik TEXT, provedeno TEXT,
    opakovat INTEGER DEFAULT 0, protokol TEXT, protokol_poslan TEXT, poznamka TEXT, vytvoril TEXT, vytvoreno TEXT, upraveno TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_jed_klic ON so_jed(klic)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_jed_zadost ON so_jed(zadost_id)');
}
function sj_dir(int $id): string { global $DATA; return $DATA . '/servis-oken/j' . $id; }
function sj_fotky(int $id): array {
  $o = [];
  foreach (glob(sj_dir($id) . '/*.jpg') ?: [] as $f) { $n = basename($f); if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) $o[] = $n; }
  sort($o); return $o;
}
function sj_nacti(PDO $pdo, int $id): ?array { $st = $pdo->prepare('SELECT * FROM so_jed WHERE id=?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function sj_dalsi(array $r): string {   // kdy je čas na další servis (jen hotové s opakováním)
  if ($r['stav'] !== 'hotovo' || (int)$r['opakovat'] <= 0) return '';
  $t = strtotime((string)($r['provedeno'] ?: $r['termin'] ?: $r['upraveno'])); return $t ? date('Y-m-d', strtotime('+' . (int)$r['opakovat'] . ' years', $t)) : '';
}
function sj_adresa(array $r): string { return implode(', ', array_filter([trim((string)$r['ulice']), trim(trim((string)$r['psc']) . ' ' . trim((string)$r['mesto']))])); }
function sj_jdi(string $url): void { header('Location: ' . $url); exit; }
function sj_json(array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
// stav poptávky v kartotéce (jen když nejde o storno / vyřízeno)
function sj_poptavka_stav(PDO $pdo, ?int $zid, string $stav): void {
  if (!$zid) return;
  try {
    $st = $pdo->prepare('SELECT stav FROM zadosti WHERE id=?'); $st->execute([$zid]); $s = (string)$st->fetchColumn();
    if ($s === '' || in_array($s, ['storno', 'vyrizeno'], true) || $s === $stav) return;
    if ($stav === 'rozpracovano' && $s !== 'nove') return;
    $pdo->prepare('UPDATE zadosti SET stav=? WHERE id=?')->execute([$stav, $zid]);
    if (function_exists('hist_pis')) hist_pis($pdo, $zid, 'servis oken: stav ' . ($stav === 'vyrizeno' ? 'Vyřízeno' : 'Rozpracováno'), function_exists('uziv_jmeno') ? uziv_jmeno() : '');
  } catch (Throwable $x) {}
}

// ───── fotky, podpis, protokol (zobrazení) ─────
if (isset($_GET['foto']) || isset($_GET['podpis']) || isset($_GET['pdf'])) {
  $id = (int)($_GET['foto'] ?? $_GET['podpis'] ?? $_GET['pdf']);
  if (isset($_GET['podpis'])) { $c = sj_dir($id) . '/podpis.png'; $typ = 'image/png'; }
  elseif (isset($_GET['pdf'])) { $r = sj_nacti($pdo, $id); $n = (string)($r['protokol'] ?? ''); if (!preg_match('/^protokol-[\w-]+\.pdf$/', $n)) { http_response_code(404); exit; } $c = sj_dir($id) . '/' . $n; $typ = 'application/pdf'; }
  else {
    $n = (string)($_GET['f'] ?? ''); if (!preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { http_response_code(404); exit; }
    if (!empty($_GET['nahled'])) $n = substr($n, 0, 16) . '_n.jpg';
    $c = sj_dir($id) . '/' . $n; $typ = 'image/jpeg';
  }
  if (!is_file($c)) { http_response_code(404); exit('Nenalezeno'); }
  header('Content-Type: ' . $typ); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=600'); readfile($c); exit;
}

// ───── hledání zákazníka (JSON pro formulář) ─────
if (isset($_GET['hledat'])) {
  $q = mb_strtolower(trim((string)$_GET['hledat'])); $qt = preg_replace('/\D+/', '', $q); $o = [];
  if (mb_strlen($q) >= 2) foreach (zk_vsichni($pdo, zk_info($pdo)) as $c) {
    $txt = mb_strtolower($c['jmeno'] . ' ' . $c['firma'] . ' ' . $c['ulice'] . ' ' . $c['mesto'] . ' ' . $c['email']);
    if (mb_strpos($txt, $q) === false && !(strlen($qt) >= 4 && strpos(preg_replace('/\D+/', '', $c['telefon']), $qt) !== false)) continue;
    $o[] = ['klic' => $c['klic'], 'jmeno' => $c['jmeno'] ?: $c['firma'], 'telefon' => $c['telefon'], 'email' => $c['email'], 'ulice' => $c['ulice'], 'mesto' => $c['mesto'], 'psc' => $c['psc']];
    if (count($o) >= 12) break;
  }
  sj_json(['ok' => true, 'zakaznici' => $o]);
}

// ───── akce (POST) ─────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $a = $_POST['akce'] ?? '';
  if ($a === 'zalozit' || $a === 'udaje_ulozit') {
    $id = (int)($_POST['id'] ?? 0);
    $f = ['jmeno' => $P('jmeno', 200), 'telefon' => $P('telefon', 60), 'email' => $P('email', 200), 'ulice' => $P('ulice', 200), 'mesto' => $P('mesto', 120), 'psc' => $P('psc', 10),
      'popis' => $P('popis', 3000), 'termin' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $P('termin', 10)) ? $P('termin', 10) : null, 'cas' => $P('cas', 40), 'poznamka' => $P('poznamka', 3000)];
    if ($f['jmeno'] === '') sj_jdi('servis-oken-jednotlivci.php?' . ($id ? 'id=' . $id . '&' : 'nove=1&') . 'chyba=jmeno');
    // zákazník v Zákaznících: vybraný klíč, jinak klíč z telefonu / e-mailu / jména; když tam ještě není, přidá se jako ručně přidaný
    $klic = $P('klic', 120);
    if ($klic === '') $klic = zk_klic(['telefon' => $f['telefon'], 'email' => $f['email'], 'jmeno' => $f['jmeno'], 'mesto' => $f['mesto']], []);
    if ($klic !== '') {
      $vse = zk_vsichni($pdo, zk_info($pdo), true);
      if (!isset($vse[$klic])) {
        $pdo->prepare('INSERT OR IGNORE INTO zakaznik_info (klic) VALUES (?)')->execute([$klic]);
        $pdo->prepare('UPDATE zakaznik_info SET jmeno=?, telefon=?, email=?, ulice=?, mesto=?, psc=?, rucne=?, zdroj=?, smazano=NULL, upraveno=? WHERE klic=?')
          ->execute([$f['jmeno'], $f['telefon'], $f['email'], $f['ulice'], $f['mesto'], $f['psc'], '1', 'servis oken', date('Y-m-d H:i'), $klic]);
      }
    }
    if ($id) {
      $pdo->prepare('UPDATE so_jed SET klic=?, jmeno=?, telefon=?, email=?, ulice=?, mesto=?, psc=?, popis=?, termin=?, cas=?, poznamka=?, upraveno=? WHERE id=?')
        ->execute([$klic, $f['jmeno'], $f['telefon'], $f['email'], $f['ulice'], $f['mesto'], $f['psc'], $f['popis'], $f['termin'], $f['cas'], $f['poznamka'], $ted, $id]);
      sj_jdi('servis-oken-jednotlivci.php?id=' . $id . '&ok=ulozeno');
    }
    $zid = (int)($_POST['zadost_id'] ?? 0) ?: null; $pred = (int)($_POST['predchozi_id'] ?? 0) ?: null;
    if ($zid) { $st = $pdo->prepare('SELECT id FROM so_jed WHERE zadost_id=?'); $st->execute([$zid]); if ($x = (int)$st->fetchColumn()) sj_jdi('servis-oken-jednotlivci.php?id=' . $x); }
    $okna = array_fill_keys(array_keys(SO_OKNA), 0);
    if ($pred && ($p = sj_nacti($pdo, $pred))) foreach ($okna as $k => $_) $okna[$k] = (int)$p[$k];   // počty oken z minula
    $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, email, ulice, mesto, psc, zadost_id, predchozi_id, popis, stav, termin, cas, poznamka, o3, o2, o1, bd, vk, dv, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$klic, $f['jmeno'], $f['telefon'], $f['email'], $f['ulice'], $f['mesto'], $f['psc'], $zid, $pred, $f['popis'], 'nova', $f['termin'], $f['cas'], $f['poznamka'],
        $okna['o3'], $okna['o2'], $okna['o1'], $okna['bd'], $okna['vk'], $okna['dv'], uziv_jmeno(), $ted, $ted]);
    $nid = (int)$pdo->lastInsertId();
    sj_poptavka_stav($pdo, $zid, 'rozpracovano');
    sj_jdi('servis-oken-jednotlivci.php?id=' . $nid . '&ok=zalozeno');
  }
  if ($a === 'dalsi_zalozit') {   // další servis podle opakování (zkopíruje zákazníka a počty oken)
    $p = sj_nacti($pdo, (int)$_POST['id']); if (!$p) sj_jdi('servis-oken-jednotlivci.php');
    $st = $pdo->prepare('SELECT id FROM so_jed WHERE predchozi_id=?'); $st->execute([$p['id']]); if ($x = (int)$st->fetchColumn()) sj_jdi('servis-oken-jednotlivci.php?id=' . $x);
    $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, email, ulice, mesto, psc, predchozi_id, popis, stav, opakovat, o3, o2, o1, bd, vk, dv, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$p['klic'], $p['jmeno'], $p['telefon'], $p['email'], $p['ulice'], $p['mesto'], $p['psc'], $p['id'], 'Pravidelný servis (předchozí ' . so_datum($p['provedeno'] ?: $p['termin']) . ')', 'nova', (int)$p['opakovat'],
        $p['o3'], $p['o2'], $p['o1'], $p['bd'], $p['vk'], $p['dv'], uziv_jmeno(), $ted, $ted]);
    sj_jdi('servis-oken-jednotlivci.php?id=' . (int)$pdo->lastInsertId() . '&ok=zalozeno');
  }
  if ($a === 'zapis_ulozit') {
    $id = (int)$_POST['id']; $r = sj_nacti($pdo, $id); if (!$r) sj_jdi('servis-oken-jednotlivci.php');
    $stav = isset(SJ_STAVY[$_POST['stav'] ?? '']) ? $_POST['stav'] : 'nova';
    $zk = array_values(array_intersect(array_keys(SO_ZKRATKY), (array)($_POST['zkratky'] ?? [])));
    $pod = (string)$r['podpis']; $dataUrl = (string)($_POST['podpis_data'] ?? '');
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) && strlen($m[1]) < 2000000) {
      $bin = base64_decode($m[1]); $inf = @getimagesizefromstring($bin);
      if ($inf && $inf[2] === IMAGETYPE_PNG) { $dir = sj_dir($id); if (!is_dir($dir)) @mkdir($dir, 0775, true); if (@file_put_contents($dir . '/podpis.png', $bin)) $pod = 'podpis.png'; }
    }
    if (!empty($_POST['podpis_smazat'])) { @unlink(sj_dir($id) . '/podpis.png'); $pod = ''; }
    $prov = preg_match('/^\d{4}-\d{2}-\d{2}$/', $P('provedeno', 10)) ? $P('provedeno', 10) : null;
    if ($stav === 'hotovo' && !$prov) $prov = $r['provedeno'] ?: $dnes;
    $okna = []; foreach (array_keys(SO_OKNA) as $k) $okna[] = max(0, (int)($_POST[$k] ?? 0));
    $pdo->prepare('UPDATE so_jed SET stav=?, k_kovani=?, k_ramy=?, k_tesneni=?, k_zlabky=?, zkratky=?, o3=?, o2=?, o1=?, bd=?, vk=?, dv=?, zavada=?, oprava=?, max_cena=?, cena=?, opraveno_hned=?, podpis=?, podepsal=?, technik=?, provedeno=?, opakovat=?, upraveno=? WHERE id=?')
      ->execute(array_merge([$stav, empty($_POST['k_kovani']) ? 0 : 1, empty($_POST['k_ramy']) ? 0 : 1, empty($_POST['k_tesneni']) ? 0 : 1, empty($_POST['k_zlabky']) ? 0 : 1, implode(',', $zk)], $okna,
        [$P('zavada', 3000), $P('oprava', 3000), $cis($_POST['max_cena'] ?? ''), $cis($_POST['cena'] ?? ''), $P('opraveno_hned', 1000), $pod, $P('podepsal', 120),
         $stav === 'hotovo' ? ($r['technik'] ?: uziv_jmeno()) : (string)$r['technik'], $prov, isset(SJ_OPAKOVAT[(int)($_POST['opakovat'] ?? 0)]) ? (int)$_POST['opakovat'] : 0, $ted, $id]));
    if ($stav === 'hotovo') sj_poptavka_stav($pdo, $r['zadost_id'] ? (int)$r['zadost_id'] : null, 'vyrizeno');
    sj_jdi('servis-oken-jednotlivci.php?id=' . $id . '&ok=ulozeno' . ($stav === 'hotovo' && $r['stav'] !== 'hotovo' ? '#protokol' : ''));
  }
  if ($a === 'foto_nahraj') {
    $id = (int)$_POST['id']; if (!sj_nacti($pdo, $id)) sj_json(['ok' => false, 'zprava' => 'Servis nenalezen.']);
    $dir = sj_dir($id);
    $ok = function ($f) { return $f && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) && filesize($f['tmp_name']) < 6 * 1048576 && (@getimagesize($f['tmp_name'])[2] ?? 0) === IMAGETYPE_JPEG; };
    if (count(sj_fotky($id)) >= 30) sj_json(['ok' => false, 'zprava' => 'Maximum 30 fotek.']);
    if (!$ok($_FILES['foto'] ?? null) || !$ok($_FILES['nahled'] ?? null)) sj_json(['ok' => false, 'zprava' => 'Soubor není fotka JPEG.']);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) sj_json(['ok' => false, 'zprava' => 'Nelze vytvořit složku.']);
    $jm = bin2hex(random_bytes(8));
    $hotovo = move_uploaded_file($_FILES['foto']['tmp_name'], "$dir/$jm.jpg") && move_uploaded_file($_FILES['nahled']['tmp_name'], "$dir/{$jm}_n.jpg");
    sj_json(['ok' => $hotovo, 'zprava' => $hotovo ? '' : 'Fotku se nepodařilo uložit.']);
  }
  if ($a === 'foto_smaz') {
    uziv_jen_admin('Fotku smí smazat jen administrátor.');
    $id = (int)$_POST['id']; $n = (string)$_POST['f'];
    if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { @unlink(sj_dir($id) . "/$n"); @unlink(sj_dir($id) . '/' . substr($n, 0, 16) . '_n.jpg'); }
    sj_jdi('servis-oken-jednotlivci.php?id=' . $id . '#fotky');
  }
  if ($a === 'protokol') {   // PDF sestavené v prohlížeči: uložit, případně poslat zákazníkovi
    $id = (int)$_POST['id']; $r = sj_nacti($pdo, $id); if (!$r) sj_json(['ok' => false, 'zprava' => 'Servis nenalezen.']);
    $up = $_FILES['pdf'] ?? null;
    if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || ($up['size'] ?? 0) < 1000 || $up['size'] > 12 * 1048576) sj_json(['ok' => false, 'zprava' => 'PDF se nepřeneslo.']);
    $obsah = (string)file_get_contents($up['tmp_name']); if (strncmp($obsah, '%PDF', 4) !== 0) sj_json(['ok' => false, 'zprava' => 'Soubor není PDF.']);
    $dir = sj_dir($id); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $nazev = 'protokol-' . date('Ymd-His') . '.pdf';
    if (@file_put_contents($dir . '/' . $nazev, $obsah) === false) sj_json(['ok' => false, 'zprava' => 'PDF se nepodařilo uložit na NAS.']);
    $pdo->prepare('UPDATE so_jed SET protokol=? WHERE id=?')->execute([$nazev, $id]);
    $poslano = null;
    if (!empty($_POST['poslat'])) {
      $em = trim((string)$r['email']);
      if (!filter_var($em, FILTER_VALIDATE_EMAIL)) sj_json(['ok' => false, 'zprava' => 'Zákazník nemá platný e-mail. PDF je uložené.']);
      require_once __DIR__ . '/lib/posta.php';
      $kdy = so_datum($r['provedeno'] ?: $r['termin']);
      $telo = "Dobrý den,\n\nděkujeme, že jste využili náš servis oken. V příloze zasíláme protokol o provedeném servisu" . ($kdy ? " ze dne $kdy" : '') . ".\n"
        . (trim((string)$r['oprava']) !== '' ? "V protokolu najdete i zjištěné závady a návrh opravy s maximální cenou. Pokud máte o opravu zájem, stačí odpovědět na tento e-mail nebo zavolat.\n" : '')
        . ((int)$r['opakovat'] > 0 && ($dl = sj_dalsi($r)) ? 'Další servis doporučujeme ' . ((int)$r['opakovat'] === 1 ? 'za rok' : 'za 2 roky') . ' (kolem ' . so_datum($dl) . "), včas se Vám ozveme.\n" : '')
        . "\nKdybyste cokoli potřebovali, ozvěte se: +420 725 559 235, info@kksystem.cz\n\nKK SYSTEM · Bezpečné dveře\nwww.bezpecne-dvere.cz";
      $poslano = posli_mail($cfg, $DATA, [$em], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], 'Protokol o servisu oken' . ($kdy ? ' – ' . $kdy : ''), $telo, [[$dir . '/' . $nazev, 'protokol-servis-oken.pdf']]);
      if ($poslano) $pdo->prepare('UPDATE so_jed SET protokol_poslan=? WHERE id=?')->execute([$ted . ' → ' . $em, $id]);
    }
    sj_json(['ok' => $poslano !== false, 'zprava' => $poslano === false ? 'E-mail se nepodařilo odeslat. PDF je uložené.' : '', 'url' => 'servis-oken-jednotlivci.php?pdf=' . $id . '&t=' . time()]);
  }
  if ($a === 'smazat') {
    uziv_jen_admin('Servis smí smazat jen administrátor.');
    $id = (int)$_POST['id']; $pdo->prepare('DELETE FROM so_jed WHERE id=?')->execute([$id]);
    $pdo->prepare('UPDATE so_jed SET predchozi_id=NULL WHERE predchozi_id=?')->execute([$id]);
    sj_jdi('servis-oken-jednotlivci.php?ok=smazano');
  }
  http_response_code(400); exit('Neznámá akce');
}

// ───── zobrazení ─────
$id = (int)($_GET['id'] ?? 0);
$OK = ['ulozeno' => 'Uloženo.', 'smazano' => 'Smazáno.', 'zalozeno' => 'Servis založen.'];
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><title>Servis oken – jednotlivci – Bezpečné dveře</title><style>
body{font:16px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:1000px;margin:0 auto;padding:14px}
.box{background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{margin:4px 0 10px;font-size:24px}h2{margin:16px 0 8px;font-size:19px}h3{margin:6px 0 8px;font-size:17px}
.mut{color:#5f6b7a;font-size:14px}a{color:#1f5fbf}.zk{background:#fff4d6;border:1px solid #f0cf7a;color:#6b4a00;border-radius:10px;padding:8px 12px;margin-bottom:12px;font-size:14px}
button,.btn{display:inline-block;background:#1f5fbf;color:#fff;border:0;border-radius:10px;padding:10px 16px;font:600 16px system-ui,Arial,sans-serif;cursor:pointer;text-decoration:none}
button.sec,.btn.sec{background:#e6ebf3;color:#1b2430}button.del{background:#fde2e2;color:#9b1c1c}button.ok,.btn.ok{background:#15803d}button:disabled{opacity:.6}
input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #c9d1dc;border-radius:9px;background:#fff;box-sizing:border-box;max-width:100%}textarea{width:100%;min-height:70px}
.ln{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:flex-end;margin:8px 0}.ln label{display:flex;flex-direction:column;font-size:13px;color:#556;gap:3px}.ln label.w{flex:1 1 260px}.ln label.w input{width:100%}
table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e3e8ef;text-align:left;vertical-align:top;font-size:15px}th{font-size:13px;color:#556;background:#f6f8fb}
.msg{background:#e6f6ea;border:1px solid #9bd2a8;border-radius:10px;padding:10px 14px;margin-bottom:14px}.err{background:#fde2e2;border-color:#f3a7a7}
.st{display:inline-block;font-size:13px;border-radius:999px;padding:3px 10px;background:#e6ebf3;white-space:nowrap}.st-hotovo{background:#d9f2e0;color:#14653a}.st-nova{background:#fff0cc;color:#7a5200}.st-zruseno{background:#fde2e2;color:#9b1c1c}
.tabs{display:flex;gap:6px;margin:0 0 12px}.tabs a{padding:9px 14px;border-radius:10px;background:#e6ebf3;color:#1b2430;text-decoration:none;font-weight:600}.tabs a.on{background:#1f5fbf;color:#fff}
.stavy{display:flex;flex-wrap:wrap;gap:8px}.stavy label{flex:1 1 140px;border:2px solid #c9d1dc;border-radius:12px;padding:12px;text-align:center;font-weight:600;cursor:pointer}.stavy input{display:none}.stavy label:has(input:checked){border-color:#1f5fbf;background:#e8f0fd}
.chk label{display:flex;align-items:center;gap:10px;padding:9px 0;font-size:17px}.chk input{width:24px;height:24px}
.okna{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:8px}.okna label{display:flex;flex-direction:column;font-size:13px;color:#556}.okna input{width:100%}
canvas#podpis{width:100%;height:180px;border:2px dashed #9aa7b8;border-radius:12px;background:#fff;touch-action:none}
details>summary{cursor:pointer;color:#1f5fbf;font-weight:600;margin:6px 0}.r{text-align:right}.nowrap{white-space:nowrap}
#vysl{border:1px solid #c9d1dc;border-radius:10px;margin-top:4px;background:#fff;max-height:300px;overflow:auto}#vysl div{padding:9px 12px;border-bottom:1px solid #eef1f5;cursor:pointer}#vysl div:hover{background:#eef3fb}
</style></head><body><?php bd_bar('servisoken'); ?><main>
<div class="zk">🧪 <b>Zkušební verze Servisu oken – jednotlivci.</b> Data se ukládají naostro do kartotéky, postup ještě dolaďujeme.</div>
<?php if (isset($_GET['ok'], $OK[$_GET['ok']])): ?><div class="msg"><?= $e($OK[$_GET['ok']]) ?></div><?php endif; ?>
<?php if (($_GET['chyba'] ?? '') === 'jmeno'): ?><div class="msg err">Vyplňte jméno zákazníka.</div><?php endif; ?>
<?php
// ═════════ NOVÝ SERVIS ═════════
if (isset($_GET['nove'])):
  $f = ['klic' => '', 'jmeno' => '', 'telefon' => '', 'email' => '', 'ulice' => '', 'mesto' => '', 'psc' => '', 'popis' => '', 'zadost_id' => 0, 'puvod' => ''];
  if ($zid = (int)($_GET['z'] ?? 0)) {   // z poptávky v kartotéce
    $st = $pdo->prepare('SELECT id FROM so_jed WHERE zadost_id=?'); $st->execute([$zid]);
    if ($x = (int)$st->fetchColumn()) { echo '<script>location.replace("servis-oken-jednotlivci.php?id=' . $x . '")</script>'; exit; }
    $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$zid]); $zr = $st->fetch(PDO::FETCH_ASSOC);
    if ($zr) { $d = json_decode((string)$zr['data_json'], true) ?: [];
      $f = ['klic' => zk_klic($d, $zr), 'jmeno' => trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')), 'telefon' => (string)($d['telefon'] ?? ''), 'email' => (string)($d['email'] ?? $zr['email'] ?? ''),
        'ulice' => (string)($d['ulice'] ?? ''), 'mesto' => (string)($d['mesto'] ?? ''), 'psc' => (string)($d['psc'] ?? ''), 'popis' => trim((string)($d['poznamka'] ?? '')), 'zadost_id' => $zid,
        'puvod' => 'z poptávky ' . (string)($d['cislo'] ?? $zr['cislo'] ?? $zid)]; }
  } elseif (($k = (string)($_GET['k'] ?? '')) !== '') {   // z karty zákazníka
    $vse = zk_vsichni($pdo, zk_info($pdo), true);
    if (isset($vse[$k])) { $c = $vse[$k]; $f = array_merge($f, ['klic' => $k, 'jmeno' => $c['jmeno'] ?: $c['firma'], 'telefon' => $c['telefon'], 'email' => $c['email'], 'ulice' => $c['ulice'], 'mesto' => $c['mesto'], 'psc' => $c['psc'], 'puvod' => 'ze Zákazníků']); }
  } ?>
<p><a href="servis-oken-jednotlivci.php">← Jednotlivci</a></p>
<h1>Nový servis oken u zákazníka</h1>
<?php if ($f['puvod']): ?><p class="mut">Údaje převzaty <?= $e($f['puvod']) ?> – zkontrolujte je.</p><?php endif; ?>
<?php if (!$f['jmeno']): ?><div class="box"><label class="mut">Najít zákazníka v kartotéce (jméno, telefon, obec…)<input id="hledej" autocomplete="off" style="width:100%" placeholder="např. Novák nebo 725…"></label><div id="vysl" hidden></div>
<p class="mut" style="margin-bottom:0">Nový zákazník? Stačí vyplnit údaje níže – přidá se do Zákazníků sám.</p></div><?php endif; ?>
<form method="post" class="box"><input type="hidden" name="akce" value="zalozit"><input type="hidden" name="klic" id="klic" value="<?= $e($f['klic']) ?>"><input type="hidden" name="zadost_id" value="<?= (int)$f['zadost_id'] ?>">
<div class="ln"><label class="w">Jméno a příjmení *<input name="jmeno" id="f_jmeno" value="<?= $e($f['jmeno']) ?>" required></label><label>Telefon<input name="telefon" id="f_telefon" value="<?= $e($f['telefon']) ?>" inputmode="tel"></label><label class="w">E-mail<input name="email" id="f_email" value="<?= $e($f['email']) ?>" type="email"></label></div>
<div class="ln"><label class="w">Ulice a číslo<input name="ulice" id="f_ulice" value="<?= $e($f['ulice']) ?>"></label><label class="w">Obec<input name="mesto" id="f_mesto" value="<?= $e($f['mesto']) ?>"></label><label>PSČ<input name="psc" id="f_psc" value="<?= $e($f['psc']) ?>" size="6" inputmode="numeric"></label></div>
<label class="mut">Co zákazník potřebuje<textarea name="popis"><?= $e($f['popis']) ?></textarea></label>
<div class="ln"><label>Termín<input type="date" name="termin"></label><label>Čas<input name="cas" placeholder="např. 9:00" size="10"></label><label class="w">Poznámka (interní)<input name="poznamka"></label></div>
<button class="ok">Založit servis</button></form>
<script>
(function () {
  var h = document.getElementById('hledej'), v = document.getElementById('vysl'), t = null; if (!h) return;
  function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  h.addEventListener('input', function () {
    clearTimeout(t); var q = h.value.trim(); if (q.length < 2) { v.hidden = true; return; }
    t = setTimeout(function () {
      fetch('servis-oken-jednotlivci.php?hledat=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        v.innerHTML = j.zakaznici.length ? '' : '<div class="mut">Nic nenalezeno – vyplňte nového zákazníka níže.</div>';
        j.zakaznici.forEach(function (c) {
          var d = document.createElement('div'); d.innerHTML = '<b>' + esc(c.jmeno) + '</b> <span class="mut">' + esc([c.telefon, [c.ulice, c.mesto].filter(Boolean).join(', ')].filter(Boolean).join(' · ')) + '</span>';
          d.addEventListener('click', function () { document.getElementById('klic').value = c.klic; ['jmeno', 'telefon', 'email', 'ulice', 'mesto', 'psc'].forEach(function (k) { document.getElementById('f_' + k).value = c[k] || ''; }); v.hidden = true; h.value = c.jmeno; });
          v.appendChild(d);
        });
        v.hidden = false;
      });
    }, 250);
  });
  // ruční změna jména / telefonu = jiný zákazník → klíč se určí znovu z vyplněných údajů
  ['f_jmeno', 'f_telefon', 'f_email'].forEach(function (i) { document.getElementById(i).addEventListener('change', function () { document.getElementById('klic').value = ''; }); });
})();
</script>
<?php
// ═════════ DETAIL / ZÁPIS ═════════
elseif ($id):
  $r = sj_nacti($pdo, $id);
  if (!$r): ?><p>Servis nenalezen. <a href="servis-oken-jednotlivci.php">Zpět</a></p><?php else:
  $zk = array_filter(explode(',', (string)$r['zkratky'])); $fot = sj_fotky($id); $dl = sj_dalsi($r);
  $pred = $r['predchozi_id'] ? sj_nacti($pdo, (int)$r['predchozi_id']) : null;
  $st = $pdo->prepare('SELECT id FROM so_jed WHERE predchozi_id=?'); $st->execute([$id]); $nasl = (int)$st->fetchColumn();
  $hist = []; if ($r['klic']) { $st = $pdo->prepare('SELECT id, stav, termin, provedeno FROM so_jed WHERE klic=? AND id<>? ORDER BY COALESCE(provedeno, termin, vytvoreno) DESC'); $st->execute([$r['klic'], $id]); $hist = $st->fetchAll(PDO::FETCH_ASSOC); } ?>
<p><a href="servis-oken-jednotlivci.php">← Jednotlivci</a></p>
<div class="box"><h1 style="margin-bottom:4px"><?= $r['klic'] ? '<a href="zakaznici.php?k=' . $e(rawurlencode($r['klic'])) . '" style="color:inherit" title="Karta zákazníka">' . $e($r['jmeno']) . '</a>' : $e($r['jmeno']) ?> <span class="st st-<?= $e($r['stav']) ?>"><?= $e(SJ_STAVY[$r['stav']] ?? $r['stav']) ?></span></h1>
<div style="font-size:17px"><?= $e(sj_adresa($r)) ?><?= function_exists('bd_mapa') ? bd_mapa(sj_adresa($r)) : '' ?></div>
<div><?= $r['telefon'] ? '📞 <a href="tel:' . $e(preg_replace('/[^\d+]/', '', $r['telefon'])) . '">' . $e($r['telefon']) . '</a>' : '' ?><?= $r['email'] ? ' · ✉ <a href="mailto:' . $e($r['email']) . '">' . $e($r['email']) . '</a>' : '' ?></div>
<?php if ($r['termin']): ?><p style="margin:6px 0 0"><b>Termín:</b> <?= $e(so_datum($r['termin'])) ?> <?= $e($r['cas']) ?></p><?php endif; ?>
<?php if ($r['popis']): ?><p style="margin:6px 0 0"><b>Požadavek:</b> <?= nl2br($e($r['popis'])) ?></p><?php endif; ?>
<?php if ($r['poznamka']): ?><p class="mut" style="margin:6px 0 0">📝 <?= $e($r['poznamka']) ?></p><?php endif; ?>
<p class="mut" style="margin:6px 0 0"><?= $r['zadost_id'] ? '<a href="kartoteka.php?id=' . (int)$r['zadost_id'] . '">Poptávka z webu</a> · ' : '' ?><?= $pred ? '<a href="?id=' . (int)$pred['id'] . '">předchozí servis ' . $e(so_datum($pred['provedeno'] ?: $pred['termin'])) . '</a> · ' : '' ?>založeno <?= $e(date('j. n. Y', strtotime($r['vytvoreno']))) ?><?= $r['vytvoril'] ? ', ' . $e($r['vytvoril']) : '' ?></p>
<details><summary>Upravit údaje a termín</summary>
<form method="post"><input type="hidden" name="akce" value="udaje_ulozit"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="klic" value="<?= $e($r['klic']) ?>">
<div class="ln"><label class="w">Jméno a příjmení *<input name="jmeno" value="<?= $e($r['jmeno']) ?>" required></label><label>Telefon<input name="telefon" value="<?= $e($r['telefon']) ?>" inputmode="tel"></label><label class="w">E-mail<input name="email" type="email" value="<?= $e($r['email']) ?>"></label></div>
<div class="ln"><label class="w">Ulice a číslo<input name="ulice" value="<?= $e($r['ulice']) ?>"></label><label class="w">Obec<input name="mesto" value="<?= $e($r['mesto']) ?>"></label><label>PSČ<input name="psc" value="<?= $e($r['psc']) ?>" size="6"></label></div>
<label class="mut">Co zákazník potřebuje<textarea name="popis"><?= $e($r['popis']) ?></textarea></label>
<div class="ln"><label>Termín<input type="date" name="termin" value="<?= $e($r['termin']) ?>"></label><label>Čas<input name="cas" value="<?= $e($r['cas']) ?>" size="10"></label><label class="w">Poznámka (interní)<input name="poznamka" value="<?= $e($r['poznamka']) ?>"></label></div>
<button class="sec">Uložit údaje</button></form></details></div>

<form method="post" id="zapis"><input type="hidden" name="akce" value="zapis_ulozit"><input type="hidden" name="id" value="<?= $id ?>">
<div class="box"><h3>Stav</h3><div class="stavy"><?php foreach (['nova' => '📋 K vyřízení', 'hotovo' => '✅ Hotovo', 'zruseno' => '✋ Zrušeno'] as $k => $t): ?><label><input type="radio" name="stav" value="<?= $k ?>"<?= $r['stav'] === $k ? ' checked' : '' ?>><?= $t ?></label><?php endforeach; ?></div>
<div class="ln"><label>Provedeno dne<input type="date" name="provedeno" value="<?= $e($r['provedeno']) ?>"></label><span class="mut">při Hotovo se doplní dnešek</span></div></div>
<div class="box"><h3>Provedeno</h3><div class="chk"><?php foreach (SO_KONTROLY as $k => $t): ?><label><input type="checkbox" name="<?= $k ?>" value="1"<?= $r[$k] ? ' checked' : '' ?>> <?= $t ?></label><?php endforeach; ?></div></div>
<div class="box"><h3>Okna</h3><div class="okna"><?php foreach (SO_OKNA as $k => $t): ?><label><?= $t ?><input type="number" min="0" name="<?= $k ?>" value="<?= (int)$r[$k] ?>" inputmode="numeric"></label><?php endforeach; ?></div></div>
<div class="box"><h3>Závady</h3><div class="chk"><?php foreach (SO_ZKRATKY as $k => $t): ?><label><input type="checkbox" name="zkratky[]" value="<?= $k ?>"<?= in_array($k, $zk, true) ? ' checked' : '' ?>> <?= $e($t) ?></label><?php endforeach; ?></div>
<label class="mut">Popis závady (okno, místnost, kování – výrobce, označení, FFH/FFB, dorn…)<textarea name="zavada" placeholder="např. ložnice, 2křídlé okno: převod Winkhaus GRK 1550/1, FFH 1300–1550, DM 15 – protočený"><?= $e($r['zavada']) ?></textarea></label>
<label class="mut">Opraveno hned na místě<input name="opraveno_hned" value="<?= $e($r['opraveno_hned']) ?>" style="width:100%"></label>
<label class="mut">Návrh opravy pro zákazníka<textarea name="oprava" placeholder="např. výměna převodu na křídle v ložnici"><?= $e($r['oprava']) ?></textarea></label>
<div class="ln"><label>Maximální cena opravy (Kč s DPH)<input name="max_cena" value="<?= $e($r['max_cena']) ?>" inputmode="decimal" size="10"></label></div></div>
<div class="box"><h3>Cena a další servis</h3><div class="ln"><label>Cena servisu (Kč s DPH)<input name="cena" value="<?= $e($r['cena']) ?>" inputmode="decimal" size="10"></label>
<label>Další servis<select name="opakovat"><?php foreach (SJ_OPAKOVAT as $k => $t): ?><option value="<?= $k ?>"<?= (int)$r['opakovat'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
<?php if ($dl): ?><span class="mut">připomenout kolem <?= $e(so_datum($dl)) ?></span><?php endif; ?></div></div>
<div class="box"><h3>Podpis zákazníka</h3><p class="mut" style="margin-top:0">Zákazník podpisem potvrzuje, že servis proběhl.</p>
<?php if ($r['podpis']): ?><p><img src="?podpis=<?= $id ?>&amp;t=<?= time() ?>" alt="Podpis" style="max-width:100%;max-height:120px;border:1px solid #e3e8ef;border-radius:8px"> <label class="mut"><input type="checkbox" name="podpis_smazat" value="1"> smazat a podepsat znovu</label></p><?php endif; ?>
<canvas id="podpis"></canvas><input type="hidden" name="podpis_data" id="podpisData"><p><button type="button" class="sec" id="podpisZnovu">Smazat podpis</button></p>
<label class="mut">Jméno podepisujícího<input name="podepsal" value="<?= $e($r['podepsal'] ?: $r['jmeno']) ?>" style="width:100%"></label></div>
<div class="box" style="position:sticky;bottom:0"><button class="ok" style="width:100%">Uložit zápis</button></div>
</form>
<div class="box" id="fotky"><h3>Fotky<?= $fot ? ' (' . count($fot) . ')' : '' ?></h3>
<div style="display:flex;flex-wrap:wrap;gap:8px"><?php foreach ($fot as $f): ?><div><a href="?foto=<?= $id ?>&amp;f=<?= $e($f) ?>" target="_blank"><img src="?foto=<?= $id ?>&amp;f=<?= $e($f) ?>&amp;nahled=1" alt="Fotka" style="width:120px;height:90px;object-fit:cover;border-radius:8px"></a>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat fotku?')"><input type="hidden" name="akce" value="foto_smaz"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="f" value="<?= $e($f) ?>"><button class="del" style="padding:3px 8px;font-size:13px">smazat</button></form><?php endif; ?></div><?php endforeach; ?></div>
<input type="file" id="fotoVyber" accept="image/*" multiple style="display:none"><p><button type="button" id="fotoTl">📷 Přidat fotky</button> <span id="fotoStav" class="mut"></span></p>
<p class="mut">Nejdřív uložte zápis, pak přidávejte fotky (stránka se po nahrání znovu načte). Do protokolu se dá prvních 6 fotek.</p></div>
<div class="box" id="protokol"><h3>Protokol pro zákazníka</h3>
<?php if ($r['protokol']): ?><p class="mut">Poslední protokol: <a href="?pdf=<?= $id ?>&amp;t=<?= time() ?>" target="_blank">otevřít PDF</a><?= $r['protokol_poslan'] ? ' · posláno ' . $e(date('j. n. Y H:i', strtotime(substr((string)$r['protokol_poslan'], 0, 19))) . substr((string)$r['protokol_poslan'], 19)) : '' ?></p><?php endif; ?>
<p><button type="button" class="sec" id="pdfOtevrit">📄 Protokol PDF (zobrazit / tisk)</button> <button type="button" id="pdfPoslat"<?= filter_var(trim((string)$r['email']), FILTER_VALIDATE_EMAIL) ? '' : ' disabled title="Zákazník nemá e-mail – doplňte ho v údajích"' ?>>📧 Poslat zákazníkovi e-mailem</button> <span id="pdfStav" class="mut"></span></p>
<p class="mut">Protokol se sestaví z uloženého zápisu – nejdřív uložte změny.</p></div>
<?php if ($r['stav'] === 'hotovo' && (int)$r['opakovat'] > 0 && !$nasl): ?><form method="post" class="box"><input type="hidden" name="akce" value="dalsi_zalozit"><input type="hidden" name="id" value="<?= $id ?>"><b>Další servis</b> <?= $dl ? 'kolem ' . $e(so_datum($dl)) : '' ?> <button class="sec">Založit další servis</button></form>
<?php elseif ($nasl): ?><p class="mut">Další servis: <a href="?id=<?= $nasl ?>">otevřít</a></p><?php endif; ?>
<?php if ($hist): ?><div class="box"><h3>Další servisy oken u zákazníka</h3><?php foreach ($hist as $h): ?><div><a href="?id=<?= (int)$h['id'] ?>"><?= $e(so_datum($h['provedeno'] ?: $h['termin']) ?: 'bez termínu') ?></a> <span class="st st-<?= $e($h['stav']) ?>"><?= $e(SJ_STAVY[$h['stav']] ?? '') ?></span></div><?php endforeach; ?></div><?php endif; ?>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat tento servis i zápis?')"><input type="hidden" name="akce" value="smazat"><input type="hidden" name="id" value="<?= $id ?>"><button class="del">Smazat servis</button></form><?php endif; ?>
<?php
  // data pro PDF protokolu (z uloženého zápisu)
  $kontroly = []; foreach (SO_KONTROLY as $k => $t) if ($r[$k]) $kontroly[] = $t;
  $okna = []; foreach (SO_OKNA as $k => $t) if ((int)$r[$k] > 0) $okna[] = $t . ': ' . (int)$r[$k];
  $pdfData = ['cislo' => 'SOJ-' . $id, 'jmeno' => $r['jmeno'], 'adresa' => sj_adresa($r), 'telefon' => $r['telefon'], 'email' => $r['email'],
    'datum' => so_datum($r['provedeno'] ?: $r['termin'] ?: date('Y-m-d')), 'stav' => SJ_STAVY[$r['stav']] ?? '', 'popis' => (string)$r['popis'], 'kontroly' => $kontroly, 'okna' => implode(', ', $okna),
    'zkratky' => implode(', ', array_map(fn($k) => SO_ZKRATKY[$k] ?? $k, $zk)), 'zavada' => (string)$r['zavada'], 'opraveno' => (string)$r['opraveno_hned'], 'oprava' => (string)$r['oprava'],
    'max_cena' => $r['max_cena'] !== null ? $kc($r['max_cena']) : '', 'cena' => $r['cena'] !== null ? $kc($r['cena']) : '', 'dalsi' => $dl ? so_datum($dl) : '',
    'podpis' => $r['podpis'] ? '?podpis=' . $id . '&t=' . time() : '', 'podepsal' => (string)($r['podepsal'] ?: $r['jmeno']), 'technik' => (string)($r['technik'] ?: uziv_jmeno()),
    'fotky' => array_map(fn($f) => '?foto=' . $id . '&f=' . $f, array_slice($fot, 0, 6))]; ?>
<script>
(function () {
  // podpis prstem
  var c = document.getElementById('podpis'), x = c.getContext('2d'), kresli = false, neco = false, out = document.getElementById('podpisData');
  function velikost() { var r = c.getBoundingClientRect(), d = window.devicePixelRatio || 1; c.width = r.width * d; c.height = r.height * d; x.scale(d, d); x.lineWidth = 2.5; x.lineCap = 'round'; x.lineJoin = 'round'; x.strokeStyle = '#0b2a6b'; neco = false; out.value = ''; }
  velikost();
  function bod(e) { var r = c.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; }
  c.addEventListener('pointerdown', function (e) { kresli = true; c.setPointerCapture(e.pointerId); var p = bod(e); x.beginPath(); x.moveTo(p[0], p[1]); });
  c.addEventListener('pointermove', function (e) { if (!kresli) return; var p = bod(e); x.lineTo(p[0], p[1]); x.stroke(); neco = true; });
  c.addEventListener('pointerup', function () { kresli = false; if (neco) out.value = c.toDataURL('image/png'); });
  document.getElementById('podpisZnovu').addEventListener('click', function () { x.setTransform(1, 0, 0, 1, 0, 0); x.clearRect(0, 0, c.width, c.height); velikost(); });
  document.querySelectorAll('input[name=stav]').forEach(function (r) { r.addEventListener('change', function () { if (r.value === 'hotovo' && r.checked) ['k_kovani', 'k_ramy', 'k_tesneni', 'k_zlabky'].forEach(function (n) { document.querySelector('[name=' + n + ']').checked = true; }); }); });
  // fotky
  var vyber = document.getElementById('fotoVyber'), tl = document.getElementById('fotoTl'), st = document.getElementById('fotoStav');
  tl.addEventListener('click', function () { vyber.click(); });
  function zmensi(s, max, q) { return createImageBitmap(s, { imageOrientation: 'from-image' }).then(function (bm) { var k = Math.min(1, max / Math.max(bm.width, bm.height)), cv = document.createElement('canvas'); cv.width = Math.round(bm.width * k); cv.height = Math.round(bm.height * k); cv.getContext('2d').drawImage(bm, 0, 0, cv.width, cv.height); return new Promise(function (ok) { cv.toBlob(ok, 'image/jpeg', q); }); }); }
  vyber.addEventListener('change', function () {
    var sb = Array.prototype.slice.call(vyber.files || []), hot = 0, chy = []; if (!sb.length) return; tl.disabled = true;
    (function dalsi(i) {
      if (i >= sb.length) { st.textContent = 'Nahráno ' + hot + (chy.length ? ', chyby: ' + chy.join('; ') : '.'); if (hot) setTimeout(function () { location.href = '?id=<?= $id ?>#fotky'; location.reload(); }, 500); else tl.disabled = false; return; }
      st.textContent = 'Nahrávám ' + (i + 1) + ' z ' + sb.length + '…';
      Promise.all([zmensi(sb[i], 1600, .82), zmensi(sb[i], 320, .75)]).then(function (b) {
        var fd = new FormData(); fd.append('akce', 'foto_nahraj'); fd.append('id', '<?= $id ?>'); fd.append('foto', b[0], 'f.jpg'); fd.append('nahled', b[1], 'n.jpg');
        return fetch('servis-oken-jednotlivci.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
      }).then(function (j) { if (j.ok) hot++; else chy.push(j.zprava); }).catch(function () { chy.push(sb[i].name); }).then(function () { dalsi(i + 1); });
    })(0);
  });

  // ── protokol PDF (jsPDF + písmo Inter z webu, stejně jako PDF poptávek) ──
  var D = <?= json_encode($pdfData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, WEB = <?= json_encode($web . '/poptavka-pdf/') ?>, LOGO = <?= json_encode($web . '/logo.jpg') ?>;
  var GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], LINE = [220, 233, 225], INK = [15, 42, 28];
  var ps = document.getElementById('pdfStav');
  function loadScript(src) { return new Promise(function (ok, ko) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); }); }
  function b64(buf) { var s = '', u = new Uint8Array(buf), i; for (i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); }
  function obr(src, max, png) {
    return new Promise(function (ok) {
      if (!src) return ok(null); var img = new Image(); if (/^https?:/.test(src)) img.crossOrigin = 'anonymous';
      img.onload = function () { var s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight)), cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(img.naturalWidth * s)); cv.height = Math.max(1, Math.round(img.naturalHeight * s));
        var cx = cv.getContext('2d'); if (!png) { cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); } cx.drawImage(img, 0, 0, cv.width, cv.height);
        try { ok({ data: cv.toDataURL(png ? 'image/png' : 'image/jpeg', .85), w: cv.width, h: cv.height, typ: png ? 'PNG' : 'JPEG' }); } catch (e) { ok(null); } };
      img.onerror = function () { ok(null); }; img.src = src;
    });
  }
  function sestav() {
    return (window.jspdf ? Promise.resolve() : loadScript(WEB + 'jspdf.umd.min.js')).then(function () {
      return Promise.all(['Inter-Regular.ttf', 'Inter-Bold.ttf'].map(function (f) { return fetch(WEB + f).then(function (r) { if (!r.ok) throw new Error(f); return r.arrayBuffer(); }).then(b64); }));
    }).then(function (f) {
      return Promise.all([obr(LOGO, 600), obr(D.podpis, 900, true)].concat(D.fotky.map(function (u) { return obr(u, 900); }))).then(function (im) {
        var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        doc.addFileToVFS('Inter-Regular.ttf', f[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal'); doc.addFileToVFS('Inter-Bold.ttf', f[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
        var W = 210, M = 14, cw = W - 2 * M, y;
        var font = function (s, z, col) { doc.setFont('Inter', s); doc.setFontSize(z); doc.setTextColor.apply(doc, col || INK); };
        function pata() { font('normal', 7.5, MUTED); doc.text('KK SYSTEM · Bezpečné dveře · Zdeněk Kučera, IČ 64682676 · +420 725 559 235 · info@kksystem.cz · www.bezpecne-dvere.cz', W / 2, 289, { align: 'center' }); }
        function misto(h) { if (y + h > 280) { pata(); doc.addPage(); y = 18; } }
        function sekce(t) { misto(16); font('bold', 9, ORANGE); doc.text(t.toUpperCase(), M, y); y += 2; doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.line(M, y, W - M, y); y += 5.5; }
        function radek(a, b) { if (!b) return; var l = doc.splitTextToSize(String(b), cw - 46); misto(l.length * 4.6 + 1.5); font('normal', 9.5, MUTED); doc.text(a, M, y); font('bold', 9.5); doc.text(l, M + 46, y); y += l.length * 4.6 + 1.5; }
        function text(t) { font('normal', 10); doc.splitTextToSize(String(t), cw).forEach(function (l) { misto(5.2); doc.text(l, M, y); y += 5.2; }); y += 1; }
        doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
        if (im[0]) { var lh = 16; doc.addImage(im[0].data, 'JPEG', M, 7, lh * im[0].w / im[0].h, lh); }
        font('bold', 15, [255, 255, 255]); doc.text('Protokol o servisu oken', W - M, 13, { align: 'right' });
        font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + D.cislo + '  ·  ' + D.datum, W - M, 19.5, { align: 'right' });
        y = 40;
        sekce('Zákazník'); radek('Jméno', D.jmeno); radek('Adresa', D.adresa); radek('Telefon', D.telefon); radek('E-mail', D.email); y += 2;
        if (D.popis) { sekce('Požadavek zákazníka'); text(D.popis); y += 2; }
        sekce('Provedený servis'); radek('Datum', D.datum); radek('Provedeno', D.kontroly.join(', ') || '–'); radek('Okna', D.okna); radek('Opraveno na místě', D.opraveno); radek('Cena servisu', D.cena ? D.cena + ' s DPH' : ''); y += 2;
        if (D.zkratky || D.zavada || D.oprava) {
          sekce('Zjištěné závady a návrh opravy'); radek('Závady', [D.zkratky, D.zavada].filter(Boolean).join(' – ')); radek('Návrh opravy', D.oprava); radek('Maximální cena', D.max_cena ? 'do ' + D.max_cena + ' s DPH' : '');
          if (D.oprava) { y += 1; font('normal', 8.5, MUTED); doc.splitTextToSize('Cena opravy bude vyčíslena podle skutečných nákladů a nepřesáhne uvedenou maximální cenu. Máte-li o opravu zájem, odpovězte na e-mail nebo zavolejte.', cw).forEach(function (l) { misto(4.4); doc.text(l, M, y); y += 4.4; }); }
          y += 2;
        }
        if (D.dalsi) { sekce('Další servis'); text('Doporučujeme další seřízení kolem ' + D.dalsi + '. Včas se Vám ozveme.'); y += 1; }
        sekce('Potvrzení'); misto(34);
        font('normal', 9.5, MUTED); doc.text('Zákazník potvrzuje provedení servisu:', M, y); doc.text('Servis provedl:', M + cw / 2 + 6, y); y += 3;
        if (im[1]) { var ph = 22, pw = Math.min(cw / 2 - 6, ph * im[1].w / im[1].h); doc.addImage(im[1].data, 'PNG', M, y, pw, pw * im[1].h / im[1].w); }
        y += 24; doc.setDrawColor.apply(doc, LINE); doc.line(M, y, M + cw / 2 - 6, y); doc.line(M + cw / 2 + 6, y, W - M, y); y += 4.5;
        font('bold', 9.5); doc.text(D.podepsal || '', M, y); doc.text(D.technik || '', M + cw / 2 + 6, y); y += 8;
        var fo = im.slice(2).filter(Boolean);
        if (fo.length) {
          sekce('Fotografie'); var bw = (cw - 6) / 2;
          fo.forEach(function (p, i) { var bh = Math.min(70, bw * p.h / p.w), ww = bh * p.w / p.h; if (i % 2 === 0) misto(bh + 4); doc.addImage(p.data, 'JPEG', M + (i % 2) * (bw + 6), y, ww, bh); if (i % 2 === 1 || i === fo.length - 1) y += 74; });
        }
        pata();
        return doc.output('blob');
      });
    });
  }
  function odeslat(poslat, okno) {
    ps.textContent = 'Sestavuji PDF…'; var tl = [document.getElementById('pdfOtevrit'), document.getElementById('pdfPoslat')]; tl.forEach(function (b) { b.disabled = true; });
    sestav().then(function (blob) {
      ps.textContent = poslat ? 'Ukládám a posílám…' : 'Ukládám…';
      var fd = new FormData(); fd.append('akce', 'protokol'); fd.append('id', '<?= $id ?>'); fd.append('poslat', poslat ? '1' : ''); fd.append('pdf', blob, 'protokol.pdf');
      return fetch('servis-oken-jednotlivci.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (okno) okno.location.href = j.url || URL.createObjectURL(blob);
        ps.textContent = j.ok ? (poslat ? '✅ Protokol odeslán na ' + D.email + '.' : '✅ PDF uloženo.') : '⚠ ' + j.zprava;
      });
    }).catch(function (e) { if (okno) okno.close(); ps.textContent = '⚠ PDF se nepodařilo sestavit (' + (e && e.message || e) + ').'; })
      .then(function () { tl[0].disabled = false; if (D.email) tl[1].disabled = false; });
  }
  document.getElementById('pdfOtevrit').addEventListener('click', function () { var w = window.open('', '_blank'); if (w) w.document.write('<p style="font:16px system-ui">Sestavuji protokol…</p>'); odeslat(false, w); });
  document.getElementById('pdfPoslat').addEventListener('click', function () { if (confirm('Poslat protokol zákazníkovi na ' + D.email + '?')) odeslat(true, null); });
})();
</script>
<?php endif;

// ═════════ PŘEHLED ═════════
else:
  $q = trim((string)($_GET['q'] ?? ''));
  $kvyr = $pdo->query("SELECT * FROM so_jed WHERE stav='nova' ORDER BY termin IS NULL, termin, cas, id")->fetchAll(PDO::FETCH_ASSOC);
  $pripom = [];
  foreach ($pdo->query("SELECT j.* FROM so_jed j WHERE j.stav='hotovo' AND j.opakovat>0 AND NOT EXISTS (SELECT 1 FROM so_jed x WHERE x.predchozi_id=j.id)")->fetchAll(PDO::FETCH_ASSOC) as $x) {
    $dl = sj_dalsi($x); if ($dl && $dl <= date('Y-m-d', strtotime('+60 days'))) { $x['dalsi'] = $dl; $pripom[] = $x; }
  }
  usort($pripom, fn($a, $b) => strcmp($a['dalsi'], $b['dalsi']));
  if ($q !== '') { $st = $pdo->prepare("SELECT * FROM so_jed WHERE stav<>'nova' AND (jmeno LIKE ? OR telefon LIKE ? OR mesto LIKE ? OR ulice LIKE ?) ORDER BY COALESCE(provedeno, termin, vytvoreno) DESC LIMIT 100"); $st->execute(array_fill(0, 4, '%' . $q . '%')); $hot = $st->fetchAll(PDO::FETCH_ASSOC); }
  else $hot = $pdo->query("SELECT * FROM so_jed WHERE stav<>'nova' ORDER BY COALESCE(provedeno, termin, vytvoreno) DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC); ?>
<h1>🪟 Servis oken</h1>
<div class="tabs"><a href="servis-oken.php">SVJ a družstva</a><a class="on" href="servis-oken-jednotlivci.php">Jednotlivci</a></div>
<p><a class="btn ok" href="?nove=1">+ Nový servis u zákazníka</a></p>
<div class="box"><h2 style="margin-top:0">K vyřízení (<?= count($kvyr) ?>)</h2>
<?php if (!$kvyr): ?><p class="mut">Nic. Nový servis založíte tlačítkem výše, z poptávky „Servis oken“ v kartotéce nebo z karty zákazníka.</p><?php else: ?>
<table><tr><th>Termín</th><th>Zákazník</th><th class="hide-m">Požadavek</th></tr>
<?php foreach ($kvyr as $x): ?><tr><td class="nowrap"><?= $x['termin'] ? $e(so_datum($x['termin']) . ' ' . $x['cas']) : '<span class="mut">bez termínu</span>' ?></td>
<td><a href="?id=<?= (int)$x['id'] ?>"><b><?= $e($x['jmeno']) ?></b></a><div class="mut"><?= $e(sj_adresa($x)) ?><?= $x['telefon'] ? ' · ' . $e($x['telefon']) : '' ?></div></td>
<td class="mut"><?= $e(mb_strimwidth((string)$x['popis'], 0, 120, '…')) ?><?= $x['zadost_id'] ? ' <span class="st">z webu</span>' : '' ?><?= $x['predchozi_id'] ? ' <span class="st">pravidelný</span>' : '' ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php if ($pripom): ?><div class="box"><h2 style="margin-top:0">⏰ Čas na další servis (<?= count($pripom) ?>)</h2>
<table><tr><th>Kdy</th><th>Zákazník</th><th></th></tr>
<?php foreach ($pripom as $x): ?><tr><td class="nowrap"<?= $x['dalsi'] < $dnes ? ' style="color:#9b1c1c;font-weight:600"' : '' ?>><?= $e(so_datum($x['dalsi'])) ?></td><td><a href="?id=<?= (int)$x['id'] ?>"><b><?= $e($x['jmeno']) ?></b></a><div class="mut"><?= $e(sj_adresa($x)) ?><?= $x['telefon'] ? ' · ' . $e($x['telefon']) : '' ?> · minule <?= $e(so_datum($x['provedeno'])) ?></div></td>
<td><form method="post"><input type="hidden" name="akce" value="dalsi_zalozit"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="sec" style="padding:6px 10px;font-size:14px">Založit další servis</button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<div class="box"><h2 style="margin-top:0">Hotové a zrušené</h2>
<form class="ln" style="margin-top:0"><label class="w">Hledat (jméno, telefon, obec)<input name="q" value="<?= $e($q) ?>"></label><button class="sec">Hledat</button></form>
<?php if (!$hot): ?><p class="mut">Zatím nic.</p><?php else: ?>
<table><tr><th>Provedeno</th><th>Zákazník</th><th>Stav</th><th class="r">Cena</th></tr>
<?php foreach ($hot as $x): $dl = sj_dalsi($x); ?><tr><td class="nowrap"><?= $e(so_datum($x['provedeno'] ?: $x['termin'])) ?></td><td><a href="?id=<?= (int)$x['id'] ?>"><b><?= $e($x['jmeno']) ?></b></a><div class="mut"><?= $e(sj_adresa($x)) ?><?= $dl ? ' · další ' . $e(so_datum($dl)) : '' ?></div></td>
<td><span class="st st-<?= $e($x['stav']) ?>"><?= $e(SJ_STAVY[$x['stav']] ?? '') ?></span><?= $x['protokol_poslan'] ? ' <span class="mut">📧</span>' : '' ?></td><td class="r nowrap"><?= $e($kc($x['cena'])) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php endif; ?>
</main></body></html>
