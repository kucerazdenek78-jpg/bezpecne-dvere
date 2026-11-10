<?php
// Sklad dveří PREMIUM 60: seznam objednaných dveří se slíbeným termínem montáže, výběr dveří k objednání ve skladu,
// list pro sklad (obrázek / text / WhatsApp) a evidence vyzvednutí. Stejná ochrana jako kartoteka.php.
// 10. 11. 2026: na listu pro sklad (stránka, obrázek i tisk) je QR kód. Kdo dveře vyzvedává, naskenuje ho mobilem (přihlášený do kartotéky)
//   → sklad.php?prevzit=<číslo listu>: dveře z listu, které ještě nejsou převzaté, jsou předvybrané a tlačítkem se zapíšou jako vyzvednuté (= skladem u nás).
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

function sk_datum($d) { $t = strtotime((string)$d); return $t ? date('j. n. Y', $t) : ''; }
function sk_radek(array $z): array {
  $d = json_decode((string)$z['data_json'], true) ?: [];
  $ot = (string)($d['otevirani'] ?? '');
  $dvere = preg_match('/prav/iu', $ot) ? 'Pravé' : (preg_match('/lev/iu', $ot) ? 'Levé' : $ot);
  $k = (string)($d['kovani'] ?? '');
  $tvar = preg_match('/hranat/iu', $k) ? 'Hranaté ' : (preg_match('/zaoblen/iu', $k) ? 'Zaoblené ' : '');
  $bar = preg_match('/inox/iu', $k) ? 'INOX' : (preg_match('/černé|cerne/iu', $k) ? ($tvar ? 'černé' : 'Černé') : $k);
  $mont = trim(sk_datum($d['termin_datum'] ?? '') . (!empty($d['termin']) ? (!empty($d['termin_datum']) ? ' · ' : '') . $d['termin'] : ''));
  return ['id' => (int)$z['id'], 'dvere' => $dvere, 'odstin' => (string)($d['odstin'] ?? ''), 'kovani' => $tvar . $bar, 'cislo' => (string)($d['obj_cislo'] ?? $z['cislo']),
    'montaz' => $mont, 'razeni' => (string)($d['termin_datum'] ?? '9999-12-31'), 'zakaznik' => trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')), 'mesto' => (string)($d['mesto'] ?? ''), 'sirka' => (string)($d['sire'] ?? ''), 'zarubne' => (string)($d['zarubne'] ?? ''),
    'stav' => (string)$z['stav'], 'sklad_stav' => (string)($z['sklad_stav'] ?? ''), 'davka' => (int)($z['sklad_davka'] ?? 0)];
}
function sk_nacti($pdo): array {
  $out = [];
  foreach ($pdo->query("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno')") as $z) {
    $d = json_decode((string)$z['data_json'], true) ?: [];
    if (($d['model_kod'] ?? '') !== 'premium60' && stripos((string)($d['model'] ?? ''), 'premium 60') === false) continue;
    $out[] = sk_radek($z);
  }
  usort($out, fn($a, $b) => [$a['razeni'], $a['cislo']] <=> [$b['razeni'], $b['cislo']]);
  return $out;
}

// ── akce ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
  $akce = $_POST['akce'] ?? '';
  if ($akce === 'davka' && $ids) {
    $pdo->prepare('INSERT INTO sklad_davky (vytvoreno, poznamka) VALUES (?,?)')->execute([date('Y-m-d H:i:s'), mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 500)]);
    $did = (int)$pdo->lastInsertId();
    $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='objednano', sklad_davka=? WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) $up->execute([$did, $i]);
    header('Location: sklad.php?davka=' . $did); exit;
  }
  if ($akce === 'vyzvednuto' && $ids) { $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='vyzvednuto' WHERE id=?"); foreach ($ids as $i) $up->execute([$i]);
    if (!empty($_POST['qr'])) { header('Location: sklad.php?prevzato=' . count($ids)); exit; } }   // převzetí přes QR z listu pro sklad
  if ($akce === 'zpet' && $ids) { $up = $pdo->prepare("UPDATE zadosti SET sklad_stav=NULL, sklad_davka=NULL WHERE id=?"); foreach ($ids as $i) $up->execute([$i]); }
  header('Location: sklad.php'); exit;
}

