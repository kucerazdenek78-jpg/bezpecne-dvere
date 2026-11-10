<?php
// Servis oken (samostatná aplikace /okna/) – SVJ, DRUŽSTVA A FIRMY (zákazníci s dokumenty na NASu, smlouvy se stavem podpisu a platností, historie servisů, připomínky). SVJ a družstva: vchody a byty (import seznamu z Excelu), servisní smlouvy
// s opakováním (další servis „N. z P“), servis domu po vchodech: servisní list v mobilu (kontroly, stav, závady, fotky, podpis),
// tisk papírového listu a report pro SVJ (výpis práce, nepřítomní, návrh oprav, úspěšnost). Stejná ochrana jako kartoteka.php.
// Data: tabulky so_* ve vlastní databázi okna.sqlite (okna/lib.php), fotky a podpisy v $DATA/okna/<id zápisu>/.
require __DIR__ . '/lib.php';
$pdo = ok_db();
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, 0, ',', ' ') . ' Kč';
$ted = date('Y-m-d H:i:s');
$P = fn($k, $max = 500) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)($_POST[$k] ?? ''))), 0, $max);
$cis = function ($v) { $v = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string)$v)); return $v === '' || !is_numeric($v) ? null : (float)$v; };
function so_jdi(string $url): void { header('Location: ' . $url); exit; }

