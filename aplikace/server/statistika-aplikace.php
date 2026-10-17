<?php
// Přehled využití zákaznické aplikace (bezpecne-dvere.cz/aplikace/) – anonymní denní součty z tabulky app_statistika,
// kterou plní aplikace-api.php (akce „udalost“). Patří do /docker/formulare/app/ vedle aplikace-api.php.
// Přístup: jen přes https://kartoteka.dvere.app/statistika-aplikace.php (Cloudflare Access) nebo přímo z místní sítě.
require __DIR__ . '/lib/start.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function je_lan($ip) { return (bool)filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE); }
$pres_tunel = isset($_SERVER['HTTP_CF_CONNECTING_IP']);
$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$access = $host === 'kartoteka.dvere.app' && !empty($_SERVER['HTTP_CF_ACCESS_AUTHENTICATED_USER_EMAIL']);
if ($pres_tunel ? !$access : !je_lan((string)($_SERVER['REMOTE_ADDR'] ?? ''))) { http_response_code(404); exit('Nenalezeno'); }

$pdo = db(); $drv = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
try { $pdo->query("SELECT 1 FROM app_statistika LIMIT 1"); $ma = true; } catch (Throwable $e) { $ma = false; }
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$NAZVY = ['navsteva_nova' => 'Nová zařízení (poprvé)', 'navsteva' => 'Návrat (další den)', 'foto' => 'Nahraná fotka dveří', 'vizualizace' => 'Vizualizace (dveře na fotce)', 'vyber' => 'Další vyzkoušené modely/odstíny', 'odeslano' => 'Odeslané poptávky a požadavky'];
$KAT = ['byt' => 'Dveře do bytu', 'domu' => 'Dveře do domu', 'int' => 'Interiérové dveře', 'aplikace-vizualizace' => 'Poptávka z vizualizace', 'aplikace-servis' => 'Servis / závada', 'aplikace-prohlidka' => 'Roční prohlídka'];
$prodejce = isset($_GET['prodejce']) ? 1 : 0; // výchozí: jen zákazníci; ?prodejce = předvádění prodejcem
$dnes = date('Y-m-d'); $od30 = date('Y-m-d', strtotime('-29 days')); $od7 = date('Y-m-d', strtotime('-6 days'));

