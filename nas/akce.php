<?php
// Akce a slevy (26. 11. 2026): nastavení akční slevy na dveře do bytu + datum „platí do".
// Dvě role jednoho souboru:
//   akce.php?api=1  → veřejné JSON API (bez přihlášení, s CORS) pro web (formulare.dvere.app): aktivni / nadpis / text / plati_do → web z toho dělá štítek „Akce platí do…".
//   akce.php        → editor jen pro admina (kartoteka.dvere.app, menu Správa). Config v /data/akce.json.
// Patří na NAS do /docker/formulare/app/akce.php.
require __DIR__ . '/lib/start.php';
$DATA_DIR = ($DATA ?? '') !== '' ? $DATA : '/data';
$AKCE_FILE = $DATA_DIR . '/akce.json';
// modely na webu (dvere-do-bytu.html) a jejich výchozí ceny [název, akční, běžná]; běžná 0 = bez přeškrtnutí
function akce_modely(): array {
  return [
    'premium60'  => ['PREMIUM 60', 38890, 42500],
    'extreme54'  => ['EXTREME 54', 31890, 32890],
    'magnum56'   => ['MAGNUM 56', 36990, 0],
    'securido65' => ['SECURIDO 65', 35890, 37890],
  ];
}
function akce_nacti(string $f): array {
  $d = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  $cin = is_array($d['ceny'] ?? null) ? $d['ceny'] : [];
  $ceny = [];
  foreach (akce_modely() as $k => $mm) {
    $ceny[$k] = [
      'akce'  => (int)($cin[$k]['akce']  ?? $mm[1]),
      'bezna' => (int)($cin[$k]['bezna'] ?? $mm[2]),
    ];
  }
  return [
    'aktivni'  => !empty($d['aktivni']),
    'nadpis'   => (string)($d['nadpis'] ?? 'Akční ceny dveří do bytu'),
    'text'     => (string)($d['text'] ?? 'Akce platí do'),
    'plati_do' => (string)($d['plati_do'] ?? ''),
    'nudge_aktivni' => !empty($d['nudge_aktivni']),
    'nudge_castka'  => (int)($d['nudge_castka'] ?? 0),
    'nudge_text'    => (string)($d['nudge_text'] ?? ''),
    'ceny'     => $ceny,
  ];
}
function akce_plati(array $a): bool {
  if (!$a['aktivni'] || $a['plati_do'] === '') return false;
  $t = strtotime($a['plati_do'] . ' 23:59:59');
  return $t !== false && $t >= time();
}
$akce = akce_nacti($AKCE_FILE);

