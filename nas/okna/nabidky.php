<?php
// Servis oken – NABÍDKY z ceníku (3. 11. 2026). Pro SVJ se položky spočítají ze seznamu bytů (počty podle velikosti),
// pro jednotlivce z jeho servisu (velikost bytu / okna), nebo se nabídka sestaví ručně. Sleva podle počtu bytů nebo servisní
// smlouvy (nesčítají se, platí vyšší), příplatek (večer / víkend / expres), doprava bez slev. Tisk / uložení jako PDF.
require __DIR__ . '/lib.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$P = fn($k, $max = 500) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)($_POST[$k] ?? ''))), 0, $max);
$num = function ($v) { $v = str_replace([' ', "\u{a0}", ',', 'Kč', '%'], ['', '', '.', '', ''], trim((string)$v)); return $v === '' || !is_numeric($v) ? null : (float)$v; };
$ted = date('Y-m-d H:i:s'); $n = ok_nast($pdo);
$cen = []; foreach (ok_cenik($pdo) as $r) $cen[$r['kod']] = $r;
const NB_STAVY = ['koncept' => 'Koncept', 'odeslana' => 'Odeslaná', 'prijata' => 'Přijatá', 'odmitnuta' => 'Odmítnutá'];
const NB_PRIPLATKY = ['' => 'bez příplatku', 'vecer' => 'servis po 18. hodině', 'vikend' => 'soboty, neděle a svátky', 'expres' => 'expres – nástup do 4 hodin'];
$polozka = fn(array $c, float $mn) => ['kod' => $c['kod'], 'nazev' => $c['nazev'], 'jednotka' => $c['jednotka'], 'mnozstvi' => $mn, 'cena' => (float)$c['cena'], 'dph' => (float)$c['dph'], 'slevy' => $c['skupina'] === 'doprava' ? 0 : 1];
$nacti = function (int $id) use ($pdo) { $st = $pdo->prepare('SELECT * FROM ok_nabidka WHERE id=?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; };
$vypocet = function (array $nb) use ($n) {
  $pol = json_decode((string)$nb['polozky'], true) ?: [];
  $byty = 0; foreach ($pol as $p) if (!empty($p['slevy']) && in_array($p['jednotka'], ['byt', 'dům'], true)) $byty += (float)$p['mnozstvi'];
  [$sl, $slTxt] = ok_sleva($n, (int)$byty, (int)$nb['smlouva'], $nb['sleva_rucne'] !== null && $nb['sleva_rucne'] !== '' ? (float)$nb['sleva_rucne'] : null);
  $pr = $nb['priplatek'] ? (float)($n['priplatek_' . $nb['priplatek']] ?? 0) : 0;
  return ok_spocitej($pol, $sl, $pr) + ['sleva' => $sl, 'sleva_txt' => $slTxt, 'priplatek' => $pr, 'byty' => $byty];
};

// ───── nová nabídka ─────
if (isset($_GET['nova']) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  $nb = ['typ' => 'jine', 'svj_id' => null, 'jed_id' => null, 'nazev' => '', 'adresa' => '', 'ic' => '', 'kontakt' => '', 'telefon' => '', 'email' => '', 'smlouva' => 0, 'interval_let' => 1, 'priplatek' => '', 'poznamka' => ''];
  $pol = []; $nezname = [];
  if ($_GET['nova'] === 'svj' && ($j = so_svj($pdo, (int)($_GET['svj'] ?? 0)))) {
    $nb = array_merge($nb, ['typ' => 'svj', 'svj_id' => (int)$j['id'], 'nazev' => $j['nazev'], 'adresa' => $j['adresa'], 'ic' => $j['ic'], 'kontakt' => $j['kontakt'], 'telefon' => $j['telefon'], 'email' => $j['email']]);
    $sm = so_smlouvy($pdo, (int)$j['id']); if ($sm && $sm[0]['aktivni']) { $nb['smlouva'] = in_array((int)$sm[0]['pocet'], [3, 5], true) ? (int)$sm[0]['pocet'] : 0; $nb['interval_let'] = (int)$sm[0]['interval_let']; }
    $poc = [];
    foreach (so_vchody($pdo, (int)$j['id']) as $v) foreach (so_byty($pdo, (int)$v['id']) as $b) {
      if ($b['typ'] !== 'byt') continue;
      $kod = ok_velikost_kod($pdo, (string)$b['velikost']);
      if ($kod === '' || !isset($cen[$kod])) { $t = trim((string)$b['velikost']) ?: '(bez velikosti)'; $nezname[$t] = ($nezname[$t] ?? 0) + 1; continue; }
      $poc[$kod] = ($poc[$kod] ?? 0) + 1;
    }
    foreach ($cen as $kod => $c) if (isset($poc[$kod])) $pol[] = $polozka($c, $poc[$kod]);
    if ($nezname && empty($_GET['preskocit'])) {   // neznámé velikosti – nejdřív přiřadit
      ok_hlava('Nová nabídka'); ok_bar('nab'); ?><main><h1>Nová nabídka – <?= $e($j['nazev']) ?></h1>
<div class="box"><h2 style="margin-top:0">Přiřaďte velikosti bytů k ceníku</h2><p class="mut">V seznamu bytů jsou zápisy velikosti, které aplikace nezná. Přiřazení se uloží a příště se použije samo.</p>
<?php foreach ($nezname as $t => $kolik): ?><form method="post" action="cenik.php" class="ln"><input type="hidden" name="akce" value="velikost"><input type="hidden" name="text" value="<?= $e($t) ?>"><input type="hidden" name="zpet" value="nabidky.php?nova=svj&svj=<?= (int)$j['id'] ?>">
<span style="min-width:150px"><b><?= $e($t) ?></b> <span class="mut">(<?= $kolik ?> bytů)</span></span><select name="kod" required><option value="">– vyberte –</option><?php foreach ($cen as $c) if ($c['skupina'] === 'byt'): ?><option value="<?= $e($c['kod']) ?>"><?= $e($c['nazev']) ?></option><?php endif; ?></select><?php if (uziv_admin()): ?><button class="sec">Uložit</button><?php else: ?><span class="mut">přiřadit může administrátor</span><?php endif; ?></form>
<?php endforeach; ?><p><a class="btn sec" href="nabidky.php?nova=svj&amp;svj=<?= (int)$j['id'] ?>&amp;preskocit=1">Pokračovat bez nich</a></p></div></main></body></html><?php
      exit;
    }
  } elseif ($_GET['nova'] === 'jed' && ($r = sj_nacti($pdo, (int)($_GET['id'] ?? 0)))) {
    $nb = array_merge($nb, ['typ' => 'jed', 'jed_id' => (int)$r['id'], 'nazev' => $r['jmeno'], 'adresa' => sj_adresa($r), 'telefon' => $r['telefon'], 'email' => $r['email'], 'smlouva' => 0]);
    if (!empty($r['kategorie']) && isset($cen[$r['kategorie']])) $pol[] = $polozka($cen[$r['kategorie']], 1);
  }
  if (!empty($cen['D0'])) $pol[] = $polozka($cen['D0'], 1);   // doprava – Most (upraví se v nabídce)
  $pdo->prepare('INSERT INTO ok_nabidka (cislo, typ, svj_id, jed_id, nazev, adresa, ic, kontakt, telefon, email, datum, platnost, smlouva, interval_let, priplatek, polozky, stav, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([ok_nabidka_cislo($pdo), $nb['typ'], $nb['svj_id'], $nb['jed_id'], $nb['nazev'], $nb['adresa'], $nb['ic'], $nb['kontakt'], $nb['telefon'], $nb['email'], date('Y-m-d'), date('Y-m-d', strtotime('+' . (int)$n['platnost_dni'] . ' days')),
      $nb['smlouva'], $nb['interval_let'], '', json_encode($pol, JSON_UNESCAPED_UNICODE), 'koncept', uziv_jmeno(), $ted, $ted]);
  header('Location: nabidky.php?id=' . (int)$pdo->lastInsertId() . '&upravit=1'); exit;
}

// ───── akce ─────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $a = $_POST['akce'] ?? ''; $id = (int)($_POST['id'] ?? 0);
  if ($a === 'ulozit' && $nacti($id)) {
    $pol = [];
    foreach ((array)($_POST['pol'] ?? []) as $p) {
      $naz = mb_substr(trim((string)($p['nazev'] ?? '')), 0, 200); $mn = $num($p['mnozstvi'] ?? '');
      if ($naz === '' || !$mn) continue;
      $pol[] = ['kod' => mb_substr(trim((string)($p['kod'] ?? '')), 0, 20), 'nazev' => $naz, 'jednotka' => mb_substr(trim((string)($p['jednotka'] ?? 'ks')), 0, 20), 'mnozstvi' => $mn,
        'cena' => (float)($num($p['cena'] ?? '') ?? 0), 'dph' => (float)($num($p['dph'] ?? '') ?? 21), 'slevy' => empty($p['slevy']) ? 0 : 1];
    }
    $sr = $num($_POST['sleva_rucne'] ?? '');
    $pdo->prepare('UPDATE ok_nabidka SET nazev=?, adresa=?, ic=?, kontakt=?, telefon=?, email=?, datum=?, platnost=?, smlouva=?, interval_let=?, priplatek=?, sleva_rucne=?, polozky=?, poznamka=?, stav=?, upraveno=? WHERE id=?')
      ->execute([$P('nazev', 200), $P('adresa', 300), $P('ic', 20), $P('kontakt', 200), $P('telefon', 60), $P('email', 200), $P('datum', 10) ?: date('Y-m-d'), $P('platnost', 10) ?: null,
        in_array((int)($_POST['smlouva'] ?? 0), [0, 3, 5], true) ? (int)$_POST['smlouva'] : 0, max(1, (int)($_POST['interval_let'] ?? 1)), isset(NB_PRIPLATKY[$_POST['priplatek'] ?? '']) ? $_POST['priplatek'] : '',
        $sr, json_encode($pol, JSON_UNESCAPED_UNICODE), $P('poznamka', 3000), isset(NB_STAVY[$_POST['stav'] ?? '']) ? $_POST['stav'] : 'koncept', $ted, $id]);
    header('Location: nabidky.php?id=' . $id . (!empty($_POST['dal']) ? '&upravit=1' : '') . '&ok=1'); exit;
  }
  if ($a === 'stav' && $nacti($id)) { $pdo->prepare('UPDATE ok_nabidka SET stav=?, upraveno=? WHERE id=?')->execute([isset(NB_STAVY[$_POST['stav'] ?? '']) ? $_POST['stav'] : 'koncept', $ted, $id]); header('Location: nabidky.php?id=' . $id); exit; }
  if ($a === 'kopie' && ($x = $nacti($id))) {
    $pdo->prepare('INSERT INTO ok_nabidka (cislo, typ, svj_id, jed_id, nazev, adresa, ic, kontakt, telefon, email, datum, platnost, smlouva, interval_let, priplatek, sleva_rucne, polozky, poznamka, stav, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([ok_nabidka_cislo($pdo), $x['typ'], $x['svj_id'], $x['jed_id'], $x['nazev'], $x['adresa'], $x['ic'], $x['kontakt'], $x['telefon'], $x['email'], date('Y-m-d'), date('Y-m-d', strtotime('+' . (int)$n['platnost_dni'] . ' days')), $x['smlouva'], $x['interval_let'], $x['priplatek'], $x['sleva_rucne'], $x['polozky'], $x['poznamka'], 'koncept', uziv_jmeno(), $ted, $ted]);
    header('Location: nabidky.php?id=' . (int)$pdo->lastInsertId() . '&upravit=1'); exit;
  }
  if ($a === 'smazat') { uziv_jen_admin('Nabídku smí smazat jen administrátor.'); $pdo->prepare('DELETE FROM ok_nabidka WHERE id=?')->execute([$id]); header('Location: nabidky.php'); exit; }
  http_response_code(400); exit('Neznámá akce');
}

$id = (int)($_GET['id'] ?? 0); $nb = $id ? $nacti($id) : null;
// ═════════ TISK ═════════
if ($nb && isset($_GET['tisk'])):
  $v = $vypocet($nb); $s = (float)$v['sum']['s']; ?><!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Nabídka <?= $e($nb['cislo']) ?> – servis oken</title><style>
body{font:13px/1.45 Arial,sans-serif;color:#1b2430;margin:0}main{max-width:780px;margin:0 auto;padding:18px}h1{font-size:22px;margin:14px 0 4px}h2{font-size:15px;margin:16px 0 6px;color:#0e4f5c}
table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #d9dee6;padding:5px 6px;text-align:left;vertical-align:top}th{background:#eef4f6;font-size:12px}.r{text-align:right}.nowrap{white-space:nowrap}.mut{color:#5f6b7a}
.hl{display:flex;justify-content:space-between;gap:12px;font-size:12px;border-bottom:3px solid #0e4f5c;padding-bottom:8px}.sum td{font-weight:700;border-top:2px solid #0e4f5c}.no{margin-top:14px}
@media print{.noprint{display:none}main{padding:0}@page{margin:14mm}}</style></head><body><main>
<p class="noprint"><a href="?id=<?= $id ?>">← zpět</a> · <button onclick="window.print()">🖨 Tisk / uložit jako PDF</button></p>
<div class="hl"><div><b style="font-size:15px">KK SYSTEM – servis oken a dveří</b><br>Zdeněk Kučera · Zd. Štěpánka 2764/1, 434 01 Most<br>IČ 64682676, DIČ CZ7801242768</div><div class="r">+420 725 559 235 · +420 471 008 994<br>info@kksystem.cz<br>www.bezpecne-dvere.cz</div></div>
<h1>Cenová nabídka – servis oken a dveří</h1>
<p>č. <b><?= $e($nb['cislo']) ?></b> · ze dne <?= $e(so_datum($nb['datum'])) ?><?= $nb['platnost'] ? ' · platnost do ' . $e(so_datum($nb['platnost'])) : '' ?></p>
<p><b>Pro:</b> <?= $e($nb['nazev']) ?><?= $nb['ic'] ? ', IČ ' . $e($nb['ic']) : '' ?><?= $nb['adresa'] ? '<br>' . $e($nb['adresa']) : '' ?><?= $nb['kontakt'] ? '<br>' . $e($nb['kontakt']) : '' ?><?= ($nb['telefon'] || $nb['email']) ? '<br>' . $e(implode(' · ', array_filter([$nb['telefon'], $nb['email']]))) : '' ?></p>
<h2>Položky<?= (int)$nb['smlouva'] ? ' – cena za jeden servis' : '' ?></h2>
<table><tr><th>Položka</th><th class="r">Množství</th><th class="r">Cena za jedn. bez DPH</th><th class="r">Bez DPH</th><th class="r">DPH</th><th class="r">Celkem s DPH</th></tr>
<?php foreach ($v['radky'] as $r): ?><tr><td><?= $e($r['nazev']) ?></td><td class="r nowrap"><?= $e(str_replace('.', ',', (string)(float)$r['mnozstvi']) . ' ' . $r['jednotka']) ?></td><td class="r nowrap"><?= $e(ok_kc($r['jedn_po'], 2)) ?></td><td class="r nowrap"><?= $e(ok_kc($r['bez'], 2)) ?></td><td class="r nowrap"><?= $e((float)$r['dph']) ?> %</td><td class="r nowrap"><?= $e(ok_kc($r['s'], 2)) ?></td></tr><?php endforeach; ?>
<tr class="sum"><td colspan="3">Celkem<?= (int)$nb['smlouva'] ? ' za jeden servis' : '' ?></td><td class="r nowrap"><?= $e(ok_kc($v['sum']['bez'], 2)) ?></td><td class="r nowrap"><?= $e(ok_kc($v['sum']['dph'], 2)) ?></td><td class="r nowrap"><?= $e(ok_kc($s, 2)) ?></td></tr></table>
<?php if ($v['sleva'] > 0): ?><p>V cenách je započtena <b>sleva <?= $e(str_replace('.', ',', (string)$v['sleva'])) ?> %</b> (<?= $e($v['sleva_txt']) ?>) – celkem <?= $e(ok_kc($v['sum']['sleva'] * 1, 2)) ?> bez DPH.</p><?php endif; ?>
<?php if ($v['priplatek'] > 0): ?><p>Ceny obsahují příplatek <?= $e(str_replace('.', ',', (string)$v['priplatek'])) ?> % – <?= $e(NB_PRIPLATKY[$nb['priplatek']]) ?>.</p><?php endif; ?>
<?php if ((int)$nb['smlouva']): ?><p class="no"><b>Servisní smlouva:</b> <?= (int)$nb['smlouva'] ?>× opakovaný servis, <?= (int)$nb['interval_let'] === 1 ? 'jednou ročně' : 'jednou za ' . (int)$nb['interval_let'] . ' roky' ?>. Cena za všechny servisy po dobu smlouvy celkem <b><?= $e(ok_kc($s * (int)$nb['smlouva'], 2)) ?></b> s DPH (při nezměněném počtu bytů a ceně dopravy).</p><?php endif; ?>
<h2>Rozsah servisu</h2><ul><?php foreach (array_filter(array_map('trim', explode("\n", (string)$n['text_rozsah']))) as $t): ?><li><?= $e($t) ?></li><?php endforeach; ?></ul>
<?php if (trim((string)$nb['poznamka']) !== ''): ?><h2>Poznámka</h2><p><?= nl2br($e($nb['poznamka'])) ?></p><?php endif; ?>
<p class="mut"><?= $e($n['text_podminky']) ?></p>
<p style="margin-top:26px">Vypracoval: <?= $e($nb['vytvoril'] ?: uziv_jmeno()) ?> · <?= $e(so_datum($nb['datum'])) ?></p>
</main></body></html>
<?php exit; endif;

ok_hlava('Nabídky'); ok_bar('nab'); ?><main>
<?php if (isset($_GET['ok'])): ?><div class="msg">Uloženo.</div><?php endif; ?>
<?php
// ═════════ DETAIL / ÚPRAVA ═════════
if ($nb):
  $v = $vypocet($nb); $pol = json_decode((string)$nb['polozky'], true) ?: []; $upr = isset($_GET['upravit']); ?>
<p><a href="nabidky.php">← Nabídky</a></p>
<h1>Nabídka <?= $e($nb['cislo']) ?> <span class="st"><?= $e(NB_STAVY[$nb['stav']] ?? $nb['stav']) ?></span></h1>
<p class="mut"><?= $nb['typ'] === 'svj' && $nb['svj_id'] ? '<a href="svj.php?svj=' . (int)$nb['svj_id'] . '">SVJ v aplikaci</a> · ' : '' ?><?= $nb['typ'] === 'jed' && $nb['jed_id'] ? '<a href="index.php?id=' . (int)$nb['jed_id'] . '">servis zákazníka</a> · ' : '' ?>vytvořil <?= $e($nb['vytvoril']) ?>, <?= $e(date('j. n. Y', strtotime($nb['vytvoreno']))) ?></p>
<?php if (!$upr): ?>
<div class="box"><b><?= $e($nb['nazev']) ?></b><div class="mut"><?= $e($nb['adresa']) ?></div>
<table style="margin-top:8px"><tr><th>Položka</th><th class="r">Množství</th><th class="r">Za jedn. bez DPH</th><th class="r">Celkem s DPH</th></tr>
<?php foreach ($v['radky'] as $r): ?><tr><td><?= $e($r['nazev']) ?></td><td class="r"><?= $e(str_replace('.', ',', (string)(float)$r['mnozstvi']) . ' ' . $r['jednotka']) ?></td><td class="r nowrap"><?= $e(ok_kc($r['jedn_po'], 2)) ?></td><td class="r nowrap"><?= $e(ok_kc($r['s'], 2)) ?></td></tr><?php endforeach; ?>
<tr><th colspan="3">Celkem s DPH<?= (int)$nb['smlouva'] ? ' (jeden servis)' : '' ?></th><th class="r nowrap"><?= $e(ok_kc($v['sum']['s'], 2)) ?></th></tr></table>
<p class="mut"><?= $v['sleva'] > 0 ? 'Sleva ' . $e(str_replace('.', ',', (string)$v['sleva'])) . ' % – ' . $e($v['sleva_txt']) . '. ' : 'Bez slevy. ' ?><?= $v['priplatek'] > 0 ? 'Příplatek ' . $e($v['priplatek']) . ' %. ' : '' ?><?= (int)$nb['smlouva'] ? 'Smlouva ' . (int)$nb['smlouva'] . '× – celkem ' . $e(ok_kc($v['sum']['s'] * (int)$nb['smlouva'], 2)) . ' s DPH.' : '' ?></p>
<p><a class="btn" href="?id=<?= $id ?>&amp;tisk=1" target="_blank">🖨 Tisk / PDF</a> <a class="btn sec" href="?id=<?= $id ?>&amp;upravit=1">✏️ Upravit</a></p></div>
<div class="ln"><form method="post"><input type="hidden" name="akce" value="stav"><input type="hidden" name="id" value="<?= $id ?>"><select name="stav" onchange="this.form.submit()"><?php foreach (NB_STAVY as $k => $t): ?><option value="<?= $k ?>"<?= $nb['stav'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></form>
<form method="post"><input type="hidden" name="akce" value="kopie"><input type="hidden" name="id" value="<?= $id ?>"><button class="sec">Kopie jako nová nabídka</button></form>
<?php if (uziv_admin()): ?><form method="post" onsubmit="return confirm('Smazat nabídku?')"><input type="hidden" name="akce" value="smazat"><input type="hidden" name="id" value="<?= $id ?>"><button class="del">Smazat</button></form><?php endif; ?></div>
<?php else: ?>
<form method="post" id="nbForm"><input type="hidden" name="akce" value="ulozit"><input type="hidden" name="id" value="<?= $id ?>">
<div class="box"><h3>Odběratel</h3>
<div class="ln"><label class="w">Název / jméno<input name="nazev" value="<?= $e($nb['nazev']) ?>"></label><label>IČ<input name="ic" value="<?= $e($nb['ic']) ?>" size="10"></label></div>
<div class="ln"><label class="w">Adresa<input name="adresa" value="<?= $e($nb['adresa']) ?>"></label></div>
<div class="ln"><label class="w">Kontaktní osoba<input name="kontakt" value="<?= $e($nb['kontakt']) ?>"></label><label>Telefon<input name="telefon" value="<?= $e($nb['telefon']) ?>"></label><label class="w">E-mail<input name="email" type="email" value="<?= $e($nb['email']) ?>"></label></div>
<div class="ln"><label>Datum<input type="date" name="datum" value="<?= $e($nb['datum']) ?>"></label><label>Platnost do<input type="date" name="platnost" value="<?= $e($nb['platnost']) ?>"></label><label>Stav<select name="stav"><?php foreach (NB_STAVY as $k => $t): ?><option value="<?= $k ?>"<?= $nb['stav'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></label></div></div>
<div class="box"><h3>Slevy a příplatky</h3>
<div class="ln"><label>Servisní smlouva<select name="smlouva"><option value="0"<?= (int)$nb['smlouva'] === 0 ? ' selected' : '' ?>>bez smlouvy (jednorázový servis)</option><option value="3"<?= (int)$nb['smlouva'] === 3 ? ' selected' : '' ?>>3× opakovaný servis (−<?= $e($n['sleva_sml3']) ?> %)</option><option value="5"<?= (int)$nb['smlouva'] === 5 ? ' selected' : '' ?>>5× opakovaný servis (−<?= $e($n['sleva_sml5']) ?> %)</option></select></label>
<label>Interval<select name="interval_let"><option value="1"<?= (int)$nb['interval_let'] === 1 ? ' selected' : '' ?>>ročně</option><option value="2"<?= (int)$nb['interval_let'] === 2 ? ' selected' : '' ?>>jednou za 2 roky</option></select></label>
<label>Příplatek<select name="priplatek"><?php foreach (NB_PRIPLATKY as $k => $t): ?><option value="<?= $k ?>"<?= $nb['priplatek'] === $k ? ' selected' : '' ?>><?= $e($t) ?><?= $k ? ' (+' . $e($n['priplatek_' . $k]) . ' %)' : '' ?></option><?php endforeach; ?></select></label>
<label>Ruční sleva % (přebije ostatní)<input name="sleva_rucne" value="<?= $e($nb['sleva_rucne']) ?>" size="4" placeholder="–"></label></div>
<p class="mut" style="margin:0">Množstevní sleva: <?= (int)$n['sleva_mn1_od'] ?>–<?= (int)$n['sleva_mn2_od'] - 1 ?> bytů −<?= $e($n['sleva_mn1']) ?> %, od <?= (int)$n['sleva_mn2_od'] ?> bytů −<?= $e($n['sleva_mn2']) ?> %. Slevy se nesčítají, platí vyšší. Teď: <b><?= $v['sleva'] > 0 ? $e($v['sleva']) . ' % – ' . $e($v['sleva_txt']) : 'bez slevy' ?></b> (<?= (int)$v['byty'] ?> bytů).</p></div>
<div class="box"><h3>Položky</h3><div style="overflow-x:auto"><table id="pol"><tr><th>Z ceníku</th><th>Název</th><th class="r">Množství</th><th>Jedn.</th><th class="r">Cena bez DPH</th><th class="r">DPH %</th><th>Slevy</th><th></th></tr>
<?php foreach (array_merge($pol, [[], []]) as $i => $p): ?><tr><td><select class="ck"><option value="">–</option><?php foreach ($cen as $c) if ($c['aktivni']): ?><option value="<?= $e($c['kod']) ?>" data-n="<?= $e($c['nazev']) ?>" data-j="<?= $e($c['jednotka']) ?>" data-c="<?= $e(round((float)$c['cena'], 4)) ?>" data-d="<?= $e((float)$c['dph']) ?>" data-s="<?= $c['skupina'] === 'doprava' ? 0 : 1 ?>"<?= ($p['kod'] ?? '') === $c['kod'] ? ' selected' : '' ?>><?= $e($c['kod']) ?></option><?php endif; ?></select><input type="hidden" name="pol[<?= $i ?>][kod]" value="<?= $e($p['kod'] ?? '') ?>"></td>
<td><input name="pol[<?= $i ?>][nazev]" value="<?= $e($p['nazev'] ?? '') ?>" style="min-width:240px;width:100%"></td><td class="r"><input name="pol[<?= $i ?>][mnozstvi]" value="<?= $e(isset($p['mnozstvi']) ? str_replace('.', ',', (string)(float)$p['mnozstvi']) : '') ?>" size="4" style="text-align:right"></td>
<td><input name="pol[<?= $i ?>][jednotka]" value="<?= $e($p['jednotka'] ?? 'ks') ?>" size="5"></td><td class="r"><input name="pol[<?= $i ?>][cena]" value="<?= $e(isset($p['cena']) ? str_replace('.', ',', (string)round((float)$p['cena'], 2)) : '') ?>" size="7" style="text-align:right"></td>
<td class="r"><input name="pol[<?= $i ?>][dph]" value="<?= $e($p['dph'] ?? $n['dph_byt']) ?>" size="3" style="text-align:right"></td><td><input type="checkbox" name="pol[<?= $i ?>][slevy]" value="1"<?= !isset($p['slevy']) || $p['slevy'] ? ' checked' : '' ?> title="Uplatnit slevu a příplatek"></td><td class="mut"><?= isset($v['radky'][$i]) && ($v['radky'][$i]['nazev'] ?? '') === ($p['nazev'] ?? null) ? $e(ok_kc($v['radky'][$i]['s'])) : '' ?></td></tr>
<?php endforeach; ?></table></div>
<p class="mut">Prázdné řádky se neuloží. Po uložení přibudou další dva prázdné. Ceny z ceníku se při změně ceníku v uložené nabídce nemění.</p></div>
<div class="box"><label class="mut">Poznámka do nabídky<textarea name="poznamka"><?= $e($nb['poznamka']) ?></textarea></label></div>
<div class="box" style="position:sticky;bottom:0;display:flex;gap:10px;flex-wrap:wrap"><button class="sec" name="dal" value="1">Uložit a pokračovat</button><button class="ok">Uložit</button><span style="align-self:center">Celkem s DPH (před uložením): <b><?= $e(ok_kc($v['sum']['s'], 2)) ?></b></span></div>
</form>
<script>
document.querySelectorAll('#pol select.ck').forEach(function (s) { s.addEventListener('change', function () { var o = s.selectedOptions[0], tr = s.closest('tr'), f = function (n) { return tr.querySelector('[name$="[' + n + ']"]'); };
  f('kod').value = o.value; if (!o.value) return; f('nazev').value = o.dataset.n; f('jednotka').value = o.dataset.j; f('cena').value = String(Math.round(+o.dataset.c * 100) / 100).replace('.', ','); f('dph').value = o.dataset.d; f('slevy').checked = o.dataset.s === '1'; if (!f('mnozstvi').value) f('mnozstvi').value = 1; }); });
</script>
<?php endif;

// ═════════ SEZNAM ═════════
else:
  $vse = $pdo->query('SELECT * FROM ok_nabidka ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
  $svjs = $pdo->query('SELECT id, nazev FROM so_svj ORDER BY nazev')->fetchAll(PDO::FETCH_ASSOC); ?>
<h1>📄 Nabídky</h1>
<div class="box ln"><a class="btn ok" href="?nova=jine">+ Nová nabídka (prázdná)</a>
<?php if ($svjs): ?><form class="ln" style="margin:0"><input type="hidden" name="nova" value="svj"><select name="svj"><?php foreach ($svjs as $j): ?><option value="<?= (int)$j['id'] ?>"><?= $e($j['nazev']) ?></option><?php endforeach; ?></select><button class="sec">Nabídka pro SVJ podle seznamu bytů</button></form><?php endif; ?>
<span class="mut">Pro jednotlivce: v jeho servisu tlačítko „📄 Nabídka z ceníku“.</span></div>
<div class="box"><?php if (!$vse): ?><p class="mut">Zatím žádné nabídky.</p><?php else: ?>
<table><tr><th>Číslo</th><th>Pro</th><th>Datum</th><th class="r">Celkem s DPH</th><th>Stav</th></tr>
<?php foreach ($vse as $x): $v = $vypocet($x); ?><tr><td><a href="?id=<?= (int)$x['id'] ?>"><?= $e($x['cislo']) ?></a></td><td><?= $e($x['nazev']) ?><div class="mut"><?= $x['typ'] === 'svj' ? 'SVJ' : ($x['typ'] === 'jed' ? 'jednotlivec' : '') ?><?= (int)$x['smlouva'] ? ' · smlouva ' . (int)$x['smlouva'] . '×' : '' ?></div></td>
<td class="nowrap"><?= $e(so_datum($x['datum'])) ?></td><td class="r nowrap"><?= $e(ok_kc($v['sum']['s'])) ?></td><td><span class="st"><?= $e(NB_STAVY[$x['stav']] ?? '') ?></span></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php endif; ?>
</main></body></html>
