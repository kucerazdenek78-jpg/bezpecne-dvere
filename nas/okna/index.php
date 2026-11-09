<?php
// Servis oken (samostatná aplikace /okna/) – JEDNOTLIVCI, úvodní stránka (2. 11. 2026). Servis u jednoho zákazníka (byt, dům): založení ručně,
// převzetím poptávky „Servis oken“ z webu (poptavky.php) nebo ze Zákazníků aplikace; zápis v mobilu (kontroly, okna, závady, návrh opravy, cena, podpis, fotky),
// protokol v PDF (tisk / e-mail zákazníkovi) a připomenutí dalšího servisu (za 1 / 2 roky). Stejná ochrana jako kartoteka.php.
// Data: tabulka so_jed ve vlastní databázi okna.sqlite, fotky, podpis a protokoly v $DATA/okna/j<id>/. Zákazníci jsou vlastní
// (sestavení ze servisů podle klíče telefon → e-mail → jméno); do Zákazníků kartotéky dveří se nic nezapisuje.
require __DIR__ . '/lib.php';
$pdo = ok_db();
// výsledek návštěvy, vazba bytu na SVJ / družstvo, číslo bytu (9. 11. 2026)
$sl = array_column($pdo->query('PRAGMA table_info(so_jed)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['vysledek' => 'TEXT', 'svj_id' => 'INTEGER', 'byt' => 'TEXT', 'plati' => 'TEXT', 'fakturace' => 'TEXT', 'polozky' => 'TEXT'] as $k => $d) if (!in_array($k, $sl, true)) $pdo->exec("ALTER TABLE so_jed ADD COLUMN $k $d");
if (!$pdo->query("SELECT hodnota FROM ok_nastaveni WHERE klic='jed_vazby_v1'")->fetchColumn()) {   // protokoly převzaté z Jotformu: výsledek, SVJ a řádky historie
  $mapa = ['Seřízeno' => 'serizeno', 'Nutná oprava – objednání dílů' => 'dily', 'Nutná oprava' => 'oprava'];
  $ryb = (int)$pdo->query("SELECT id FROM so_svj WHERE ic='28750519'")->fetchColumn(); $b701 = (int)$pdo->query("SELECT id FROM so_svj WHERE ic='28742699'")->fetchColumn();
  foreach ($pdo->query("SELECT id, poznamka FROM so_jed WHERE vytvoril='import Jotform'")->fetchAll(PDO::FETCH_ASSOC) as $x) {
    $v = null; if (preg_match('/Výsledek: (.+?)\. /u', (string)$x['poznamka'], $m)) $v = $mapa[$m[1]] ?? null;
    $svj = str_contains((string)$x['poznamka'], 'RYBÁRNA') ? $ryb : (str_contains((string)$x['poznamka'], 'Blok 701') ? $b701 : 0);
    $pdo->prepare('UPDATE so_jed SET vysledek=?, svj_id=? WHERE id=?')->execute([$v, $svj ?: null, $x['id']]);
    if (preg_match('/odeslání (\d{10,})/', (string)$x['poznamka'], $m)) $pdo->prepare('UPDATE so_historie SET dokument=? WHERE dokument=?')->execute(['jednotlivec #' . $x['id'], 'Jotform odeslání ' . $m[1]]);
  }
  $pdo->prepare("INSERT INTO ok_nastaveni (klic, hodnota) VALUES ('jed_vazby_v1', ?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota")->execute([date('Y-m-d H:i:s')]);
}
// servis bytu patřícího k SVJ / družstvu → řádek v historii domu (aktualizuje se při každém uložení)
function sj_do_historie(PDO $pdo, int $id): void {
  $r = sj_nacti($pdo, $id); if (!$r) return; $zn = 'jednotlivec #' . $id;
  $pdo->prepare('DELETE FROM so_historie WHERE dokument=?')->execute([$zn]);
  if (!$r['svj_id'] || $r['stav'] !== 'hotovo') return;
  $vys = SJ_VYSLEDKY[$r['vysledek'] ?? ''] ?? ''; $vys = trim(preg_replace('/^\S+\s/u', '', $vys));
  $txt = trim(implode(' ', array_filter([(string)$r['opraveno_hned'], (string)$r['zavada'], (string)$r['oprava']])));
  $popis = 'Byt ' . $r['jmeno'] . ($r['byt'] ? ' (' . $r['byt'] . ')' : '') . ($r['ulice'] ? ', ' . $r['ulice'] : '') . ($vys ? ' – ' . $vys : '') . ($txt !== '' ? ': ' . mb_strimwidth($txt, 0, 300, '…') : '')
    . (($r['plati'] ?? '') === 'svj' ? ' [platí SVJ]' : (($r['plati'] ?? '') === 'najemnik' ? ' [platí nájemník]' : ''));
  $pdo->prepare('INSERT INTO so_historie (svj_id, datum, druh, popis, dokument, vytvoreno, kdo) VALUES (?,?,?,?,?,?,?)')
    ->execute([(int)$r['svj_id'], $r['provedeno'] ?: date('Y-m-d'), in_array($r['vysledek'], ['oprava', 'dily'], true) ? 'zavady' : 'servis', $popis, $zn, date('Y-m-d H:i:s'), uziv_jmeno()]);
}
$svjSeznam = $pdo->query('SELECT id, nazev FROM so_svj ORDER BY nazev')->fetchAll(PDO::FETCH_KEY_PAIR);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, 0, ',', ' ') . ' Kč';
$ted = date('Y-m-d H:i:s'); $dnes = date('Y-m-d');
$P = fn($k, $max = 500) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)($_POST[$k] ?? ''))), 0, $max);
$cis = function ($v) { $v = str_replace([' ', "\u{a0}", ',', 'Kč', 'kč'], ['', '', '.', '', ''], trim((string)$v)); return $v === '' || !is_numeric($v) ? null : (float)$v; };
$web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
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
  if (mb_strlen($q) >= 2) foreach (array_reverse(ok_zakaznici($pdo)) as $c) {
    $txt = mb_strtolower($c['jmeno'] . ' ' . $c['ulice'] . ' ' . $c['mesto'] . ' ' . $c['email']);
    if (mb_strpos($txt, $q) === false && !(strlen($qt) >= 4 && strpos(preg_replace('/\D+/', '', $c['telefon']), $qt) !== false)) continue;
    $o[] = ['klic' => $c['klic'], 'jmeno' => $c['jmeno'], 'telefon' => $c['telefon'], 'email' => $c['email'], 'ulice' => $c['ulice'], 'mesto' => $c['mesto'], 'psc' => $c['psc']];
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
    $vSvj = (int)($_POST['svj_id'] ?? 0); $vSvj = isset($svjSeznam[$vSvj]) ? $vSvj : null; $vByt = $P('byt', 60); $vPl = isset(SJ_PLATI[$_POST['plati'] ?? '']) && $vSvj ? $_POST['plati'] : null;
    if ($f['jmeno'] === '') sj_jdi('index.php?' . ($id ? 'id=' . $id . '&' : 'nove=1&') . 'chyba=jmeno');
    // zákazník aplikace: vybraný klíč, jinak klíč z telefonu / e-mailu / jména (do Zákazníků kartotéky dveří se nic nezapisuje)
    $klic = $P('klic', 120);
    if ($klic === '') $klic = ok_klic($f['telefon'], $f['email'], $f['jmeno'], $f['mesto']);
    if ($id) {
      $pdo->prepare('UPDATE so_jed SET klic=?, jmeno=?, telefon=?, email=?, ulice=?, mesto=?, psc=?, popis=?, termin=?, cas=?, poznamka=?, svj_id=?, byt=?, plati=?, upraveno=? WHERE id=?')
        ->execute([$klic, $f['jmeno'], $f['telefon'], $f['email'], $f['ulice'], $f['mesto'], $f['psc'], $f['popis'], $f['termin'], $f['cas'], $f['poznamka'], $vSvj, $vByt, $vPl, $ted, $id]);
      sj_do_historie($pdo, $id);
      sj_jdi('index.php?id=' . $id . '&ok=ulozeno');
    }
    $zid = (int)($_POST['zadost_id'] ?? 0) ?: null; $pred = (int)($_POST['predchozi_id'] ?? 0) ?: null;
    if ($zid) { $st = $pdo->prepare('SELECT id FROM so_jed WHERE zadost_id=?'); $st->execute([$zid]); if ($x = (int)$st->fetchColumn()) sj_jdi('index.php?id=' . $x); }
    $okna = array_fill_keys(array_keys(SO_OKNA), 0);
    if ($pred && ($p = sj_nacti($pdo, $pred))) foreach ($okna as $k => $_) $okna[$k] = (int)$p[$k];   // počty oken z minula
    $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, email, ulice, mesto, psc, zadost_id, predchozi_id, popis, stav, termin, cas, poznamka, o3, o2, o1, bd, vk, dv, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$klic, $f['jmeno'], $f['telefon'], $f['email'], $f['ulice'], $f['mesto'], $f['psc'], $zid, $pred, $f['popis'], 'nova', $f['termin'], $f['cas'], $f['poznamka'],
        $okna['o3'], $okna['o2'], $okna['o1'], $okna['bd'], $okna['vk'], $okna['dv'], uziv_jmeno(), $ted, $ted]);
    $nid = (int)$pdo->lastInsertId();
    if (!$vSvj && $pred && ($p = sj_nacti($pdo, $pred))) { $vSvj = $p['svj_id'] ?: null; if ($vByt === '') $vByt = (string)$p['byt']; $vPl = null; }   // navazující: kdo platí se vybere znovu
    $pdo->prepare('UPDATE so_jed SET svj_id=?, byt=?, plati=? WHERE id=?')->execute([$vSvj, $vByt, $vSvj ? $vPl : null, $nid]);
    sj_poptavka_stav($pdo, $zid, 'rozpracovano');
    sj_jdi('index.php?id=' . $nid . '&ok=zalozeno');
  }
  if ($a === 'dalsi_zalozit') {   // další servis podle opakování (zkopíruje zákazníka a počty oken)
    $p = sj_nacti($pdo, (int)$_POST['id']); if (!$p) sj_jdi('index.php');
    $st = $pdo->prepare('SELECT id FROM so_jed WHERE predchozi_id=?'); $st->execute([$p['id']]); if ($x = (int)$st->fetchColumn()) sj_jdi('index.php?id=' . $x);
    $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, email, ulice, mesto, psc, predchozi_id, popis, stav, opakovat, o3, o2, o1, bd, vk, dv, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$p['klic'], $p['jmeno'], $p['telefon'], $p['email'], $p['ulice'], $p['mesto'], $p['psc'], $p['id'], 'Pravidelný servis (předchozí ' . so_datum($p['provedeno'] ?: $p['termin']) . ')', 'nova', (int)$p['opakovat'],
        $p['o3'], $p['o2'], $p['o1'], $p['bd'], $p['vk'], $p['dv'], uziv_jmeno(), $ted, $ted]);
    $nid = (int)$pdo->lastInsertId(); $pdo->prepare('UPDATE so_jed SET svj_id=?, byt=? WHERE id=?')->execute([$p['svj_id'] ?: null, $p['byt'], $nid]);
    sj_jdi('index.php?id=' . $nid . '&ok=zalozeno');
  }
  if ($a === 'fakturace') {   // předat k fakturaci / vrátit (přehled všech: fakturace.php)
    $id = (int)$_POST['id']; $pdo->prepare('UPDATE so_jed SET fakturace=?, upraveno=? WHERE id=?')->execute([($_POST['k'] ?? '') === 'vratit' ? null : date('j. n. Y') . ' – ' . uziv_jmeno(), $ted, $id]);
    sj_jdi('index.php?id=' . $id . '&ok=ulozeno');
  }
  if ($a === 'oprava_zalozit') {   // navazující oprava po „Nutná oprava“: stejný zákazník, byt a SVJ; kdo platí se vybere znovu
    $p = sj_nacti($pdo, (int)$_POST['id']); if (!$p) sj_jdi('index.php');
    $st = $pdo->prepare('SELECT id FROM so_jed WHERE predchozi_id=?'); $st->execute([$p['id']]); if ($x = (int)$st->fetchColumn()) sj_jdi('index.php?id=' . $x);
    $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, email, ulice, mesto, psc, predchozi_id, popis, stav, svj_id, byt, o3, o2, o1, bd, vk, dv, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$p['klic'], $p['jmeno'], $p['telefon'], $p['email'], $p['ulice'], $p['mesto'], $p['psc'], $p['id'],
        trim('Oprava podle servisu ' . so_datum($p['provedeno'] ?: $p['termin']) . ($p['oprava'] ? ': ' . $p['oprava'] : ($p['zavada'] ? ': ' . $p['zavada'] : ''))), 'nova', $p['svj_id'] ?: null, $p['byt'],
        $p['o3'], $p['o2'], $p['o1'], $p['bd'], $p['vk'], $p['dv'], uziv_jmeno(), $ted, $ted]);
    sj_jdi('index.php?id=' . (int)$pdo->lastInsertId() . '&ok=zalozeno');
  }
  if ($a === 'zapis_ulozit') {
    $id = (int)$_POST['id']; $r = sj_nacti($pdo, $id); if (!$r) sj_jdi('index.php');
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
    // položky k vyúčtování (JSON z editoru): každá má název, množství, cenu/j s DPH a sazbu DPH; cena servisu = jejich součet
    $polIn = json_decode((string)($_POST['polozky'] ?? ''), true); $polC = []; $polSum = 0.0;
    if (is_array($polIn)) foreach ($polIn as $it) {
      if (!is_array($it)) continue;
      $nz = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($it['nazev'] ?? ''))), 0, 120);
      $mn = round((float)str_replace(',', '.', (string)($it['mn'] ?? 1)), 2); $cj = round((float)str_replace(',', '.', (string)($it['cena'] ?? 0)), 2);
      if ($nz === '' || $mn <= 0) continue;
      $dph = in_array((int)($it['dph'] ?? 12), [0, 12, 15, 21], true) ? (int)$it['dph'] : 12;
      $polC[] = ['kod' => mb_substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($it['kod'] ?? '')), 0, 20), 'nazev' => $nz, 'mn' => $mn, 'jedn' => mb_substr(preg_replace('/[\x00-\x1f]/u', '', (string)($it['jedn'] ?? 'ks')), 0, 20), 'cena' => $cj, 'dph' => $dph];
      $polSum += $mn * $cj;
    }
    $polJson = $polC ? json_encode($polC, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $cenaFin = $polC ? round($polSum, 2) : $cis($_POST['cena'] ?? '');
    $katFin = ''; foreach ($polC as $it) if ($it['kod'] !== '') { $katFin = $it['kod']; break; } if ($katFin === '') $katFin = $P('kategorie', 20);
    $pdo->prepare('UPDATE so_jed SET stav=?, k_kovani=?, k_ramy=?, k_tesneni=?, k_zlabky=?, zkratky=?, o3=?, o2=?, o1=?, bd=?, vk=?, dv=?, zavada=?, oprava=?, max_cena=?, cena=?, opraveno_hned=?, podpis=?, podepsal=?, technik=?, provedeno=?, opakovat=?, kategorie=?, polozky=?, upraveno=? WHERE id=?')
      ->execute(array_merge([$stav, empty($_POST['k_kovani']) ? 0 : 1, empty($_POST['k_ramy']) ? 0 : 1, empty($_POST['k_tesneni']) ? 0 : 1, empty($_POST['k_zlabky']) ? 0 : 1, implode(',', $zk)], $okna,
        [$P('zavada', 3000), $P('oprava', 3000), $cis($_POST['max_cena'] ?? ''), $cenaFin, $P('opraveno_hned', 1000), $pod, $P('podepsal', 120),
         $stav === 'hotovo' ? ($r['technik'] ?: uziv_jmeno()) : (string)$r['technik'], $prov, isset(SJ_OPAKOVAT[(int)($_POST['opakovat'] ?? 0)]) ? (int)$_POST['opakovat'] : 0, $katFin, $polJson, $ted, $id]));
    $pdo->prepare('UPDATE so_jed SET vysledek=? WHERE id=?')->execute([isset(SJ_VYSLEDKY[$_POST['vysledek'] ?? '']) ? $_POST['vysledek'] : null, $id]);
    if ($r['svj_id'] && isset($_POST['plati'])) $pdo->prepare('UPDATE so_jed SET plati=? WHERE id=?')->execute([isset(SJ_PLATI[$_POST['plati']]) ? $_POST['plati'] : null, $id]);
    sj_do_historie($pdo, $id);
    if ($stav === 'hotovo') sj_poptavka_stav($pdo, $r['zadost_id'] ? (int)$r['zadost_id'] : null, 'vyrizeno');
    sj_jdi('index.php?id=' . $id . '&ok=ulozeno' . ($stav === 'hotovo' && $r['stav'] !== 'hotovo' ? '#protokol' : ''));
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
    sj_jdi('index.php?id=' . $id . '#fotky');
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
      require_once OK_APP . '/lib/posta.php';
      $kdy = so_datum($r['provedeno'] ?: $r['termin']);
      $telo = "Dobrý den,\n\nděkujeme, že jste využili náš servis oken. V příloze zasíláme protokol o provedeném servisu" . ($kdy ? " ze dne $kdy" : '') . ".\n"
        . (trim((string)$r['oprava']) !== '' ? "V protokolu najdete i zjištěné závady a návrh opravy s maximální cenou. Pokud máte o opravu zájem, stačí odpovědět na tento e-mail nebo zavolat.\n" : '')
        . ((int)$r['opakovat'] > 0 && ($dl = sj_dalsi($r)) ? 'Další servis doporučujeme ' . ((int)$r['opakovat'] === 1 ? 'za rok' : 'za 2 roky') . ' (kolem ' . so_datum($dl) . "), včas se Vám ozveme.\n" : '')
        . "\nKdybyste cokoli potřebovali, ozvěte se: +420 725 559 235, info@kksystem.cz\n\nKK SYSTEM · Bezpečné dveře\nwww.bezpecne-dvere.cz";
      $poslano = posli_mail($cfg, $DATA, [$em], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], 'Protokol o servisu oken' . ($kdy ? ' – ' . $kdy : ''), $telo, [[$dir . '/' . $nazev, 'protokol-servis-oken.pdf']]);
      if ($poslano) $pdo->prepare('UPDATE so_jed SET protokol_poslan=? WHERE id=?')->execute([$ted . ' → ' . $em, $id]);
    }
    sj_json(['ok' => $poslano !== false, 'zprava' => $poslano === false ? 'E-mail se nepodařilo odeslat. PDF je uložené.' : '', 'url' => 'index.php?pdf=' . $id . '&t=' . time()]);
  }
  if ($a === 'smazat') {
    uziv_jen_admin('Servis smí smazat jen administrátor.');
    $id = (int)$_POST['id']; $pdo->prepare('DELETE FROM so_jed WHERE id=?')->execute([$id]);
    $pdo->prepare('UPDATE so_jed SET predchozi_id=NULL WHERE predchozi_id=?')->execute([$id]);
    sj_jdi('index.php?ok=smazano');
  }
  http_response_code(400); exit('Neznámá akce');
}