// --- Veřejné API (bez přihlášení, s CORS) ---
if (isset($_GET['api'])) {
  header('Content-Type: application/json; charset=utf-8');
  header('Access-Control-Allow-Origin: *');
  header('Cache-Control: public, max-age=300');
  $platne = akce_plati($akce);
  echo json_encode([
    'aktivni'  => $platne,
    'nadpis'   => $akce['nadpis'],
    'text'     => $akce['text'],
    'plati_do' => $platne ? $akce['plati_do'] : '',
    'ceny'     => $akce['ceny'],   // ceny modelů jsou nezávislé na termínu akce (web je tahá vždy)
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// --- Editor (jen kartotéka + admin) ---
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) { header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit; }
if (!uziv_admin()) { http_response_code(403); require_once __DIR__ . '/lib/menu.php'; echo '<!doctype html><meta charset="utf-8"><body style="font:16px system-ui;padding:24px">Tato stránka je jen pro administrátora.</body>'; exit; }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$ulozeno = false; $chyba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nov = [
    'aktivni'  => !empty($_POST['aktivni']),
    'nadpis'   => trim((string)($_POST['nadpis'] ?? '')),
    'text'     => trim((string)($_POST['text'] ?? '')),
    'plati_do' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['plati_do'] ?? '')) ? (string)$_POST['plati_do'] : '',
    'nudge_aktivni' => !empty($_POST['nudge_aktivni']),
    'nudge_castka'  => max(0, (int)preg_replace('/\D/', '', (string)($_POST['nudge_castka'] ?? '0'))),
    'nudge_text'    => trim((string)($_POST['nudge_text'] ?? '')),
    'ceny'     => [],
  ];
  foreach (akce_modely() as $k => $mm) {
    $nov['ceny'][$k] = [
      'akce'  => max(0, (int)preg_replace('/\D/', '', (string)($_POST['cena_' . $k . '_akce']  ?? '0'))),
      'bezna' => max(0, (int)preg_replace('/\D/', '', (string)($_POST['cena_' . $k . '_bezna'] ?? '0'))),
    ];
  }
  if (@file_put_contents($AKCE_FILE, json_encode($nov, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) $chyba = 'Nepodařilo se uložit soubor ' . $AKCE_FILE;
  else { $akce = akce_nacti($AKCE_FILE); $ulozeno = true; }
}
$platne = akce_plati($akce);
$verejne = 'https://' . (string)($cfg['web_host'] ?? 'formulare.dvere.app') . '/akce.php?api=1';
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><link rel="apple-touch-icon" href="kartoteka-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Kartotéka"><title>Akce a slevy – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
main{max-width:720px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:16px 18px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
h1{font-size:22px;margin:.2em 0}
label{display:block;margin:12px 0 4px;font-weight:600}
input[type=text],input[type=date]{width:100%;box-sizing:border-box;font:inherit;padding:9px 10px;border:1px solid #c9d1dc;border-radius:8px}
.row{display:flex;align-items:center;gap:8px;margin:8px 0}
.row input[type=checkbox]{width:20px;height:20px}
button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:11px 18px;font:inherit;font-weight:600;cursor:pointer;margin-top:14px}
.mut{color:#6b7787;font-size:13px}
.ok{background:#d9f2e0;color:#14653a;border-radius:8px;padding:10px 12px;margin-bottom:12px}
.err{background:#fde0e0;color:#8a1c1c;border-radius:8px;padding:10px 12px;margin-bottom:12px}
.nahled{margin-top:10px}
.stitek{display:inline-flex;align-items:center;gap:8px;background:#fff4e5;color:#8a4b00;border:1px solid #f0c98a;border-radius:999px;padding:7px 14px;font-weight:700}
.stitek.off{background:#eef1f5;color:#6b7787;border-color:#dce1e8}
code{background:#eef1f5;padding:2px 6px;border-radius:5px;font-size:13px;word-break:break-all}
.cm{margin-top:8px}
.cm-r{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px 10px;align-items:end;padding:10px 0;border-top:1px solid #eef1f5}
.cm-r:first-of-type{border-top:0}
.cm-nm{grid-column:1 / -1;font-weight:700;color:#0f2747;margin-bottom:-2px}
.cm label{margin:0 0 3px;font-size:12px;color:#6b7787;font-weight:600}
.cm-sl{grid-column:1 / -1;font-size:13px;color:#14653a;min-height:18px}
.cm-sl.warn{color:#b42318;font-weight:700}
@media (max-width:560px){.cm-r{grid-template-columns:1fr 1fr}.cm-nm,.cm-sl{grid-column:1 / -1}}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('akce'); ?><main>
  <div class="box">
    <h1>🏷️ Akce a slevy</h1>
    <p class="mut">Akční sleva na dveře do bytu a termín „platí do". Když je akce zapnutá a termín nevypršel, na webu se u dveří do bytu ukáže štítek s odpočtem termínu (tlak času).</p>
    <?php if ($ulozeno): ?><div class="ok">✓ Uloženo.</div><?php endif; ?>
    <?php if ($chyba): ?><div class="err"><?= $e($chyba) ?></div><?php endif; ?>
    <form method="post">
      <div class="row"><input type="checkbox" id="aktivni" name="aktivni" value="1"<?= $akce['aktivni'] ? ' checked' : '' ?>><label for="aktivni" style="margin:0">Akce zapnutá (zobrazovat štítek na webu)</label></div>
      <label for="nadpis">Nadpis akce</label>
      <input type="text" id="nadpis" name="nadpis" value="<?= $e($akce['nadpis']) ?>" placeholder="např. Akční ceny PREMIUM 60">
      <label for="text">Text před datem</label>
      <input type="text" id="text" name="text" value="<?= $e($akce['text']) ?>" placeholder="např. Akce platí do">
      <label for="plati_do">Platí do</label>
      <input type="date" id="plati_do" name="plati_do" value="<?= $e($akce['plati_do']) ?>">

      <hr style="border:0;border-top:1px solid #e3e8ef;margin:20px 0">
      <h2 style="font-size:17px;margin:0 0 4px">🕒 24h pobídka pro váhající zákazníky</h2>
      <p class="mut">Když zákazník do nabídky nereaguje, připomínka mu nabídne slevu navíc, pokud potvrdí zájem do 24 hodin. Propíše se do připomínkových e-mailů nabídek. Zákazník potvrdí jedním tlačítkem a v kartotéce na úvodu vyskočí „💰 MÁ ZÁJEM".</p>
      <div class="row"><input type="checkbox" id="nudge_aktivni" name="nudge_aktivni" value="1"<?= $akce['nudge_aktivni'] ? ' checked' : '' ?>><label for="nudge_aktivni" style="margin:0">Pobídka zapnutá (nabízet slevu v připomínkách)</label></div>
      <label for="nudge_castka">Sleva za rozhodnutí do 24 h (Kč)</label>
      <input type="text" inputmode="numeric" id="nudge_castka" name="nudge_castka" value="<?= $akce['nudge_castka'] > 0 ? $e($akce['nudge_castka']) : '' ?>" placeholder="např. 1000">
      <label for="nudge_text">Doplňující věta (nepovinné)</label>
      <input type="text" id="nudge_text" name="nudge_text" value="<?= $e($akce['nudge_text']) ?>" placeholder="např. Platí jen pro objednávku do 24 hodin.">

      <hr style="border:0;border-top:1px solid #e3e8ef;margin:20px 0">
      <h2 style="font-size:17px;margin:0 0 4px">💰 Ceny modelů na webu</h2>
      <p class="mut">Akční cena a běžná (přeškrtnutá) cena na stránce „Dveře do bytu" – cenové pruhy i srovnávací tabulka, a ceny v nových poptávkách/objednávkách z webu. <b>Běžná = cena bez slevy</b> (ukazuje, kam až můžeš jít). Běžná 0 = bez přeškrtnutí. Už vystavené nabídky se nemění.</p>
      <div class="cm">
        <?php foreach (akce_modely() as $k => $mm): $c = $akce['ceny'][$k]; ?>
        <div class="cm-r" data-model="<?= $e($k) ?>">
          <div class="cm-nm"><?= $e($mm[0]) ?></div>
          <div><label for="cena_<?= $e($k) ?>_akce">Akční cena (Kč)</label><input type="text" inputmode="numeric" id="cena_<?= $e($k) ?>_akce" name="cena_<?= $e($k) ?>_akce" class="c-akce" value="<?= $c['akce'] > 0 ? $e($c['akce']) : '' ?>" placeholder="0"></div>
          <div><label for="cena_<?= $e($k) ?>_bezna">Běžná cena bez slevy (Kč)</label><input type="text" inputmode="numeric" id="cena_<?= $e($k) ?>_bezna" name="cena_<?= $e($k) ?>_bezna" class="c-bezna" value="<?= $c['bezna'] > 0 ? $e($c['bezna']) : '' ?>" placeholder="0 = bez přeškrtnutí"></div>
          <div class="cm-sl"></div>
        </div>
        <?php endforeach; ?>
      </div>

      <button type="submit">Uložit</button>
    </form>
    <script>
    (function(){
      function cislo(s){ return parseInt(String(s).replace(/\D/g,''),10) || 0; }
      function fmt(n){ return String(n).replace(/\B(?=(\d{3})+(?!\d))/g,' '); }
      function prepocti(r){
        var a = cislo(r.querySelector('.c-akce').value), b = cislo(r.querySelector('.c-bezna').value), sl = r.querySelector('.cm-sl');
        sl.className = 'cm-sl';
        if (!a) { sl.textContent = ''; return; }
        if (!b) { sl.textContent = 'Běžná cena nezadaná – bez přeškrtnutí, sleva se nepočítá.'; return; }
        if (a >= b) { sl.className = 'cm-sl warn'; sl.textContent = '⚠ Akční cena je vyšší nebo stejná jako běžná (' + fmt(b) + ' Kč) – to by nebyla sleva. Dej akční níž.'; return; }
        var usetri = b - a, pct = Math.round(usetri / b * 100);
        sl.textContent = 'Sleva ' + fmt(usetri) + ' Kč (' + pct + ' %) · zákazník ušetří oproti běžné ceně ' + fmt(b) + ' Kč.';
      }
      [].forEach.call(document.querySelectorAll('.cm-r'), function(r){
        [].forEach.call(r.querySelectorAll('input'), function(i){ i.addEventListener('input', function(){ prepocti(r); }); });
        prepocti(r);
      });
    })();
    </script>
  </div>
  <div class="box">
    <h2 style="margin-top:0;font-size:17px">Náhled štítku</h2>
    <div class="nahled">
      <?php if ($platne): $dt = date('j. n. Y', strtotime($akce['plati_do'])); ?>
        <span class="stitek">⏳ <?= $e($akce['text']) ?> <b><?= $e($dt) ?></b></span>
      <?php else: ?>
        <span class="stitek off">Akce vypnutá nebo po termínu – na webu se štítek nezobrazuje.</span>
      <?php endif; ?>
    </div>
    <p class="mut" style="margin-top:14px">Web si akci načítá z veřejné adresy:<br><code><?= $e($verejne) ?></code></p>
  </div>
</main></body></html>
