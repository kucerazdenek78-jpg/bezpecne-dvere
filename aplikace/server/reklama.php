<?php
// Reklamní materiály – složky se soubory ke stažení (Facebook, reely, obrázky). Patří do /docker/formulare/app/.
// Soubory leží mimo web v /data/reklama/<složka>/ (na NAS /docker/formulare/data/reklama/). Přístup: jen kartoteka.dvere.app (Cloudflare Access) nebo místní síť.
// Popisy složek: v každé složce volitelný soubor _popis.txt (první řádek = název složky, další řádky = poznámka).
// Popisy souborů: soubor _soubory.txt, řádky "nazev-souboru.ext | popis".
require __DIR__ . '/lib/start.php';
header('X-Robots-Tag: noindex');
function je_lan($ip) { return (bool)filter_var($ip, FILTER_VALIDATE_IP) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE); }
$pres_tunel = isset($_SERVER['HTTP_CF_CONNECTING_IP']);
$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$access = $host === 'kartoteka.dvere.app' && !empty($_SERVER['HTTP_CF_ACCESS_AUTHENTICATED_USER_EMAIL']);
if ($pres_tunel ? !$access : !je_lan((string)($_SERVER['REMOTE_ADDR'] ?? ''))) { http_response_code(404); exit('Nenalezeno'); }

$ROOT = rtrim($DATA, '/') . '/reklama';
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'pdf' => 'application/pdf', 'zip' => 'application/zip', 'svg' => 'image/svg+xml', 'txt' => 'text/plain; charset=utf-8'];

// stažení / náhled souboru
if (isset($_GET['f'])) {
  $rel = (string)$_GET['f'];
  if (!preg_match('~^[^/\\\\.][^/\\\\]*/[^/\\\\.][^/\\\\]*$~u', $rel)) { http_response_code(404); exit('Nenalezeno'); }
  $p = realpath($ROOT . '/' . $rel);
  $base = realpath($ROOT);
  if (!$p || !$base || strpos($p, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($p)) { http_response_code(404); exit('Nenalezeno'); }
  $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
  $mime = $MIME[$ext] ?? 'application/octet-stream';
  $size = filesize($p);
  header('Content-Type: ' . $mime);
  header('Cache-Control: private, max-age=3600');
  header('Content-Disposition: ' . (isset($_GET['d']) ? 'attachment' : 'inline') . '; filename="' . rawurlencode(basename($p)) . '"');
  // podpora Range (přehrávání videa)
  $start = 0; $end = $size - 1;
  if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] !== '') $start = (int)$m[1];
    if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); $end = $size - 1; }
    if ($start > $end) { http_response_code(416); exit; }
    http_response_code(206); header("Content-Range: bytes $start-$end/$size");
  }
  header('Accept-Ranges: bytes'); header('Content-Length: ' . ($end - $start + 1));
  $fh = fopen($p, 'rb'); fseek($fh, $start); $left = $end - $start + 1;
  while ($left > 0 && !feof($fh)) { $buf = fread($fh, min(8192, $left)); echo $buf; $left -= strlen($buf); }
  fclose($fh); exit;
}

