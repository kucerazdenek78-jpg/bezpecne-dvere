<?php
// Přehledy prodejů (26. 11. 2026): měsíční přehled objednávek dveří (počet, tržby, odhad zisku) + graf. JEN PRO ADMINA.
// Zdroj: zadosti typ='objednavka_interni' (servisy oken jsou v samostatných so_* tabulkách, sem nepatří). Tržba = data_json.cena_celkem, datum = vytvoreno, mimo storno.
// Zisk = průměrná marže na dveřích (výchozí 40 %, přepíše ?marze=NN). Patří na NAS do /docker/formulare/app/prehledy.php; v menu (Správa) ho přidává zavaděč lib/menu.php.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) { header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit; }
if (!uziv_admin()) { http_response_code(403); require_once __DIR__ . '/lib/menu.php'; echo '<!doctype html><meta charset="utf-8"><body style="font:16px system-ui;padding:24px">Tato stránka je jen pro administrátora.</body>'; exit; }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$pdo = db();
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => number_format(round((float)$n), 0, ',', ' ') . ' Kč';

// marže (zisk) – výchozí 40 %, lze přepnout ?marze=35
$MARZE = isset($_GET['marze']) ? max(0, min(100, (int)$_GET['marze'])) : 40;
$MKOEF = $MARZE / 100;

$KAT = ['byt' => 'Byt', 'dum' => 'Dům', 'interier' => 'Interiér'];
$mes = [];            // 'YYYY-MM' => ['pocet','trzby']
$kat = [];            // kategorie => ['pocet','trzby']
$celkPocet = 0; $celkTrzby = 0.0; $bezCeny = 0; $prvni = null; $posledni = null;

