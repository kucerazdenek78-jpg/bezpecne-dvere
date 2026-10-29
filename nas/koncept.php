<?php
// Rozpracované nabídky / objednávky z webu (nabidka.js): uložení bez odeslání a pozdější načtení.
// Veřejné na formulare.dvere.app, ale každý koncept jde číst a měnit jen s jeho tajným klíčem (token, 24 hex znaků).
//   POST (JSON v těle): {akce:'ulozit', id?, t?, druh, cislo, zakaznik, kategorie, cena, prodavac, data} → {ok, id, t}
//                       {akce:'hotovo', id, t}  → koncept označen jako odeslaný (zmizí z Rozpracovaných)
//   GET ?id=N&t=TOKEN → {ok, data, druh}
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

if ($met === 'GET') {
  $k = $najdi((int)($_GET['id'] ?? 0), (string)($_GET['t'] ?? ''));
  if (!$k) konc_konec(404, ['ok' => false, 'chyba' => 'nenalezeno']);
  konc_konec(200, ['ok' => true, 'druh' => $k['druh'], 'stav' => $k['stav'], 'upraveno' => $k['upraveno'], 'data' => json_decode((string)$k['data_json'], true)]);
}
if ($met !== 'POST') konc_konec(405, ['ok' => false]);
$raw = (string)file_get_contents('php://input', false, null, 0, 600001);
if (strlen($raw) > 600000) konc_konec(413, ['ok' => false, 'chyba' => 'velke']);
$in = json_decode($raw, true); if (!is_array($in)) konc_konec(400, ['ok' => false, 'chyba' => 'data']);
$id = (int)($in['id'] ?? 0); $t = (string)($in['t'] ?? ''); $ted = date('Y-m-d H:i:s');
$c = fn($k, $max = 120) => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)($in[$k] ?? ''))), 0, $max);

if (($in['akce'] ?? '') === 'hotovo') {
  $k = $najdi($id, $t); if (!$k) konc_konec(404, ['ok' => false]);
  $pdo->prepare("UPDATE koncepty SET stav='odeslano', upraveno=?, cislo=COALESCE(NULLIF(?, ''), cislo) WHERE id=?")->execute([$ted, $c('cislo', 40), $id]);
  konc_konec(200, ['ok' => true]);
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
