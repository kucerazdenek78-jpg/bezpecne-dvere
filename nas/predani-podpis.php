<?php
// Předávací a záruční list. Dva režimy:
// - Na papíře (tisk): list se vytiskne předem, podepíše se u zákazníka a předání se potvrdí až načtením QR kódu (predani.php).
// - Na displeji (online předání, od 30. 10. 2026): technik u zákazníka vyplní datum a případné výhrady, zákazník (a montážník) se podepíše
//   prstem na displeji, předání se HNED zapíše (datum, záruka, odpis ze skladu, poděkování) a podepsaný list se uloží a pošle zákazníkovi.
// PDF se sestaví v prohlížeči z předlohy na webu (PoptavkaPDF.buildHandover), uloží se na NAS (/data/predani).
// Stejná ochrana jako predani.php (Cloudflare Access + heslo).
require __DIR__ . '/lib/start.php';
require __DIR__ . '/lib/posta.php';
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
$web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$z = null;
if (preg_match('/^[a-f0-9]{24}$/', $t)) {
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND data_json LIKE ?");
  $st->execute(['%"predani_token":"' . $t . '"%']); $z = $st->fetch(PDO::FETCH_ASSOC);
}
function json_ven(int $kod, array $x) { http_response_code($kod); header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
$d = $z ? (json_decode((string)$z['data_json'], true) ?: []) : [];

// stažení uloženého listu
if ($z && isset($_GET['pdf'])) {
  $f = (string)($d['predani_pdf'] ?? '');
  $cesta = $DATA . '/predani/' . $f;
  if (!preg_match('/^[A-Za-z0-9_-]+\.pdf$/', $f) || !is_file($cesta)) { http_response_code(404); exit('Soubor neexistuje'); }
  header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $f . '"'); header('Content-Length: ' . filesize($cesta)); readfile($cesta); exit;
}

