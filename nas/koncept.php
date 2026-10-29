<?php
// Rozpracované nabídky / objednávky z webu (nabidka.js): uložení bez odeslání a pozdější načtení.
// Veřejné na formulare.dvere.app, ale každý koncept jde číst a měnit jen s jeho tajným klíčem (token, 24 hex znaků).
//   POST (JSON v těle): {akce:'ulozit', id?, t?, druh, cislo, zakaznik, kategorie, cena, prodavac, data} → {ok, id, t}
//                       {akce:'hotovo', id, t}  → koncept označen jako odeslaný (zmizí z Rozpracovaných)
//                       {akce:'soubor', id, t, i, n, b64}  → PDF rozpočtu po částech (base64, ~1,5 MB na část; i = pořadí od 0, n = počet částí)
//                       {akce:'bez_souboru', id, t}         → PDF rozpočtu smazat (v nabídce už není)
//   GET ?id=N&t=TOKEN → {ok, data, druh, rozpocet_pdf}
//   GET ?id=N&t=TOKEN&soubor=rozpocet → PDF rozpočtu (application/pdf)
// Soubory: /data/koncepty/<id>/rozpocet.pdf (mimo web).
// Patří na NAS do /docker/formulare/app/koncept.php. Seznam pro prodavače: koncepty.php (kartotéka).
require __DIR__ . '/lib/start.php';
require_once __DIR__ . '/lib/koncepty.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$povolene = (array)($cfg['povolene_origin'] ?? []);
if (in_array($origin, $povolene, true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type'); }
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
function konc_konec(int $kod, array $o) { http_response_code($kod); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; }
$met = $_SERVER['REQUEST_METHOD'] ?? '';
if ($met === 'OPTIONS') { http_response_code(204); exit; }
if (!in_array($origin, $povolene, true)) konc_konec(403, ['ok' => false, 'chyba' => 'origin']);
$pdo = db(); konc_tabulka($pdo);

// ochrana proti zneužití: nejvýš 120 operací za hodinu z jedné adresy
$ip = klient_ip();
$st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='koncept' AND cas>?"); $st->execute([$ip, time() - 3600]);
if ((int)$st->fetchColumn() >= 120) konc_konec(429, ['ok' => false, 'chyba' => 'limit']);
$pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'koncept', ?)")->execute([$ip, time()]);

$najdi = function (int $id, string $t) use ($pdo) {
  if ($id <= 0 || !preg_match('/^[0-9a-f]{24}$/', $t)) return null;
  $q = $pdo->prepare("SELECT * FROM koncepty WHERE id=? AND stav<>'smazano'"); $q->execute([$id]); $k = $q->fetch(PDO::FETCH_ASSOC);
  return ($k && hash_equals((string)$k['token'], $t)) ? $k : null;
};

$adr = fn(int $id) => $DATA . '/koncepty/' . $id;
if ($met === 'GET') {
  $k = $najdi((int)($_GET['id'] ?? 0), (string)($_GET['t'] ?? ''));
  if (!$k) konc_konec(404, ['ok' => false, 'chyba' => 'nenalezeno']);
  $pdf = $adr((int)$k['id']) . '/rozpocet.pdf';
  if (($_GET['soubor'] ?? '') === 'rozpocet') {
    if (!is_file($pdf)) konc_konec(404, ['ok' => false, 'chyba' => 'bez souboru']);
    header('Content-Type: application/pdf'); header('Content-Length: ' . filesize($pdf)); readfile($pdf); exit;
  }
  konc_konec(200, ['ok' => true, 'druh' => $k['druh'], 'stav' => $k['stav'], 'upraveno' => $k['upraveno'], 'data' => json_decode((string)$k['data_json'], true), 'rozpocet_pdf' => is_file($pdf) ? filesize($pdf) : 0]);
}
if ($met !== 'POST') konc_konec(405, ['ok' => false]);
$raw = (string)file_get_contents('php://input', false, null, 0, 6000001);
if (strlen($raw) > 6000000) konc_konec(413, ['ok' => false, 'chyba' => 'velke']);
$in = json_decode($raw, true); if (!is_array($in)) konc_konec(400, ['ok' => false, 'chyba' => 'data']);
$id = (int)($in['id'] ?? 0); $t = (string)($in['t'] ?? ''); $ted = date('Y-m-d H:i:s');
$c = fn($k, $max = 120) => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)($in[$k] ?? ''))), 0, $max);