// ───── fotky a podpis (zobrazení) ─────
if (isset($_GET['kovfoto'])) {
  $svj = (int)$_GET['kovfoto']; $n = (string)($_GET['f'] ?? ''); if (!preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { http_response_code(404); exit; }
  if (!empty($_GET['nahled'])) $n = substr($n, 0, 16) . '_n.jpg';
  $c = so_kovani_dir($svj) . '/' . $n; if (!is_file($c)) { http_response_code(404); exit('Nenalezeno'); }
  header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=86400'); readfile($c); exit;
}
if (isset($_GET['foto']) || isset($_GET['podpis'])) {
  $zid = (int)($_GET['foto'] ?? $_GET['podpis']);
  if (isset($_GET['podpis'])) { $c = so_dir($zid) . '/podpis.png'; $typ = 'image/png'; }
  else {
    $n = (string)($_GET['f'] ?? ''); if (!preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { http_response_code(404); exit; }
    if (!empty($_GET['nahled'])) $n = substr($n, 0, 16) . '_n.jpg';
    $c = so_dir($zid) . '/' . $n; $typ = 'image/jpeg';
  }
  if (!is_file($c)) { http_response_code(404); exit('Nenalezeno'); }
  header('Content-Type: ' . $typ); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=86400'); readfile($c); exit;
}

// zápis bytu v servisu (založí prázdný, když ještě není)
function so_zaznam(PDO $pdo, int $servis, int $byt, bool $zalozit = false): ?array {
  $st = $pdo->prepare('SELECT * FROM so_zaznam WHERE servis_id=? AND byt_id=?'); $st->execute([$servis, $byt]); $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r && $zalozit) { $pdo->prepare('INSERT INTO so_zaznam (servis_id, byt_id, upraveno) VALUES (?,?,?)')->execute([$servis, $byt, date('Y-m-d H:i:s')]); return so_zaznam($pdo, $servis, $byt); }
  return $r ?: null;
}

// ───── akce (POST) ─────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $a = $_POST['akce'] ?? '';
  if ($a === 'svj_ulozit') {
    $id = (int)($_POST['id'] ?? 0); $f = [$P('nazev', 200), $P('ic', 20), $P('adresa', 300), $P('kontakt', 200), $P('telefon', 100), $P('email', 200), $P('poznamka', 3000), isset(SO_TYPY_ZAK[$_POST['typ'] ?? '']) ? $_POST['typ'] : 'svj', $P('slozka', 500)];
    if ($f[0] === '') so_jdi('svj.php?svj=' . ($id ?: 'nove') . '&chyba=nazev');
    if ($id) $pdo->prepare('UPDATE so_svj SET nazev=?, ic=?, adresa=?, kontakt=?, telefon=?, email=?, poznamka=?, typ=?, slozka=?, upraveno=? WHERE id=?')->execute(array_merge($f, [$ted, $id]));
    else { $pdo->prepare('INSERT INTO so_svj (nazev, ic, adresa, kontakt, telefon, email, poznamka, typ, slozka, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($f, [$ted, $ted])); $id = (int)$pdo->lastInsertId(); }
    so_jdi('svj.php?svj=' . $id . '&ok=ulozeno');
  }
  if ($a === 'svj_smazat') {
    uziv_jen_admin('SVJ smí smazat jen administrátor.');
    $id = (int)$_POST['id'];
    foreach ($pdo->query('SELECT id FROM so_vchod WHERE svj_id=' . $id)->fetchAll(PDO::FETCH_COLUMN) as $v) $pdo->exec('DELETE FROM so_byt WHERE vchod_id=' . (int)$v);
    foreach ($pdo->query('SELECT id FROM so_servis WHERE svj_id=' . $id)->fetchAll(PDO::FETCH_COLUMN) as $s) { $pdo->exec('DELETE FROM so_zaznam WHERE servis_id=' . (int)$s); $pdo->exec('DELETE FROM so_plan WHERE servis_id=' . (int)$s); }
    foreach (['so_vchod', 'so_smlouva', 'so_servis', 'so_historie'] as $t) $pdo->exec("DELETE FROM $t WHERE svj_id=" . $id);
    $pdo->exec('DELETE FROM so_svj WHERE id=' . $id);
    so_jdi('svj.php?ok=smazano');
  }
  if ($a === 'smlouva_ulozit') {
    $id = (int)($_POST['id'] ?? 0); $svj = (int)$_POST['svj_id'];
    $f = [$P('cislo', 60), $P('od', 10), max(1, (int)($_POST['interval_let'] ?? 1)), max(1, (int)($_POST['pocet'] ?? 1)), $cis($_POST['limit_opravy'] ?? ''), $cis($_POST['pokuta'] ?? ''),
      ($_POST['havarie_h'] ?? '') === '' ? null : (int)$_POST['havarie_h'], ($_POST['zaruka_mes'] ?? '') === '' ? null : (int)$_POST['zaruka_mes'], max(0, (int)($_POST['nahradni'] ?? 1)), $P('nabidka', 60), $P('poznamka', 3000), empty($_POST['aktivni']) ? 0 : 1,
      isset(SO_PODPIS[$_POST['stav_podpisu'] ?? '']) ? $_POST['stav_podpisu'] : 'podepsana', $P('platnost_do', 10), $P('dokument', 500), max(7, (int)($_POST['pripomenout_dni'] ?? 60))];
    if ($id) $pdo->prepare('UPDATE so_smlouva SET cislo=?, od=?, interval_let=?, pocet=?, limit_opravy=?, pokuta=?, havarie_h=?, zaruka_mes=?, nahradni=?, nabidka=?, poznamka=?, aktivni=?, stav_podpisu=?, platnost_do=?, dokument=?, pripomenout_dni=? WHERE id=? AND svj_id=?')->execute(array_merge($f, [$id, $svj]));
    else $pdo->prepare('INSERT INTO so_smlouva (cislo, od, interval_let, pocet, limit_opravy, pokuta, havarie_h, zaruka_mes, nahradni, nabidka, poznamka, aktivni, stav_podpisu, platnost_do, dokument, pripomenout_dni, svj_id, vytvoreno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($f, [$svj, $ted]));
    so_jdi('svj.php?svj=' . $svj . '&ok=smlouva#smlouvy');
  }
  if ($a === 'smlouva_smazat') {
    uziv_jen_admin('Smlouvu smí smazat jen administrátor.');
    $pdo->prepare('DELETE FROM so_smlouva WHERE id=?')->execute([(int)$_POST['id']]); so_jdi('svj.php?svj=' . (int)$_POST['svj_id'] . '&ok=smazano#smlouvy');
  }
  if ($a === 'historie_ulozit') {
    $id = (int)($_POST['id'] ?? 0); $svj = (int)$_POST['svj_id'];
    $f = [($_POST['smlouva_id'] ?? '') === '' ? null : (int)$_POST['smlouva_id'], $P('datum', 10), isset(SO_HIST[$_POST['druh'] ?? '']) ? $_POST['druh'] : 'jine', $P('popis', 1000), $P('dokument', 500)];
    if ($f[1] !== '') {
      if ($id) $pdo->prepare('UPDATE so_historie SET smlouva_id=?, datum=?, druh=?, popis=?, dokument=? WHERE id=? AND svj_id=?')->execute(array_merge($f, [$id, $svj]));
      else $pdo->prepare('INSERT INTO so_historie (smlouva_id, datum, druh, popis, dokument, svj_id, vytvoreno, kdo) VALUES (?,?,?,?,?,?,?,?)')->execute(array_merge($f, [$svj, $ted, uziv_jmeno()]));
    }
    so_jdi('svj.php?svj=' . $svj . '&ok=ulozeno#historie');
  }
  if ($a === 'historie_smazat') {
    uziv_jen_admin('Záznam historie smí smazat jen administrátor.');
    $pdo->prepare('DELETE FROM so_historie WHERE id=?')->execute([(int)$_POST['id']]); so_jdi('svj.php?svj=' . (int)$_POST['svj_id'] . '&ok=smazano#historie');
  }
  if ($a === 'vchod_ulozit') {
    $id = (int)($_POST['id'] ?? 0); $svj = (int)$_POST['svj_id']; $cp = $P('cp', 30);
    if ($cp !== '') {
      if ($id) $pdo->prepare('UPDATE so_vchod SET cp=?, ulice=?, poradi=? WHERE id=? AND svj_id=?')->execute([$cp, $P('ulice', 200), (int)($_POST['poradi'] ?? 0), $id, $svj]);
      else $pdo->prepare('INSERT INTO so_vchod (svj_id, cp, ulice, poradi) VALUES (?,?,?,?)')->execute([$svj, $cp, $P('ulice', 200), (int)($_POST['poradi'] ?? 0)]);
    }
    so_jdi('svj.php?svj=' . $svj . '&ok=ulozeno#vchody');
  }
  if ($a === 'vchod_smazat') {
    uziv_jen_admin('Vchod smí smazat jen administrátor.');
    $id = (int)$_POST['id']; $pdo->prepare('UPDATE so_byt SET aktivni=0 WHERE vchod_id=?')->execute([$id]); $pdo->prepare('DELETE FROM so_vchod WHERE id=?')->execute([$id]);
    so_jdi('svj.php?svj=' . (int)$_POST['svj_id'] . '&ok=smazano#vchody');
  }
  if ($a === 'byt_ulozit') {
    $id = (int)($_POST['id'] ?? 0); $vchod = (int)$_POST['vchod_id']; $typ = isset(SO_TYPY[$_POST['typ'] ?? '']) ? $_POST['typ'] : 'byt';
    $f = [$typ, $P('patro', 20), $P('cislo', 20), $P('velikost', 20), $P('jmeno', 200), $P('vztah', 60), $P('najemce', 200), $P('telefon', 200), $P('poznamka', 1000)];
    foreach (array_keys(SO_OKNA) as $k) $f[] = max(0, (int)($_POST[$k] ?? 0));
    if ($id) $pdo->prepare('UPDATE so_byt SET typ=?, patro=?, cislo=?, velikost=?, jmeno=?, vztah=?, najemce=?, telefon=?, poznamka=?, o3=?, o2=?, o1=?, bd=?, vk=?, dv=? WHERE id=?')->execute(array_merge($f, [$id]));
    else $pdo->prepare('INSERT INTO so_byt (typ, patro, cislo, velikost, jmeno, vztah, najemce, telefon, poznamka, o3, o2, o1, bd, vk, dv, vchod_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge($f, [$vchod]));
    $zpet = (string)($_POST['zpet'] ?? ''); so_jdi(preg_match('/^svj\.php\?[\w=&%#.-]*$/', $zpet) ? $zpet : 'svj.php');
  }
  if ($a === 'byt_smazat') {
    uziv_jen_admin('Byt smí odebrat jen administrátor.');
    $pdo->prepare('UPDATE so_byt SET aktivni=0 WHERE id=?')->execute([(int)$_POST['id']]); so_jdi('svj.php?svj=' . (int)$_POST['svj_id'] . '&ok=smazano#vchody');
  }
  if ($a === 'import') {
    $svj = (int)$_POST['svj_id']; $d = json_decode((string)($_POST['data'] ?? ''), true);
    if (!so_svj($pdo, $svj) || !is_array($d)) so_jdi('svj.php?svj=' . $svj . '&chyba=import');
    $nV = 0; $nN = 0; $nA = 0; $s = fn($x, $m = 200) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$x)), 0, $m);
    $pdo->beginTransaction();
    foreach ((array)($d['vchody'] ?? []) as $i => $v) {
      $cp = $s($v['cp'] ?? '', 30); if ($cp === '') continue;
      $st = $pdo->prepare('SELECT id FROM so_vchod WHERE svj_id=? AND cp=?'); $st->execute([$svj, $cp]); $vid = (int)$st->fetchColumn();
      if (!$vid) { $pdo->prepare('INSERT INTO so_vchod (svj_id, cp, ulice, poradi) VALUES (?,?,?,?)')->execute([$svj, $cp, $s($v['ulice'] ?? ''), $i]); $vid = (int)$pdo->lastInsertId(); $nV++; }
      foreach ((array)($v['byty'] ?? []) as $j => $b) {
        $typ = ($b['typ'] ?? 'byt') === 'chodba' ? 'chodba' : 'byt'; $cislo = $s($b['cislo'] ?? '', 20); $patro = $s($b['patro'] ?? '', 20);
        if ($typ === 'byt' && $cislo === '') continue; if ($typ === 'chodba' && $patro === '') continue;
        $st = $typ === 'byt' ? $pdo->prepare("SELECT id FROM so_byt WHERE vchod_id=? AND typ='byt' AND cislo=? AND aktivni=1") : $pdo->prepare("SELECT id FROM so_byt WHERE vchod_id=? AND typ='chodba' AND patro=? AND aktivni=1");
        $st->execute([$vid, $typ === 'byt' ? $cislo : $patro]); $bid = (int)$st->fetchColumn();
        $f = ['patro' => $patro, 'velikost' => $s($b['velikost'] ?? '', 20), 'jmeno' => $s($b['jmeno'] ?? ''), 'vztah' => $s($b['vztah'] ?? '', 60), 'najemce' => $s($b['najemce'] ?? ''), 'telefon' => $s($b['telefon'] ?? ''), 'poznamka' => $s($b['poznamka'] ?? '', 1000)];
        if ($bid) {   // aktualizace: přepíše jen vyplněné údaje
          $set = []; $val = [];
          foreach ($f as $k => $x) if ($x !== '') { $set[] = "$k=?"; $val[] = $x; }
          if ($set) { $val[] = $bid; $pdo->prepare('UPDATE so_byt SET ' . implode(', ', $set) . ' WHERE id=?')->execute($val); }
          $nA++;
        } else {
          $pdo->prepare('INSERT INTO so_byt (vchod_id, typ, cislo, patro, velikost, jmeno, vztah, najemce, telefon, poznamka, poradi) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$vid, $typ, $cislo, $f['patro'], $f['velikost'], $f['jmeno'], $f['vztah'], $f['najemce'], $f['telefon'], $f['poznamka'], $typ === 'chodba' ? $j : 0]);
          $nN++;
        }
      }
    }
    $pdo->commit();
    so_jdi('svj.php?svj=' . $svj . '&ok=import&n=' . $nN . '&a=' . $nA . '&v=' . $nV . '#vchody');
  }
  if ($a === 'servis_zalozit') {
    $svj = (int)$_POST['svj_id']; $sm = (int)($_POST['smlouva_id'] ?? 0) ?: null;
    $pdo->prepare('INSERT INTO so_servis (svj_id, smlouva_id, nazev, stav, vytvoreno) VALUES (?,?,?,?,?)')->execute([$svj, $sm, $P('nazev', 200) ?: 'Servis oken ' . date('Y'), 'probiha', $ted]);
    so_jdi('svj.php?servis=' . (int)$pdo->lastInsertId());
  }
  if ($a === 'servis_ulozit') {
    $sid = (int)$_POST['servis_id'];
    $pdo->prepare('UPDATE so_servis SET nazev=?, poznamka=? WHERE id=?')->execute([$P('nazev', 200), $P('poznamka', 3000), $sid]);
    foreach ((array)($_POST['datum'] ?? []) as $vid => $dt) {
      $pdo->prepare('INSERT INTO so_plan (servis_id, vchod_id, datum, cas) VALUES (?,?,?,?) ON CONFLICT(servis_id, vchod_id) DO UPDATE SET datum=excluded.datum, cas=excluded.cas')
        ->execute([$sid, (int)$vid, mb_substr(trim((string)$dt), 0, 10), mb_substr(trim((string)($_POST['cas'][$vid] ?? '')), 0, 40)]);
    }
    so_jdi('svj.php?servis=' . $sid . '&ok=ulozeno');
  }
  if ($a === 'servis_stav') {
    $sid = (int)$_POST['servis_id']; $uz = ($_POST['stav'] ?? '') === 'uzavreno';
    $pdo->prepare('UPDATE so_servis SET stav=?, uzavreno=? WHERE id=?')->execute([$uz ? 'uzavreno' : 'probiha', $uz ? $ted : null, $sid]);
    so_jdi('svj.php?servis=' . $sid . '&ok=ulozeno');
  }
  if ($a === 'servis_smazat') {
    uziv_jen_admin('Servis smí smazat jen administrátor.');
    $sid = (int)$_POST['servis_id']; $svj = (int)$pdo->query('SELECT svj_id FROM so_servis WHERE id=' . $sid)->fetchColumn();
    $pdo->exec('DELETE FROM so_zaznam WHERE servis_id=' . $sid); $pdo->exec('DELETE FROM so_plan WHERE servis_id=' . $sid); $pdo->exec('DELETE FROM so_servis WHERE id=' . $sid);
    so_jdi('svj.php?svj=' . $svj . '&ok=smazano#servisy');
  }
  if ($a === 'zaznam_ulozit') {
    $sid = (int)$_POST['servis_id']; $bid = (int)$_POST['byt_id'];
    $z = so_zaznam($pdo, $sid, $bid, true); $stav = isset(SO_STAVY[$_POST['stav'] ?? '']) ? $_POST['stav'] : '';
    $zk = array_values(array_intersect(array_keys(SO_ZKRATKY), (array)($_POST['zkratky'] ?? [])));
    $pod = (string)$z['podpis'];
    $dataUrl = (string)($_POST['podpis_data'] ?? '');
    if (preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) && strlen($m[1]) < 2000000) {
      $bin = base64_decode($m[1]); $inf = @getimagesizefromstring($bin);
      if ($inf && $inf[2] === IMAGETYPE_PNG) { $dir = so_dir((int)$z['id']); if (!is_dir($dir)) @mkdir($dir, 0775, true); if (@file_put_contents($dir . '/podpis.png', $bin)) $pod = 'podpis.png'; }
    }
    if (!empty($_POST['podpis_smazat'])) { @unlink(so_dir((int)$z['id']) . '/podpis.png'); $pod = ''; }
    $pdo->prepare('UPDATE so_zaznam SET stav=?, k_kovani=?, k_ramy=?, k_tesneni=?, k_zlabky=?, zkratky=?, zavada=?, oprava=?, max_cena=?, opraveno_hned=?, podpis=?, podepsal=?, technik=?, kdy=COALESCE(NULLIF(?, \'\'), kdy), upraveno=? WHERE id=?')
      ->execute([$stav, empty($_POST['k_kovani']) ? 0 : 1, empty($_POST['k_ramy']) ? 0 : 1, empty($_POST['k_tesneni']) ? 0 : 1, empty($_POST['k_zlabky']) ? 0 : 1, implode(',', $zk),
        $P('zavada', 3000), $P('oprava', 3000), $cis($_POST['max_cena'] ?? ''), $P('opraveno_hned', 1000), $pod, $P('podepsal', 120), uziv_jmeno(), $stav !== '' && $stav !== $z['stav'] ? $ted : '', $ted, (int)$z['id']]);
    // počty oken a telefon se uloží k bytu (platí i pro další servisy)
    $okna = []; foreach (array_keys(SO_OKNA) as $k) $okna[] = max(0, (int)($_POST[$k] ?? 0));
    $pdo->prepare('UPDATE so_byt SET o3=?, o2=?, o1=?, bd=?, vk=?, dv=? WHERE id=?')->execute(array_merge($okna, [$bid]));
    if (isset($_POST['telefon'])) $pdo->prepare('UPDATE so_byt SET telefon=? WHERE id=?')->execute([$P('telefon', 200), $bid]);
    $vchod = (int)$pdo->query('SELECT vchod_id FROM so_byt WHERE id=' . $bid)->fetchColumn();
    if (!empty($_POST['dalsi'])) {   // další byt ve vchodu
      $ids = array_map(fn($b) => (int)$b['id'], so_byty($pdo, $vchod)); $i = array_search($bid, $ids, true);
      if ($i !== false && isset($ids[$i + 1])) so_jdi('svj.php?servis=' . $sid . '&byt=' . $ids[$i + 1] . '&ok=ulozeno');
    }
    so_jdi('svj.php?servis=' . $sid . '&vchod=' . $vchod . '&ok=ulozeno#b' . $bid);
  }
  if ($a === 'foto_nahraj') {
    header('Content-Type: application/json; charset=utf-8');
    $z = so_zaznam($pdo, (int)$_POST['servis_id'], (int)$_POST['byt_id'], true); $dir = so_dir((int)$z['id']);
    $ok = function ($f) { return $f && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) && filesize($f['tmp_name']) < 6 * 1048576 && (@getimagesize($f['tmp_name'])[2] ?? 0) === IMAGETYPE_JPEG; };
    if (count(so_fotky((int)$z['id'])) >= 30) exit(json_encode(['ok' => false, 'zprava' => 'Maximum 30 fotek u bytu.']));
    if (!$ok($_FILES['foto'] ?? null) || !$ok($_FILES['nahled'] ?? null)) exit(json_encode(['ok' => false, 'zprava' => 'Soubor není fotka JPEG.']));
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) exit(json_encode(['ok' => false, 'zprava' => 'Nelze vytvořit složku.']));
    $jm = bin2hex(random_bytes(8));
    $hotovo = move_uploaded_file($_FILES['foto']['tmp_name'], "$dir/$jm.jpg") && move_uploaded_file($_FILES['nahled']['tmp_name'], "$dir/{$jm}_n.jpg");
    exit(json_encode(['ok' => $hotovo, 'zprava' => $hotovo ? '' : 'Fotku se nepodařilo uložit.']));
  }
  if ($a === 'foto_smaz') {
    uziv_jen_admin('Fotku smí smazat jen administrátor.');
    $zid = (int)$_POST['zid']; $n = (string)$_POST['f'];
    if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { @unlink(so_dir($zid) . "/$n"); @unlink(so_dir($zid) . '/' . substr($n, 0, 16) . '_n.jpg'); }
    so_jdi('svj.php?servis=' . (int)$_POST['servis_id'] . '&byt=' . (int)$_POST['byt_id'] . '#fotky');
  }
  if ($a === 'kovani_ulozit') {
    $id = (int)$_POST['svj_id']; if (!so_svj($pdo, $id)) so_jdi('svj.php');
    $pdo->prepare('UPDATE so_svj SET kovani_typ=?, kovani_podtyp=?, kovani_rok=?, kovani_pozn=?, upraveno=? WHERE id=?')
      ->execute([$P('kovani_typ', 60), $P('kovani_podtyp', 80), $P('kovani_rok', 40), $P('kovani_pozn', 2000), $ted, $id]);
    so_jdi('svj.php?svj=' . $id . '&ok=ulozeno#kovani');
  }
  if ($a === 'kovani_foto') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_POST['svj_id']; if (!so_svj($pdo, $id)) exit(json_encode(['ok' => false, 'zprava' => 'Neznámý zákazník.']));
    $okf = function ($f) { return $f && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']) && filesize($f['tmp_name']) < 6 * 1048576 && (@getimagesize($f['tmp_name'])[2] ?? 0) === IMAGETYPE_JPEG; };
    if (count(so_kovani_fotky($id)) >= 20) exit(json_encode(['ok' => false, 'zprava' => 'Maximum 20 fotek kování.']));
    if (!$okf($_FILES['foto'] ?? null) || !$okf($_FILES['nahled'] ?? null)) exit(json_encode(['ok' => false, 'zprava' => 'Soubor není fotka JPEG.']));
    $dir = so_kovani_dir($id); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) exit(json_encode(['ok' => false, 'zprava' => 'Nelze vytvořit složku.']));
    $jm = bin2hex(random_bytes(8));
    $hot = move_uploaded_file($_FILES['foto']['tmp_name'], "$dir/$jm.jpg") && move_uploaded_file($_FILES['nahled']['tmp_name'], "$dir/{$jm}_n.jpg");
    exit(json_encode(['ok' => $hot, 'zprava' => $hot ? '' : 'Fotku se nepodařilo uložit.']));
  }
  if ($a === 'kovani_foto_smaz') {
    uziv_jen_admin('Fotku smí smazat jen administrátor.');
    $id = (int)$_POST['svj_id']; $n = (string)$_POST['f'];
    if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { @unlink(so_kovani_dir($id) . "/$n"); @unlink(so_kovani_dir($id) . '/' . substr($n, 0, 16) . '_n.jpg'); }
    so_jdi('svj.php?svj=' . $id . '#kovani');
  }
  http_response_code(400); exit('Neznámá akce');
}

