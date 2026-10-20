<?php
// Servisní požadavky zákazníků (z aplikace Moje dveře a ze stránky moje.php): přijmout, domluvit termín návštěvy, vyřídit. Vyřízený požadavek se zapíše do „rodného listu dveří“ (tabulka servis).
// Stejná ochrana jako kartoteka.php / servis.php.
require __DIR__ . '/lib/start.php'; require_once __DIR__ . '/lib/pozadavky.php'; require_once __DIR__ . '/lib/bonus.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
$u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (empty($cfg['prehled_hash']) || !hash_equals((string)$cfg['prehled_user'], $u) || !password_verify($pw, $cfg['prehled_hash'])) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$pdo = db(); poz_tabulky($pdo); require_once __DIR__ . '/lib/menu.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// fotka od zákazníka
if (isset($_GET['foto'])) {
  $jm = (string)$_GET['foto'];
  if (preg_match('/^S\d+_[0-9a-f]{8}_foto\d\.(jpg|png|webp)$/', $jm) && is_file($DATA . '/ulozeno/' . $jm)) {
    $ext = substr($jm, -3); header('Content-Type: ' . ($ext === 'png' ? 'image/png' : ($ext === 'jpg' ? 'image/jpeg' : 'image/webp'))); header('X-Content-Type-Options: nosniff'); readfile($DATA . '/ulozeno/' . $jm); exit;
  }
  http_response_code(404); exit('Not found');
}

function poz_zadost(PDO $pdo, int $id): ?array { $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$id]); $z = $st->fetch(PDO::FETCH_ASSOC); return $z ?: null; }
$zprava = ''; bonus_tabulky($pdo); dop_tabulka($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['akce'] ?? '', ['rec_schvalit', 'rec_zamitnout', 'dop_stav'], true)) {
  $akce = (string)$_POST['akce']; $id = (int)($_POST['id'] ?? 0);
  if ($akce === 'rec_schvalit') $zprava = bonus_schval_recenzi($pdo, $id);
  elseif ($akce === 'rec_zamitnout') { bonus_zamitni_recenzi($pdo, $id); $zprava = 'Recenze zamítnuta, zákazník může potvrzení poslat znovu.'; }
  else { $stv = (string)($_POST['stav'] ?? ''); if (isset(DOP_STAVY[$stv])) { $pdo->prepare('UPDATE doporuceni SET stav=?, upraveno=? WHERE id=?')->execute([$stv, date('Y-m-d H:i:s'), $id]); $zprava = 'Doporučení aktualizováno.'; } }
  header('Location: servis-pozadavky.php?z=' . rawurlencode($zprava)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0); $akce = (string)($_POST['akce'] ?? '');
  $st = $pdo->prepare('SELECT * FROM servis_pozadavky WHERE id=?'); $st->execute([$id]); $r = $st->fetch(PDO::FETCH_ASSOC);
  if ($r) {
    $ted = date('Y-m-d H:i:s'); $z = poz_zadost($pdo, (int)$r['zadost_id']); $d = $z ? (json_decode((string)$z['data_json'], true) ?: []) : [];
    $cisloObj = (string)($d['obj_cislo'] ?? ($z['cislo'] ?? ''));
    $datum = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : '';
    if ($akce === 'prijmout') {
      $pdo->prepare("UPDATE servis_pozadavky SET stav='prijato', upraveno=? WHERE id=? AND stav='nove'")->execute([$ted, $id]); $zprava = 'Požadavek ' . $r['cislo'] . ' je přijatý. Zákazník to vidí v aplikaci.';
    } elseif ($akce === 'naplanovat') {
      $dt = $datum('termin_datum');
      if (!$dt) $zprava = 'Zadejte datum návštěvy.';
      else {
        $pdo->prepare("UPDATE servis_pozadavky SET stav='naplanovano', termin_datum=?, termin_cas=?, zprava=?, upraveno=? WHERE id=?")
          ->execute([$dt, poz_cisti((string)($_POST['termin_cas'] ?? ''), 60), poz_cisti((string)($_POST['zprava'] ?? ''), 500), $ted, $id]);
        $st->execute([$id]); $r2 = $st->fetch(PDO::FETCH_ASSOC);
        $zprava = 'Termín návštěvy ' . poz_datum($dt) . ' uložen, zákazník ho vidí v aplikaci.';
        if (!empty($_POST['poslat'])) $zprava .= poz_mail_termin($cfg, $DATA, $r2, $cisloObj) ? ' E-mail zákazníkovi odešel.' : ' E-mail se neodeslal (zákazník nemá e-mail nebo selhalo odeslání).';
      }
    } elseif ($akce === 'hotovo') {
      $dt = $datum('hotovo_dne') ?: date('Y-m-d'); $vej = poz_cisti((string)($_POST['verejne'] ?? ''), 400);
      $druh = $r['druh'] === 'prohlidka' ? 'kontrola' : (in_array($_POST['druh'] ?? '', ['servis', 'reklamace'], true) ? $_POST['druh'] : 'servis');
      $dalsi = $datum('dalsi_kontrola');
      $pdo->prepare("UPDATE servis_pozadavky SET stav='hotovo', hotovo_dne=?, hotovo_popis=?, interni=?, upraveno=? WHERE id=? AND stav<>'hotovo'")->execute([$dt, $vej, poz_cisti((string)($_POST['interni'] ?? ''), 1000), $ted, $id]);
      if (!$pdo->query('SELECT 1 FROM servis WHERE pozadavek_id=' . $id)->fetchColumn())
        $pdo->prepare('INSERT INTO servis (zadost_id, datum, druh, poznamka, dalsi_kontrola, vytvoreno, verejne, pozadavek_id) VALUES (?,?,?,?,?,?,?,?)')
          ->execute([$r['zadost_id'], $dt, $druh, 'Požadavek ' . $r['cislo'] . ': ' . mb_substr((string)$r['popis'], 0, 200), $dalsi ?: null, $ted, $vej, $id]);
      $zprava = 'Požadavek ' . $r['cislo'] . ' je vyřízen a zapsán do historie dveří.';
    } elseif ($akce === 'zrusit') {
      $pdo->prepare("UPDATE servis_pozadavky SET stav='zruseno', upraveno=? WHERE id=?")->execute([$ted, $id]); $zprava = 'Požadavek ' . $r['cislo'] . ' zrušen.';
    }
  }
  header('Location: servis-pozadavky.php?z=' . rawurlencode($zprava)); exit;
}
if (isset($_GET['z'])) $zprava = mb_substr((string)$_GET['z'], 0, 300);