$vse = sk_nacti($pdo);
$davka = isset($_GET['davka']) ? (int)$_GET['davka'] : 0;
$prevzit = isset($_GET['prevzit']) ? (int)$_GET['prevzit'] : 0;
$qrUrl = 'https://' . (string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app') . '/sklad.php?prevzit=' . $davka;   // QR na listu pro sklad
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><link rel="apple-touch-icon" href="kartoteka-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Kartotéka"><title>Sklad dveří – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
header{background:#14233b;color:#fff;padding:10px 16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}header a{color:#cfe0ff}
main{max-width:1100px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e4e8ee;text-align:left;vertical-align:top}th{font-size:13px;color:#5a6676}
.btn,button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:9px 14px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}
.btn.sec,button.sec{background:#e6ebf3;color:#1b2430}.mut{color:#6b7787;font-size:13px}.chip{border-radius:999px;padding:2px 9px;font-size:12px;background:#e6ebf3}
.chip.ok{background:#d9f2e0;color:#14653a}.chip.warn{background:#fff0cc;color:#7a5200}
textarea{width:100%;box-sizing:border-box;font:inherit;padding:8px;border:1px solid #c9d1dc;border-radius:8px}
.list{background:#d9ead3;border-radius:6px;padding:14px 18px;max-width:640px}.list table td,.list table th{border:0;font-size:18px;padding:3px 10px 3px 0;color:#2d4a1f}.list th{background:#f3e0d0;color:#442;font-size:17px;padding:5px 10px 5px 4px}
@media(max-width:700px){.hide-m{display:none}}@media print{header,.nop{display:none}body{background:#fff}.box{box-shadow:none}}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('sklad'); ?><main>
<?php if (isset($_GET['prevzato'])): ?><div class="box" style="background:#d9f2e0;color:#14653a;font-size:17px">✅ Zapsáno: <?= (int)$_GET['prevzato'] ?> ks dveří je u nás (vyzvednuto, skladem).</div><?php endif; ?>
<?php if ($prevzit):
  $rad = array_values(array_filter($vse, fn($r) => $r['davka'] === $prevzit));
  $zbyva = array_values(array_filter($rad, fn($r) => $r['sklad_stav'] === 'objednano')); ?>
  <div class="box"><h2 style="margin-top:0">📦 Převzetí dveří ze skladu – list č. <?= $prevzit ?></h2>
  <?php if (!$rad): ?><p>List č. <?= $prevzit ?> nenalezen (nebo už v něm nic není). <a href="sklad.php">Sklad</a></p>
  <?php elseif (!$zbyva): ?><p>Všechny dveře z tohoto listu už jsou zapsané jako vyzvednuté (u nás). <a href="sklad.php">Sklad</a></p>
  <?php else: ?><p>Zkontroluj, které dveře opravdu bereš, a potvrď. Zapíšou se jako <b>vyzvednuté – skladem u nás</b>.</p>
    <form method="post"><input type="hidden" name="akce" value="vyzvednuto"><input type="hidden" name="qr" value="1">
    <?php foreach ($zbyva as $r): ?><label style="display:flex;gap:12px;align-items:flex-start;padding:12px 4px;border-bottom:1px solid #e4e8ee;font-size:17px"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" checked style="width:26px;height:26px;flex:none">
      <span><b><?= $e($r['dvere'] . ' · ' . $r['odstin'] . ' · ' . $r['kovani']) ?></b><?= $r['sirka'] ? ' · šířka ' . $e($r['sirka']) : '' ?><br><span class="mut">obj. <?= $e($r['cislo']) ?><?= $r['zakaznik'] ? ' · ' . $e($r['zakaznik']) : '' ?><?= $r['montaz'] ? ' · montáž ' . $e($r['montaz']) : '' ?></span></span></label><?php endforeach; ?>
    <p><button style="width:100%;padding:16px;font-size:18px;background:#16a34a">✅ Převzato – dveře jsou u nás</button></p></form>
    <?php if (count($zbyva) < count($rad)): ?><p class="mut"><?= count($rad) - count($zbyva) ?> ks z listu už bylo zapsáno dřív.</p><?php endif; ?>
  <?php endif; ?></div>
<?php elseif ($davka):
  $st = $pdo->prepare('SELECT * FROM sklad_davky WHERE id=?'); $st->execute([$davka]); $dv = $st->fetch(PDO::FETCH_ASSOC);
  $rad = array_values(array_filter($vse, fn($r) => $r['davka'] === $davka));
  if (!$dv || !$rad): ?><p>Dávka nenalezena. <a href="sklad.php">Zpět</a></p><?php else:
  $datum = sk_datum($dv['vytvoreno']); $pozn = (string)$dv['poznamka'];
  $text = "Objednávka dveří PREMIUM 60 – $datum\n\n" . implode("\n", array_map(fn($r) => '• ' . trim($r['dvere'] . ' – ' . $r['odstin'] . ' – ' . $r['kovani'] . ' – č. obj. ' . $r['cislo'] . ($r['sirka'] ? ' – šířka ' . $r['sirka'] : '')), $rad)) . ($pozn !== '' ? "\n\n$pozn" : ''); ?>
  <div class="box nop"><b>List pro sklad</b> – vytvořeno <?= $e($datum) ?>. Dveře jsou označeny jako „objednáno ve skladu".
    <p><button id="bShare">Sdílet obrázek (WhatsApp…)</button> <button class="sec" id="bPng">Stáhnout obrázek</button> <button class="sec" id="bCopy">Kopírovat text</button>
    <a class="btn sec" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($text) ?>">Poslat textem přes WhatsApp</a> <button class="sec" onclick="print()">Tisk</button></p>
    <div class="mut" id="msg"></div></div>
  <div class="list"><table><tr><th>Dveře</th><th>Odstín</th><th>Kování</th><th>Číslo obj.</th></tr>
    <?php foreach ($rad as $r): ?><tr><td><?= $e($r['dvere']) ?></td><td><?= $e($r['odstin']) ?></td><td><?= $e($r['kovani']) ?></td><td><?= $e($r['cislo']) ?></td></tr><?php endforeach; ?></table>
    <?php if ($pozn !== ''): ?><p style="font-size:19px;margin:16px 0 4px"><?= nl2br($e($pozn)) ?></p><?php endif; ?><p class="mut" style="margin:8px 0 0">Ze dne <?= $e($datum) ?></p>
    <div style="display:flex;gap:14px;align-items:center;margin-top:12px;background:#fff;border-radius:6px;padding:10px"><img id="qrImg" alt="QR kód pro převzetí" width="132" height="132">
      <div style="font-size:15px;color:#2d4a1f"><b>Při vyzvednutí naskenujte mobilem</b><br>dveře se zapíšou jako převzaté – skladem u nás (list č. <?= $davka ?>).</div></div></div>
  <script src="qrcode.js"></script>
  <script>
  var QRURL = <?= json_encode($qrUrl) ?>, QR = null;
  try { QR = qrcode(0, 'M'); QR.addData(QRURL); QR.make(); document.getElementById('qrImg').src = QR.createDataURL(4, 2); } catch (e) { QR = null; }
  var ROWS = <?= json_encode(array_map(fn($r) => [$r['dvere'], $r['odstin'], $r['kovani'], $r['cislo']], $rad), JSON_UNESCAPED_UNICODE) ?>, POZN = <?= json_encode($pozn, JSON_UNESCAPED_UNICODE) ?>, DATUM = <?= json_encode($datum) ?>, TEXT = <?= json_encode($text, JSON_UNESCAPED_UNICODE) ?>;
  function kresli() {
    var f = '34px Arial,Helvetica,sans-serif', pad = 28, rh = 56, cols = [0, 190, 520, 820], W = 1080, notes = POZN ? POZN.split('\n') : [];
    var H = pad + rh * (ROWS.length + 1) + (notes.length ? 40 + notes.length * 50 : 0) + 90 + (QR ? 250 : 0);
    var c = document.createElement('canvas'); c.width = W; c.height = H; var x = c.getContext('2d');
    x.fillStyle = '#fff'; x.fillRect(0, 0, W, H); x.fillStyle = '#d9ead3'; x.fillRect(40, 20, W - 80, H - (QR ? 40 : 90)); x.fillStyle = '#f3e0d0'; x.fillRect(40, 20, W - 80, rh);
    x.font = f; x.fillStyle = '#3b3b3b'; var ox = 70, y = 20 + 40;
    ['Dveře', 'Odstín', 'Kování', 'Číslo obj.'].forEach(function (t, i) { x.fillText(t, ox + cols[i], y); });
    ROWS.forEach(function (r, k) { y += rh; r.forEach(function (t, i) { x.fillText(t, ox + cols[i], y); }); });
    if (notes.length) { y += 70; notes.forEach(function (t) { x.fillText(t, ox, y); y += 50; }); }
    x.font = '28px Arial'; x.fillStyle = '#555'; x.fillText('Ze dne ' + DATUM, 70, H - 40 - (QR ? 250 : 0));
    if (QR) { var n = QR.getModuleCount(), s = Math.floor(210 / (n + 4)), qx = 70, qy = H - 265, z = s * (n + 4);
      x.fillStyle = '#fff'; x.fillRect(qx, qy, z, z); x.fillStyle = '#000';
      for (var i = 0; i < n; i++) for (var j = 0; j < n; j++) if (QR.isDark(i, j)) x.fillRect(qx + (j + 2) * s, qy + (i + 2) * s, s, s);
      x.fillStyle = '#2d4a1f'; x.font = 'bold 30px Arial'; x.fillText('Při vyzvednutí naskenujte mobilem', qx + z + 30, qy + 80);
      x.font = '26px Arial'; x.fillText('dveře se zapíšou jako převzaté – skladem u nás', qx + z + 30, qy + 125); x.fillText('(list č. ' + <?= (int)$davka ?> + ')', qx + z + 30, qy + 165); }
    return c;
  }
  function blob() { return new Promise(function (ok) { kresli().toBlob(ok, 'image/png'); }); }
  var m = document.getElementById('msg');
  document.getElementById('bPng').onclick = function () { blob().then(function (b) { var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'Objednavka-dveri-' + DATUM.replace(/\D+/g, '-') + '.png'; a.click(); }); };
  document.getElementById('bCopy').onclick = function () { (navigator.clipboard ? navigator.clipboard.writeText(TEXT) : Promise.reject()).then(function () { m.textContent = 'Text zkopírován.'; }, function () { m.textContent = 'Kopírování se nepovedlo – použijte tlačítko WhatsApp.'; }); };
  document.getElementById('bShare').onclick = function () { blob().then(function (b) {
    var file = new File([b], 'objednavka-dveri.png', { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [file] })) navigator.share({ files: [file], text: 'Objednávka dveří ' + DATUM }).catch(function () {});
    else { m.textContent = 'Sdílení obrázku tenhle prohlížeč neumí – obrázek se stáhl, pošlete ho z galerie.'; document.getElementById('bPng').click(); } }); };
  </script>
<?php endif; else:
  $k_obj = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === '' && !in_array($r['stav'], ['namontovano', 'vyrizeno'], true)));
  $ve_skladu = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === 'objednano'));
  $vyz = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === 'vyzvednuto' && !in_array($r['stav'], ['namontovano', 'vyrizeno'], true))); ?>
  <div class="box"><h2 style="margin-top:0">K objednání ve skladu (<?= count($k_obj) ?>)</h2>
    <p class="mut">Objednávky dveří PREMIUM 60 od zákazníků, seřazené podle slíbeného termínu montáže. Označ, co si chceš připravit, a vytvoř list pro sklad.</p>
    <?php if (!$k_obj): ?><p>Nic k objednání.</p><?php else: ?>
    <form method="post"><input type="hidden" name="akce" value="davka"><table>
      <tr><th></th><th>Montáž</th><th>Dveře</th><th>Odstín</th><th>Kování</th><th>Č. obj.</th><th class="hide-m">Zákazník</th></tr>
      <?php foreach ($k_obj as $r): ?><tr><td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" style="width:20px;height:20px"></td><td><?= $e($r['montaz'] ?: '–') ?></td><td><?= $e($r['dvere']) ?></td><td><?= $e($r['odstin']) ?></td><td><?= $e($r['kovani']) ?></td>
        <td><a href="kartoteka.php?id=<?= $r['id'] ?>"><?= $e($r['cislo']) ?></a></td><td class="hide-m"><?= $e(trim($r['zakaznik'] . ($r['mesto'] ? ', ' . $r['mesto'] : ''))) ?></td></tr><?php endforeach; ?></table>
      <p><textarea name="poznamka" rows="2" placeholder="Poznámka pro sklad (např. Všechny nové kování hranaté INOX)"></textarea></p>
      <button>Vytvořit list pro sklad z označených</button></form><?php endif; ?></div>
  <?php if ($ve_skladu): ?><div class="box"><h2 style="margin-top:0">Objednáno ve skladu – k vyzvednutí (<?= count($ve_skladu) ?>)</h2>
    <form method="post"><input type="hidden" name="akce" value="vyzvednuto"><table>
      <tr><th></th><th>Montáž</th><th>Dveře</th><th>Odstín</th><th>Kování</th><th>Č. obj.</th><th>List</th></tr>
      <?php foreach ($ve_skladu as $r): ?><tr><td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" style="width:20px;height:20px"></td><td><?= $e($r['montaz'] ?: '–') ?></td><td><?= $e($r['dvere']) ?></td><td><?= $e($r['odstin']) ?></td><td><?= $e($r['kovani']) ?></td>
        <td><a href="kartoteka.php?id=<?= $r['id'] ?>"><?= $e($r['cislo']) ?></a></td><td><a href="sklad.php?davka=<?= $r['davka'] ?>">list č. <?= $r['davka'] ?></a></td></tr><?php endforeach; ?></table>
      <p><button>Označené vyzvednuto</button> <button class="sec" name="akce" value="zpet" onclick="return confirm('Vrátit označené zpět do „K objednání“?')">Vrátit zpět do „K objednání“</button></p></form></div><?php endif; ?>
  <?php if ($vyz): ?><div class="box"><h2 style="margin-top:0">Vyzvednuto, čeká na montáž (<?= count($vyz) ?>)</h2><table>
    <?php foreach ($vyz as $r): ?><tr><td><?= $e($r['montaz'] ?: '–') ?></td><td><?= $e($r['dvere'] . ' · ' . $r['odstin'] . ' · ' . $r['kovani']) ?></td><td><a href="kartoteka.php?id=<?= $r['id'] ?>"><?= $e($r['cislo']) ?></a></td><td class="hide-m"><?= $e(trim($r['zakaznik'] . ($r['mesto'] ? ', ' . $r['mesto'] : ''))) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
  <?php
    $kontrola = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === 'vyzvednuto' && !in_array($r['stav'], ['namontovano', 'vyrizeno', 'storno'], true)));
    usort($kontrola, fn($a, $b) => [$a['dvere'], $a['zarubne'], $a['odstin'], $a['kovani'], $a['sirka']] <=> [$b['dvere'], $b['zarubne'], $b['odstin'], $b['kovani'], $b['sirka']]);
    $souhrn = [];
    foreach ($kontrola as $r) { $k = implode(' | ', [$r['dvere'] ?: '?', $r['zarubne'] ?: '?', $r['odstin'] ?: '?', $r['kovani'] ?: '?', $r['sirka'] ?: '?']); $souhrn[$k] = ($souhrn[$k] ?? 0) + 1; }
  ?>
  <div class="box"><h2 style="margin-top:0">Kontrola skladu – co má být u nás (<?= count($kontrola) ?> ks)</h2>
    <p style="margin:0 0 8px;color:#556">Dveře označené jako vyzvednuté, které ještě nejsou namontované ani vyřízené. Podle tohoto seznamu zkontroluj fyzický stav.</p>
    <?php if (!$kontrola): ?><p>Nic – žádné vyzvednuté a dosud nepředané dveře.</p><?php else: ?>
    <h3 style="margin:10px 0 4px;font-size:15px">Souhrn podle provedení</h3>
    <table><tr><th>Počet</th><th>Dveře</th><th>Zárubeň</th><th>Barva</th><th>Kování</th><th>Šířka</th></tr>
      <?php foreach ($souhrn as $k => $n): $c = explode(' | ', $k); ?><tr><td><b><?= (int)$n ?>×</b></td><?php foreach ($c as $x): ?><td><?= $e($x) ?></td><?php endforeach; ?></tr><?php endforeach; ?></table>
    <h3 style="margin:14px 0 4px;font-size:15px">Po kusech</h3>
    <table><tr><th>Dveře</th><th>Zárubeň</th><th>Barva</th><th>Kování</th><th>Šířka</th><th>Č. obj.</th><th class="hide-m">Zákazník</th><th>Montáž</th></tr>
      <?php foreach ($kontrola as $r): ?><tr><td><?= $e($r['dvere']) ?></td><td><?= $e($r['zarubne']) ?></td><td><?= $e($r['odstin']) ?></td><td><?= $e($r['kovani']) ?></td><td><?= $e($r['sirka']) ?></td>
        <td><a href="kartoteka.php?id=<?= $r['id'] ?>"><?= $e($r['cislo']) ?></a></td><td class="hide-m"><?= $e(trim($r['zakaznik'] . ($r['mesto'] ? ', ' . $r['mesto'] : ''))) ?></td><td><?= $e($r['montaz'] ?: '–') ?></td></tr><?php endforeach; ?></table>
    <?php endif; ?></div>
<?php endif; ?></main></body></html>
