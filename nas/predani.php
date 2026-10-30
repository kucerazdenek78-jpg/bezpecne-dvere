<?php
// Stránka dveří: jediný QR kód z 2. strany předávacího listu. Před předáním nabídne potvrzení předání (datum předání, záruka,
// odpis ze skladu), po předání servisní záznamy a termín další kontroly. Stejná ochrana jako kartoteka.php (Cloudflare Access + heslo).
// 30. 10. 2026: navíc online předání – zákazník podepíše list na displeji (predani-podpis.php?rezim=podpis), předání se zapíše hned.
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
$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$z = null;
if (preg_match('/^[a-f0-9]{24}$/', $t)) {
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND data_json LIKE ?");
  $st->execute(['%"predani_token":"' . $t . '"%']); $z = $st->fetch(PDO::FETCH_ASSOC);
}
function roky_zaruky(array $d): ?int { return preg_match('/(\d+)\s*(?:rok|let)/u', (string)($d['zaruka'] ?? ''), $m) ? (int)$m[1] : null; }

// Registrace záruky od zákazníka (formulář na webu, typ „registrace“): +12 měsíců k základní záruce, jen PREMIUM 60 / EXTREME 54, do 30 dnů od předání.
function registrace_info(PDO $pdo, array $z, array $d): array {
  $klic = ltrim(preg_replace('/\D+/', '', (string)($d['obj_cislo'] ?? $z['cislo'])), '0'); $nalezena = null;
  if ($klic !== '') foreach ($pdo->query("SELECT * FROM zadosti WHERE typ='registrace' ORDER BY id DESC") as $r) {
    $x = json_decode((string)$r['data_json'], true) ?: [];
    if (ltrim(preg_replace('/\D+/', '', (string)($x['cislo_objednavky'] ?? '')), '0') === $klic) { $nalezena = $r; break; }
  }
  $model = in_array($d['model_kod'] ?? '', ['premium60', 'extreme54', 'magnum56'], true);
  $zakladni = !preg_match('/Ano,\s*na/u', (string)($d['zaruka'] ?? ''));
  $lhuta = !empty($z['predano_dne']) ? date('Y-m-d', strtotime($z['predano_dne'] . ' +30 days')) : '';
  $vTermínu = $nalezena && $lhuta !== '' && substr((string)$nalezena['vytvoreno'], 0, 10) <= $lhuta;
  return ['reg' => $nalezena, 'model' => $model, 'zakladni' => $zakladni, 'lhuta' => $lhuta, 'v_terminu' => $vTermínu,
    'lze' => $model && $zakladni && $nalezena && $vTermínu && !empty($z['zaruka_do']) && empty($z['zaruka_prodlouzeno'])];
}

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $d = json_decode((string)$z['data_json'], true) ?: [];
  if (($_POST['akce'] ?? '') === 'servis' && !empty($z['predano_dne'])) {
    $dat = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? $v : null;
    $druhy = ['kontrola', 'servis', 'reklamace']; $druh = in_array($_POST['druh'] ?? '', $druhy, true) ? $_POST['druh'] : 'servis';
    $pdo->prepare('INSERT INTO servis (zadost_id, datum, druh, poznamka, dalsi_kontrola, vytvoreno) VALUES (?,?,?,?,?,?)')
      ->execute([$z['id'], $dat($_POST['datum'] ?? '') ?: date('Y-m-d'), $druh, mb_substr(trim((string)($_POST['poznamka'] ?? '')), 0, 2000), $dat($_POST['dalsi'] ?? ''), date('Y-m-d H:i:s')]);
    header('Location: predani.php?t=' . $t . '&hotovo=servis'); exit;
  }
  if (($_POST['akce'] ?? '') === 'montaz') {
    $d0 = json_decode((string)$z['data_json'], true) ?: [];
    $dne = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['montaz_dne'] ?? '') ? $_POST['montaz_dne'] : '';
    $cas = preg_match('/^\d{2}:\d{2}$/', $_POST['montaz_cas'] ?? '') ? $_POST['montaz_cas'] : '';
    $dl = max(0.5, min(24, (float)str_replace(',', '.', (string)($_POST['montaz_delka'] ?? '3'))));
    $d0['montaz_dne'] = $dne; $d0['montaz_cas'] = $cas; $d0['montaz_delka'] = $dl; $d0['montaz_pozn'] = mb_substr(trim((string)($_POST['montaz_pozn'] ?? '')), 0, 500);
    $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d0, JSON_UNESCAPED_UNICODE), $z['id']]);
    header('Location: predani.php?t=' . $t . '&hotovo=montaz'); exit;
  }
  if (($_POST['akce'] ?? '') === 'prodlouzit') {
    $ri = registrace_info($pdo, $z, $d);
    if ($ri['lze']) $pdo->prepare("UPDATE zadosti SET zaruka_do=?, zaruka_prodlouzeno=? WHERE id=?")->execute([date('Y-m-d', strtotime($z['zaruka_do'] . ' +12 months')), date('Y-m-d'), $z['id']]);
    header('Location: predani.php?t=' . $t . '&hotovo=prodlouzeno'); exit;
  }
  if (($_POST['akce'] ?? '') === 'servis_smazat') {
    uziv_jen_admin(); $pdo->prepare('DELETE FROM servis WHERE id=? AND zadost_id=?')->execute([(int)($_POST['sid'] ?? 0), $z['id']]);
    header('Location: predani.php?t=' . $t); exit;
  }
  if (($_POST['akce'] ?? '') === 'zrusit') {
    $pdo->prepare("UPDATE zadosti SET predano_dne=NULL, zaruka_od=NULL, zaruka_do=NULL, zaruka_prodlouzeno=NULL, stav='rozpracovano', sklad_stav='vyzvednuto' WHERE id=?")->execute([$z['id']]);
  } elseif (!empty($z['predano_dne'])) {
    // už předáno: opakované potvrzení (dvojklik, znovu otevřený QR) nic nepřepíše a nic neposílá
  } else {
    $dne = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['datum'] ?? '') ? $_POST['datum'] : date('Y-m-d');
    $r = roky_zaruky($d); $do = null;
    if ($r) { $dt = new DateTime($dne); $dt->modify("+$r years")->modify('-1 day'); $do = $dt->format('Y-m-d'); }
    $pdo->prepare("UPDATE zadosti SET predano_dne=?, zaruka_od=?, zaruka_do=?, stav='namontovano', sklad_stav='predano' WHERE id=?")->execute([$dne, $dne, $do, $z['id']]);
    // poděkování zákazníkovi: jen při prvním potvrzení předání, jen když má platný e-mail
    $em = trim((string)($d['email'] ?? ''));
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && empty($d['podekovani_odeslano']) && substr((string)($z['vytvoreno'] ?? ''), 0, 10) >= '2026-11-01') {   // poděkování s aplikací jen u objednávek od 1. 11. 2026
      require_once __DIR__ . '/lib/posta.php';
      if (empty($d['zakaznik_token'])) $d['zakaznik_token'] = bin2hex(random_bytes(12));
      if (posli_podekovani($cfg, $DATA, $em, (string)($z['cislo'] ?? ($d['cislo'] ?? '')), (string)$d['zakaznik_token'])) $d['podekovani_odeslano'] = date('Y-m-d H:i:s');
      $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]);
    }
  }
  header('Location: predani.php?t=' . $t . '&hotovo=1'); exit;
}
$d = $z ? (json_decode((string)$z['data_json'], true) ?: []) : [];
if ($z && empty($d['zakaznik_token'])) { $d['zakaznik_token'] = bin2hex(random_bytes(12)); $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]); }
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Předání díla</title><style>
body{font:17px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:560px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:12px;padding:16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{font-size:22px;margin:0 0 4px}.mut{color:#6b7787;font-size:14px}
dl{margin:8px 0 0;display:grid;grid-template-columns:auto 1fr;gap:4px 12px}dt{color:#6b7787}dd{margin:0}
button{background:#1d8a4a;color:#fff;border:0;border-radius:10px;padding:16px;font:600 18px inherit;width:100%;cursor:pointer}button.sec{background:#e6ebf3;color:#1b2430;font-weight:400;font-size:15px;padding:10px;margin-top:10px}
input[type=date]{font:inherit;padding:8px;border:1px solid #c9d1dc;border-radius:8px}.ok{background:#d9f2e0;color:#14653a;padding:10px 12px;border-radius:10px;margin-bottom:12px}.warn{background:#fff0cc;color:#7a5200;padding:10px 12px;border-radius:10px;margin:10px 0 0}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar(''); ?><main>
<?php if (!$z): ?><div class="box"><h1>Objednávka nenalezena</h1><p>QR kód není platný nebo objednávka v kartotéce neexistuje.</p></div>
<?php else:
  $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')); $adresa = implode(', ', array_filter([$d['ulice'] ?? '', trim(($d['psc'] ?? '') . ' ' . ($d['mesto'] ?? ''))]));
  $roky = roky_zaruky($d); $predano = !empty($z['predano_dne']); ?>
  <?php if (isset($_GET['hotovo'])): ?><div class="ok"><?= ($_GET['hotovo'] ?? '') === 'montaz' ? 'Termín montáže uložen.' : (($_GET['hotovo'] ?? '') === 'servis' ? 'Servisní záznam uložen.' : (($_GET['hotovo'] ?? '') === 'prodlouzeno' ? 'Záruka prodloužena o 12 měsíců.' : ($predano ? 'Předání zapsáno, dveře odepsány ze skladu.' : 'Předání zrušeno.'))) ?></div><?php endif; ?>
  <div class="box"><h1>Předání díla – objednávka <?= $e($d['obj_cislo'] ?? $z['cislo']) ?></h1>
    <div class="mut"><?= $e($jmeno) ?><?= $adresa ? ' · ' . $e($adresa) : '' ?></div>
    <dl><dt>Dveře</dt><dd><?= $e(implode(' · ', array_filter([$d['model'] ?? '', $d['odstin'] ?? '', $d['kovani'] ?? '', $d['otevirani'] ?? '']))) ?></dd>
      <dt>Cena</dt><dd><?= $e($kc($d['cena_celkem'] ?? null)) ?></dd>
      <dt>Záloha</dt><dd><?= !empty($z['zaloha_prijata_dne']) ? 'přijata ' . $e($kc($z['zaloha_prijata_kc'])) : 'zatím nezaevidována' ?></dd>
      <dt>K doplacení</dt><dd><?= $e($kc($d['doplatek'] ?? null)) ?></dd>
      <dt>Záruka</dt><dd><?= $e($d['zaruka'] ?? '–') ?></dd></dl></div>
  <?php if ($predano): ?>
    <?php $dnes = date('Y-m-d'); $ri = registrace_info($pdo, $z, $d);
      if (empty($z['zaruka_do'])) { $zst = ['Délka záruky neurčena', '#7a5200', '#fff0cc']; }
      elseif ($z['zaruka_do'] >= $dnes) { $zst = ['V záruce do ' . date('j. n. Y', strtotime($z['zaruka_do'])), '#14653a', '#d9f2e0']; }
      else { $zst = ['Záruka skončila ' . date('j. n. Y', strtotime($z['zaruka_do'])), '#9b1c1c', '#fde2e2']; } ?>
    <div class="box" style="background:<?= $zst[2] ?>;color:<?= $zst[1] ?>;font-size:20px;font-weight:600"><?= $e($zst[0]) ?><?= !empty($z['zaruka_prodlouzeno']) ? '<div style="font-size:14px;font-weight:400">včetně prodloužení o 12 měsíců za registraci</div>' : '' ?></div>
    <?php if ($ri['model'] && $ri['zakladni']): ?><div class="box"><b>Registrace záruky (+12 měsíců)</b><br>
      <?php if ($ri['reg']): ?>Zákazník dveře zaregistroval <?= $e(date('j. n. Y', strtotime($ri['reg']['vytvoreno']))) ?> – <?= $ri['v_terminu'] ? 'v termínu 30 dnů od předání' : 'po termínu 30 dnů (do ' . $e(date('j. n. Y', strtotime($ri['lhuta']))) . ')' ?>.
        <?php if (!empty($z['zaruka_prodlouzeno'])): ?><br>Prodloužení schváleno <?= $e(date('j. n. Y', strtotime($z['zaruka_prodlouzeno']))) ?>.
        <?php elseif ($ri['lze']): ?><form method="post"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="prodlouzit"><button style="margin-top:8px">Schválit prodloužení o 12 měsíců</button></form><?php endif; ?>
      <?php else: ?>Zatím žádná registrace (lhůta do <?= $e(date('j. n. Y', strtotime($ri['lhuta']))) ?>).<?php endif; ?></div><?php endif; ?>
    <div class="box"><b>Předávací a záruční list</b><br><?php if (!empty($d['predani_pdf'])): ?><a href="predani-podpis.php?t=<?= $e($t) ?>&pdf=1" target="_blank">Zobrazit uložený list (PDF)</a> · <span class="mut"><?= $e(($d['predani_rezim'] ?? '') === 'podpis' ? 'podepsáno na displeji' : 'tištěná verze') ?>, <?= $e(date('j. n. Y H:i', strtotime((string)($d['predani_cas'] ?? 'now')))) ?></span><br><?php endif; ?><a href="predani-podpis.php?t=<?= $e($t) ?>"><?= !empty($d['predani_pdf']) ? 'Vystavit nový list' : 'Vystavit list k tisku' ?></a></div>
    <div class="box"><b>Předáno <?= $e(date('j. n. Y', strtotime($z['predano_dne']))) ?></b><br>Záruka od <?= $e(date('j. n. Y', strtotime($z['zaruka_od']))) ?><?= $z['zaruka_do'] ? ' do ' . $e(date('j. n. Y', strtotime($z['zaruka_do']))) : ' (délka záruky nebyla v objednávce určena)' ?>
      <form method="post" onsubmit="return confirm('Zrušit zápis předání a vrátit dveře do evidence skladu?')"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="zrusit"><button class="sec">Zrušit zápis předání</button></form></div>
    <?php $sv = $pdo->prepare('SELECT * FROM servis WHERE zadost_id=? ORDER BY datum DESC, id DESC'); $sv->execute([$z['id']]); $servisy = $sv->fetchAll(PDO::FETCH_ASSOC);
      $dalsi = ''; foreach ($servisy as $q) if (!empty($q['dalsi_kontrola']) && ($dalsi === '' || $q['dalsi_kontrola'] > $dalsi)) $dalsi = $q['dalsi_kontrola'];
      $prvni = (new DateTime($z['predano_dne']))->modify('+1 year')->format('Y-m-d'); $termin = $dalsi !== '' ? $dalsi : $prvni; $po = $termin < date('Y-m-d'); ?>
    <div class="box"><h2 style="font-size:18px;margin:0 0 6px">Servis a kontroly</h2>
      <p>Další kontrola / servis: <b<?= $po ? ' style="color:#b00020"' : '' ?>><?= $e(date('j. n. Y', strtotime($termin))) ?></b><?= $po ? ' (po termínu)' : '' ?><?= $dalsi === '' ? ' <span class="mut">(odhad: rok od předání)</span>' : '' ?></p>
      <?php foreach ($servisy as $q): ?><div style="border-top:1px solid #e4e8ee;padding:8px 0"><b><?= $e(date('j. n. Y', strtotime($q['datum']))) ?></b> · <?= $e(['kontrola' => 'kontrola', 'servis' => 'servis', 'reklamace' => 'reklamace'][$q['druh']] ?? $q['druh']) ?><?= $q['poznamka'] !== '' ? '<br>' . nl2br($e($q['poznamka'])) : '' ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Smazat záznam?')"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="servis_smazat"><input type="hidden" name="sid" value="<?= (int)$q['id'] ?>"><button class="sec" style="width:auto;padding:2px 8px;margin:0 0 0 8px;font-size:12px">smazat</button></form></div><?php endforeach; ?>
      <form method="post" style="border-top:1px solid #e4e8ee;padding-top:10px;margin-top:6px"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="servis">
        <p><label>Datum <input type="date" name="datum" value="<?= date('Y-m-d') ?>"></label> <select name="druh" style="font:inherit;padding:8px"><option value="kontrola">Roční kontrola</option><option value="servis">Servis</option><option value="reklamace">Reklamace</option></select></p>
        <p><textarea name="poznamka" rows="3" style="width:100%;box-sizing:border-box;font:inherit;padding:8px" placeholder="Co se dělalo, zjištěné závady…"></textarea></p>
        <p><label>Další kontrola <input type="date" name="dalsi" value="<?= date('Y-m-d', strtotime('+1 year')) ?>"></label></p>
        <button>Uložit záznam</button></form></div>
  <?php else: ?>
    <div class="box"><b>✍️ Předat online – podpis na displeji</b><p class="mut" style="margin:4px 0 10px">U zákazníka: vyplníte datum a případné výhrady, zákazník se podepíše prstem na telefonu / tabletu. Předání se hned zapíše (záruka, odpis ze skladu) a podepsaný list se uloží a pošle zákazníkovi e-mailem.</p><a href="predani-podpis.php?t=<?= $e($t) ?>&amp;rezim=podpis" style="display:block;text-align:center;background:#1d8a4a;color:#fff;border-radius:10px;padding:14px;font-weight:600;text-decoration:none">✍️ Předat s podpisem na displeji</a></div>
    <div class="box"><form method="post"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="predat">
      <p class="mut" style="margin:0 0 8px">Zákazník podepsal list na papíře? Zkontrolujte datum (předvyplněno dnešním) a potvrďte.</p>
      <p><label>Datum předání <input type="date" name="datum" value="<?= date('Y-m-d') ?>"></label></p>
      <?php if ($roky): ?><p class="mut">Záruka <?= $roky ?> <?= $roky === 1 ? 'rok' : ($roky < 5 ? 'roky' : 'let') ?> od data předání.</p><?php else: ?><div class="warn">V objednávce není určena délka záruky – zapíše se jen datum předání.</div><?php endif; ?>
      <button>Potvrdit předání</button></form></div>
    <div class="box"><b>Předávací list k tisku (před cestou)</b><p class="mut" style="margin:4px 0 10px">List se vytiskne předem, nic se tím nezapíše. Po podpisu u zákazníka naskenujete QR kód na 2. straně, zkontrolujete datum a potvrdíte předání tlačítkem výše.</p><a href="predani-podpis.php?t=<?= $e($t) ?>" style="display:block;text-align:center;background:#1d6fb8;color:#fff;border-radius:10px;padding:14px;font-weight:600;text-decoration:none">🖨 Vytisknout předávací list</a></div>
  <?php endif; ?>
  <?php if (!$predano): $mD = ($d['montaz_dne'] ?? '') ?: ($d['termin_datum'] ?? ''); ?>
    <div class="box"><b>📅 Termín montáže</b> <span class="mut">(zobrazí se v kalendáři montáží)</span>
      <form method="post"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="montaz">
      <p><label>Datum <input type="date" name="montaz_dne" value="<?= $e($mD) ?>"></label> <label>od <input type="time" name="montaz_cas" value="<?= $e($d['montaz_cas'] ?? '') ?>" style="font:inherit;padding:8px;border:1px solid #c9d1dc;border-radius:8px"></label> <label>trvá (h) <input type="number" step="0.5" min="0.5" max="24" name="montaz_delka" value="<?= $e($d['montaz_delka'] ?? 3) ?>" style="width:70px;font:inherit;padding:8px;border:1px solid #c9d1dc;border-radius:8px"></label></p>
      <p><input name="montaz_pozn" value="<?= $e($d['montaz_pozn'] ?? '') ?>" placeholder="Poznámka (parkování, klíče, patro…)" style="width:100%;box-sizing:border-box;font:inherit;padding:8px"></p>
      <button class="sec" style="margin-top:0">Uložit termín</button> <a href="montaze.php" class="mut">Kalendář montáží →</a></form></div>
  <?php endif; ?>
  <div class="box"><span class="mut">Odkaz pro zákazníka (záruka, servis):</span><br><a href="<?= $e('https://formulare.dvere.app/moje.php?t=' . $d['zakaznik_token']) ?>" style="word-break:break-all"><?= $e('https://formulare.dvere.app/moje.php?t=' . $d['zakaznik_token']) ?></a></div>
<?php endif; ?></main></body></html>