$nacti = function (string $stavy) use ($pdo): array {
  $out = [];
  foreach ($pdo->query("SELECT p.*, z.data_json AS zdata, z.cislo AS zcislo FROM servis_pozadavky p LEFT JOIN zadosti z ON z.id=p.zadost_id WHERE p.stav IN ($stavy) ORDER BY p.id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC) as $r) { $r['d'] = json_decode((string)$r['zdata'], true) ?: []; $out[] = $r; }
  return $out;
};
$nove = $nacti("'nove'"); $vrizeni = $nacti("'prijato','naplanovano'"); $hotove = array_slice($nacti("'hotovo'"), 0, 15);

function karta(array $r, callable $e, array $akce): void {
  $d = $r['d']; $cisloObj = (string)($d['obj_cislo'] ?? $r['zcislo']);
  echo '<div class="box"><div class="hl"><b>' . $e($r['cislo']) . '</b> · ' . $e(POZ_DRUHY[$r['druh']] ?? $r['druh']) . ' · <span class="mut">' . $e(poz_datum($r['vytvoreno'])) . ' · ' . $e($r['zdroj']) . '</span> <span class="st st-' . $e($r['stav']) . '">' . $e(POZ_STAVY[$r['stav']] ?? $r['stav']) . '</span></div>';
  echo '<div><a href="kartoteka.php?id=' . (int)$r['zadost_id'] . '">Obj. ' . $e($cisloObj) . '</a> · ' . $e($d['model'] ?? '') . (!empty($d['odstin']) ? ' · ' . $e($d['odstin']) : '') . '</div>';
  echo '<div><b>' . $e($r['jmeno']) . '</b> · <a href="tel:' . $e(preg_replace('/[^\d+]/', '', (string)$r['telefon'])) . '">' . $e($r['telefon']) . '</a>' . ($r['email'] ? ' · ' . $e($r['email']) : '') . '<br><span class="mut">' . $e($r['adresa']) . '</span></div>';
  echo '<p class="pop">' . nl2br($e($r['popis'])) . '</p>';
  if ($r['preferovany']) echo '<div class="mut">Zákazník navrhuje: ' . $e($r['preferovany']) . '</div>';
  foreach (array_filter(explode("\n", (string)$r['fotky'])) as $f) echo '<a href="servis-pozadavky.php?foto=' . $e(rawurlencode($f)) . '" target="_blank"><img class="ft" src="servis-pozadavky.php?foto=' . $e(rawurlencode($f)) . '" alt="foto"></a>';
  if ($r['stav'] === 'naplanovano') echo '<div class="ok2">📅 Návštěva: <b>' . $e(poz_datum($r['termin_datum'])) . '</b>' . ($r['termin_cas'] ? ' · ' . $e($r['termin_cas']) : '') . ($r['zprava'] ? '<br>Zpráva zákazníkovi: ' . $e($r['zprava']) : '') . '</div>';
  if ($r['stav'] === 'hotovo') echo '<div class="ok2">✓ Vyřízeno ' . $e(poz_datum($r['hotovo_dne'])) . ($r['hotovo_popis'] ? ': ' . $e($r['hotovo_popis']) : '') . ($r['interni'] ? '<br><span class="mut">Interně: ' . $e($r['interni']) . '</span>' : '') . '</div>';
  $f0 = '<input type="hidden" name="id" value="' . (int)$r['id'] . '">';
  if (in_array('prijmout', $akce, true)) echo '<form method="post" class="ln">' . $f0 . '<input type="hidden" name="akce" value="prijmout"><button>Přijmout požadavek</button></form>';
  if (in_array('naplanovat', $akce, true)) echo '<form method="post" class="ln">' . $f0 . '<input type="hidden" name="akce" value="naplanovat"><label>Datum návštěvy <input type="date" name="termin_datum" value="' . $e($r['termin_datum']) . '" required></label>'
    . '<label>Čas <input name="termin_cas" value="' . $e($r['termin_cas']) . '" placeholder="např. 9–11 h"></label><label class="w">Zpráva zákazníkovi (zobrazí se v aplikaci) <input name="zprava" value="' . $e($r['zprava']) . '" placeholder="např. přijedeme s technikem, prosíme o přístup ke dveřím"></label>'
    . '<label class="chk"><input type="checkbox" name="poslat" value="1" ' . ($r['email'] ? 'checked' : 'disabled') . '> poslat zákazníkovi e-mail</label><button>' . ($r['stav'] === 'naplanovano' ? 'Změnit termín' : 'Uložit termín') . '</button></form>';
  if (in_array('hotovo', $akce, true)) echo '<details><summary>Označit jako vyřízené</summary><form method="post" class="ln">' . $f0 . '<input type="hidden" name="akce" value="hotovo"><label>Datum <input type="date" name="hotovo_dne" value="' . date('Y-m-d') . '"></label>'
    . ($r['druh'] === 'prohlidka' ? '' : '<label>Druh <select name="druh"><option value="servis">Servis</option><option value="reklamace">Reklamace</option></select></label>')
    . '<label class="w">Co bylo uděláno (vidí zákazník v historii dveří) <input name="verejne" placeholder="např. seřízení pantů, promazání zámku"></label><label class="w">Interní poznámka (jen pro nás) <input name="interni"></label>'
    . '<label>Další kontrola <input type="date" name="dalsi_kontrola"></label><button>Vyřídit a zapsat do historie</button></form></details>';
  if (in_array('zrusit', $akce, true)) echo '<form method="post" class="ln" onsubmit="return confirm(\'Zrušit požadavek?\')">' . $f0 . '<input type="hidden" name="akce" value="zrusit"><button class="sec">Zrušit požadavek</button></form>';
  echo '</div>';
}
bd_bar('pozadavky');
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><title>Servisní požadavky – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:900px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{margin:0 0 10px}h2{margin:18px 0 8px;font-size:18px}.mut{color:#6b7787;font-size:13px}
button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:9px 14px;font:inherit;cursor:pointer}button.sec{background:#e6ebf3;color:#1b2430}a{color:#1f5fbf}
.ln{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:flex-end;margin:10px 0 0}.ln label{display:flex;flex-direction:column;font-size:13px;color:#556;gap:2px}.ln label.w{flex:1 1 280px}.ln label.chk{flex-direction:row;align-items:center;gap:6px}
input,select{font:inherit;padding:7px 8px;border:1px solid #c9d1dc;border-radius:8px}.pop{background:#f6f8fb;border-radius:8px;padding:8px 10px;margin:8px 0}.ft{height:90px;border-radius:8px;margin:0 6px 6px 0}
.st{font-size:12px;border-radius:999px;padding:2px 9px;background:#e6ebf3}.st-nove{background:#fde2e2;color:#9b1c1c}.st-prijato{background:#fff0cc;color:#7a5200}.st-naplanovano,.st-hotovo{background:#d9f2e0;color:#14653a}
.ok2{background:#eaf6ee;border-radius:8px;padding:8px 10px;margin:8px 0}.msg{background:#e6f6ea;border:1px solid #9bd2a8;border-radius:10px;padding:10px 14px;margin-bottom:14px}summary{cursor:pointer;margin-top:10px;color:#1f5fbf}
</style></head><body><main><h1>Servisní požadavky</h1>
<?php if ($zprava): ?><div class="msg"><?= $e($zprava) ?></div><?php endif; ?>
<p class="mut">Požadavky, které zákazníci pošlou z aplikace Moje dveře nebo ze stránky Moje dveře (QR z předávacího listu). Po přijetí a zadání termínu to zákazník vidí u svých dveří; vyřízený servis se zapíše do historie dveří.</p>
<h2>Nové (<?= count($nove) ?>)</h2><?php foreach ($nove as $r) karta($r, $e, ['prijmout', 'naplanovat', 'hotovo', 'zrusit']); if (!$nove) echo '<p class="mut">Žádné nové požadavky.</p>'; ?>
<h2>V řešení (<?= count($vrizeni) ?>)</h2><?php foreach ($vrizeni as $r) karta($r, $e, ['naplanovat', 'hotovo', 'zrusit']); if (!$vrizeni) echo '<p class="mut">Nic se nevyřizuje.</p>'; ?>
<h2>Naposledy vyřízené</h2><?php foreach ($hotove as $r) karta($r, $e, []); if (!$hotove) echo '<p class="mut">Zatím nic.</p>'; ?>
<?php
$rec = $pdo->query("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND bonus_rec_dne IS NOT NULL AND bonus_rec_dne<>'' AND (bonus_rec_schv_dne IS NULL OR bonus_rec_schv_dne='') ORDER BY bonus_rec_dne")->fetchAll(PDO::FETCH_ASSOC);
$dops = $pdo->query("SELECT d.*, z.data_json, z.cislo AS zc FROM doporuceni d LEFT JOIN zadosti z ON z.id=d.zadost_id WHERE d.stav NOT IN ('vyplaceno','zruseno') ORDER BY d.id DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
<h2>Recenze ke schválení (<?= count($rec) ?>)</h2>
<?php foreach ($rec as $q): $qd = json_decode((string)$q['data_json'], true) ?: []; ?><div class="box"><b>Obj. <?= $e($qd['obj_cislo'] ?? $q['cislo']) ?></b> · <?= $e(trim(($qd['jmeno'] ?? '') . ' ' . ($qd['prijmeni'] ?? ''))) ?> · <?= $e($qd['model'] ?? '') ?><div class="mut">Zákazník potvrdil recenzi <?= $e(poz_datum($q['bonus_rec_dne'])) ?>. Najděte ji na Google, pak schvalte (+6 měsíců záruky).</div>
  <form method="post" class="ln"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><button name="akce" value="rec_schvalit">Schválit +6 měsíců</button> <button class="sec" name="akce" value="rec_zamitnout">Zamítnout (recenze nenalezena)</button></form></div>
<?php endforeach; if (!$rec) echo '<p class="mut">Žádné recenze ke schválení.</p>'; ?>
<h2>Doporučení z aplikace (<?= count($dops) ?>)</h2>
<?php foreach ($dops as $q): $qd = json_decode((string)$q['data_json'], true) ?: []; ?><div class="box"><b><?= $e($q['jmeno']) ?></b> · <a href="tel:<?= $e(preg_replace('/\s+/', '', $q['telefon'])) ?>"><?= $e($q['telefon']) ?></a> <span class="st"><?= $e(DOP_STAVY[$q['stav']] ?? $q['stav']) ?></span>
  <div class="mut">Doporučuje <?= $e(trim(($qd['jmeno'] ?? '') . ' ' . ($qd['prijmeni'] ?? ''))) ?>, obj. <?= $e($qd['obj_cislo'] ?? $q['zc']) ?> (<?= $e($qd['model'] ?? '') ?>) · <?= $e(poz_datum(substr($q['vytvoreno'], 0, 10))) ?></div>
  <form method="post" class="ln"><input type="hidden" name="akce" value="dop_stav"><input type="hidden" name="id" value="<?= (int)$q['id'] ?>"><label>Stav <select name="stav"><?php foreach (DOP_STAVY as $k => $v): ?><option value="<?= $e($k) ?>"<?= $k === $q['stav'] ? ' selected' : '' ?>><?= $e($v) ?></option><?php endforeach; ?></select></label><button>Uložit</button></form></div>
<?php endforeach; if (!$dops) echo '<p class="mut">Žádná otevřená doporučení.</p>'; ?>
</main></body></html>