// uložení listu (a u podpisu na displeji i zápis předání)
if ($z && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $dat = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['datum'] ?? '')) ? $_POST['datum'] : date('Y-m-d');
  $rezim = ($_POST['rezim'] ?? '') === 'papir' ? 'papir' : 'podpis';
  $up = $_FILES['pdf'] ?? null;
  if (!$up || ($up['error'] ?? 1) !== UPLOAD_ERR_OK || ($up['size'] ?? 0) < 1000 || $up['size'] > 8 * 1024 * 1024) json_ven(400, ['ok' => false, 'zprava' => 'PDF se nepřenesl.']);
  $obsah = (string)file_get_contents($up['tmp_name']);
  if (strncmp($obsah, '%PDF', 4) !== 0) json_ven(400, ['ok' => false, 'zprava' => 'Soubor není PDF.']);
  @mkdir($DATA . '/predani', 0775, true);
  $cisloBez = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)($d['obj_cislo'] ?? $z['cislo'])) ?: 'objednavka';
  $nazev = $cisloBez . '-' . ($rezim === 'podpis' ? 'podepsano' : 'tisk') . '-' . date('Ymd-His') . '.pdf';
  $cesta = $DATA . '/predani/' . $nazev;
  if (file_put_contents($cesta, $obsah) === false) json_ven(500, ['ok' => false, 'zprava' => 'PDF se nepodařilo uložit na NAS.']);

  $d['predani_pdf'] = $nazev; $d['predani_rezim'] = $rezim; $d['predani_cas'] = date('c');
  $d['predani_montaznik'] = mb_substr(trim((string)($_POST['montaznik'] ?? '')), 0, 80);
  $d['predani_vyhrady'] = mb_substr(trim((string)($_POST['vyhrady'] ?? '')), 0, 300);
  if ($rezim === 'podpis') $d['predani_podepsal'] = function_exists('uziv_jmeno') ? (string)uziv_jmeno() : '';
  $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]);

  // Tisk listu předání NEZAPISUJE (podpis na papíře, potvrzení přes QR v predani.php).
  // Podpis na displeji = předání proběhlo teď → zapsat datum předání, záruku, odpis ze skladu a poslat poděkování (stejně jako predani.php).
  $nove = false;
  if ($rezim === 'podpis' && empty($z['predano_dne'])) {
    $roky = preg_match('/(\d+)\s*(?:rok|let)/u', (string)($d['zaruka'] ?? ''), $mm) ? (int)$mm[1] : 0; $do = null;
    if ($roky) { $dt = new DateTime($dat); $dt->modify("+$roky years")->modify('-1 day'); $do = $dt->format('Y-m-d'); }
    $pdo->prepare("UPDATE zadosti SET predano_dne=?, zaruka_od=?, zaruka_do=?, stav='namontovano', sklad_stav='predano' WHERE id=?")->execute([$dat, $dat, $do, $z['id']]);
    $nove = true;
    $em = trim((string)($d['email'] ?? ''));
    if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && empty($d['podekovani_odeslano']) && substr((string)($z['vytvoreno'] ?? ''), 0, 10) >= '2026-11-01') {   // poděkování s aplikací jen u objednávek od 1. 11. 2026
      if (empty($d['zakaznik_token'])) $d['zakaznik_token'] = bin2hex(random_bytes(12));
      if (posli_podekovani($cfg, $DATA, $em, (string)($z['cislo'] ?? ($d['cislo'] ?? '')), (string)$d['zakaznik_token'])) $d['podekovani_odeslano'] = date('Y-m-d H:i:s');
      $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]);
    }
  }

  // kopie podepsaného listu zákazníkovi e-mailem
  $mail = 'bez_emailu'; $email = trim((string)($d['email'] ?? ''));
  if ($rezim === 'podpis') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $mail = 'bez_emailu';
    elseif (($_POST['kopie'] ?? '') !== '1') $mail = 'nechtel';
    else $mail = posli_predani_kopii($cfg, $DATA, $email, (string)($d['obj_cislo'] ?? $z['cislo']), $cesta) ? (($cfg['mail_rezim'] ?? 'smtp') === 'soubor' ? 'zkusebni' : 'odeslano') : 'chyba';
  } else $mail = 'papir';
  $z2 = $pdo->prepare('SELECT predano_dne, zaruka_do FROM zadosti WHERE id=?'); $z2->execute([$z['id']]); $zz = $z2->fetch(PDO::FETCH_ASSOC);
  json_ven(200, ['ok' => true, 'mail' => $mail, 'nove_predani' => $nove, 'predano' => $zz['predano_dne'] ?? '', 'zaruka_do' => $zz['zaruka_do'] ?? '', 'email' => $email]);
}

