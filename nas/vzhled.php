<?php
// Knihovna skutečných vzhledů dveří ERKADO pro nabídky (13. 11. 2026, přání Zdeňka).
// Prodejce v nabídce vloží k položce snímek z konfigurátoru ERKADO (klika, sklo, intarzie přesně podle výpočtu);
// snímek se sem uloží pod klíčem konfigurace a u stejné konfigurace se příště nabídne sám (nabidka.js).
//   GET  ?k=<40 hex>                      → {ok:true, obr:'data:image/jpeg;base64,…', popis, kdy} nebo 404
//   POST (JSON): {k, popis, b64}          → uloží /data/erkado-vzhled/<k>.jpg + <k>.json (novější přepíše starší)
// Veřejné na formulare.dvere.app jen pro povolené weby (Origin), s limitem ukládání; ukládá se pouze ověřený JPEG do 600 kB.
// Patří na NAS do /docker/formulare/app/vzhled.php.
require __DIR__ . '/lib/start.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$povolene = (array)($cfg['povolene_origin'] ?? []);
if (in_array($origin, $povolene, true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type'); }
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
function vz_konec(int $kod, array $o) { http_response_code($kod); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; }
$met = $_SERVER['REQUEST_METHOD'] ?? '';
if ($met === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($origin, $povolene, true)) vz_konec(403, ['ok' => false, 'chyba' => 'origin']);

$ip = klient_ip();
$dir = $DATA . '/erkado-vzhled';
$klic = fn($k) => preg_match('/^[0-9a-f]{40}$/', (string)$k) ? (string)$k : '';

if ($met === 'GET') {
  $k = $klic($_GET['k'] ?? ''); if ($k === '') vz_konec(400, ['ok' => false, 'chyba' => 'klic']);
  $f = "$dir/$k.jpg"; if (!is_file($f)) vz_konec(404, ['ok' => false]);
  $m = json_decode((string)@file_get_contents("$dir/$k.json"), true) ?: [];
  vz_konec(200, ['ok' => true, 'obr' => 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($f)), 'popis' => (string)($m['popis'] ?? ''), 'kdy' => (string)($m['kdy'] ?? '')]);
}
if ($met !== 'POST') vz_konec(405, ['ok' => false]);
// limit jen na ukládání (čtení podle 40místného klíče se nepočítá, ať neubírá z limitu formulářů): nejvýš 40 uložení za hodinu z jedné adresy
$pdo = db();
$st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='vzhled' AND cas>?"); $st->execute([$ip, time() - 3600]);
if ((int)$st->fetchColumn() >= 40) vz_konec(429, ['ok' => false, 'chyba' => 'limit']);
$pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'vzhled', ?)")->execute([$ip, time()]);
$raw = (string)file_get_contents('php://input', false, null, 0, 900001);
if (strlen($raw) > 900000) vz_konec(413, ['ok' => false, 'chyba' => 'velke']);
$in = json_decode($raw, true); if (!is_array($in)) vz_konec(400, ['ok' => false, 'chyba' => 'data']);
$k = $klic($in['k'] ?? ''); if ($k === '') vz_konec(400, ['ok' => false, 'chyba' => 'klic']);
$bin = base64_decode(preg_replace('#^data:image/jpeg;base64,#', '', (string)($in['b64'] ?? '')), true);
if ($bin === false || strlen($bin) < 500 || strlen($bin) > 600000) vz_konec(400, ['ok' => false, 'chyba' => 'obrazek']);
$i = @getimagesizefromstring($bin);
if (!$i || $i[2] !== IMAGETYPE_JPEG || $i[0] < 60 || $i[1] < 60 || $i[0] > 2000 || $i[1] > 2000) vz_konec(400, ['ok' => false, 'chyba' => 'neni jpeg']);
if (!is_dir($dir) && !@mkdir($dir, 0770, true)) vz_konec(500, ['ok' => false, 'chyba' => 'adresar']);
if (file_put_contents("$dir/$k.jpg", $bin, LOCK_EX) === false) vz_konec(500, ['ok' => false, 'chyba' => 'zapis']);
$popis = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['popis'] ?? ''))), 0, 400);
@file_put_contents("$dir/$k.json", json_encode(['popis' => $popis, 'kdy' => date('Y-m-d H:i:s'), 'ip' => $ip], JSON_UNESCAPED_UNICODE), LOCK_EX);
vz_konec(200, ['ok' => true]);