$soucty = []; $dny = []; $modely = []; $kat = []; $prvni = '';
if ($ma) {
  $st = $pdo->prepare("SELECT udalost, SUM(pocet) c, SUM(CASE WHEN den >= ? THEN pocet ELSE 0 END) d30, SUM(CASE WHEN den >= ? THEN pocet ELSE 0 END) d7, SUM(CASE WHEN den = ? THEN pocet ELSE 0 END) d1 FROM app_statistika WHERE prodejce = ? GROUP BY udalost");
  $st->execute([$od30, $od7, $dnes, $prodejce]);
  foreach ($st as $r) $soucty[$r['udalost']] = $r;
  $st = $pdo->prepare("SELECT den, udalost, SUM(pocet) c FROM app_statistika WHERE prodejce = ? AND den >= ? GROUP BY den, udalost ORDER BY den DESC");
  $st->execute([$prodejce, $od30]);
  foreach ($st as $r) $dny[(string)$r['den']][$r['udalost']] = (int)$r['c'];
  $st = $pdo->prepare("SELECT kat, model, SUM(pocet) c FROM app_statistika WHERE prodejce = ? AND udalost IN ('vizualizace','vyber') AND model <> '' GROUP BY kat, model ORDER BY c DESC LIMIT 40");
  $st->execute([$prodejce]); $modely = $st->fetchAll(PDO::FETCH_ASSOC);
  $st = $pdo->prepare("SELECT udalost, kat, SUM(pocet) c FROM app_statistika WHERE prodejce = ? AND udalost IN ('foto','vizualizace','odeslano') GROUP BY udalost, kat ORDER BY c DESC");
  $st->execute([$prodejce]); $kat = $st->fetchAll(PDO::FETCH_ASSOC);
  $prvni = (string)$pdo->query("SELECT MIN(den) FROM app_statistika")->fetchColumn();
}
$n = function ($u, $k) use ($soucty) { return (int)($soucty[$u][$k] ?? 0); };
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Využití aplikace – kartotéka</title>
<style>
:root{--navy:#123d27;--line:#dfe6e1;--muted:#5b6b62}
body{margin:0;font:15px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f7f5;color:#1d2b23}
main{max-width:980px;margin:0 auto;padding:16px}
h1{font-size:1.4rem;color:var(--navy);margin:.2rem 0 .2rem} h2{font-size:1.05rem;color:var(--navy);margin:1.6rem 0 .5rem}
.sub{color:var(--muted);font-size:.9rem;margin:0 0 1rem} .sub a{color:#c2410c}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.6rem}
.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:.8rem .9rem}
.card b{display:block;font-size:1.7rem;color:var(--navy);line-height:1.1;font-variant-numeric:tabular-nums}
.card span{font-size:.82rem;color:var(--muted)} .card small{display:block;font-size:.78rem;color:var(--muted);margin-top:.3rem}
.tw{overflow-x:auto;background:#fff;border:1px solid var(--line);border-radius:12px}
table{border-collapse:collapse;width:100%;font-variant-numeric:tabular-nums}
th,td{padding:.45rem .7rem;border-bottom:1px solid var(--line);text-align:right;white-space:nowrap} th:first-child,td:first-child{text-align:left}
th{font-size:.78rem;color:var(--muted);font-weight:600;background:#f8faf9} tr:last-child td{border-bottom:0}
.note{font-size:.82rem;color:var(--muted);margin-top:1.4rem}
</style></head><body><main>
<h1>Využití aplikace</h1>
<p class="sub"><?= $prodejce ? 'Předvádění prodejcem (režim prodejce)' : 'Zákazníci (bez předvádění prodejcem)' ?> · <a href="?<?= $prodejce ? '' : 'prodejce' ?>"><?= $prodejce ? 'zobrazit zákazníky' : 'zobrazit předvádění prodejcem' ?></a><?= $prvni ? ' · měří se od ' . $h(date('j. n. Y', strtotime($prvni))) : '' ?></p>
<?php if (!$ma): ?><p>Zatím nejsou žádná data – tabulka vznikne s první událostí z aplikace.</p><?php else: ?>
<div class="cards">
<?php foreach (['navsteva_nova', 'foto', 'vizualizace', 'odeslano'] as $u): ?>
  <div class="card"><span><?= $h($NAZVY[$u]) ?></span><b><?= $n($u, 'c') ?></b><small>30 dní: <?= $n($u, 'd30') ?> · 7 dní: <?= $n($u, 'd7') ?> · dnes: <?= $n($u, 'd1') ?></small></div>
<?php endforeach; ?>
</div>

<h2>Po dnech (posledních 30 dní)</h2>
<div class="tw"><table><tr><th>Den</th><th>Nová zařízení</th><th>Návraty</th><th>Fotky</th><th>Vizualizace</th><th>Další modely</th><th>Odesláno</th></tr>
<?php if (!$dny): ?><tr><td colspan="7">Žádná data.</td></tr><?php endif; foreach ($dny as $d => $r): ?>
<tr><td><?= $h(date('j. n. Y', strtotime($d))) ?></td><?php foreach (['navsteva_nova', 'navsteva', 'foto', 'vizualizace', 'vyber', 'odeslano'] as $u): ?><td><?= (int)($r[$u] ?? 0) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?></table></div>

<h2>Podle druhu dveří (celkem)</h2>
<div class="tw"><table><tr><th>Druh</th><th>Událost</th><th>Počet</th></tr>
<?php if (!$kat): ?><tr><td colspan="3">Žádná data.</td></tr><?php endif; foreach ($kat as $r): ?>
<tr><td><?= $h($KAT[$r['kat']] ?? ($r['kat'] ?: '–')) ?></td><td><?= $h($NAZVY[$r['udalost']] ?? $r['udalost']) ?></td><td><?= (int)$r['c'] ?></td></tr>
<?php endforeach; ?></table></div>

<h2>Nejzkoušenější modely a odstíny (celkem)</h2>
<div class="tw"><table><tr><th>Model / odstín</th><th>Druh</th><th>Vyzkoušeno</th></tr>
<?php if (!$modely): ?><tr><td colspan="3">Žádná data.</td></tr><?php endif; foreach ($modely as $r): ?>
<tr><td><?= $h($r['model']) ?></td><td><?= $h($KAT[$r['kat']] ?? $r['kat']) ?></td><td><?= (int)$r['c'] ?></td></tr>
<?php endforeach; ?></table></div>
<?php endif; ?>
<p class="note">Anonymní součty: aplikace posílá jen druh události, druh dveří a model/odstín. Neukládá se jméno, fotka, IP adresa ani identifikátor telefonu.
„Nová zařízení“ = první otevření aplikace v daném telefonu/prohlížeči; „Návrat“ = otevření v další den. Po smazání dat prohlížeče se telefon započítá znovu jako nový.
Poptávky samotné (s kontakty) najdete v kartotéce.</p>
</main></body></html>