// ───── zobrazení ─────
$id = (int)($_GET['id'] ?? 0);
$OK = ['ulozeno' => 'Uloženo.', 'smazano' => 'Smazáno.', 'zalozeno' => 'Servis založen.'];
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0e4f5c"><link rel="manifest" href="manifest.webmanifest"><link rel="icon" href="okna-192.png"><link rel="apple-touch-icon" href="okna-192.png">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Servis oken"><title>Jednotlivci – Servis oken</title><style>
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
</style></head><body><?php ok_bar('jed'); ?><main>
<div class="zk">🧪 <b>Zkušební verze aplikace Servis oken.</b> Má vlastní databázi a vlastní zákazníky – se systémem dveří se nemíchá.</div>
<?php if (!isset($_GET['id']) && !isset($_GET['nove'])): ?>
<div class="box" id="instal" hidden style="border-left:5px solid #0e7490"><b>📲 Servis oken jako aplikace v mobilu</b>
<p class="mut" style="margin:4px 0 8px">Ikona na ploše, otevře se rovnou na Jednotlivce, bez lišty prohlížeče. Přihlášení zůstává stejné.</p>
<p style="margin:0"><button type="button" id="instalTl">Nainstalovat aplikaci</button> <button type="button" class="sec" id="instalZavrit">Teď ne</button></p>
<p class="mut" id="instalIos" hidden style="margin:8px 0 0">Na iPhonu: v Safari klepněte na <b>Sdílet</b> (čtvereček se šipkou) → <b>Přidat na plochu</b>.</p>
<p class="mut" id="instalRucne" hidden style="margin:8px 0 0">Prohlížeč instalaci nenabídl sám: v Chromu otevřete menu <b>⋮</b> → <b>Přidat na plochu</b> / <b>Nainstalovat aplikaci</b>.</p></div>
<?php endif; ?>
<script>
(function () {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js', { scope: './' }).catch(function () {});
  var b = document.getElementById('instal'); if (!b) return;
  var app = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true, skryto = false;
  try { skryto = localStorage.getItem('okna_instal_skryt') === '1'; } catch (e) {}
  if (app || skryto) return;
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent), vyzva = null;
  b.hidden = false; if (ios) { document.getElementById('instalIos').hidden = false; document.getElementById('instalTl').hidden = true; }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); vyzva = e; });
  document.getElementById('instalTl').addEventListener('click', function () {
    if (!vyzva) { document.getElementById('instalRucne').hidden = false; return; }
    vyzva.prompt(); vyzva.userChoice.then(function (r) { if (r.outcome === 'accepted') b.hidden = true; vyzva = null; });
  });
  window.addEventListener('appinstalled', function () { b.hidden = true; });
  document.getElementById('instalZavrit').addEventListener('click', function () { b.hidden = true; try { localStorage.setItem('okna_instal_skryt', '1'); } catch (e) {} });
})();
</script>
<?php if (isset($_GET['ok'], $OK[$_GET['ok']])): ?><div class="msg"><?= $e($OK[$_GET['ok']]) ?></div><?php endif; ?>
<?php if (($_GET['chyba'] ?? '') === 'jmeno'): ?><div class="msg err">Vyplňte jméno zákazníka.</div><?php endif; ?>
<?php
// ═════════ NOVÝ SERVIS ═════════
if (isset($_GET['nove'])):
  $f = ['klic' => '', 'jmeno' => '', 'telefon' => '', 'email' => '', 'ulice' => '', 'mesto' => '', 'psc' => '', 'popis' => '', 'zadost_id' => 0, 'puvod' => ''];
  if ($zid = (int)($_GET['z'] ?? 0)) {   // z poptávky v kartotéce
    $st = $pdo->prepare('SELECT id FROM so_jed WHERE zadost_id=?'); $st->execute([$zid]);
    if ($x = (int)$st->fetchColumn()) { echo '<script>location.replace("index.php?id=' . $x . '")</script>'; exit; }
    $st = db()->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$zid]); $zr = $st->fetch(PDO::FETCH_ASSOC);
    if ($zr) { $d = json_decode((string)$zr['data_json'], true) ?: [];
      $jm0 = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')); $f = ['klic' => ok_klic((string)($d['telefon'] ?? ''), (string)($d['email'] ?? $zr['email'] ?? ''), $jm0, (string)($d['mesto'] ?? '')), 'jmeno' => $jm0, 'telefon' => (string)($d['telefon'] ?? ''), 'email' => (string)($d['email'] ?? $zr['email'] ?? ''),
        'ulice' => (string)($d['ulice'] ?? ''), 'mesto' => (string)($d['mesto'] ?? ''), 'psc' => (string)($d['psc'] ?? ''), 'popis' => trim((string)($d['poznamka'] ?? '')), 'zadost_id' => $zid,
        'puvod' => 'z poptávky ' . (string)($d['cislo'] ?? $zr['cislo'] ?? $zid)]; }
  } elseif (($k = (string)($_GET['k'] ?? '')) !== '') {   // ze Zákazníků aplikace
    $vse = ok_zakaznici($pdo);
    if (isset($vse[$k])) { $c = $vse[$k]; $f = array_merge($f, ['klic' => $k, 'jmeno' => $c['jmeno'], 'telefon' => $c['telefon'], 'email' => $c['email'], 'ulice' => $c['ulice'], 'mesto' => $c['mesto'], 'psc' => $c['psc'], 'puvod' => 'ze Zákazníků']); }
  } ?>