// ───── zobrazení ─────
$svjId = isset($_GET['svj']) ? $_GET['svj'] : null; $servisId = (int)($_GET['servis'] ?? 0);
$tisk = isset($_GET['tisk']) || isset($_GET['report']);
$OK = ['ulozeno' => 'Uloženo.', 'smazano' => 'Smazáno.', 'smlouva' => 'Smlouva uložena.', 'import' => 'Seznam nahrán.'];
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0e4f5c"><title>SVJ a družstva – Servis oken</title><style>
body{font:16px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:1000px;margin:0 auto;padding:14px}
.box{background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{margin:4px 0 10px;font-size:24px}h2{margin:16px 0 8px;font-size:19px}h3{margin:6px 0 8px;font-size:17px}
.mut{color:#5f6b7a;font-size:14px}a{color:#1f5fbf}.zk{background:#fff4d6;border:1px solid #f0cf7a;color:#6b4a00;border-radius:10px;padding:8px 12px;margin-bottom:12px;font-size:14px}
button,.btn{display:inline-block;background:#1f5fbf;color:#fff;border:0;border-radius:10px;padding:10px 16px;font:600 16px system-ui,Arial,sans-serif;cursor:pointer;text-decoration:none}
button.sec,.btn.sec{background:#e6ebf3;color:#1b2430}button.del{background:#fde2e2;color:#9b1c1c}button.ok,.btn.ok{background:#15803d}
input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #c9d1dc;border-radius:9px;background:#fff;box-sizing:border-box;max-width:100%}textarea{width:100%;min-height:70px}
.ln{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:flex-end;margin:8px 0}.ln label{display:flex;flex-direction:column;font-size:13px;color:#556;gap:3px}.ln label.w{flex:1 1 260px}.ln label.w input{width:100%}
table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e3e8ef;text-align:left;vertical-align:top;font-size:15px}th{font-size:13px;color:#556;background:#f6f8fb}
.msg{background:#e6f6ea;border:1px solid #9bd2a8;border-radius:10px;padding:10px 14px;margin-bottom:14px}.err{background:#fde2e2;border-color:#f3a7a7}
.st{display:inline-block;font-size:13px;border-radius:999px;padding:3px 10px;background:#e6ebf3;white-space:nowrap}.st-hotovo{background:#d9f2e0;color:#14653a}.st-nebyl,.st-nahradni{background:#fff0cc;color:#7a5200}.st-odmitl{background:#fde2e2;color:#9b1c1c}
.bar{height:10px;background:#e6ebf3;border-radius:6px;overflow:hidden}.bar i{display:block;height:100%;background:#15803d}
.byty a.byt{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:13px 12px;border-bottom:1px solid #e3e8ef;text-decoration:none;color:inherit}.byty a.byt:active{background:#eef3fb}
.stavy{display:flex;flex-wrap:wrap;gap:8px}.stavy label{flex:1 1 140px;border:2px solid #c9d1dc;border-radius:12px;padding:12px;text-align:center;font-weight:600;cursor:pointer}.stavy input{display:none}.stavy label:has(input:checked){border-color:#1f5fbf;background:#e8f0fd}
.chk label{display:flex;align-items:center;gap:10px;padding:9px 0;font-size:17px}.chk input{width:24px;height:24px}
.okna{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:8px}.okna label{display:flex;flex-direction:column;font-size:13px;color:#556}.okna input{width:100%}
canvas#podpis{width:100%;height:180px;border:2px dashed #9aa7b8;border-radius:12px;background:#fff;touch-action:none}
details>summary{cursor:pointer;color:#1f5fbf;font-weight:600;margin:6px 0}.r{text-align:right}.nowrap{white-space:nowrap}
@media print{.bdbar,.noprint,.bdpom-btn,.bdpom-okno{display:none!important}body{background:#fff;font-size:12px}main{max-width:none;padding:0}.box{box-shadow:none;padding:0;margin-bottom:10px}th,td{font-size:11px;padding:3px 5px;border:1px solid #999}h1{font-size:18px}h2{font-size:15px}.pb{page-break-before:always}}
</style></head><body><?php if (!$tisk) ok_bar('svj'); ?><main>
<?php if (!$tisk): ?><div class="zk noprint">🧪 <b>Zkušební verze aplikace Servis oken.</b> Má vlastní databázi – se systémem dveří se nemíchá. Jotformové protokoly zatím běží dál.</div><?php endif; ?>
<?php if (isset($_GET['ok'], $OK[$_GET['ok']])): ?><div class="msg noprint"><?= $e($OK[$_GET['ok']]) ?><?php if ($_GET['ok'] === 'import'): ?> Nové: <?= (int)($_GET['n'] ?? 0) ?>, aktualizované: <?= (int)($_GET['a'] ?? 0) ?>, nových vchodů: <?= (int)($_GET['v'] ?? 0) ?>.<?php endif; ?></div><?php endif; ?>
<?php if (!empty($_GET['chyba'])): ?><div class="msg err noprint"><?= $_GET['chyba'] === 'nazev' ? 'Vyplňte název SVJ.' : 'Import se nepodařil.' ?></div><?php endif; ?>
<?php
// ═════════ PŘEHLED ═════════
if ($svjId === null && !$servisId):
  $svjs = $pdo->query('SELECT * FROM so_svj ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC);
  $bezi = $pdo->query("SELECT s.*, j.nazev AS svj_nazev FROM so_servis s JOIN so_svj j ON j.id=s.svj_id WHERE s.stav='probiha' ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
  $pr = so_pripominky($pdo);
  $fTyp = (string)($_GET['typ'] ?? ''); $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
  if ($fTyp !== '') $svjs = array_filter($svjs, fn($j) => ($j['typ'] ?? 'svj') === $fTyp);
  if ($q !== '') $svjs = array_filter($svjs, fn($j) => mb_strpos(mb_strtolower($j['nazev'] . ' ' . $j['adresa'] . ' ' . $j['kontakt'] . ' ' . $j['ic']), $q) !== false);
?>
<h1>🏢 SVJ, družstva a firmy</h1>
<?php if ($pr['termin'] || $pr['nova'] || $pr['overit']): ?><div class="box" style="border-left:5px solid #d97706"><h2 style="margin-top:0">⏰ Připomenutí smluv</h2>
<?php if ($pr['termin']): ?><h3>Servis podle smlouvy – blíží se / prošel termín</h3><?php foreach ($pr['termin'] as $x): ?>
  <p><a href="?svj=<?= (int)$x['sm']['svj_id'] ?>#smlouvy"><b><?= $e($x['sm']['svj_nazev']) ?></b></a> – <?= (int)$x['ss']['dalsi'] ?>. servis z <?= (int)$x['ss']['pocet'] ?> do <b><?= $e(so_datum($x['ss']['do'])) ?></b>
  <span class="st <?= $x['ss']['dni'] < 0 ? 'st-odmitl' : 'st-nebyl' ?>"><?= $x['ss']['dni'] < 0 ? 'po termínu ' . -$x['ss']['dni'] . ' dní' : 'za ' . $x['ss']['dni'] . ' dní' ?></span></p><?php endforeach; endif; ?>
<?php if ($pr['nova']): ?><h3>Smlouva splněná nebo vypršelá – nabídnout novou</h3><?php foreach ($pr['nova'] as $x): ?>
  <p><a href="?svj=<?= (int)$x['sm']['svj_id'] ?>#smlouvy"><b><?= $e($x['sm']['svj_nazev']) ?></b></a> <span class="mut">– <?= $x['ss']['konec'] ? 'proběhlo ' . $x['ss']['hotovo'] . ' z ' . $x['ss']['pocet'] : 'platnost do ' . $e(so_datum($x['ss']['platnost_do'])) ?></span>
  <a class="btn sec" style="padding:5px 10px;font-size:14px" href="nabidky.php?nova=svj&amp;svj=<?= (int)$x['sm']['svj_id'] ?>">📄 Nabídka</a></p><?php endforeach; endif; ?>
<?php if ($pr['overit']): ?><details><summary>Smlouvy k ověření – na NASu je jen návrh (<?= count($pr['overit']) ?>)</summary><?php foreach ($pr['overit'] as $x): ?>
  <p><a href="?svj=<?= (int)$x['sm']['svj_id'] ?>#smlouvy"><b><?= $e($x['sm']['svj_nazev']) ?></b></a> <span class="mut">– <?= $e(SO_PODPIS[$x['sm']['stav_podpisu']] ?? '') ?>. Až potvrdíte podpis a datum, začne se hlídat termín servisu.</span></p><?php endforeach; ?></details><?php endif; ?>
</div><?php endif; ?>
<?php if ($bezi): ?><div class="box"><h2 style="margin-top:0">Běžící servisy</h2>
<?php foreach ($bezi as $s): $sh = so_souhrn($pdo, $s); $b = array_sum(array_column($sh, 'bytu')); $h = array_sum(array_column($sh, 'hotovo')); ?>
  <p><a class="btn" href="?servis=<?= (int)$s['id'] ?>"><?= $e($s['svj_nazev']) ?> – <?= $e($s['nazev']) ?></a> <span class="mut">hotovo <?= $h ?> z <?= $b ?> bytů</span></p>
<?php endforeach; ?></div><?php endif; ?>
<div class="box"><h2 style="margin-top:0">Zákazníci <span class="mut">(<?= count($svjs) ?>)</span></h2>
<form class="ln noprint"><label class="w">Hledat (název, adresa, kontakt, IČ)<input name="q" value="<?= $e($_GET['q'] ?? '') ?>"></label>
<label>Druh<select name="typ"><option value="">všichni</option><?php foreach (SO_TYPY_ZAK as $k => $t): ?><option value="<?= $k ?>"<?= $fTyp === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label><button class="sec">Filtrovat</button></form>
<?php if (!$svjs): ?><p class="mut">Nic nenalezeno.</p><?php else: ?>
<div style="overflow-x:auto"><table><tr><th>Název</th><th>Poslední servis</th><th>Vchody / byty</th><th>Smlouva</th></tr>
<?php foreach ($svjs as $j): $vch = so_vchody($pdo, (int)$j['id']); $nb = 0; foreach ($vch as $v) $nb += (int)$pdo->query("SELECT COUNT(*) FROM so_byt WHERE aktivni=1 AND typ='byt' AND vchod_id=" . (int)$v['id'])->fetchColumn();
  $sm = so_smlouvy($pdo, (int)$j['id']); $sm = $sm && $sm[0]['aktivni'] ? $sm[0] : null; $ss = $sm ? so_smlouva_stav($pdo, $sm) : null; $ps = so_posledni_servis($pdo, (int)$j['id']); ?>
  <tr><td><a href="?svj=<?= (int)$j['id'] ?>"><b><?= $e($j['nazev']) ?></b></a> <span class="st"><?= $e(SO_TYPY_ZAK[$j['typ'] ?? 'svj'] ?? '') ?></span><div class="mut"><?= $e(implode(' · ', array_filter([(string)$j['adresa'], (string)$j['kontakt']]))) ?></div></td>
  <td class="nowrap"><?= $ps ? $e(so_datum($ps)) : '<span class="mut">–</span>' ?></td><td><?= count($vch) ?> / <?= $nb ?></td>
  <td><?php if (!$ss): ?><span class="mut">bez smlouvy</span><?php elseif (!$ss['podepsana']): ?><span class="st st-nebyl">návrh – ověřit</span><?php elseif ($ss['konec'] || $ss['vyprsela']): ?><span class="st st-odmitl"><?= $ss['konec'] ? 'splněno ' . $ss['hotovo'] . ' z ' . $ss['pocet'] : 'vypršela' ?> – nová smlouva</span><?php else: $blizko = $ss['dni'] <= (int)($sm['pripomenout_dni'] ?? 60); ?>
    <span class="st <?= $blizko ? 'st-nebyl' : 'st-hotovo' ?>">další <?= $ss['dalsi'] ?>. z <?= $ss['pocet'] ?> do <?= $e(so_datum($ss['do'])) ?></span><?php endif; ?></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
<p><a class="btn" href="?svj=nove">+ Nový zákazník (SVJ, družstvo, firma)</a></p></div>
<?php
// ═════════ SVJ ═════════
elseif ($svjId !== null):
  $j = $svjId === 'nove' ? ['id' => 0, 'nazev' => '', 'ic' => '', 'adresa' => '', 'kontakt' => '', 'telefon' => '', 'email' => '', 'poznamka' => '', 'typ' => 'svj', 'slozka' => ''] : so_svj($pdo, (int)$svjId);
  if (!$j): ?><p>SVJ nenalezeno. <a href="svj.php">Zpět</a></p><?php else:
  $sid = (int)$j['id'];
  // úprava jednoho bytu
  if ($sid && isset($_GET['byt_upravit'])):
    $bid = (int)$_GET['byt_upravit']; $b = $bid ? $pdo->query('SELECT * FROM so_byt WHERE id=' . $bid)->fetch(PDO::FETCH_ASSOC) : ['id' => 0, 'vchod_id' => (int)($_GET['vchod'] ?? 0), 'typ' => $_GET['typ'] ?? 'byt', 'patro' => '', 'cislo' => '', 'velikost' => '', 'jmeno' => '', 'vztah' => '', 'najemce' => '', 'telefon' => '', 'poznamka' => '', 'o3' => 0, 'o2' => 0, 'o1' => 0, 'bd' => 0, 'vk' => 0, 'dv' => 0];
?>
<p class="noprint"><a href="?svj=<?= $sid ?>#vchody">← <?= $e($j['nazev']) ?></a></p>
<div class="box"><h1><?= $bid ? 'Upravit' : 'Přidat' ?>: <?= $e(so_byt_nazev($b)) ?></h1>
<form method="post"><input type="hidden" name="akce" value="byt_ulozit"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="vchod_id" value="<?= (int)$b['vchod_id'] ?>"><input type="hidden" name="zpet" value="svj.php?svj=<?= $sid ?>&ok=ulozeno#vchody">
<div class="ln"><label>Druh<select name="typ"><?php foreach (SO_TYPY as $k => $t): ?><option value="<?= $k ?>"<?= $b['typ'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label>
<label>Patro<input name="patro" value="<?= $e($b['patro']) ?>" size="5"></label><label>Číslo bytu<input name="cislo" value="<?= $e($b['cislo']) ?>" size="6"></label><label>Velikost<input name="velikost" value="<?= $e($b['velikost']) ?>" size="6"></label>
<label class="w">Jméno (u Ostatní název, např. Sklep)<input name="jmeno" value="<?= $e($b['jmeno']) ?>"></label><label>Vztah<input name="vztah" value="<?= $e($b['vztah']) ?>" size="12"></label></div>
<div class="ln"><label class="w">Nájemce / podnájemce<input name="najemce" value="<?= $e($b['najemce']) ?>"></label><label class="w">Telefon<input name="telefon" value="<?= $e($b['telefon']) ?>"></label></div>
<div class="ln"><label class="w">Poznámka<input name="poznamka" value="<?= $e($b['poznamka']) ?>"></label></div>
<h3>Počty oken</h3><div class="okna"><?php foreach (SO_OKNA as $k => $t): ?><label><?= $t ?><input type="number" min="0" name="<?= $k ?>" value="<?= (int)$b[$k] ?>" inputmode="numeric"></label><?php endforeach; ?></div>
<p><button>Uložit</button></p></form>
<?php if ($bid && uziv_admin()): ?><form method="post" onsubmit="return confirm('Odebrat ze seznamu? (Zápisy ze servisů zůstanou.)')"><input type="hidden" name="akce" value="byt_smazat"><input type="hidden" name="id" value="<?= $bid ?>"><input type="hidden" name="svj_id" value="<?= $sid ?>"><button class="del">Odebrat ze seznamu</button></form><?php endif; ?>
</div>
<?php else: ?>
<p class="noprint"><a href="svj.php">← Servis oken</a></p>
<h1><?= $sid ? $e($j['nazev']) : 'Nový zákazník' ?> <?= $sid ? '<span class="st">' . $e(SO_TYPY_ZAK[$j['typ'] ?? 'svj'] ?? '') . '</span>' : '' ?></h1>
<?php if ($sid): ?><div class="mut" style="margin:-4px 0 10px"><?= $e($j['adresa']) ?><?= ok_mapa((string)$j['adresa']) ?><?= $j['ic'] ? ' · IČ ' . $e($j['ic']) : '' ?><?php if (!empty($j['slozka'])): ?><br>Složka na NASu: <?= so_slozka_odkaz((string)$j['slozka']) ?> <code style="font-size:12px"><?= $e($j['slozka']) ?></code><?php endif; ?><?= $j['poznamka'] ? '<br>📝 ' . nl2br($e($j['poznamka'])) : '' ?><?php $kv = trim(implode(', ', array_filter([(string)($j['kovani_typ'] ?? ''), (string)($j['kovani_podtyp'] ?? ''), ($j['kovani_rok'] ?? '') !== '' ? 'rok ' . $j['kovani_rok'] : '']))); if ($kv !== ''): ?><br>🔧 Kování: <a href="#kovani"><?= $e($kv) ?></a><?php endif; ?></div><?php endif; ?>
<div class="box"><?php if ($sid): ?><details><summary>Údaje zákazníka (upravit)</summary><?php endif; ?>
<form method="post"><input type="hidden" name="akce" value="svj_ulozit"><input type="hidden" name="id" value="<?= $sid ?>">
<div class="ln"><label class="w">Název *<input name="nazev" value="<?= $e($j['nazev']) ?>" required placeholder="např. SV bl. 8, SNP 2649, 2650"></label><label>Druh<select name="typ"><?php foreach (SO_TYPY_ZAK as $k => $t): ?><option value="<?= $k ?>"<?= ($j['typ'] ?? 'svj') === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label><label>IČ<input name="ic" value="<?= $e($j['ic']) ?>" size="10"></label></div>
<div class="ln"><label class="w">Složka s dokumenty na NASu<input name="slozka" value="<?= $e($j['slozka'] ?? '') ?>" placeholder="/Firma/0 KKSYSTEM SHAREPOINT/7 SERVIS/ZÁKAZNÍCI/…"></label></div>
<div class="ln"><label class="w">Adresa<input name="adresa" value="<?= $e($j['adresa']) ?>"></label></div>
<div class="ln"><label class="w">Předseda / správce<input name="kontakt" value="<?= $e($j['kontakt']) ?>"></label><label>Telefon<input name="telefon" value="<?= $e($j['telefon']) ?>"></label><label class="w">E-mail (pro report)<input name="email" type="email" value="<?= $e($j['email']) ?>"></label></div>
<div class="ln"><label class="w">Poznámka (klíče, přístup…)<textarea name="poznamka"><?= $e($j['poznamka']) ?></textarea></label></div>
<button>Uložit</button></form><?php if ($sid): ?></details><?php if ($j['kontakt'] || $j['telefon'] || $j['email']): ?><p class="mut"><?= $e($j['kontakt']) ?> <?= $j['telefon'] ? '· <a href="tel:' . $e(preg_replace('/\s+/', '', $j['telefon'])) . '">' . $e($j['telefon']) . '</a>' : '' ?> <?= $j['email'] ? '· ' . $e($j['email']) : '' ?></p><?php endif; ?><?php endif; ?></div>
<?php if ($sid):
  $smlouvy = so_smlouvy($pdo, $sid); $vchody = so_vchody($pdo, $sid); $servisy = so_servisy($pdo, $sid); ?>
<p class="noprint"><a class="btn sec" href="nabidky.php?nova=svj&amp;svj=<?= $sid ?>">📄 Nabídka z ceníku pro toto SVJ</a></p>
<div class="box" id="kovani"><h2 style="margin-top:0">🔧 Kování na oknech</h2>
<p class="mut" style="margin-top:0">Společný údaj pro celý dům / firmu – značka a typ kování, rok výroby a fotky pro orientaci (poznávací prvky, štítky). Pomáhá při shánění dílů a při servisu.</p>
<form method="post"><input type="hidden" name="akce" value="kovani_ulozit"><input type="hidden" name="svj_id" value="<?= $sid ?>">
<div class="ln"><label>Značka kování<input name="kovani_typ" list="kovZn" value="<?= $e($j['kovani_typ'] ?? '') ?>" placeholder="např. Roto"></label>
<label class="w">Typ / podtyp<input name="kovani_podtyp" value="<?= $e($j['kovani_podtyp'] ?? '') ?>" placeholder="např. Roto NT, dřevo / plast"></label>
<label>Rok výroby oken<input name="kovani_rok" value="<?= $e($j['kovani_rok'] ?? '') ?>" placeholder="např. 2008" style="width:120px"></label></div>
<datalist id="kovZn"><?php foreach (SO_KOVANI_ZNACKY as $z): ?><option value="<?= $e($z) ?>"><?php endforeach; ?></datalist>
<div class="ln"><label class="w">Poznámka ke kování<textarea name="kovani_pozn" rows="2" placeholder="kde je štítek, zvláštnosti, dodavatel dílů…"><?= $e($j['kovani_pozn'] ?? '') ?></textarea></label></div>
<p style="margin:6px 0 0"><button class="sec">Uložit kování</button></p></form>
<?php $kf = so_kovani_fotky($sid); ?>
<h3 style="margin:14px 0 6px">Fotky kování<?= $kf ? ' (' . count($kf) . ')' : '' ?></h3>
<div style="display:flex;flex-wrap:wrap;gap:8px"><?php foreach ($kf as $f): ?><div><a href="?kovfoto=<?= $sid ?>&amp;f=<?= $e($f) ?>" target="_blank"><img src="?kovfoto=<?= $sid ?>&amp;f=<?= $e($f) ?>&amp;nahled=1" alt="Kování" style="width:120px;height:90px;object-fit:cover;border-radius:8px"></a>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat fotku?')" style="margin-top:2px"><input type="hidden" name="akce" value="kovani_foto_smaz"><input type="hidden" name="svj_id" value="<?= $sid ?>"><input type="hidden" name="f" value="<?= $e($f) ?>"><button class="del" style="padding:3px 8px;font-size:13px">smazat</button></form><?php endif; ?></div><?php endforeach; ?></div>
<input type="file" id="kovVyber" accept="image/*" multiple style="display:none"><p class="noprint" style="margin-top:8px"><button type="button" class="sec" id="kovTl">📷 Přidat fotky kování</button> <span id="kovStav" class="mut"></span></p></div>
<script>
(function () {
  var vyber = document.getElementById('kovVyber'), tl = document.getElementById('kovTl'), st = document.getElementById('kovStav'); if (!tl) return;
  tl.addEventListener('click', function () { vyber.click(); });
  function zmensi(s, max, q) { return createImageBitmap(s, { imageOrientation: 'from-image' }).then(function (bm) { var k = Math.min(1, max / Math.max(bm.width, bm.height)), cv = document.createElement('canvas'); cv.width = Math.round(bm.width * k); cv.height = Math.round(bm.height * k); cv.getContext('2d').drawImage(bm, 0, 0, cv.width, cv.height); return new Promise(function (ok) { cv.toBlob(ok, 'image/jpeg', q); }); }); }
  vyber.addEventListener('change', function () {
    var sb = Array.prototype.slice.call(vyber.files || []), hot = 0, chy = []; if (!sb.length) return; tl.disabled = true;
    (function dalsi(i) {
      if (i >= sb.length) { st.textContent = 'Nahráno ' + hot + (chy.length ? ', chyby: ' + chy.join('; ') : '.'); if (hot) setTimeout(function () { location.href = '?svj=<?= $sid ?>#kovani'; location.reload(); }, 500); else tl.disabled = false; return; }
      st.textContent = 'Nahrávám ' + (i + 1) + ' z ' + sb.length + '…';
      Promise.all([zmensi(sb[i], 1600, .82), zmensi(sb[i], 320, .75)]).then(function (b) {
        var fd = new FormData(); fd.append('akce', 'kovani_foto'); fd.append('svj_id', '<?= $sid ?>'); fd.append('foto', b[0], 'f.jpg'); fd.append('nahled', b[1], 'n.jpg');
        return fetch('svj.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
      }).then(function (j) { if (j.ok) hot++; else chy.push(j.zprava); }).catch(function () { chy.push(sb[i].name); }).then(function () { dalsi(i + 1); });
    })(0);
  });
})();
</script>
<div class="box" id="servisy"><h2 style="margin-top:0">Servisy</h2>
<?php foreach ($servisy as $s): $sh = so_souhrn($pdo, $s); $b = array_sum(array_column($sh, 'bytu')); $h = array_sum(array_column($sh, 'hotovo')); ?>
  <p><a href="?servis=<?= (int)$s['id'] ?>"><b><?= $e($s['nazev']) ?></b></a> <span class="st <?= $s['stav'] === 'uzavreno' ? 'st-hotovo' : 'st-nebyl' ?>"><?= $s['stav'] === 'uzavreno' ? 'uzavřeno' : 'probíhá' ?></span> <span class="mut">hotovo <?= $h ?> z <?= $b ?> bytů · založeno <?= $e(so_datum($s['vytvoreno'])) ?></span></p>
<?php endforeach; ?>
<?php if ($vchody): ?><form method="post" class="ln noprint"><input type="hidden" name="akce" value="servis_zalozit"><input type="hidden" name="svj_id" value="<?= $sid ?>">
  <label class="w">Název servisu<input name="nazev" value="Servis oken <?= date('Y') ?>"></label>
  <label>Podle smlouvy<select name="smlouva_id"><option value="">bez smlouvy (jednorázový)</option><?php foreach ($smlouvy as $sm): if (!$sm['aktivni']) continue; $ss = so_smlouva_stav($pdo, $sm); ?><option value="<?= (int)$sm['id'] ?>" selected><?= $e($sm['cislo'] ?: 'smlouva od ' . so_datum($sm['od'])) ?><?= $ss['konec'] ? ' (už splněna)' : ' – ' . $ss['dalsi'] . '. z ' . $ss['pocet'] ?></option><?php endforeach; ?></select></label>
  <button>+ Založit servis</button></form><?php else: ?><p class="mut">Nejdřív nahrajte vchody a byty (níže).</p><?php endif; ?></div>

<div class="box" id="smlouvy"><h2 style="margin-top:0">Servisní smlouvy</h2>
<?php $smForm = function ($sm) use ($e, $sid) { ?>
  <form method="post"><input type="hidden" name="akce" value="smlouva_ulozit"><input type="hidden" name="svj_id" value="<?= $sid ?>"><input type="hidden" name="id" value="<?= (int)$sm['id'] ?>">
  <div class="ln"><label>Číslo / označení<input name="cislo" value="<?= $e($sm['cislo']) ?>" size="12"></label><label>Podepsána dne<input type="date" name="od" value="<?= $e($sm['od']) ?>"></label>
  <label>Opakování<select name="interval_let"><option value="1"<?= (int)$sm['interval_let'] === 1 ? ' selected' : '' ?>>ročně</option><option value="2"<?= (int)$sm['interval_let'] === 2 ? ' selected' : '' ?>>jednou za 2 roky</option></select></label>
  <label>Počet servisů<select name="pocet"><?php foreach ([1, 3, 5] as $n): ?><option<?= (int)$sm['pocet'] === $n ? ' selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
  <label>Nabídka č.<input name="nabidka" value="<?= $e($sm['nabidka']) ?>" size="10"></label></div>
  <div class="ln"><label>Oprava bez souhlasu do (Kč bez DPH)<input name="limit_opravy" value="<?= $e($sm['limit_opravy']) ?>" size="8" inputmode="decimal"></label><label>Pokuta za nepřístupný byt (Kč)<input name="pokuta" value="<?= $e($sm['pokuta']) ?>" size="8" inputmode="decimal"></label>
  <label>Havárie do (h)<input name="havarie_h" value="<?= $e($sm['havarie_h']) ?>" size="4" inputmode="numeric"></label><label>Záruka na seřízení (měs.)<input name="zaruka_mes" value="<?= $e($sm['zaruka_mes']) ?>" size="4" inputmode="numeric"></label>
  <label>Náhradních termínů<input name="nahradni" value="<?= $e($sm['nahradni']) ?>" size="3" inputmode="numeric"></label></div>
  <div class="ln"><label>Stav podpisu<select name="stav_podpisu"><?php foreach (SO_PODPIS as $k => $t): ?><option value="<?= $k ?>"<?= ($sm['stav_podpisu'] ?? 'podepsana') === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
  <label>Platná do (prázdné = od + počet × interval)<input type="date" name="platnost_do" value="<?= $e($sm['platnost_do'] ?? '') ?>"></label><label>Připomenout dní předem<input name="pripomenout_dni" value="<?= (int)($sm['pripomenout_dni'] ?? 60) ?>" size="4" inputmode="numeric"></label></div>
  <div class="ln"><label class="w">Dokument smlouvy na NASu (cesta)<input name="dokument" value="<?= $e($sm['dokument'] ?? '') ?>"></label></div>
  <div class="ln"><label class="w">Poznámka<input name="poznamka" value="<?= $e($sm['poznamka']) ?>"></label><label style="flex-direction:row;align-items:center;gap:6px"><input type="checkbox" name="aktivni" value="1"<?= $sm['aktivni'] ? ' checked' : '' ?>> platná</label></div>
  <button>Uložit smlouvu</button></form>
<?php };
  foreach ($smlouvy as $sm): $ss = so_smlouva_stav($pdo, $sm); ?>
  <div style="border-top:1px solid #e3e8ef;padding-top:8px;margin-top:8px"><b><?= $e($sm['cislo'] ?: 'Smlouva') ?></b> · od <?= $e(so_datum($sm['od'])) ?> · <?= (int)$sm['pocet'] === 1 ? 'jednorázový servis' : (int)$sm['pocet'] . '× ' . ((int)$sm['interval_let'] === 2 ? 'jednou za 2 roky' : 'ročně') ?> · platná do <?= $e(so_datum($ss['platnost_do'])) ?>
  <span class="st <?= $ss['podepsana'] ? 'st-hotovo' : 'st-nebyl' ?>"><?= $e(SO_PODPIS[$sm['stav_podpisu'] ?? 'podepsana'] ?? '') ?></span><?= $sm['aktivni'] ? '' : ' <span class="st">neplatná</span>' ?><?= $ss['vyprsela'] && !$ss['konec'] && $sm['aktivni'] ? ' <span class="st st-odmitl">vypršela</span>' : '' ?>
  <?php if (!empty($sm['dokument'])): ?><br><span class="mut">📎 <?= $e($sm['dokument']) ?></span><?php endif; ?><?php if ($sm['poznamka']): ?><br><span class="mut">📝 <?= $e($sm['poznamka']) ?></span><?php endif; ?>
  <br><span class="mut">Proběhlo <?= $ss['hotovo'] ?> z <?= $ss['pocet'] ?><?= $ss['konec'] ? ' – smlouva je splněna' : ' · další ' . $ss['dalsi'] . '. servis do ' . so_datum($ss['do']) ?>
  <?= $sm['limit_opravy'] !== null ? ' · oprava bez souhlasu do ' . $kc($sm['limit_opravy']) : '' ?><?= $sm['pokuta'] !== null ? ' · pokuta ' . $kc($sm['pokuta']) : '' ?><?= $sm['havarie_h'] ? ' · havárie do ' . (int)$sm['havarie_h'] . ' h' : '' ?><?= $sm['zaruka_mes'] ? ' · záruka ' . (int)$sm['zaruka_mes'] . ' měs.' : '' ?></span>
  <details><summary>Upravit</summary><?php $smForm($sm); ?><?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat smlouvu?')" style="margin-top:8px"><input type="hidden" name="akce" value="smlouva_smazat"><input type="hidden" name="id" value="<?= (int)$sm['id'] ?>"><input type="hidden" name="svj_id" value="<?= $sid ?>"><button class="del">Smazat smlouvu</button></form><?php endif; ?></details></div>
<?php endforeach; ?>
<details><summary>+ Přidat smlouvu</summary><?php $smForm(['id' => 0, 'cislo' => '', 'od' => date('Y-m-d'), 'interval_let' => 1, 'pocet' => 5, 'limit_opravy' => '', 'pokuta' => '', 'havarie_h' => '', 'zaruka_mes' => 3, 'nahradni' => 1, 'nabidka' => '', 'poznamka' => '', 'aktivni' => 1, 'stav_podpisu' => 'podepsana', 'platnost_do' => '', 'dokument' => '', 'pripomenout_dni' => 60]); ?></details></div>

<div class="box" id="byty"><h2 style="margin-top:0">🔧 Seřízení a opravy jednotlivých bytů</h2>
<p class="mut" style="margin-top:0">Když SVJ / družstvo objedná seřízení nebo opravu u konkrétního nájemníka: založte servis bytu – zápis, fotky, podpis a protokol jsou stejné jako u jednotlivců a po dokončení se zapíše i sem do historie domu.</p>
<?php $jb = $pdo->prepare('SELECT * FROM so_jed WHERE svj_id=? ORDER BY CASE stav WHEN \'nova\' THEN 0 ELSE 1 END, COALESCE(provedeno, termin, vytvoreno) DESC'); $jb->execute([$sid]); $jb = $jb->fetchAll(PDO::FETCH_ASSOC);
  if ($jb): ?><div style="overflow-x:auto"><table><tr><th>Termín / provedeno</th><th>Byt / nájemník</th><th>Stav</th></tr>
<?php foreach ($jb as $x): ?><tr><td class="nowrap"><?= $e(so_datum($x['provedeno'] ?: $x['termin']) ?: 'bez termínu') ?></td>
  <td><a href="index.php?id=<?= (int)$x['id'] ?>"><b><?= $e($x['jmeno']) ?></b></a><div class="mut"><?= $e(implode(', ', array_filter([(string)$x['ulice'], $x['byt'] ? 'byt ' . $x['byt'] : '', (string)$x['telefon']]))) ?></div></td>
  <td><span class="st <?= $x['stav'] === 'hotovo' ? 'st-hotovo' : 'st-nebyl' ?>"><?= $x['stav'] === 'hotovo' ? 'hotovo' : ($x['stav'] === 'zruseno' ? 'zrušeno' : 'k vyřízení') ?></span><?php $vy = ['oprava' => 'nutná oprava', 'dily' => 'objednat díly'][$x['vysledek'] ?? ''] ?? ''; ?><?= $vy ? ' <span class="st st-odmitl">' . $vy . '</span>' : '' ?><div class="mut"><?= ($x['plati'] ?? '') === 'svj' ? '💳 platí SVJ' : (($x['plati'] ?? '') === 'najemnik' ? '💳 platí nájemník' : '💳 neurčeno') ?></div></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
<p style="margin-bottom:0"><a class="btn ok" href="index.php?nove=1&amp;svj=<?= $sid ?>">+ Seřízení / oprava bytu</a></p></div>

<div class="box" id="historie"><h2 style="margin-top:0">Historie – provedené servisy, zápisy a dokumenty</h2>
<?php $hist = so_historie($pdo, $sid); $smNazev = []; foreach ($smlouvy as $sm) $smNazev[(int)$sm['id']] = ($sm['cislo'] ?: 'smlouva') . ' od ' . so_datum($sm['od']);
  $hForm = function ($h) use ($e, $sid, $smNazev) { ?>
  <form method="post"><input type="hidden" name="akce" value="historie_ulozit"><input type="hidden" name="svj_id" value="<?= $sid ?>"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
  <div class="ln"><label>Datum<input type="date" name="datum" value="<?= $e($h['datum']) ?>" required></label><label>Druh<select name="druh"><?php foreach (SO_HIST as $k => $t): ?><option value="<?= $k ?>"<?= $h['druh'] === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
  <label>Podle smlouvy<select name="smlouva_id"><option value="">–</option><?php foreach ($smNazev as $k => $t): ?><option value="<?= $k ?>"<?= (int)$h['smlouva_id'] === $k ? ' selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label></div>
  <div class="ln"><label class="w">Popis<input name="popis" value="<?= $e($h['popis']) ?>"></label></div>
  <div class="ln"><label class="w">Dokument / zápis na NASu (cesta)<input name="dokument" value="<?= $e($h['dokument']) ?>"></label></div><button>Uložit</button></form>
<?php };
  if (!$hist): ?><p class="mut">Zatím nic.</p><?php else: ?><div style="overflow-x:auto"><table><tr><th>Datum</th><th>Co</th><th>Popis a dokument</th><th></th></tr>
<?php foreach ($hist as $h): ?><tr><td class="nowrap"><?= $e(so_datum($h['datum'])) ?></td><td class="nowrap"><?= $e(SO_HIST[$h['druh']] ?? $h['druh']) ?></td>
  <td><?= $e($h['popis']) ?><?= $h['smlouva_id'] && isset($smNazev[(int)$h['smlouva_id']]) ? ' <span class="st">' . $e($smNazev[(int)$h['smlouva_id']]) . '</span>' : '' ?><?php if ($h['dokument']): ?><div class="mut" style="word-break:break-all">📎 <?= $e(preg_replace('#^.*/ZÁKAZNÍCI/[^/]+/#u', '', (string)$h['dokument'])) ?></div><?php endif; ?></td>
  <td class="noprint"><details><summary>upravit</summary><?php $hForm($h); ?><?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat záznam?')" style="margin-top:6px"><input type="hidden" name="akce" value="historie_smazat"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="svj_id" value="<?= $sid ?>"><button class="del">Smazat</button></form><?php endif; ?></details></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
<details class="noprint"><summary>+ Zapsat provedený servis / dokument</summary><?php $hForm(['id' => 0, 'datum' => date('Y-m-d'), 'druh' => 'servis', 'smlouva_id' => $smlouvy && $smlouvy[0]['aktivni'] ? $smlouvy[0]['id'] : null, 'popis' => '', 'dokument' => (string)($j['slozka'] ?? '') !== '' ? $j['slozka'] . '/' : '']); ?>
<p class="mut">Servis zapsaný „podle smlouvy“ se počítá do plnění smlouvy (další termín se posune). Servisy vedené v aplikaci (výše) se počítají samy.</p></details></div>

<div class="box" id="vchody"><h2 style="margin-top:0">Vchody a byty</h2>
<details class="noprint"<?= $vchody ? '' : ' open' ?>><summary>📥 Nahrát seznam bytů z Excelu (.xls, .xlsx)</summary>
  <p class="mut">Každý list = jeden vchod (název listu je číslo popisné). Hledám sloupce patro, č. bytu, velikost bytu, jméno, vztah, nájemce, telefon, poznámka; okna na chodbě po patrech (řádky pod „Chodba okna“). Byty, které už v seznamu jsou (stejné číslo bytu ve vchodu), se jen aktualizují.</p>
  <input type="file" id="xls" accept=".xls,.xlsx,.ods"> <span id="xlsStav" class="mut"></span>
  <div id="xlsNahled"></div>
  <form method="post" id="xlsForm" style="display:none"><input type="hidden" name="akce" value="import"><input type="hidden" name="svj_id" value="<?= $sid ?>"><input type="hidden" name="data" id="xlsData"><p><button>Nahrát do seznamu</button></p></form>
</details>
<details class="noprint"><summary>+ Přidat vchod ručně</summary><form method="post" class="ln"><input type="hidden" name="akce" value="vchod_ulozit"><input type="hidden" name="svj_id" value="<?= $sid ?>"><label>Číslo popisné<input name="cp" required size="8"></label><label class="w">Ulice<input name="ulice"></label><button>Přidat</button></form></details>
<?php foreach ($vchody as $v): $byty = so_byty($pdo, (int)$v['id']); $nb = count(array_filter($byty, fn($b) => $b['typ'] === 'byt')); ?>
  <h3 style="margin-top:16px">Vchod č. p. <?= $e($v['cp']) ?> <?= $v['ulice'] ? '· ' . $e($v['ulice']) : '' ?> <span class="mut">(<?= $nb ?> bytů)</span></h3>
  <details><summary>Zobrazit byty</summary>
  <div style="overflow-x:auto"><table><tr><th>Patro</th><th>Byt</th><th>Vel.</th><th>Jméno</th><th>Vztah</th><th>Telefon</th><th>Okna</th><th></th></tr>
  <?php foreach ($byty as $b): $ok = []; foreach (SO_OKNA as $k => $t) if ((int)$b[$k]) $ok[] = (int)$b[$k] . '× ' . $t; ?>
    <tr><td><?= $e($b['patro']) ?></td><td><?= $b['typ'] === 'byt' ? $e($b['cislo']) : '<i>' . $e(so_byt_nazev($b)) . '</i>' ?></td><td><?= $e($b['velikost']) ?></td><td><?= $e($b['jmeno']) ?><?= $b['najemce'] ? '<div class="mut">' . $e($b['najemce']) . '</div>' : '' ?></td><td><?= $e($b['vztah']) ?></td><td><?= $e($b['telefon']) ?></td><td class="mut"><?= $e(implode(', ', $ok)) ?></td>
    <td><a href="?svj=<?= $sid ?>&byt_upravit=<?= (int)$b['id'] ?>">upravit</a></td></tr>
  <?php endforeach; ?></table></div>
  <p class="noprint"><a class="btn sec" href="?svj=<?= $sid ?>&byt_upravit=0&vchod=<?= (int)$v['id'] ?>">+ byt</a> <a class="btn sec" href="?svj=<?= $sid ?>&byt_upravit=0&vchod=<?= (int)$v['id'] ?>&typ=chodba">+ chodba</a> <a class="btn sec" href="?svj=<?= $sid ?>&byt_upravit=0&vchod=<?= (int)$v['id'] ?>&typ=ostatni">+ sklep / prodejna</a></p>
  <details><summary>Upravit vchod</summary><form method="post" class="ln"><input type="hidden" name="akce" value="vchod_ulozit"><input type="hidden" name="svj_id" value="<?= $sid ?>"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><label>Číslo popisné<input name="cp" value="<?= $e($v['cp']) ?>" size="8"></label><label class="w">Ulice<input name="ulice" value="<?= $e($v['ulice']) ?>"></label><label>Pořadí<input name="poradi" value="<?= (int)$v['poradi'] ?>" size="3"></label><button>Uložit</button></form>
  <?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat vchod i jeho seznam bytů?')"><input type="hidden" name="akce" value="vchod_smazat"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="svj_id" value="<?= $sid ?>"><button class="del">Smazat vchod</button></form><?php endif; ?></details>
  </details>
<?php endforeach; ?></div>
<?php if (uziv_admin()): ?><details class="noprint"><summary style="color:#9b1c1c">Smazat celé SVJ</summary><form method="post" onsubmit="return confirm('Opravdu smazat SVJ, jeho vchody, byty, smlouvy i servisy?')"><input type="hidden" name="akce" value="svj_smazat"><input type="hidden" name="id" value="<?= $sid ?>"><button class="del">Smazat SVJ se vším</button></form></details><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
  var inp = document.getElementById('xls'); if (!inp) return;
  var stav = document.getElementById('xlsStav'), nahled = document.getElementById('xlsNahled'), form = document.getElementById('xlsForm'), data = document.getElementById('xlsData');
  function norm(s) { return String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[\s.:]+/g, ''); }
  function txt(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); }
  function rozeber(ws, jmeno) {
    var rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' }), hi = -1, col = {}, cp = '';
    for (var i = 0; i < Math.min(rows.length, 30); i++) {
      var r = rows[i].map(norm);
      if (!cp) rows[i].forEach(function (c) { var m = /c\s*\.?\s*p\s*\.?\s*(\d{2,5})/i.exec(String(c).normalize('NFD').replace(/[\u0300-\u036f]/g, '')); if (m) cp = m[1]; });
      if (r.some(function (c) { return c.indexOf('bytu') >= 0 || c === 'byt' || c === 'cbytu'; }) && r.some(function (c) { return c.indexOf('jmeno') >= 0; })) { hi = i; break; }
    }
    if (hi < 0) return null;
    rows[hi].map(norm).forEach(function (c, k) {
      if (c.indexOf('patro') >= 0 && col.patro == null) col.patro = k;
      else if ((c.indexOf('bytu') >= 0 || c === 'byt') && c.indexOf('velikost') < 0 && col.cislo == null) col.cislo = k;
      else if (c.indexOf('velikost') >= 0) col.velikost = k;
      else if (c.indexOf('jmeno') >= 0 && col.jmeno == null) col.jmeno = k;
      else if (c.indexOf('vztah') >= 0) col.vztah = k;
      else if (c.indexOf('najemce') >= 0 || c.indexOf('podnajem') >= 0) col.najemce = k;
      else if (c.indexOf('telefon') >= 0) col.telefon = k;
      else if (c.indexOf('poznamka') >= 0) col.poznamka = k;
      else if (c === 'cp' || c === 'cislopopisne') col.cp = k;
    });
    if (/^\d{2,5}$/.test(txt(jmeno))) cp = txt(jmeno);
    var skup = {}, poradi = [], chodba = false, akt = cp;
    function pridej(c, b) { c = c || cp || ''; if (!skup[c]) { skup[c] = []; poradi.push(c); } skup[c].push(b); }
    for (var j = hi + 1; j < rows.length; j++) {
      var r = rows[j], g = function (k) { return col[k] == null ? '' : txt(r[col[k]]); };
      if (/^\d{2,5}$/.test(g('cp'))) akt = g('cp');   // číslo popisné v řádku (list s více vchody, např. Náhradní termín)
      var vse = r.map(txt).filter(Boolean);
      if (vse.some(function (c) { return /chodba/i.test(c); })) chodba = true;
      if (chodba) { var p = vse.filter(function (c) { return /^\d+\s*\.?\s*p\.?$/i.test(c); })[0]; if (p) { pridej(akt, { typ: 'chodba', patro: p.replace(/\s+/g, '') }); continue; } }
      if (g('cislo') && /\d/.test(g('cislo'))) pridej(akt, { typ: 'byt', patro: g('patro'), cislo: g('cislo'), velikost: g('velikost'), jmeno: g('jmeno'), vztah: g('vztah'), najemce: g('najemce'), telefon: g('telefon'), poznamka: g('poznamka') });
    }
    return poradi.map(function (c) { return { list: jmeno, cp: c, byty: skup[c], vedlejsi: /nahradn/i.test(String(jmeno).normalize('NFD').replace(/[\u0300-\u036f]/g, '')) }; });
  }
  inp.addEventListener('change', function () {
    var f = inp.files[0]; if (!f) return;
    if (!window.XLSX) { stav.textContent = 'Nepodařilo se načíst čtečku Excelu (internet?).'; return; }
    stav.textContent = 'Čtu soubor…';
    f.arrayBuffer().then(function (buf) {
      var wb = XLSX.read(buf, { type: 'array' }), vchody = [];
      wb.SheetNames.forEach(function (n) { (rozeber(wb.Sheets[n], n) || []).forEach(function (v) { if (v.byty.length) vchody.push(v); }); });
      if (!vchody.length) { stav.textContent = 'V souboru jsem nenašel tabulku s čísly bytů a jmény.'; form.style.display = 'none'; nahled.innerHTML = ''; return; }
      stav.textContent = 'Nalezeno ' + vchody.length + ' vchodů. Zkontrolujte čísla popisná a nahrajte.';
      var h = '<table style="margin:10px 0"><tr><th>Nahrát</th><th>List</th><th>Č. p.</th><th>Bytů</th><th>Chodeb</th><th>Ukázka</th></tr>';
      vchody.forEach(function (v, i) {
        var nb = v.byty.filter(function (b) { return b.typ === 'byt'; }), nc = v.byty.length - nb.length;
        h += '<tr><td><input type="checkbox" data-i="' + i + '"' + (v.vedlejsi ? '' : ' checked') + '></td><td>' + v.list.replace(/</g, '&lt;') + '</td><td><input data-cp="' + i + '" value="' + (v.cp || '').replace(/"/g, '') + '" size="6"></td><td>' + nb.length + '</td><td>' + nc + '</td><td class="mut">' + nb.slice(0, 2).map(function (b) { return 'byt ' + b.cislo + ', ' + (b.velikost || '?') + ', ' + (b.jmeno || '').replace(/</g, '&lt;'); }).join(' · ') + '</td></tr>';
      });
      nahled.innerHTML = h + '</table>' + (vchody.some(function (v) { return v.vedlejsi; }) ? '<p class="mut">Listy „Náhradní termín“ jsou předem odškrtnuté (jsou v nich jen zkrácená jména).</p>' : ''); form.style.display = '';
      form.onsubmit = function () {
        var out = [];
        vchody.forEach(function (v, i) { if (!nahled.querySelector('[data-i="' + i + '"]').checked) return; var cp = nahled.querySelector('[data-cp="' + i + '"]').value.trim(); if (!cp) return; out.push({ cp: cp, byty: v.byty }); });
        if (!out.length) { alert('Není co nahrát – zaškrtněte vchod a vyplňte číslo popisné.'); return false; }
        data.value = JSON.stringify({ vchody: out }); return true;
      };
    }).catch(function () { stav.textContent = 'Soubor se nepodařilo přečíst.'; });
  });
})();
</script>
<?php endif; endif; endif; ?>
<?php
// ═════════ SERVIS ═════════
elseif ($servisId):
  $s = $pdo->query('SELECT * FROM so_servis WHERE id=' . $servisId)->fetch(PDO::FETCH_ASSOC);
  if (!$s): ?><p>Servis nenalezen. <a href="svj.php">Zpět</a></p><?php else:
  $j = so_svj($pdo, (int)$s['svj_id']); $sm = $s['smlouva_id'] ? $pdo->query('SELECT * FROM so_smlouva WHERE id=' . (int)$s['smlouva_id'])->fetch(PDO::FETCH_ASSOC) : null;
  $poradi = ''; if ($sm) { $st = $pdo->prepare('SELECT COUNT(*) FROM so_servis WHERE smlouva_id=? AND id<=?'); $st->execute([$sm['id'], $s['id']]); $poradi = (int)$st->fetchColumn() . '. z ' . (int)$sm['pocet']; }
  $plan = []; foreach ($pdo->query('SELECT * FROM so_plan WHERE servis_id=' . $servisId)->fetchAll(PDO::FETCH_ASSOC) as $p) $plan[(int)$p['vchod_id']] = $p;
  $vchodId = (int)($_GET['vchod'] ?? 0); $bytId = (int)($_GET['byt'] ?? 0);
  $zp = function ($z) { return trim(implode(', ', array_filter([$z ? implode(', ', array_map(fn($k) => SO_ZKRATKY[$k] ?? $k, array_filter(explode(',', (string)$z['zkratky'])))) : '', $z ? trim((string)$z['zavada']) : '']))); };

  // ── zápis jednoho bytu ──
  if ($bytId):
    $b = $pdo->query('SELECT b.*, v.cp, v.svj_id FROM so_byt b JOIN so_vchod v ON v.id=b.vchod_id WHERE b.id=' . $bytId)->fetch(PDO::FETCH_ASSOC);
    if (!$b || (int)$b['svj_id'] !== (int)$s['svj_id']): ?><p>Byt nenalezen.</p><?php else:
    $z = so_zaznam($pdo, $servisId, $bytId) ?: ['id' => 0, 'stav' => '', 'k_kovani' => 0, 'k_ramy' => 0, 'k_tesneni' => 0, 'k_zlabky' => 0, 'zkratky' => '', 'zavada' => '', 'oprava' => '', 'max_cena' => null, 'opraveno_hned' => '', 'podpis' => '', 'podepsal' => '', 'technik' => '', 'kdy' => ''];
    $zk = array_filter(explode(',', (string)$z['zkratky'])); ?>
<p class="noprint"><a href="?servis=<?= $servisId ?>&vchod=<?= (int)$b['vchod_id'] ?>#b<?= $bytId ?>">← vchod <?= $e($b['cp']) ?></a></p>
<div class="box"><h1 style="margin-bottom:2px"><?= $e(so_byt_nazev($b)) ?> <span class="mut" style="font-size:16px">č. p. <?= $e($b['cp']) ?><?= $b['patro'] !== '' && $b['typ'] === 'byt' ? ' · ' . $e($b['patro']) . '. patro' : '' ?><?= $b['velikost'] ? ' · ' . $e($b['velikost']) : '' ?></span></h1>
<div style="font-size:18px"><b><?= $e($b['jmeno']) ?></b> <?= $b['vztah'] ? '<span class="mut">(' . $e($b['vztah']) . ')</span>' : '' ?><?= $b['najemce'] ? '<div class="mut">' . $e($b['najemce']) . '</div>' : '' ?></div>
<?php if ($b['poznamka']): ?><p class="mut">📝 <?= $e($b['poznamka']) ?></p><?php endif; ?>
<?php if ($z['kdy']): ?><p class="mut">Naposledy uloženo <?= $e(date('j. n. Y H:i', strtotime($z['kdy']))) ?><?= $z['technik'] ? ' · ' . $e($z['technik']) : '' ?></p><?php endif; ?></div>
<form method="post" id="zapis"><input type="hidden" name="akce" value="zaznam_ulozit"><input type="hidden" name="servis_id" value="<?= $servisId ?>"><input type="hidden" name="byt_id" value="<?= $bytId ?>">
<div class="box"><h3>Stav</h3><div class="stavy"><?php foreach (['hotovo' => '✅ Hotovo', 'nebyl' => '🚪 Nebyl doma', 'odmitl' => '✋ Odmítl', 'nahradni' => '📅 Náhradní termín'] as $k => $t): ?><label><input type="radio" name="stav" value="<?= $k ?>"<?= $z['stav'] === $k ? ' checked' : '' ?>><?= $t ?></label><?php endforeach; ?></div>
<p class="mut" style="margin:8px 0 0"><label><input type="radio" name="stav" value=""<?= $z['stav'] === '' ? ' checked' : '' ?>> ještě nic</label></p></div>
<div class="box"><h3>Provedeno</h3><div class="chk"><?php foreach (SO_KONTROLY as $k => $t): ?><label><input type="checkbox" name="<?= $k ?>" value="1"<?= $z[$k] ? ' checked' : '' ?>> <?= $t ?></label><?php endforeach; ?></div></div>
<div class="box"><h3>Okna v <?= $b['typ'] === 'byt' ? 'bytě' : 'tomto místě' ?></h3><div class="okna"><?php foreach (SO_OKNA as $k => $t): ?><label><?= $t ?><input type="number" min="0" name="<?= $k ?>" value="<?= (int)$b[$k] ?>" inputmode="numeric"></label><?php endforeach; ?></div><p class="mut">Počty zůstanou u bytu i pro další servisy; do reportu se sčítají za hotové byty.</p></div>
<div class="box"><h3>Závady</h3><div class="chk"><?php foreach (SO_ZKRATKY as $k => $t): ?><label><input type="checkbox" name="zkratky[]" value="<?= $k ?>"<?= in_array($k, $zk, true) ? ' checked' : '' ?>> <?= $e($t) ?></label><?php endforeach; ?></div>
<label class="mut">Popis závady (okno, místnost, kování – výrobce, označení, FFH/FFB, dorn…)<textarea name="zavada" placeholder="např. ložnice, 2křídlé okno: převod Winkhaus GRK 1550/1, FFH 1300–1550, DM 15 – protočený"><?= $e($z['zavada']) ?></textarea></label>
<label class="mut">Opraveno hned (pod limitem smlouvy<?= $sm && $sm['limit_opravy'] !== null ? ' ' . $kc($sm['limit_opravy']) : '' ?>)<input name="opraveno_hned" value="<?= $e($z['opraveno_hned']) ?>" style="width:100%"></label>
<label class="mut">Návrh opravy pro SVJ<textarea name="oprava" placeholder="např. výměna okenní kliky 1 ks"><?= $e($z['oprava']) ?></textarea></label>
<div class="ln"><label>Maximální cena opravy (Kč s DPH)<input name="max_cena" value="<?= $e($z['max_cena']) ?>" inputmode="decimal" size="10"></label><label class="w">Telefon<input name="telefon" value="<?= $e($b['telefon']) ?>" inputmode="tel"></label></div></div>
<div class="box"><h3>Podpis zákazníka</h3><p class="mut" style="margin-top:0">Zákazník podpisem potvrzuje, že servis proběhl.</p>
<?php if ($z['podpis']): ?><p><img src="?podpis=<?= (int)$z['id'] ?>&amp;t=<?= time() ?>" alt="Podpis" style="max-width:100%;max-height:120px;border:1px solid #e3e8ef;border-radius:8px"> <label class="mut"><input type="checkbox" name="podpis_smazat" value="1"> smazat a podepsat znovu</label></p><?php endif; ?>
<canvas id="podpis"></canvas><input type="hidden" name="podpis_data" id="podpisData"><p><button type="button" class="sec" id="podpisZnovu">Smazat podpis</button></p>
<label class="mut">Jméno podepisujícího<input name="podepsal" value="<?= $e($z['podepsal'] ?: $b['jmeno']) ?>" style="width:100%"></label></div>
<div class="box noprint" style="position:sticky;bottom:0;display:flex;gap:10px;flex-wrap:wrap"><button class="ok" name="dalsi" value="1" style="flex:1">Uložit a další byt →</button><button class="sec" style="flex:1">Uložit</button></div>
</form>
<div class="box" id="fotky"><h3>Fotky<?php $fot = $z['id'] ? so_fotky((int)$z['id']) : []; echo $fot ? ' (' . count($fot) . ')' : ''; ?></h3>
<div style="display:flex;flex-wrap:wrap;gap:8px"><?php foreach ($fot as $f): ?><div><a href="?foto=<?= (int)$z['id'] ?>&amp;f=<?= $e($f) ?>" target="_blank"><img src="?foto=<?= (int)$z['id'] ?>&amp;f=<?= $e($f) ?>&amp;nahled=1" alt="Fotka" style="width:120px;height:90px;object-fit:cover;border-radius:8px"></a>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat fotku?')"><input type="hidden" name="akce" value="foto_smaz"><input type="hidden" name="zid" value="<?= (int)$z['id'] ?>"><input type="hidden" name="f" value="<?= $e($f) ?>"><input type="hidden" name="servis_id" value="<?= $servisId ?>"><input type="hidden" name="byt_id" value="<?= $bytId ?>"><button class="del" style="padding:3px 8px;font-size:13px">smazat</button></form><?php endif; ?></div><?php endforeach; ?></div>
<input type="file" id="fotoVyber" accept="image/*" multiple style="display:none"><p><button type="button" id="fotoTl">📷 Přidat fotky</button> <span id="fotoStav" class="mut"></span></p>
<p class="mut">Nejdřív uložte zápis, pak přidávejte fotky (stránka se po nahrání znovu načte).</p></div>
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
  // Hotovo zaškrtne všechny provedené kontroly
  document.querySelectorAll('input[name=stav]').forEach(function (r) { r.addEventListener('change', function () { if (r.value === 'hotovo' && r.checked) ['k_kovani', 'k_ramy', 'k_tesneni', 'k_zlabky'].forEach(function (n) { document.querySelector('[name=' + n + ']').checked = true; }); }); });
  // fotky (zmenšení v prohlížeči jako v kartotéce)
  var vyber = document.getElementById('fotoVyber'), tl = document.getElementById('fotoTl'), st = document.getElementById('fotoStav');
  tl.addEventListener('click', function () { vyber.click(); });
  function zmensi(s, max, q) { return createImageBitmap(s, { imageOrientation: 'from-image' }).then(function (bm) { var k = Math.min(1, max / Math.max(bm.width, bm.height)), cv = document.createElement('canvas'); cv.width = Math.round(bm.width * k); cv.height = Math.round(bm.height * k); cv.getContext('2d').drawImage(bm, 0, 0, cv.width, cv.height); return new Promise(function (ok) { cv.toBlob(ok, 'image/jpeg', q); }); }); }
  vyber.addEventListener('change', function () {
    var sb = Array.prototype.slice.call(vyber.files || []), hot = 0, chy = []; if (!sb.length) return; tl.disabled = true;
    (function dalsi(i) {
      if (i >= sb.length) { st.textContent = 'Nahráno ' + hot + (chy.length ? ', chyby: ' + chy.join('; ') : '.'); if (hot) setTimeout(function () { location.href = '?servis=<?= $servisId ?>&byt=<?= $bytId ?>#fotky'; location.reload(); }, 500); else tl.disabled = false; return; }
      st.textContent = 'Nahrávám ' + (i + 1) + ' z ' + sb.length + '…';
      Promise.all([zmensi(sb[i], 1600, .82), zmensi(sb[i], 320, .75)]).then(function (b) {
        var fd = new FormData(); fd.append('akce', 'foto_nahraj'); fd.append('servis_id', '<?= $servisId ?>'); fd.append('byt_id', '<?= $bytId ?>'); fd.append('foto', b[0], 'f.jpg'); fd.append('nahled', b[1], 'n.jpg');
        return fetch('svj.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
      }).then(function (j) { if (j.ok) hot++; else chy.push(j.zprava); }).catch(function () { chy.push(sb[i].name); }).then(function () { dalsi(i + 1); });
    })(0);
  });
})();
</script>
<?php endif;

  // ── seznam bytů vchodu (mobil) ──
  elseif ($vchodId):
    $v = $pdo->query('SELECT * FROM so_vchod WHERE id=' . $vchodId)->fetch(PDO::FETCH_ASSOC);
    $sh = array_values(array_filter(so_souhrn($pdo, $s), fn($x) => (int)$x['vchod']['id'] === $vchodId))[0] ?? null;
    if (!$v || !$sh): ?><p>Vchod nenalezen.</p><?php else: ?>
<p class="noprint"><a href="?servis=<?= $servisId ?>">← <?= $e($s['nazev']) ?></a></p>
<h1>Vchod <?= $e($v['cp']) ?> <span class="mut" style="font-size:16px"><?= isset($plan[$vchodId]) && $plan[$vchodId]['datum'] ? $e(so_datum($plan[$vchodId]['datum']) . ' ' . $plan[$vchodId]['cas']) : '' ?></span></h1>
<div class="box"><b>Hotovo <?= $sh['hotovo'] ?> z <?= $sh['bytu'] ?></b> · nebyl doma <?= $sh['nebyl'] + $sh['nahradni'] ?> · odmítl <?= $sh['odmitl'] ?><div class="bar" style="margin-top:6px"><i style="width:<?= $sh['uspesnost'] ?>%"></i></div></div>
<div class="box byty" style="padding:0">
<?php foreach ($sh['byty'] as $x): $b = $x['byt']; $z = $x['z']; $st = $z['stav'] ?? ''; ?>
  <a class="byt" id="b<?= (int)$b['id'] ?>" href="?servis=<?= $servisId ?>&byt=<?= (int)$b['id'] ?>"><span><b><?= $e(so_byt_nazev($b)) ?></b><?= $b['typ'] === 'byt' && $b['patro'] !== '' ? ' <span class="mut">' . $e($b['patro']) . '. p.</span>' : '' ?><br><span class="mut"><?= $e($b['jmeno']) ?></span><?= $z && $zp($z) !== '' ? '<br><span style="color:#9b1c1c;font-size:14px">⚠ ' . $e($zp($z)) . '</span>' : '' ?></span>
  <span class="st st-<?= $e($st) ?>"><?= $e(SO_STAVY[$st] ?? $st) ?><?= $z && $z['podpis'] ? ' ✍' : '' ?></span></a>
<?php endforeach; ?></div>
<p class="noprint"><a class="btn sec" href="?servis=<?= $servisId ?>&tisk=<?= $vchodId ?>" target="_blank">🖨 Papírový list vchodu</a></p>
<?php endif;

  // ── tisk papírového listu ──
  elseif (isset($_GET['tisk'])):
    $sh = so_souhrn($pdo, $s); $jen = (int)$_GET['tisk'];
    foreach ($sh as $i => $x): if ($jen && (int)$x['vchod']['id'] !== $jen) continue; ?>
<div class="<?= $i ? 'pb' : '' ?>"><h1>SERVIS OKEN – <?= $e($j['nazev']) ?>, č. p. <?= $e($x['vchod']['cp']) ?></h1>
<p><?= $e($s['nazev']) ?><?= isset($plan[(int)$x['vchod']['id']]) ? ' · ' . $e(so_datum($plan[(int)$x['vchod']['id']]['datum']) . ' ' . $plan[(int)$x['vchod']['id']]['cas']) : '' ?></p>
<table><tr><th>Patro</th><th>Byt</th><th>Vel.</th><th>Jméno</th><th>Kování seřízení</th><th>Rámy a křídla</th><th>Těsnění promazání</th><th>Žlábky čištění</th><th>Podpis zákazníka</th><th>Technik</th><th>Poznámka</th></tr>
<?php foreach ($x['byty'] as $y): $b = $y['byt']; $z = $y['z']; $k = fn($n) => $z && $z[$n] ? '✕' : ''; ?>
<tr style="height:30px"><td><?= $e($b['patro']) ?></td><td><?= $b['typ'] === 'byt' ? $e($b['cislo']) : $e(so_byt_nazev($b)) ?></td><td><?= $e($b['velikost']) ?></td><td><?= $e($b['jmeno']) ?></td><td><?= $k('k_kovani') ?></td><td><?= $k('k_ramy') ?></td><td><?= $k('k_tesneni') ?></td><td><?= $k('k_zlabky') ?></td><td><?= $z && $z['podpis'] ? '<img src="?podpis=' . (int)$z['id'] . '" style="height:24px">' : '' ?></td><td><?= $e($z['technik'] ?? '') ?></td><td><?= $e(trim(($z ? (SO_STAVY[$z['stav']] ?? '') : '') . ' ' . ($z ? $zp($z) : ''))) ?></td></tr>
<?php endforeach; ?></table>
<p style="font-size:11px">O – okapničky · P – krytky pantů · BD – balkonové dveře · PARAPET – prohnutý parapet · KLIKA – povolená klika. Provedené úkony označí technik křížkem, závady zapište do poznámky.</p></div>
<?php endforeach; ?><script>window.print && setTimeout(function () { window.print(); }, 400);</script>
<?php
  // ── report pro SVJ ──
  elseif (isset($_GET['report'])):
    $sh = so_souhrn($pdo, $s); $tb = array_sum(array_column($sh, 'bytu')); $th = array_sum(array_column($sh, 'hotovo')); ?>
<p class="noprint"><a href="?servis=<?= $servisId ?>">← zpět</a> · <button type="button" onclick="window.print()">🖨 Tisk / uložit jako PDF</button></p>
<div style="display:flex;justify-content:space-between;gap:12px;font-size:12px;border-bottom:2px solid #0f2747;padding-bottom:6px"><div><b>Zdeněk Kučera – KK SYSTEM · BEZPEČNÉ DVEŘE CZ</b><br>Zd. Štěpánka 2764/1, 434 01 Most · IČ 64682676, DIČ CZ7801242768</div><div class="r">+420 725 559 235 · +420 471 008 994<br>info@kksystem.cz · www.bezpecne-dvere.cz</div></div>
<h1 style="margin-top:12px">Zápis z provedení servisu oken</h1>
<p><b>Zadavatel:</b> <?= $e($j['nazev']) ?><?= $j['adresa'] ? ', ' . $e($j['adresa']) : '' ?><?= $j['kontakt'] ? '<br><b>Kontakt:</b> ' . $e($j['kontakt']) . ($j['telefon'] ? ', ' . $e($j['telefon']) : '') : '' ?>
<br><b>Servis:</b> <?= $e($s['nazev']) ?><?= $poradi ? ' (' . $poradi . ' podle smlouvy' . ($sm['cislo'] ? ' ' . $e($sm['cislo']) : '') . ')' : '' ?> · <b>Zpracováno:</b> <?= date('j. n. Y') ?>, <?= $e(uziv_jmeno()) ?></p>
<h2>1. Výpis skutečně odvedené práce</h2>
<table><tr><th>Vchod</th><th>Termín</th><?php foreach (SO_OKNA as $t): ?><th class="r"><?= $t ?></th><?php endforeach; ?><th class="r">Bytů hotovo</th></tr>
<?php $sum = array_fill_keys(array_keys(SO_OKNA), 0); foreach ($sh as $x): $p = $plan[(int)$x['vchod']['id']] ?? null; ?>
<tr><td><?= $e($x['vchod']['cp']) ?></td><td><?= $p ? $e(so_datum($p['datum'])) : '' ?></td><?php foreach (SO_OKNA as $k => $t): $n = $x['okna'][$k] + $x['okna_chodby'][$k]; $sum[$k] += $n; ?><td class="r"><?= $n ?: '' ?></td><?php endforeach; ?><td class="r"><?= $x['hotovo'] ?> / <?= $x['bytu'] ?></td></tr>
<?php endforeach; ?><tr><th>Celkem</th><th></th><?php foreach ($sum as $n): ?><th class="r"><?= $n ?></th><?php endforeach; ?><th class="r"><?= $th ?> / <?= $tb ?></th></tr></table>
<p class="mut">Počty oken zahrnují hotové byty a okna na chodbách / ve společných prostorách, u kterých byl servis proveden.</p>
<h2>2. Nepřítomní (servis neproveden)</h2>
<?php $np = 0; foreach ($sh as $x) $np += count($x['nepritomni']); if (!$np): ?><p>Žádní.</p><?php else: ?>
<table><tr><th>Vchod</th><th>Byt</th><th>Jméno</th><th>Stav</th></tr><?php foreach ($sh as $x) foreach ($x['nepritomni'] as $b): $z = so_zaznam($pdo, $servisId, (int)$b['id']); ?><tr><td><?= $e($x['vchod']['cp']) ?></td><td><?= $e($b['cislo']) ?></td><td><?= $e($b['jmeno']) ?></td><td><?= $e(SO_STAVY[$z['stav'] ?? ''] ?? '') ?></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php $odm = []; foreach ($sh as $x) foreach ($x['byty'] as $y) if (($y['z']['stav'] ?? '') === 'odmitl') $odm[] = [$x['vchod']['cp'], $y['byt']]; if ($odm): ?><p><b>Servis odmítli:</b> <?= $e(implode(', ', array_map(fn($o) => $o[0] . '/' . $o[1]['cislo'] . ' ' . $o[1]['jmeno'], $odm))) ?></p><?php endif; ?>
<h2 class="pb">3. Zjištěné závady a návrh oprav</h2>
<?php $zav = []; foreach ($sh as $x) foreach ($x['zavady'] as $y) $zav[] = [$x['vchod']['cp'], $y['byt'], $y['z']]; if (!$zav): ?><p>Bez závad.</p><?php else: ?>
<table><tr><th>Č. p.</th><th>Byt</th><th>Jméno</th><th>Závada</th><th>Návrh opravy</th><th class="r">Maximální cena za odstranění</th><th>Souhlas s odstraněním</th><th>Telefon</th></tr>
<?php $celk = 0; foreach ($zav as [$cp, $b, $z]): $celk += (float)$z['max_cena']; ?><tr><td><?= $e($cp) ?></td><td><?= $e($b['typ'] === 'byt' ? $b['cislo'] : so_byt_nazev($b)) ?></td><td><?= $e($b['typ'] === 'byt' ? $b['jmeno'] : '') ?></td><td><?= $e($zp($z)) ?><?= $z['opraveno_hned'] ? '<br><i>Opraveno hned: ' . $e($z['opraveno_hned']) . '</i>' : '' ?></td><td><?= $e($z['oprava']) ?></td><td class="r nowrap"><?= $z['max_cena'] !== null ? 'do ' . $kc($z['max_cena']) : '' ?></td><td style="min-width:70px"></td><td></td></tr><?php endforeach; ?>
<tr><th colspan="5">Cena všech oprav s DPH</th><th class="r nowrap"><?= $celk ? 'do ' . $kc($celk) : '' ?></th><th></th><th></th></tr></table>
<p>V případě zájmu označte ANO u řádku s cenou opravy a přiložte telefonní spojení, zašlete zpět na info@kksystem.cz. Ceny oprav budou vyčísleny dle skutečných nákladů a nepřesáhnou hranici maximální ceny.</p><?php endif; ?>
<h2>4. Úspěšnost kontroly oken</h2>
<table><tr><th>Vchod</th><th class="r">Bytů</th><th class="r">Zkontrolováno</th><th style="width:45%">Podíl</th></tr>
<?php foreach ($sh as $x): ?><tr><td><?= $e($x['vchod']['cp']) ?></td><td class="r"><?= $x['bytu'] ?></td><td class="r"><?= $x['hotovo'] ?></td><td><div style="display:flex;align-items:center;gap:8px"><div class="bar" style="flex:1"><i style="width:<?= $x['uspesnost'] ?>%;print-color-adjust:exact;-webkit-print-color-adjust:exact"></i></div><span class="nowrap"><?= number_format($x['uspesnost'], 1, ',', '') ?> %</span></div></td></tr><?php endforeach; ?>
<tr><th>Celkem</th><th class="r"><?= $tb ?></th><th class="r"><?= $th ?></th><th><?= $tb ? number_format(100 * $th / $tb, 1, ',', '') : '0' ?> %</th></tr></table>
<p style="margin-top:28px">Zpracoval: <?= $e(uziv_jmeno()) ?> · <?= date('j. n. Y') ?></p>
<?php
  // ── přehled servisu ──
  else:
    $sh = so_souhrn($pdo, $s); ?>
<p class="noprint"><a href="?svj=<?= (int)$j['id'] ?>#servisy">← <?= $e($j['nazev']) ?></a></p>
<h1><?= $e($s['nazev']) ?> <span class="st <?= $s['stav'] === 'uzavreno' ? 'st-hotovo' : 'st-nebyl' ?>"><?= $s['stav'] === 'uzavreno' ? 'uzavřeno' : 'probíhá' ?></span></h1>
<p class="mut"><?= $e($j['nazev']) ?><?= $poradi ? ' · ' . $poradi . ' podle smlouvy' : ' · jednorázový' ?><?= $sm && $sm['limit_opravy'] !== null ? ' · oprava bez souhlasu do ' . $kc($sm['limit_opravy']) : '' ?></p>
<div class="box"><h2 style="margin-top:0">Vchody</h2>
<?php foreach ($sh as $x): $vid = (int)$x['vchod']['id']; ?>
  <div style="border-bottom:1px solid #e3e8ef;padding:10px 0"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
  <a class="btn" href="?servis=<?= $servisId ?>&vchod=<?= $vid ?>" style="min-width:160px">Vchod <?= $e($x['vchod']['cp']) ?> →</a>
  <span><b><?= $x['hotovo'] ?> / <?= $x['bytu'] ?></b> hotovo · nebyl doma <?= $x['nebyl'] + $x['nahradni'] ?> · odmítl <?= $x['odmitl'] ?></span></div>
  <div class="bar" style="margin-top:8px"><i style="width:<?= $x['uspesnost'] ?>%"></i></div></div>
<?php endforeach; ?>
<p class="noprint"><a class="btn sec" href="nabidky.php?nova=oprava&amp;servis=<?= $servisId ?>">🛠 Nabídka oprav z reportu</a> <a class="btn sec" href="?servis=<?= $servisId ?>&report=1">📄 Report pro SVJ</a> <a class="btn sec" href="?servis=<?= $servisId ?>&tisk=0" target="_blank">🖨 Papírové listy všech vchodů</a></p></div>
<div class="box noprint"><h2 style="margin-top:0">Plán po vchodech</h2>
<form method="post"><input type="hidden" name="akce" value="servis_ulozit"><input type="hidden" name="servis_id" value="<?= $servisId ?>">
<div class="ln"><label class="w">Název servisu<input name="nazev" value="<?= $e($s['nazev']) ?>"></label></div>
<table><tr><th>Vchod</th><th>Datum</th><th>Čas</th></tr><?php foreach ($sh as $x): $vid = (int)$x['vchod']['id']; $p = $plan[$vid] ?? ['datum' => '', 'cas' => '']; ?>
<tr><td><?= $e($x['vchod']['cp']) ?></td><td><input type="date" name="datum[<?= $vid ?>]" value="<?= $e($p['datum']) ?>"></td><td><input name="cas[<?= $vid ?>]" value="<?= $e($p['cas']) ?>" placeholder="13:00 – 16:30" size="12"></td></tr><?php endforeach; ?></table>
<div class="ln"><label class="w">Poznámka<input name="poznamka" value="<?= $e($s['poznamka']) ?>"></label></div><button>Uložit plán</button></form></div>
<div class="noprint" style="display:flex;gap:10px;flex-wrap:wrap"><form method="post"><input type="hidden" name="akce" value="servis_stav"><input type="hidden" name="servis_id" value="<?= $servisId ?>"><input type="hidden" name="stav" value="<?= $s['stav'] === 'uzavreno' ? 'probiha' : 'uzavreno' ?>"><button class="sec"><?= $s['stav'] === 'uzavreno' ? 'Znovu otevřít servis' : 'Uzavřít servis' ?></button></form>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat servis i všechny zápisy?')"><input type="hidden" name="akce" value="servis_smazat"><input type="hidden" name="servis_id" value="<?= $servisId ?>"><button class="del">Smazat servis</button></form><?php endif; ?></div>
<?php endif; endif; endif; ?>
</main></body></html>