foreach ($pdo->query("SELECT stav, vytvoreno, data_json FROM zadosti WHERE typ='objednavka_interni'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
  if ((string)($r['stav'] ?? '') === 'storno') continue;
  $t = strtotime((string)$r['vytvoreno']); if (!$t) continue;
  $d = json_decode((string)$r['data_json'], true) ?: [];
  $cena = (float)($d['cena_celkem'] ?? 0);
  if ($cena <= 0) $bezCeny++;
  $m = date('Y-m', $t);
  if (!isset($mes[$m])) $mes[$m] = ['pocet' => 0, 'trzby' => 0.0];
  $mes[$m]['pocet']++; $mes[$m]['trzby'] += $cena;
  $kkey = (string)($d['kategorie'] ?? '');
  if (!isset($KAT[$kkey])) {   // bytové objednávky z objednavka-nova.html kategorii neukládají – poznáme je podle modelu dveří do bytu
    $mk = strtolower((string)($d['model_kod'] ?? ''));
    $mn = (string)($d['model'] ?? '');
    $kkey = (in_array($mk, ['premium60', 'extreme54', 'magnum56', 'securido65'], true) || preg_match('/premium\s*60|extreme\s*54|magnum\s*56|securido\s*65/iu', $mn)) ? 'byt' : 'jine';
  }
  if (!isset($kat[$kkey])) $kat[$kkey] = ['pocet' => 0, 'trzby' => 0.0];
  $kat[$kkey]['pocet']++; $kat[$kkey]['trzby'] += $cena;
  $celkPocet++; $celkTrzby += $cena;
  if ($prvni === null || $m < $prvni) $prvni = $m; if ($posledni === null || $m > $posledni) $posledni = $m;
}
krsort($mes);                       // tabulka: nejnovější nahoře
$mesChrono = array_reverse($mes, true);   // graf: chronologicky
// graf jen posledních 24 měsíců
$grafData = array_slice($mesChrono, -24, 24, true);
$maxTrzby = 0; foreach ($grafData as $v) $maxTrzby = max($maxTrzby, $v['trzby']);

// --- SVG sloupcový graf tržeb ---
function graf_svg(array $data, float $max, callable $kc): string {
  if (!$data || $max <= 0) return '<p class="mut">Zatím nejsou žádná data pro graf.</p>';
  $n = count($data); $W = 900; $H = 300; $padL = 70; $padB = 46; $padT = 14; $padR = 10;
  $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
  $bw = $plotW / $n; $barW = min(46, $bw * 0.62);
  $s = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" width="100%" preserveAspectRatio="xMidYMid meet" font-family="system-ui,Arial,sans-serif" role="img" aria-label="Měsíční tržby">';
  // osa Y: 4 vodorovné linky + popisky
  for ($i = 0; $i <= 4; $i++) {
    $y = $padT + $plotH - $plotH * $i / 4; $val = $max * $i / 4;
    $s .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($W - $padR) . '" y2="' . round($y, 1) . '" stroke="#e4e8ee"/>';
    $s .= '<text x="' . ($padL - 8) . '" y="' . round($y + 4, 1) . '" text-anchor="end" font-size="11" fill="#6b7787">' . $kc($val) . '</text>';
  }
  $i = 0;
  foreach ($data as $m => $v) {
    $x = $padL + $bw * $i + ($bw - $barW) / 2;
    $bh = $plotH * ($v['trzby'] / $max); $y = $padT + $plotH - $bh;
    $lbl = substr($m, 5, 2) . '/' . substr($m, 2, 2);
    $s .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round($bh, 1) . '" rx="3" fill="#1f5fbf"><title>' . $lbl . ': ' . $kc($v['trzby']) . ' (' . $v['pocet'] . ' obj.)</title></rect>';
    $s .= '<text x="' . round($x + $barW / 2, 1) . '" y="' . ($H - $padB + 16) . '" text-anchor="middle" font-size="11" fill="#5a6676" transform="rotate(-60 ' . round($x + $barW / 2, 1) . ' ' . ($H - $padB + 16) . ')">' . $lbl . '</text>';
    $i++;
  }
  $s .= '</svg>';
  return $s;
}
$svg = graf_svg($grafData, (float)$maxTrzby, $kc);
$prumObj = $celkPocet ? $celkTrzby / $celkPocet : 0;
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><link rel="apple-touch-icon" href="kartoteka-192.png"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Kartotéka"><title>Přehledy prodejů – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
main{max-width:1100px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
h1{font-size:22px;margin:.2em 0}h2{font-size:17px;margin:0 0 .5em}
table{border-collapse:collapse;width:100%}th,td{padding:7px 9px;border-bottom:1px solid #e4e8ee;text-align:right;vertical-align:top}th{font-size:13px;color:#5a6676}th:first-child,td:first-child{text-align:left}
tr.sum td{font-weight:700;border-top:2px solid #cfd6e0;background:#f7f9fc}
.mut{color:#6b7787;font-size:13px}
.kpis{display:flex;flex-wrap:wrap;gap:12px}.kpi{flex:1 1 150px;background:#f7f9fc;border:1px solid #e4e8ee;border-radius:10px;padding:10px 12px}.kpi b{display:block;font-size:22px;color:#14233b}.kpi span{font-size:12px;color:#6b7787}
.marze{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap}.marze a{display:inline-block;padding:4px 10px;border-radius:999px;background:#e6ebf3;color:#1b2430;text-decoration:none;font-size:13px}.marze a.on{background:#1f5fbf;color:#fff}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('prehledy'); ?><main>
  <div class="box">
    <h1>📊 Přehledy prodejů</h1>
    <p class="mut">Objednávky dveří (bez storen). Tržba = cena objednávky, odhad zisku = <b><?= $MARZE ?> %</b> z tržeb (průměrná marže na dveřích). Období: <?= $prvni ? $e(date('n/Y', strtotime($prvni . '-01'))) . ' – ' . $e(date('n/Y', strtotime($posledni . '-01'))) : '–' ?>.</p>
    <div class="kpis">
      <div class="kpi"><b><?= $celkPocet ?></b><span>objednávek celkem</span></div>
      <div class="kpi"><b><?= $kc($celkTrzby) ?></b><span>tržby celkem</span></div>
      <div class="kpi"><b><?= $kc($celkTrzby * $MKOEF) ?></b><span>odhad zisku (<?= $MARZE ?> %)</span></div>
      <div class="kpi"><b><?= $kc($prumObj) ?></b><span>průměr na objednávku</span></div>
    </div>
    <p class="mut" style="margin:.7em 0 0">Marže zisku: <span class="marze"><?php foreach ([30,35,40,45,50] as $mm): ?><a class="<?= $mm === $MARZE ? 'on' : '' ?>" href="prehledy.php?marze=<?= $mm ?>"><?= $mm ?> %</a><?php endforeach; ?></span><?= $bezCeny ? ' · <b>' . $bezCeny . '</b> objednávek bez vyplněné ceny (počítají se do počtu, ne do tržeb)' : '' ?></p>
  </div>

  <div class="box">
    <h2>Tržby po měsících (posledních <?= count($grafData) ?>)</h2>
    <?= $svg ?>
  </div>

  <div class="box">
    <h2>Měsíční přehled</h2>
    <table><tr><th>Měsíc</th><th>Objednávek</th><th>Tržby</th><th>Odhad zisku (<?= $MARZE ?> %)</th><th>Ø na objednávku</th></tr>
    <?php foreach ($mes as $m => $v): ?>
      <tr><td><?= $e(date('n/Y', strtotime($m . '-01'))) ?></td><td><?= $v['pocet'] ?></td><td><?= $kc($v['trzby']) ?></td><td><?= $kc($v['trzby'] * $MKOEF) ?></td><td><?= $kc($v['pocet'] ? $v['trzby'] / $v['pocet'] : 0) ?></td></tr>
    <?php endforeach; ?>
      <tr class="sum"><td>Celkem</td><td><?= $celkPocet ?></td><td><?= $kc($celkTrzby) ?></td><td><?= $kc($celkTrzby * $MKOEF) ?></td><td><?= $kc($prumObj) ?></td></tr>
    </table>
  </div>

  <div class="box">
    <h2>Podle kategorie</h2>
    <table><tr><th>Kategorie</th><th>Objednávek</th><th>Tržby</th><th>Odhad zisku (<?= $MARZE ?> %)</th></tr>
    <?php $KATN = $KAT + ['jine' => 'Jiné / neuvedeno']; foreach ($KATN as $kk => $kn): if (empty($kat[$kk])) continue; ?>
      <tr><td><?= $e($kn) ?></td><td><?= $kat[$kk]['pocet'] ?></td><td><?= $kc($kat[$kk]['trzby']) ?></td><td><?= $kc($kat[$kk]['trzby'] * $MKOEF) ?></td></tr>
    <?php endforeach; ?>
    </table>
    <p class="mut" style="margin:.6em 0 0">Zisk je orientační odhad (<?= $MARZE ?> % z tržeb). Servis oken se sem nepočítá – je ve vlastním modulu.</p>
  </div>
</main></body></html>
