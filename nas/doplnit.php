<?php
// Veřejná stránka pro zákazníka: doplnění poptávky (fotky z mobilu + rozměry / poznámka). Odkaz posílá prodejce z detailu poptávky
// v kartotéce (lib/doplneni.php). Přístup jen s podepsaným odkazem ?t=<id>-<16 hex>; nic jiného z kartotéky zákazník nevidí.
// Fotky se zmenší v prohlížeči (1600 px) a uloží k poptávce do Fotek (lib/foto.php), text do data_json (doplneni_odpovedi) a historie,
// firma (příjemci poptávek z config.php) dostane e-mail. Ochrana: limit dotazů z jedné adresy, max. počet fotek u záznamu (FOTO_MAX_KUSU).
require __DIR__ . '/lib/start.php';
require_once __DIR__ . '/lib/doplneni.php'; require_once __DIR__ . '/lib/foto.php'; require_once __DIR__ . '/lib/zamek.php';
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$pdo = db(); zamek_init($pdo);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$t = (string)($_GET['t'] ?? $_POST['t'] ?? ''); $id = dp_id_z_tokenu($t);
$r = $id ? dp_data($pdo, $id) : [];
if (!$r || $r['typ'] !== 'poptavka') { http_response_code(404); exit('Odkaz neplatí. Ozvěte se nám prosím na +420 725 559 235.'); }
$d = json_decode((string)$r['data_json'], true) ?: []; $cis = dp_cislo($r, $d);
$ip = klient_ip();
function dp_limit(PDO $pdo, string $ip): bool {
  $s = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='doplneni' AND cas>?"); $s->execute([$ip, time() - 3600]);
  if ((int)$s->fetchColumn() >= 120) return false;
  $pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'doplneni', ?)")->execute([$ip, time()]); return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!dp_limit($pdo, $ip)) foto_json(false, 'Příliš mnoho pokusů, zkuste to prosím za hodinu.');
  $akce = (string)($_POST['akce'] ?? '');
  if ($akce === 'foto') {   // jedna fotka (JSON odpověď)
    if (count(foto_seznam($id)) >= FOTO_MAX_KUSU) foto_json(false, 'Fotek je už dost, děkujeme.');
    $f = $_FILES['foto'] ?? null; $nh = $_FILES['nahled'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !foto_jpeg_ok($f['tmp_name']) || !$nh || $nh['error'] !== UPLOAD_ERR_OK || !foto_jpeg_ok($nh['tmp_name'])) foto_json(false, 'Fotku se nepodařilo zpracovat.');
    $dir = foto_dir($id); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) foto_json(false, 'Fotku se nepodařilo uložit.');
    $jm = bin2hex(random_bytes(8));
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $jm . '.jpg') || !move_uploaded_file($nh['tmp_name'], $dir . '/' . $jm . '_n.jpg')) foto_json(false, 'Fotku se nepodařilo uložit.');
    foto_json(true);
  }
  if ($akce === 'hotovo') {   // text + souhrn, upozornění firmě
    $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 3000); $fotek = max(0, min(100, (int)($_POST['fotek'] ?? 0)));
    if ($text === '' && !$fotek) { header('Location: doplnit.php?t=' . rawurlencode($t) . '&prazdne=1'); exit; }
    $r = dp_data($pdo, $id); $d = json_decode((string)$r['data_json'], true) ?: [];
    $d['doplneni_odpovedi'] = array_slice(array_merge((array)($d['doplneni_odpovedi'] ?? []), [['cas' => date('Y-m-d H:i:s'), 'text' => $text, 'fotek' => $fotek]]), -20);
    dp_ulozit($pdo, $id, $d);
    hist_pis($pdo, $id, 'zákazník doplnil poptávku', ($fotek ? 'fotek: ' . $fotek . '. ' : '') . $text);
    require_once __DIR__ . '/lib/posta.php';
    $komu = array_values(array_filter((array)($cfg['prijemci']['poptavka']['to'] ?? []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    $jm = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''));
    if ($komu) posli_mail($cfg, $DATA, $komu, [], (string)($d['email'] ?? $cfg['from']), 'Zákazník doplnil poptávku ' . ($cis !== '' ? $cis : '#' . $id) . ($jm !== '' ? ' – ' . $jm : ''),
      "Zákazník doplnil poptávku " . ($cis !== '' ? $cis : '#' . $id) . ($jm !== '' ? " ($jm)" : '') . ".\n\n" . ($fotek ? "Nahrané fotky: $fotek (jsou u poptávky ve Fotkách)\n" : '') . ($text !== '' ? "Text od zákazníka:\n$text\n" : '')
      . "\nOtevřít v kartotéce: " . rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/') . '/kartoteka.php?id=' . $id . "\n");
    header('Location: doplnit.php?t=' . rawurlencode($t) . '&dekujeme=1'); exit;
  }
  http_response_code(400); exit;
}
$pozad = (array)($d['doplneni'] ?? []); $posl = $pozad ? $pozad[count($pozad) - 1] : null;
$seznam = $posl ? dp_seznam((array)($posl['co'] ?? []), (string)($posl['text'] ?? '')) : [];
$chceFoto = !$posl || array_intersect((array)($posl['co'] ?? []), DP_FOTO);
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Doplnění poptávky – Bezpečné dveře</title><style>
body{margin:0;background:#f3f5f8;color:#16233a;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.w{max-width:560px;margin:0 auto;padding:16px}.hl{background:#123d27;color:#fff;padding:14px 16px;font-weight:700;font-size:18px}
.box{background:#fff;border-radius:12px;padding:16px;margin:0 0 14px;box-shadow:0 1px 3px #0002}h1{font-size:21px;margin:0 0 8px}
ul{margin:.3rem 0 .2rem;padding-left:1.2rem}textarea{width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:10px}
.b{display:block;width:100%;box-sizing:border-box;border:0;border-radius:12px;padding:15px;font:700 17px system-ui,Arial,sans-serif;cursor:pointer;text-align:center}
.b1{background:#16a34a;color:#fff}.b2{background:#e8f0fb;color:#1d5fb8;margin-bottom:8px}.mut{color:#64708a;font-size:14px}
.nh{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0}.nh img{width:96px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #d8dde6}
.ok{background:#d9f2e0;color:#14653a;border-radius:10px;padding:12px 14px}.err{background:#fde2e2;color:#9b1c1c;border-radius:10px;padding:10px 14px;margin:0 0 10px}
</style></head><body><div class="hl">Bezpečné dveře · KK SYSTEM</div><div class="w">
<?php if (isset($_GET['dekujeme'])): ?>
  <div class="box"><h1>Děkujeme!</h1><div class="ok">Doplnění poptávky<?= $cis !== '' ? ' č. ' . $e($cis) : '' ?> jsme dostali. Ozveme se Vám s nabídkou.</div>
  <p class="mut" style="margin-top:12px">Kdybyste chtěli poslat ještě něco, můžete stránku otevřít znovu ze stejného odkazu. Telefon: +420 725 559 235</p></div>
<?php else: ?>
  <div class="box"><h1>Doplnění poptávky<?= $cis !== '' ? ' č. ' . $e($cis) : '' ?></h1>
    <?php if (isset($_GET['prazdne'])): ?><div class="err">Přidejte prosím fotku nebo napište text.</div><?php endif; ?>
    <?php if ($seznam): ?><p style="margin:0">Abychom Vám mohli připravit přesnou nabídku, potřebujeme ještě:</p><ul><?php foreach ($seznam as $s): ?><li><?= $e($s) ?></li><?php endforeach; ?></ul>
    <?php else: ?><p style="margin:0">Pošlete nám prosím fotky dveří a doplňující údaje k Vaší poptávce.</p><?php endif; ?></div>
  <form class="box" method="post" id="f"><input type="hidden" name="t" value="<?= $e($t) ?>"><input type="hidden" name="akce" value="hotovo"><input type="hidden" name="fotek" id="fotek" value="0">
    <p style="margin:0 0 8px;font-weight:700">Fotky<?= $chceFoto ? '' : ' (nepovinné)' ?></p>
    <input type="file" id="fFot" accept="image/*" capture="environment" style="display:none"><input type="file" id="fGal" accept="image/*" multiple style="display:none">
    <button type="button" class="b b2" id="bFot">📷 Vyfotit</button><button type="button" class="b b2" id="bGal">🖼️ Vybrat z galerie</button>
    <div class="nh" id="nh"></div><div class="mut" id="stav"></div>
    <p style="margin:14px 0 6px;font-weight:700">Rozměry, poznámka</p>
    <textarea name="text" rows="4" placeholder="Např. zárubeň 90 × 197 cm, zeď 15 cm, 3. patro bez výtahu…"></textarea>
    <p style="margin:14px 0 0"><button class="b b1" id="bOk">Odeslat</button></p>
    <p class="mut">Fotky uvidí jen naši prodejci a použijí je pouze k přípravě nabídky.</p></form>
  <script>
  (function () {
    var T = <?= json_encode($t) ?>, nh = document.getElementById('nh'), st = document.getElementById('stav'), fot = document.getElementById('fotek'), ok = document.getElementById('bOk'), bezi = 0, hotovo = 0;
    document.getElementById('bFot').onclick = function () { document.getElementById('fFot').click(); };
    document.getElementById('bGal').onclick = function () { document.getElementById('fGal').click(); };
    function zmensi(s, max, q) {
      var nacti = window.createImageBitmap ? createImageBitmap(s, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(s); })
        : new Promise(function (a, b) { var i = new Image(); i.onload = function () { a(i); }; i.onerror = b; i.src = URL.createObjectURL(s); });
      return nacti.then(function (bm) { var k = Math.min(1, max / Math.max(bm.width, bm.height)), c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(bm.width * k)); c.height = Math.max(1, Math.round(bm.height * k)); c.getContext('2d').drawImage(bm, 0, 0, c.width, c.height);
        return new Promise(function (a, b) { c.toBlob(function (x) { x ? a(x) : b(); }, 'image/jpeg', q); }); });
    }
    function obnov() { st.textContent = bezi ? 'Nahrávám fotky… (' + hotovo + ' hotovo)' : (hotovo ? 'Nahráno fotek: ' + hotovo : ''); ok.disabled = bezi > 0; fot.value = hotovo; }
    function nahraj(soubory) {
      soubory = [].slice.call(soubory || []); if (!soubory.length) return;
      var fronta = Promise.resolve();
      soubory.forEach(function (s) { bezi++; obnov(); fronta = fronta.then(function () {
        return Promise.all([zmensi(s, 1600, 0.82), zmensi(s, 320, 0.75)]).then(function (b) {
          var fd = new FormData(); fd.append('t', T); fd.append('akce', 'foto'); fd.append('foto', b[0], 'foto.jpg'); fd.append('nahled', b[1], 'nahled.jpg');
          return fetch('doplnit.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) throw new Error(j.zprava || 'chyba'); hotovo++; var im = document.createElement('img'); im.src = URL.createObjectURL(b[1]); nh.appendChild(im); });
        }).catch(function (x) { alert('Fotku se nepodařilo nahrát: ' + (x && x.message || 'chyba') + '. Zkuste to prosím znovu.'); }).then(function () { bezi--; obnov(); });
      }); });
    }
    document.getElementById('fFot').onchange = function () { nahraj(this.files); this.value = ''; };
    document.getElementById('fGal').onchange = function () { nahraj(this.files); this.value = ''; };
  })();
  </script>
<?php endif; ?></div></body></html>