<p><a href="index.php">← Jednotlivci</a></p>
<h1>Nový servis oken u zákazníka</h1>
<?php $objSvj = (int)($_GET['svj'] ?? 0); if ($objSvj && isset($svjSeznam[$objSvj])): ?><div class="msg">🏢 Objednává <b><?= $e($svjSeznam[$objSvj]) ?></b> – vyplňte nájemníka, adresu a číslo bytu. Servis se po dokončení zapíše i do historie domu.</div><?php endif; ?>
<?php if ($f['puvod']): ?><p class="mut">Údaje převzaty <?= $e($f['puvod']) ?> – zkontrolujte je.</p><?php endif; ?>
<?php if (!$f['jmeno']): ?><div class="box"><label class="mut">Najít zákazníka, u kterého už servis byl (jméno, telefon, obec…)<input id="hledej" autocomplete="off" style="width:100%" placeholder="např. Novák nebo 725…"></label><div id="vysl" hidden></div>
<p class="mut" style="margin-bottom:0">Nový zákazník? Stačí vyplnit údaje níže.</p></div><?php endif; ?>
<form method="post" class="box"><input type="hidden" name="akce" value="zalozit"><input type="hidden" name="klic" id="klic" value="<?= $e($f['klic']) ?>"><input type="hidden" name="zadost_id" value="<?= (int)$f['zadost_id'] ?>">
<div class="ln"><label class="w">Jméno a příjmení *<input name="jmeno" id="f_jmeno" value="<?= $e($f['jmeno']) ?>" required></label><label>Telefon<input name="telefon" id="f_telefon" value="<?= $e($f['telefon']) ?>" inputmode="tel"></label><label class="w">E-mail<input name="email" id="f_email" value="<?= $e($f['email']) ?>" type="email"></label></div>
<div class="ln"><label class="w">Ulice a číslo<input name="ulice" id="f_ulice" value="<?= $e($f['ulice']) ?>"></label><label class="w">Obec<input name="mesto" id="f_mesto" value="<?= $e($f['mesto']) ?>"></label><label>PSČ<input name="psc" id="f_psc" value="<?= $e($f['psc']) ?>" size="6" inputmode="numeric"></label></div>
<?php $SVJV = (int)($_GET['svj'] ?? 0); $BYTV = ''; $PLV = $SVJV ? 'svj' : ''; ?><div class="ln"><label class="w">Patří k SVJ / družstvu (servis se zapíše i do historie domu)<select name="svj_id"><option value="">– ne –</option><?php foreach ($svjSeznam as $k => $t): ?><option value="<?= (int)$k ?>"<?= (int)($SVJV ?? 0) === (int)$k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label><label>Číslo bytu / patro<input name="byt" value="<?= $e($BYTV ?? '') ?>" size="12" placeholder="např. 12, 3. p."></label>
<label>Platí (jen u SVJ / družstva)<select name="plati"><option value="">– vyberte –</option><?php foreach (SJ_PLATI as $k => $t): ?><option value="<?= $k ?>"<?= ($PLV ?? '') === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label></div>
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
      fetch('index.php?hledat=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
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
  if (!$r): ?><p>Servis nenalezen. <a href="index.php">Zpět</a></p><?php else:
  $zk = array_filter(explode(',', (string)$r['zkratky'])); $fot = sj_fotky($id); $dl = sj_dalsi($r);
  $pred = $r['predchozi_id'] ? sj_nacti($pdo, (int)$r['predchozi_id']) : null;
  $st = $pdo->prepare('SELECT id FROM so_jed WHERE predchozi_id=?'); $st->execute([$id]); $nasl = (int)$st->fetchColumn();
  $hist = []; if ($r['klic']) { $st = $pdo->prepare('SELECT id, stav, termin, provedeno FROM so_jed WHERE klic=? AND id<>? ORDER BY COALESCE(provedeno, termin, vytvoreno) DESC'); $st->execute([$r['klic'], $id]); $hist = $st->fetchAll(PDO::FETCH_ASSOC); } ?>
<p><a href="index.php">← Jednotlivci</a></p>
<?php if ($r['stav'] === 'hotovo'):   // co po dokončení servisu ještě zbývá – dokud není všechno, servis není uzavřený
  $kProt = (bool)$r['protokol_poslan'] || (!$r['email'] && $r['protokol']); $kCena = is_numeric($r['cena']); $kFak = (bool)($r['fakturace'] ?? '');
  $kOpr = !in_array($r['vysledek'] ?? '', ['oprava', 'dily'], true) || $nasl; $openOpr = !$kOpr; $kPl = !$r['svj_id'] || isset(SJ_PLATI[$r['plati'] ?? '']); $vse = $kProt && $kCena && $kFak && $kOpr && $kPl;
  $ok = fn($b) => $b ? '✅' : '⬜'; ?>
<div class="box" style="border-left:6px solid <?= $vse ? '#15803d' : '#d97706' ?>"><h2 style="margin-top:0"><?= $vse ? '✔ Servis je uzavřený' : 'Servis proběhl – co ještě zbývá' ?></h2>
<p style="margin:4px 0"><?= $ok($kProt) ?> <b>Protokol zákazníkovi</b> – <?= $r['protokol_poslan'] ? 'poslán ' . $e(date('j. n. Y', strtotime(substr((string)$r['protokol_poslan'], 0, 19)) ?: time())) : ($r['protokol'] ? ($r['email'] ? 'vytvořen, ale neposlán – <a href="#protokol">poslat</a>' : 'vytvořen (zákazník nemá e-mail – vytisknout / předat)') : '<a href="#protokol">vytvořit a poslat PDF</a>') ?></p>
<?php if ($r['svj_id']): ?><p style="margin:4px 0"><?= $ok($kPl) ?> <b>Kdo platí</b> – <?= $kPl ? $e(SJ_PLATI[$r['plati']]) : 'vyberte v zápisu (Cena a další servis)' ?></p><?php endif; ?>
<p style="margin:4px 0"><?= $ok($kCena && $kFak) ?> <b>Fakturace</b> – <?php if (!$kCena): ?>nejdřív doplňte cenu servisu v zápisu (Cena a další servis)<?php elseif ($kFak): ?>předáno k fakturaci <?= $e($r['fakturace']) ?> · <a href="fakturace.php?i=1">přehled</a>
  <form method="post" style="display:inline"><input type="hidden" name="akce" value="fakturace"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="k" value="vratit"><button class="sec" style="padding:3px 8px;font-size:13px">vrátit</button></form>
  <?php elseif ($openOpr): ?><?= $e(ok_kc($r['cena'])) ?> s DPH zatím – <b>počká na dokončení opravy</b> a vyfakturuje se jako celek (servis + oprava). Založte opravu níže.
  <?php else: ?><?= $e(ok_kc($r['cena'])) ?> s DPH<?= ($r['plati'] ?? '') === 'svj' && isset($svjSeznam[(int)$r['svj_id']]) ? ' – fakturuje se SVJ ' . $e($svjSeznam[(int)$r['svj_id']]) : '' ?>
  <form method="post" style="display:inline"><input type="hidden" name="akce" value="fakturace"><input type="hidden" name="id" value="<?= $id ?>"><button class="ok" style="padding:5px 10px;font-size:14px">🧾 Předat k fakturaci</button></form> <a href="fakturace.php">všechny podklady</a><?php endif; ?></p>
<?php if (in_array($r['vysledek'] ?? '', ['oprava', 'dily'], true)): ?><p style="margin:4px 0"><?= $ok($kOpr) ?> <b>Oprava</b> – <?= $nasl ? '<a href="?id=' . $nasl . '">založena (navazující servis)</a>' : 'zatím nezaložena – tlačítko „Založit opravu“ níže' ?></p><?php endif; ?>
<?php if ($dl): ?><p class="mut" style="margin:4px 0">🔁 Další pravidelný servis: <?= $e(so_datum($dl)) ?> (připomene se v přehledu)</p><?php endif; ?>
</div><?php endif; ?>
<div class="box"><h1 style="margin-bottom:4px"><?= $r['klic'] ? '<a href="zakaznici.php?k=' . $e(rawurlencode($r['klic'])) . '" style="color:inherit" title="Zákazník – všechny servisy">' . $e($r['jmeno']) . '</a>' : $e($r['jmeno']) ?> <span class="st st-<?= $e($r['stav']) ?>"><?= $e(SJ_STAVY[$r['stav']] ?? $r['stav']) ?></span></h1>
<div style="font-size:17px"><?= $e(sj_adresa($r)) ?><?= $r['byt'] ? ', byt ' . $e($r['byt']) : '' ?><?= ok_mapa(sj_adresa($r)) ?></div>
<?php if ($r['svj_id'] && isset($svjSeznam[(int)$r['svj_id']])): ?><div>🏢 <a href="svj.php?svj=<?= (int)$r['svj_id'] ?>#historie"><?= $e($svjSeznam[(int)$r['svj_id']]) ?></a> ·
  <?= isset(SJ_PLATI[$r['plati'] ?? '']) ? '💳 platí <b>' . $e(SJ_PLATI[$r['plati']]) . '</b>' : '<span class="st st-zruseno">vyberte, kdo platí (SVJ / nájemník)</span>' ?></div><?php endif; ?>
<?php if (!empty($r['vysledek']) && isset(SJ_VYSLEDKY[$r['vysledek']])): ?><div style="margin-top:4px"><span class="st <?= $r['vysledek'] === 'serizeno' ? 'st-hotovo' : 'st-nova' ?>"><?= $e(SJ_VYSLEDKY[$r['vysledek']]) ?></span></div><?php endif; ?>
<div><?= $r['telefon'] ? '📞 <a href="tel:' . $e(preg_replace('/[^\d+]/', '', $r['telefon'])) . '">' . $e($r['telefon']) . '</a>' : '' ?><?= $r['email'] ? ' · ✉ <a href="mailto:' . $e($r['email']) . '">' . $e($r['email']) . '</a>' : '' ?></div>
<?php if ($r['termin']): ?><p style="margin:6px 0 0"><b>Termín:</b> <?= $e(so_datum($r['termin'])) ?> <?= $e($r['cas']) ?></p><?php endif; ?>
<?php if ($r['popis']): ?><p style="margin:6px 0 0"><b>Požadavek:</b> <?= nl2br($e($r['popis'])) ?></p><?php endif; ?>
<?php if ($r['poznamka']): ?><p class="mut" style="margin:6px 0 0">📝 <?= $e($r['poznamka']) ?></p><?php endif; ?>
<p class="mut" style="margin:6px 0 0"><?= $r['zadost_id'] ? '<a href="../kartoteka.php?id=' . (int)$r['zadost_id'] . '" target="_blank">Poptávka z webu</a> · ' : '' ?><?= $pred ? '<a href="?id=' . (int)$pred['id'] . '">předchozí servis ' . $e(so_datum($pred['provedeno'] ?: $pred['termin'])) . '</a> · ' : '' ?>založeno <?= $e(date('j. n. Y', strtotime($r['vytvoreno']))) ?><?= $r['vytvoril'] ? ', ' . $e($r['vytvoril']) : '' ?></p>
<details><summary>Upravit údaje a termín</summary>
<form method="post"><input type="hidden" name="akce" value="udaje_ulozit"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="klic" value="<?= $e($r['klic']) ?>">
<div class="ln"><label class="w">Jméno a příjmení *<input name="jmeno" value="<?= $e($r['jmeno']) ?>" required></label><label>Telefon<input name="telefon" value="<?= $e($r['telefon']) ?>" inputmode="tel"></label><label class="w">E-mail<input name="email" type="email" value="<?= $e($r['email']) ?>"></label></div>
<div class="ln"><label class="w">Ulice a číslo<input name="ulice" value="<?= $e($r['ulice']) ?>"></label><label class="w">Obec<input name="mesto" value="<?= $e($r['mesto']) ?>"></label><label>PSČ<input name="psc" value="<?= $e($r['psc']) ?>" size="6"></label></div>
<?php $SVJV = (int)$r['svj_id']; $BYTV = (string)$r['byt']; $PLV = (string)($r['plati'] ?? ''); ?><div class="ln"><label class="w">Patří k SVJ / družstvu (servis se zapíše i do historie domu)<select name="svj_id"><option value="">– ne –</option><?php foreach ($svjSeznam as $k => $t): ?><option value="<?= (int)$k ?>"<?= (int)($SVJV ?? 0) === (int)$k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label><label>Číslo bytu / patro<input name="byt" value="<?= $e($BYTV ?? '') ?>" size="12" placeholder="např. 12, 3. p."></label>
<label>Platí (jen u SVJ / družstva)<select name="plati"><option value="">– vyberte –</option><?php foreach (SJ_PLATI as $k => $t): ?><option value="<?= $k ?>"<?= ($PLV ?? '') === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label></div>
<label class="mut">Co zákazník potřebuje<textarea name="popis"><?= $e($r['popis']) ?></textarea></label>
<div class="ln"><label>Termín<input type="date" name="termin" value="<?= $e($r['termin']) ?>"></label><label>Čas<input name="cas" value="<?= $e($r['cas']) ?>" size="10"></label><label class="w">Poznámka (interní)<input name="poznamka" value="<?= $e($r['poznamka']) ?>"></label></div>
<button class="sec">Uložit údaje</button></form></details></div>

<form method="post" id="zapis"><input type="hidden" name="akce" value="zapis_ulozit"><input type="hidden" name="id" value="<?= $id ?>">
<div class="box"><h3>Stav</h3><div class="stavy"><?php foreach (['nova' => '📋 K vyřízení', 'hotovo' => '✅ Hotovo', 'zruseno' => '✋ Zrušeno'] as $k => $t): ?><label><input type="radio" name="stav" value="<?= $k ?>"<?= $r['stav'] === $k ? ' checked' : '' ?>><?= $t ?></label><?php endforeach; ?></div>
<div class="ln"><label>Provedeno dne<input type="date" name="provedeno" value="<?= $e($r['provedeno']) ?>"></label><span class="mut">při Hotovo se doplní dnešek</span></div>
<h3 style="margin-top:14px">Výsledek návštěvy</h3><div class="stavy"><?php foreach (SJ_VYSLEDKY as $k => $t): ?><label><input type="radio" name="vysledek" value="<?= $k ?>"<?= ($r['vysledek'] ?? '') === $k ? ' checked' : '' ?>><?= $t ?></label><?php endforeach; ?></div>
<p class="mut" style="margin-bottom:0">Při „Nutná oprava“ vyplňte níže závadu a návrh opravy – oprava se pak zapíše jako další servis (navazující).</p></div>
<div class="box"><h3>Provedeno</h3><div class="chk"><?php foreach (SO_KONTROLY as $k => $t): ?><label><input type="checkbox" name="<?= $k ?>" value="1"<?= $r[$k] ? ' checked' : '' ?>> <?= $t ?></label><?php endforeach; ?></div></div>
<div class="box"><h3>Okna</h3><div class="okna"><?php foreach (SO_OKNA as $k => $t): ?><label><?= $t ?><input type="number" min="0" name="<?= $k ?>" value="<?= (int)$r[$k] ?>" inputmode="numeric"></label><?php endforeach; ?></div></div>
<div class="box"><h3>Závady</h3><div class="chk"><?php foreach (SO_ZKRATKY as $k => $t): ?><label><input type="checkbox" name="zkratky[]" value="<?= $k ?>"<?= in_array($k, $zk, true) ? ' checked' : '' ?>> <?= $e($t) ?></label><?php endforeach; ?></div>
<label class="mut">Popis závady (okno, místnost, kování – výrobce, označení, FFH/FFB, dorn…)<textarea name="zavada" placeholder="např. ložnice, 2křídlé okno: převod Winkhaus GRK 1550/1, FFH 1300–1550, DM 15 – protočený"><?= $e($r['zavada']) ?></textarea></label>
<label class="mut">Opraveno hned na místě<input name="opraveno_hned" value="<?= $e($r['opraveno_hned']) ?>" style="width:100%"></label>
<label class="mut">Návrh opravy pro zákazníka<textarea name="oprava" placeholder="např. výměna převodu na křídle v ložnici"><?= $e($r['oprava']) ?></textarea></label>
<div class="ln"><label>Maximální cena opravy (Kč s DPH)<input name="max_cena" value="<?= $e($r['max_cena']) ?>" inputmode="decimal" size="10"></label></div></div>
<?php
$cenVse = $pdo->query("SELECT kod, nazev, skupina, jednotka, cena, dph FROM ok_cenik WHERE aktivni=1 ORDER BY CASE skupina WHEN 'byt' THEN 0 WHEN 'okno' THEN 1 WHEN 'prace' THEN 2 WHEN 'doprava' THEN 3 ELSE 4 END, poradi, id")->fetchAll(PDO::FETCH_ASSOC);
$polStart = [];
$pjStart = json_decode((string)($r['polozky'] ?? ''), true);
if (is_array($pjStart) && $pjStart) $polStart = $pjStart;
elseif (is_numeric($r['cena']) && (float)$r['cena'] > 0) {   // starší servis bez položek → jedna položka podle vybraného bytu/typu
  $c0 = null; foreach ($cenVse as $c) if ($c['kod'] === (string)($r['kategorie'] ?? '')) { $c0 = $c; break; }
  $polStart = [['kod' => (string)($r['kategorie'] ?? ''), 'nazev' => $c0 ? $c0['nazev'] : 'Servis oken a dveří', 'mn' => 1, 'jedn' => $c0 ? $c0['jednotka'] : 'servis', 'cena' => round((float)$r['cena'], 2), 'dph' => $c0 ? (int)$c0['dph'] : 12]];
}
?>
<style>#polList .polrow{border:1px solid #e3e8ef;border-radius:10px;padding:8px 10px;margin-bottom:8px;background:#fafbfc}#polList .pr1{display:flex;gap:8px;align-items:center}#polList .pr1 .pn{flex:1;min-width:0}#polList .pr2{display:flex;flex-wrap:wrap;gap:8px 10px;align-items:flex-end;margin-top:8px}#polList .pr2 label{display:flex;flex-direction:column;font-size:12px;color:#5f6b7a;gap:2px}#polList .pr2 input,#polList .pr2 select{padding:7px 8px}#polList .pm{width:62px;text-align:right}#polList .pc{width:92px;text-align:right}#polList .pd{width:74px}#polList .cl{margin-left:auto;font-weight:700;color:#1b2430;align-self:center;white-space:nowrap}#polList .px{background:#fde2e2;color:#9b1c1c;border:0;border-radius:8px;padding:7px 11px;cursor:pointer;font-weight:700}</style>
<div class="box"><h3>Cena a další servis</h3>
<?php if ($r['svj_id']): ?><div class="ln"><span class="mut">Platí:</span><?php foreach (SJ_PLATI as $k => $t): ?><label style="flex-direction:row;align-items:center;gap:6px;font-size:16px;color:inherit"><input type="radio" name="plati" value="<?= $k ?>"<?= ($r['plati'] ?? '') === $k ? ' checked' : '' ?>> <?= $e($t) ?></label><?php endforeach; ?></div><?php endif; ?>
<h4 style="margin:4px 0 6px">Položky k vyúčtování</h4>
<p class="mut" style="margin:0 0 6px">Přidejte vše, co se účtuje – servis bytu, dopravu, kličku, kování… Cena servisu je jejich součet. Množství i cenu lze přepsat.</p>
<div id="polList"></div>
<p style="text-align:right;margin:2px 0 0;font-size:17px"><b>Celkem s DPH: <span id="polSuma">0 Kč</span></b></p>
<div class="ln" style="align-items:flex-end;margin-top:8px"><label class="w">Přidat z ceníku<select id="polSel"><option value="">– vyberte položku –</option><?php $g = ''; foreach ($cenVse as $c): if ($c['skupina'] !== $g) { if ($g !== '') echo '</optgroup>'; $g = $c['skupina']; echo '<optgroup label="' . $e(OK_SKUPINY[$g] ?? $g) . '">'; } $cs = round($c['cena'] * (1 + $c['dph'] / 100)); ?><option value="<?= $e($c['kod']) ?>" data-nazev="<?= $e($c['nazev']) ?>" data-cena="<?= $cs ?>" data-dph="<?= (int)$c['dph'] ?>" data-jedn="<?= $e($c['jednotka']) ?>"><?= $e($c['nazev']) ?> – <?= ok_kc($cs) ?>/<?= $e($c['jednotka']) ?></option><?php endforeach; if ($g !== '') echo '</optgroup>'; ?></select></label>
<label>Počet<input type="number" min="0" step="0.5" id="polMn" value="1" style="width:80px"></label>
<button type="button" class="sec" id="polPridat">+ Přidat</button> <button type="button" class="sec" id="polVlastni">+ Vlastní položka</button></div>
<input type="hidden" name="polozky" id="polData"><input type="hidden" name="kategorie" id="katHid" value="<?= $e($r['kategorie'] ?? '') ?>"><input type="hidden" name="cena" id="cenaHid" value="<?= $e($r['cena']) ?>">
<div class="ln" style="margin-top:10px"><label>Další servis<select name="opakovat"><?php foreach (SJ_OPAKOVAT as $k => $t): ?><option value="<?= $k ?>"<?= (int)$r['opakovat'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
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
<p><a class="btn sec" href="nabidky.php?nova=jed&amp;id=<?= $id ?>">📄 Nabídka z ceníku pro zákazníka</a> <a class="btn sec" href="nabidky.php?nova=oprava&amp;jed=<?= $id ?>">🛠 Nabídka na opravu</a></p>
<?php if (in_array($r['vysledek'] ?? '', ['oprava', 'dily'], true) && !$nasl): ?><form method="post" class="box" style="border-left:5px solid #c2410c"><input type="hidden" name="akce" value="oprava_zalozit"><input type="hidden" name="id" value="<?= $id ?>"><b>Nutná oprava</b> – až budou díly a termín: <button class="ok">🛠 Založit opravu (navazující servis)</button></form><?php endif; ?>
<?php if ($r['stav'] === 'hotovo' && (int)$r['opakovat'] > 0 && !$nasl): ?><form method="post" class="box"><input type="hidden" name="akce" value="dalsi_zalozit"><input type="hidden" name="id" value="<?= $id ?>"><b>Další servis</b> <?= $dl ? 'kolem ' . $e(so_datum($dl)) : '' ?> <button class="sec">Založit další servis</button></form>
<?php elseif ($nasl): ?><p class="mut">Další servis: <a href="?id=<?= $nasl ?>">otevřít</a></p><?php endif; ?>
<?php if ($hist): ?><div class="box"><h3>Další servisy oken u zákazníka</h3><?php foreach ($hist as $h): ?><div><a href="?id=<?= (int)$h['id'] ?>"><?= $e(so_datum($h['provedeno'] ?: $h['termin']) ?: 'bez termínu') ?></a> <span class="st st-<?= $e($h['stav']) ?>"><?= $e(SJ_STAVY[$h['stav']] ?? '') ?></span></div><?php endforeach; ?></div><?php endif; ?>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat tento servis i zápis?')"><input type="hidden" name="akce" value="smazat"><input type="hidden" name="id" value="<?= $id ?>"><button class="del">Smazat servis</button></form><?php endif; ?>
<?php
  // data pro PDF protokolu (z uloženého zápisu)
  $kontroly = []; foreach (SO_KONTROLY as $k => $t) if ($r[$k]) $kontroly[] = $t;
  $okna = []; foreach (SO_OKNA as $k => $t) if ((int)$r[$k] > 0) $okna[] = $t . ': ' . (int)$r[$k];
  $pdfData = ['cislo' => 'SOJ-' . $id, 'jmeno' => $r['jmeno'], 'adresa' => sj_adresa($r), 'telefon' => $r['telefon'], 'email' => $r['email'],
    'datum' => so_datum($r['provedeno'] ?: $r['termin'] ?: date('Y-m-d')), 'stav' => trim((SJ_STAVY[$r['stav']] ?? '') . (($r['plati'] ?? '') === 'svj' && isset($svjSeznam[(int)$r['svj_id']]) ? ' · hradí ' . $svjSeznam[(int)$r['svj_id']] : (($r['plati'] ?? '') === 'najemnik' ? ' · hradí zákazník' : '')) . (isset(SJ_VYSLEDKY[$r['vysledek'] ?? '']) ? ' – ' . preg_replace('/^\S+\s/u', '', SJ_VYSLEDKY[$r['vysledek']]) : '')), 'popis' => (string)$r['popis'], 'kontroly' => $kontroly, 'okna' => implode(', ', $okna),
    'zkratky' => implode(', ', array_map(fn($k) => SO_ZKRATKY[$k] ?? $k, $zk)), 'zavada' => (string)$r['zavada'], 'opraveno' => (string)$r['opraveno_hned'], 'oprava' => (string)$r['oprava'],
    'max_cena' => $r['max_cena'] !== null ? $kc($r['max_cena']) : '', 'cena' => $r['cena'] !== null ? $kc($r['cena']) : '', 'dalsi' => $dl ? so_datum($dl) : '',
    'polozky' => array_map(fn($x) => ['nazev' => (string)($x['nazev'] ?? ''), 'mn' => (float)($x['mn'] ?? 1), 'jedn' => (string)($x['jedn'] ?? ''), 'cena' => (float)($x['cena'] ?? 0)], (is_array($pjStart = json_decode((string)($r['polozky'] ?? ''), true)) ? $pjStart : [])),
    'podpis' => $r['podpis'] ? '?podpis=' . $id . '&t=' . time() : '', 'podepsal' => (string)($r['podepsal'] ?: $r['jmeno']), 'technik' => (string)($r['technik'] ?: uziv_jmeno()),
    'fotky' => array_map(fn($f) => '?foto=' . $id . '&f=' . $f, array_slice($fot, 0, 6))]; ?>
<script>
(function () {
  // ── položky k vyúčtování ──
  var P = <?= json_encode(array_values(array_map(fn($x) => ['kod' => (string)($x['kod'] ?? ''), 'nazev' => (string)($x['nazev'] ?? ''), 'mn' => (float)($x['mn'] ?? 1), 'jedn' => (string)($x['jedn'] ?? 'ks'), 'cena' => (float)($x['cena'] ?? 0), 'dph' => (int)($x['dph'] ?? 12)], $polStart)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var tb = document.getElementById('polList'), suma = document.getElementById('polSuma'), dataIn = document.getElementById('polData'), cenaIn = document.getElementById('cenaHid'), katIn = document.getElementById('katHid');
  var kc = function (n) { return (Math.round(n * 100) / 100).toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' Kč'; };
  function uloz() {
    var sum = 0, kat = ''; P.forEach(function (it) { sum += (+it.mn || 0) * (+it.cena || 0); if (!kat && it.kod) kat = it.kod; });
    suma.textContent = kc(sum); cenaIn.value = Math.round(sum * 100) / 100; katIn.value = kat;
    dataIn.value = JSON.stringify(P);
  }
  function vykresli() {
    tb.innerHTML = '';
    if (!P.length) { tb.innerHTML = '<p class="mut" style="margin:0 0 6px">Zatím žádná položka. Přidejte z ceníku níže, nebo „Vlastní položka“.</p>'; return; }
    P.forEach(function (it, i) {
      var row = document.createElement('div'); row.className = 'polrow';
      row.innerHTML = '<div class="pr1"><input class="pn" placeholder="Název položky"><button type="button" class="px" title="Odebrat">×</button></div>'
        + '<div class="pr2"><label>Množ.<input class="pm" type="number" min="0" step="0.5"></label>'
        + '<label>Cena/j s DPH<input class="pc" type="number" min="0" step="0.01"></label>'
        + '<label>DPH<select class="pd"><option value="12">12 %</option><option value="21">21 %</option><option value="15">15 %</option><option value="0">0 %</option></select></label>'
        + '<span class="cl"></span></div>';
      var nn = row.querySelector('.pn'), mm = row.querySelector('.pm'), cc = row.querySelector('.pc'), dd = row.querySelector('.pd'), cl = row.querySelector('.cl');
      nn.value = it.nazev; mm.value = it.mn; cc.value = it.cena; dd.value = String(it.dph);
      function pre() { cl.textContent = kc((+mm.value || 0) * (+cc.value || 0)); }
      pre();
      nn.addEventListener('input', function () { it.nazev = nn.value; it.kod = ''; uloz(); });
      mm.addEventListener('input', function () { it.mn = +mm.value || 0; pre(); uloz(); });
      cc.addEventListener('input', function () { it.cena = +cc.value || 0; it.kod = ''; pre(); uloz(); });
      dd.addEventListener('change', function () { it.dph = +dd.value; it.kod = ''; uloz(); });
      row.querySelector('.px').addEventListener('click', function () { P.splice(i, 1); vykresli(); uloz(); });
      tb.appendChild(row);
    });
  }
  vykresli(); uloz();
  document.getElementById('polPridat').addEventListener('click', function () {
    var o = document.getElementById('polSel').selectedOptions[0]; if (!o || !o.value) { alert('Vyberte položku z ceníku.'); return; }
    var mn = +document.getElementById('polMn').value || 1;
    P.push({ kod: o.value, nazev: o.dataset.nazev, mn: mn, jedn: o.dataset.jedn, cena: +o.dataset.cena, dph: +o.dataset.dph });
    document.getElementById('polSel').value = ''; document.getElementById('polMn').value = 1; vykresli(); uloz();
  });
  document.getElementById('polVlastni').addEventListener('click', function () {
    P.push({ kod: '', nazev: '', mn: 1, jedn: 'ks', cena: 0, dph: 12 }); vykresli(); uloz();
    var ni = tb.querySelectorAll('.pn'); if (ni.length) ni[ni.length - 1].focus();
  });
  var frm = document.getElementById('zapis'); if (frm) frm.addEventListener('submit', uloz);
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
        return fetch('index.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
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
        var kcp = function (n) { return (Math.round(n * 100) / 100).toLocaleString('cs-CZ', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' Kč'; };
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
        sekce('Provedený servis'); radek('Datum', D.datum); radek('Provedeno', D.kontroly.join(', ') || '–'); radek('Okna', D.okna); radek('Opraveno na místě', D.opraveno);
        if (D.polozky && D.polozky.length) {
          y += 1; font('bold', 9, MUTED); doc.text('Položka', M, y); doc.text('Množ.', W - M - 52, y, { align: 'right' }); doc.text('Cena/j', W - M - 26, y, { align: 'right' }); doc.text('Celkem', W - M, y, { align: 'right' }); y += 1.5; doc.setDrawColor.apply(doc, LINE); doc.line(M, y, W - M, y); y += 4.5;
          D.polozky.forEach(function (it) { misto(5.2); var nm = doc.splitTextToSize(String(it.nazev), cw - 60); font('normal', 9.5, INK); doc.text(nm, M, y); font('normal', 9.5, MUTED);
            doc.text(String(it.mn).replace('.', ',') + (it.jedn ? ' ' + it.jedn : ''), W - M - 52, y, { align: 'right' }); doc.text(kcp(it.cena), W - M - 26, y, { align: 'right' }); font('bold', 9.5, INK); doc.text(kcp(it.mn * it.cena), W - M, y, { align: 'right' }); y += Math.max(5.2, nm.length * 4.6); });
          doc.setDrawColor.apply(doc, LINE); doc.line(M, y, W - M, y); y += 5; font('bold', 10, INK); doc.text('Celkem s DPH', M, y); doc.text(D.cena + ' s DPH', W - M, y, { align: 'right' }); y += 3;
        } else radek('Cena servisu', D.cena ? D.cena + ' s DPH' : '');
        y += 2;
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
      return fetch('index.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
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
<h1>🏠 Jednotlivci</h1>
<p><a class="btn ok" href="?nove=1">+ Nový servis u zákazníka</a></p>
<div class="box"><h2 style="margin-top:0">K vyřízení (<?= count($kvyr) ?>)</h2>
<?php if (!$kvyr): ?><p class="mut">Nic. Nový servis založíte tlačítkem výše, převzetím poptávky z webu (📨 Poptávky z webu) nebo ze Zákazníků.</p><?php else: ?>
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
<td><span class="st st-<?= $e($x['stav']) ?>"><?= $e(SJ_STAVY[$x['stav']] ?? '') ?></span><?= in_array($x['vysledek'] ?? '', ['oprava', 'dily'], true) ? ' <span class="st st-nova">' . $e(SJ_VYSLEDKY[$x['vysledek']]) . '</span>' : '' ?><?= $x['protokol_poslan'] ? ' <span class="mut">📧</span>' : '' ?></td><td class="r nowrap"><?= $e($kc($x['cena'])) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php endif; ?>
</main></body></html>