if (($in['akce'] ?? '') === 'hotovo') {
  $k = $najdi($id, $t); if (!$k) konc_konec(404, ['ok' => false]);
  $pdo->prepare("UPDATE koncepty SET stav='odeslano', upraveno=?, cislo=COALESCE(NULLIF(?, ''), cislo) WHERE id=?")->execute([$ted, $c('cislo', 40), $id]);
  konc_konec(200, ['ok' => true]);
}
if (in_array($in['akce'] ?? '', ['soubor', 'bez_souboru'], true)) {   // PDF rozpočtu z jiného programu
  $k = $najdi($id, $t); if (!$k) konc_konec(404, ['ok' => false]);
  $dir = $adr($id); if (!is_dir($dir) && !@mkdir($dir, 0770, true)) konc_konec(500, ['ok' => false, 'chyba' => 'adresar']);
  if ($in['akce'] === 'bez_souboru') { @unlink($dir . '/rozpocet.pdf'); @unlink($dir . '/rozpocet.part'); konc_konec(200, ['ok' => true]); }
  $i = (int)($in['i'] ?? -1); $n = (int)($in['n'] ?? 0); $bin = base64_decode((string)($in['b64'] ?? ''), true);
  if ($i < 0 || $n < 1 || $n > 40 || $i >= $n || $bin === false) konc_konec(400, ['ok' => false, 'chyba' => 'cast']);
  $part = $dir . '/rozpocet.part';
  if ($i === 0) @unlink($part);
  elseif (!is_file($part)) konc_konec(409, ['ok' => false, 'chyba' => 'poradi']);
  if (file_put_contents($part, $bin, FILE_APPEND | LOCK_EX) === false) konc_konec(500, ['ok' => false, 'chyba' => 'zapis']);
  if (filesize($part) > 40 * 1048576) { @unlink($part); konc_konec(413, ['ok' => false, 'chyba' => 'velke']); }
  if ($i === $n - 1) {
    $hl = (string)file_get_contents($part, false, null, 0, 5);
    if ($hl !== '%PDF-') { @unlink($part); konc_konec(400, ['ok' => false, 'chyba' => 'neni pdf']); }
    rename($part, $dir . '/rozpocet.pdf');
  }
  konc_konec(200, ['ok' => true, 'hotovo' => $i === $n - 1]);
}
if (($in['akce'] ?? '') !== 'ulozit' || !is_array($in['data'] ?? null)) konc_konec(400, ['ok' => false, 'chyba' => 'akce']);
$druh = ($in['druh'] ?? '') === 'objednavka-vice' ? 'objednavka-vice' : 'nabidka';
$data = json_encode($in['data'], JSON_UNESCAPED_UNICODE);
$hod = [$c('cislo', 40), $c('zakaznik'), $c('kategorie', 40), is_numeric($in['cena'] ?? null) ? (float)$in['cena'] : null, $c('prodavac', 60)];
if ($k = $najdi($id, $t)) {
  $pdo->prepare("UPDATE koncepty SET upraveno=?, stav='rozpracovano', druh=?, cislo=?, zakaznik=?, kategorie=?, cena=?, prodavac=?, data_json=? WHERE id=?")
    ->execute(array_merge([$ted, $druh], $hod, [$data, $id]));
  konc_konec(200, ['ok' => true, 'id' => $id, 't' => $t]);
}
$t = bin2hex(random_bytes(12));
$pdo->prepare("INSERT INTO koncepty (token, vytvoreno, upraveno, stav, druh, cislo, zakaznik, kategorie, cena, prodavac, data_json) VALUES (?,?,?,'rozpracovano',?,?,?,?,?,?,?)")
  ->execute(array_merge([$t, $ted, $ted, $druh], $hod, [$data]));
konc_konec(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 't' => $t]);