if ($z && empty($d['zakaznik_token'])) { $d['zakaznik_token'] = bin2hex(random_bytes(12)); $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]); }
$predano = $z && !empty($z['predano_dne']);
$roky = $z && preg_match('/(\d+)\s*(?:rok|let)/u', (string)($d['zaruka'] ?? ''), $m) ? (int)$m[1] : 0;
$vychozi = ($_GET['rezim'] ?? '') === 'podpis' && !$predano ? 'displej' : 'papir';
// data pro předlohu listu: s tokenem předání (2. strana s QR kódem je jen pro montážníka a zákazníkovi se nedává)
$dd = $d;
$J = fn($x) => json_encode($x, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Předávací list</title><style>
body{font:17px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:620px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:12px;padding:16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{font-size:22px;margin:0 0 4px}h2{font-size:17px;margin:0 0 8px}.mut{color:#6b7787;font-size:14px}
label{display:block;margin:10px 0 4px;font-weight:600;font-size:15px}input[type=text],input[type=date],textarea{width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:8px}
.row{display:flex;gap:10px;align-items:flex-start;margin:8px 0;font-weight:400}.row input{margin-top:5px;width:20px;height:20px;flex:none}.row span{flex:1}
button{background:#1d8a4a;color:#fff;border:0;border-radius:10px;padding:16px;font:600 18px inherit;width:100%;cursor:pointer}button:disabled{opacity:.5}button.sec{background:#e6ebf3;color:#1b2430;font-weight:500;font-size:15px;padding:10px;margin-top:8px}
.bdp button.bdp-x{width:auto;font-weight:400}
.ok{background:#d9f2e0;color:#14653a;padding:10px 12px;border-radius:10px;margin-bottom:12px}.err{background:#fde2e2;color:#9b1c1c;padding:10px 12px;border-radius:10px;margin:10px 0}.warn{background:#fff0cc;color:#7a5200;padding:10px 12px;border-radius:10px;margin-bottom:12px}
a.btn{display:block;text-align:center;background:#1d8a4a;color:#fff;border-radius:10px;padding:14px;font-weight:600;text-decoration:none;margin-top:8px}a.btn.sec{background:#e6ebf3;color:#1b2430;font-weight:500}
</style></head><body><main>
<?php if (!$z): ?><div class="box"><h1>Objednávka nenalezena</h1><p>QR kód není platný nebo objednávka v kartotéce neexistuje.</p></div>
<?php else:
  $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')); $adresa = implode(', ', array_filter([$d['ulice'] ?? '', trim(($d['psc'] ?? '') . ' ' . ($d['mesto'] ?? ''))])); ?>
  <div class="box"><h1>Předávací list – <?= $e($d['obj_cislo'] ?? $z['cislo']) ?></h1>
    <div class="mut"><?= $e($jmeno) ?><?= $adresa ? ' · ' . $e($adresa) : '' ?></div>
    <div class="mut"><?= $e(implode(' · ', array_filter([$d['model'] ?? '', $d['odstin'] ?? '', $d['kovani'] ?? '', $d['otevirani'] ?? '']))) ?></div>
    <p class="mut" style="margin:8px 0 0"><a href="predani.php?t=<?= $e($t) ?>">← zpět na stránku dveří</a></p></div>
  <?php if ($predano): ?><div class="warn">Předání je už zapsáno (<?= $e(date('j. n. Y', strtotime($z['predano_dne']))) ?>).<?= !empty($d['predani_pdf']) ? ' <a href="predani-podpis.php?t=' . $e($t) . '&pdf=1" target="_blank">Zobrazit uložený list</a>.' : '' ?> Nový list se uloží jako další verze, datum předání a záruka se nezmění.</div><?php endif; ?>
  <div id="formular">
  <div class="box"><h2>1. Montážník</h2>
    <label for="montaznik">Předá (montážník) – nepovinné</label><input type="text" id="montaznik" autocomplete="off" placeholder="jméno montážníka, jinak se doplní rukou">
  </div>
  <div class="box"><h2>2. Jak předáváte?</h2>
    <div id="kotva"></div>
    <div id="online" hidden>
      <label for="datum">Datum předání</label><input type="date" id="datum" value="<?= $e($predano ? substr((string)$z['predano_dne'], 0, 10) : date('Y-m-d')) ?>"<?= $predano ? ' disabled' : '' ?>>
      <p class="mut" style="margin:4px 0 0"><?= $roky ? 'Záruka ' . $roky . ' ' . ($roky === 1 ? 'rok' : ($roky < 5 ? 'roky' : 'let')) . ' od data předání.' : 'V objednávce není určena délka záruky – zapíše se jen datum předání.' ?></p>
      <label for="vyhrady">Výhrady zákazníka – nepovinné</label><textarea id="vyhrady" rows="2" maxlength="300" placeholder="bez výhrad"></textarea>
      <label class="row"><input type="checkbox" id="kopie"<?= filter_var((string)($d['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? ' checked' : ' disabled' ?>><span>Poslat zákazníkovi podepsaný list e-mailem <?= !empty($d['email']) ? '(' . $e($d['email']) . ')' : '(zákazník nemá e-mail – list mu ukažte, stáhněte nebo pošlete jinak)' ?></span></label>
    </div>
    <p class="mut" id="papirInfo" style="margin:0">List se vytiskne <b>předem</b> (datum předání, záruka do a podpisy zůstanou prázdné k doplnění rukou). U zákazníka ho podepíšete na papíře. Potom naskenujete QR kód na listu, zkontrolujete datum a potvrdíte. Teprve tím se dveře v kartotéce přepnou na předané a začne záruka.</p>
  </div>
  <div id="chyba" class="err" hidden></div>
  <button type="button" id="hotovo">Připravit list k tisku</button>
  </div>
  <div id="vysledek" hidden></div>
<?php endif; ?></main>
<?php if ($z): ?>
<script>window.__D=<?= $J($dd) ?>;window.__T=<?= $J($t) ?>;window.__EMAIL=<?= $J((string)($d['email'] ?? '')) ?>;window.__ZARUKA=<?= (int)$roky ?>;window.__REZIM=<?= $J($vychozi) ?>;window.__PREDANO=<?= $predano ? 'true' : 'false' ?>;</script>
<script src="<?= $e($web) ?>/platba/qrcode.js"></script>
<script src="<?= $e($web) ?>/platba/platba.js?v=2"></script>
<script src="<?= $e($web) ?>/poptavka-pdf/poptavka-pdf-nova.js?v=20261030a"></script>
<script src="<?= $e($web) ?>/podpis/podpis.js?v=20261030a"></script>
<script>
(function () {
  var $ = function (s) { return document.querySelector(s); };
  try { $('#montaznik').value = localStorage.getItem('bd-montaznik') || ''; } catch (e) {}
  function chyba(t) { var x = $('#chyba'); x.textContent = t; x.hidden = !t; if (t) x.scrollIntoView({ block: 'center' }); }
  function zarukaDo(dat) { if (!window.__ZARUKA || !dat) return ''; var d = new Date(dat + 'T12:00:00'); d.setFullYear(d.getFullYear() + window.__ZARUKA); d.setDate(d.getDate() - 1); return d.toISOString().slice(0, 10); }
  function cz(v) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || ''); return m ? (+m[3]) + '. ' + (+m[2]) + '. ' + m[1] : ''; }
  var ui = window.BDPodpis ? window.BDPodpis.volba($('#kotva'), { nadpis: 'Podpis listu', vychozi: window.__REZIM,
    papirText: 'Na papíře: list se teď jen připraví k tisku, předání se zapíše až po podpisu přes QR kód.',
    displejText: 'Na displeji: zákazník se podepíše prstem, předání se zapíše hned a podepsaný list se uloží' + (window.__EMAIL ? ' a pošle zákazníkovi.' : '.'),
    zakaznik: 'Podpis zákazníka (objednatel)', zhotovitel: 'Podpis montážníka (za zhotovitele) – nepovinné' }) : null;
  if (ui) ui.el.insertBefore($('#online'), ui.el.querySelector('.bdp-pole-zak'));   // datum a výhrady nad pole pro podpis (podepisuje se až nakonec)
  function prepni() {
    var disp = ui && ui.rezim() === 'displej';
    $('#online').hidden = !disp; $('#papirInfo').hidden = disp;
    $('#hotovo').textContent = disp ? (window.__PREDANO ? 'Uložit podepsaný list' : '✍️ Zapsat předání a uložit podepsaný list') : 'Připravit list k tisku';
  }
  if (ui) ui.el.querySelectorAll('input[type=radio]').forEach(function (i) { i.addEventListener('change', prepni); });
  prepni();
  $('#hotovo').addEventListener('click', function () {
    chyba('');
    var papir = !ui || ui.rezim() === 'papir';
    var datum = papir ? '' : $('#datum').value;
    if (!papir) { var pe = ui.chyba(); if (pe) { chyba(pe); return; } if (!/^\d{4}-\d{2}-\d{2}$/.test(datum)) { chyba('Vyplňte datum předání.'); return; } }
    var btn = $('#hotovo'), txt0 = btn.textContent; btn.disabled = true; btn.textContent = papir ? 'Připravuji list…' : 'Připravuji podepsaný list…';
    try { localStorage.setItem('bd-montaznik', $('#montaznik').value); } catch (e) {}
    var o = papir ? { datum: '', zarukaDo: '', montaznik: $('#montaznik').value.trim(), vyhrady: '', podpisZak: null, podpisZhot: null }
      : { datum: datum, zarukaDo: zarukaDo(datum), montaznik: $('#montaznik').value.trim(), vyhrady: $('#vyhrady').value.trim(), podpisZak: ui.zakaznik(), podpisZhot: ui.zhotovitel() };
    window.PoptavkaPDF.buildHandover(window.__D, o).then(function (blob) {
      btn.textContent = 'Ukládám…';
      var fd = new FormData(); fd.append('t', window.__T); fd.append('rezim', papir ? 'papir' : 'podpis');
      fd.append('montaznik', o.montaznik); fd.append('vyhrady', o.vyhrady || ''); fd.append('datum', datum);
      fd.append('kopie', !papir && $('#kopie').checked ? '1' : '');
      fd.append('pdf', blob, 'predavaci-list.pdf');
      return fetch('predani-podpis.php', { method: 'POST', body: fd }).then(function (r) { return r.json().catch(function () { return { ok: false, zprava: 'Server neodpověděl (' + r.status + ').' }; }); }).then(function (j) {
        if (!j.ok) throw new Error(j.zprava || 'Uložení se nezdařilo.');
        var url = URL.createObjectURL(blob), v = $('#vysledek'), hlava, m;
        if (papir) {
          hlava = '<div class="ok"><b>List je připraven k tisku ✓</b><br>Předání zatím není zapsáno. Po podpisu u zákazníka naskenujte QR kód „Předávací QR“ vpravo dole na listu a potvrďte předání.</div>';
          m = '<p>Vytiskněte list na 1 stranu. Podepíše se na papíře a papírovou kopii necháte zákazníkovi – nemá-li e-mail, tiskárnu ani WhatsApp, je to jeho jediný doklad. QR kód pro vás je vpravo dole na listu.</p>';
        } else {
          hlava = '<div class="ok"><b>' + (j.nove_predani ? 'Předání zapsáno ✓' : 'Podepsaný list uložen ✓') + '</b><br>' + (j.predano ? 'Předáno ' + cz(j.predano) + (j.zaruka_do ? ', záruka do ' + cz(j.zaruka_do) : '') + '. ' : '') + (j.nove_predani ? 'Dveře jsou odepsané ze skladu. ' : '') + 'Podepsaný list je uložený v kartotéce.</div>';
          m = { odeslano: '<p>Kopie listu odešla zákazníkovi na ' + (j.email || 'e-mail') + '.</p>', zkusebni: '<p>Zkušební režim – e-mail se jen uložil na NAS.</p>',
            chyba: '<div class="warn">E-mail zákazníkovi se nepodařilo odeslat – stáhněte PDF a pošlete mu ho jinak.</div>', nechtel: '<p>Kopie e-mailem se neposílala.</p>',
            bez_emailu: '<div class="warn">Zákazník nemá e-mail – stáhněte PDF a pošlete mu ho jinak (WhatsApp), nebo list vytiskněte.</div>' }[j.mail] || '';
        }
        $('#formular').hidden = true; v.hidden = false;
        v.innerHTML = hlava + '<div class="box">' + m +
          '<a class="btn" href="' + url + '" target="_blank">' + (papir ? '🖨 Zobrazit a vytisknout list (PDF)' : '📄 Zobrazit podepsaný list (PDF)') + '</a><a class="btn sec" href="' + url + '" download="predavaci-list-' + (window.__D.obj_cislo || '') + '.pdf">⬇ Stáhnout PDF</a>' +
          '<a class="btn sec" href="predani.php?t=' + encodeURIComponent(window.__T) + '">Na stránku dveří</a></div>';
        window.scrollTo(0, 0);
      });
    }).catch(function (err) { btn.disabled = false; btn.textContent = txt0; chyba('Nepodařilo se: ' + (err && err.message ? err.message : err)); });
  });
})();
</script>
<?php endif; ?></body></html>