header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
$slozky = [];
if (is_dir($ROOT)) {
  $d = scandir($ROOT); sort($d, SORT_NATURAL);
  foreach ($d as $s) {
    if ($s[0] === '.' || !is_dir("$ROOT/$s")) continue;
    $popis = is_file("$ROOT/$s/_popis.txt") ? preg_split('/\R/', trim((string)file_get_contents("$ROOT/$s/_popis.txt")), 2) : [];
    $nazev = $popis[0] ?? preg_replace('/^\d+-/', '', $s); $pozn = $popis[1] ?? '';
    $pop = [];
    if (is_file("$ROOT/$s/_soubory.txt")) foreach (preg_split('/\R/', (string)file_get_contents("$ROOT/$s/_soubory.txt")) as $l) { $x = explode('|', $l, 2); if (count($x) == 2) $pop[trim($x[0])] = trim($x[1]); }
    $soubory = [];
    $f = scandir("$ROOT/$s"); sort($f, SORT_NATURAL);
    foreach ($f as $n) { if ($n[0] === '.' || $n[0] === '_' || !is_file("$ROOT/$s/$n")) continue; $soubory[] = ['n' => $n, 'ext' => strtolower(pathinfo($n, PATHINFO_EXTENSION)), 'size' => filesize("$ROOT/$s/$n"), 'pop' => $pop[$n] ?? '']; }
    $slozky[] = ['s' => $s, 'nazev' => $nazev, 'pozn' => $pozn, 'soubory' => $soubory];
  }
}
$vel = function ($b) { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' kB'; };
?><!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Reklamní materiály</title>
<style>
:root{--g:#123d27;--a:#15803d;--b:#dfe5e1;--bg:#f4f7f5}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,Segoe UI,Roboto,sans-serif;background:var(--bg);color:#1f2a24}
header{background:var(--g);color:#fff;padding:1.1rem 1.2rem}header h1{margin:0;font-size:1.35rem}header p{margin:.2rem 0 0;color:#b9dcc6;font-size:.9rem}
main{max-width:1100px;margin:0 auto;padding:1.2rem}
nav.k{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1.2rem}nav.k a{background:#fff;border:1px solid var(--b);border-radius:99px;padding:.35rem .9rem;color:var(--g);text-decoration:none;font-weight:600;font-size:.9rem}
section{background:#fff;border:1px solid var(--b);border-radius:14px;padding:1.1rem;margin-bottom:1.4rem}section h2{margin:0 0 .2rem;color:var(--g);font-size:1.2rem}.pozn{color:#5b6b62;margin:.1rem 0 .9rem;font-size:.92rem}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:1rem}
.card{border:1px solid var(--b);border-radius:12px;overflow:hidden;background:#fafcfb;display:flex;flex-direction:column}
.th{height:200px;background:#e9eeeb;display:flex;align-items:center;justify-content:center;overflow:hidden}.th img,.th video{max-width:100%;max-height:100%;object-fit:contain;display:block}.th .ic{font-size:2.4rem}
.cb{padding:.7rem .8rem;display:flex;flex-direction:column;gap:.25rem;flex:1}.cb b{word-break:break-all;font-size:.88rem}.cb small{color:#5b6b62}
.btn{display:inline-block;margin-top:auto;background:var(--a);color:#fff;text-decoration:none;padding:.45rem .8rem;border-radius:9px;font-weight:700;font-size:.88rem;text-align:center}
.prazdne{color:#5b6b62}
</style></head><body>
<header><h1>Reklamní materiály</h1><p>Hotové obrázky, reely a podklady k reklamám. Klikni na „Stáhnout“ a nasaď je na Facebook, Google nebo Sklik. Jen pro interní použití.</p></header>
<main>
<?php if (!$slozky): ?><p class="prazdne">Zatím tu nejsou žádné složky. Soubory se ukládají na NAS do <code>/docker/formulare/data/reklama/&lt;složka&gt;/</code>.</p><?php else: ?>
<nav class="k"><?php foreach ($slozky as $z): ?><a href="#<?= $h($z['s']) ?>"><?= $h($z['nazev']) ?></a><?php endforeach; ?></nav>
<?php foreach ($slozky as $z): ?>
<section id="<?= $h($z['s']) ?>"><h2><?= $h($z['nazev']) ?></h2><?php if ($z['pozn']): ?><p class="pozn"><?= nl2br($h($z['pozn'])) ?></p><?php endif; ?>
<?php if (!$z['soubory']): ?><p class="prazdne">Složka je prázdná.</p><?php else: ?><div class="grid">
<?php foreach ($z['soubory'] as $f): $u = '?f=' . rawurlencode($z['s'] . '/' . $f['n']); ?>
<div class="card"><div class="th"><?php if (in_array($f['ext'], ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'])): ?><a href="<?= $h($u) ?>" target="_blank"><img loading="lazy" src="<?= $h($u) ?>" alt=""></a><?php elseif ($f['ext'] === 'mp4'): ?><video src="<?= $h($u) ?>" controls preload="metadata" playsinline></video><?php else: ?><span class="ic">📄</span><?php endif; ?></div>
<div class="cb"><b><?= $h($f['n']) ?></b><?php if ($f['pop']): ?><small><?= $h($f['pop']) ?></small><?php endif; ?><small><?= $h($vel($f['size'])) ?></small><a class="btn" href="<?= $h($u) ?>&amp;d=1">Stáhnout</a></div></div>
<?php endforeach; ?></div><?php endif; ?></section>
<?php endforeach; endif; ?>
</main></body></html>
