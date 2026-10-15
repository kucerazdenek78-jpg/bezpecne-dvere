<?php
// API zákaznické aplikace (bezpecne-dvere.cz/aplikace/). Patří do /docker/formulare/app/ vedle odeslat.php.
// Akce „sparovat“: zákazník zadá číslo objednávky + poslední 4 číslice telefonu z objednávky → vrátí model, odstín
// a záruku z objednávky v kartotéce (tabulka zadosti, typ objednavka_interni). Adresu, telefon ani ceny nevrací.
// Nic nezapisuje do zadosti; pokusy se počítají v tabulce pokusy (limit na IP za hodinu).
require __DIR__ . '/lib/start.php'; require_once __DIR__ . '/lib/zaruka.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $cfg['povolene_origin'], true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
function vystup($http, $data) { http_response_code($http); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function chyba($http, $kod, $zprava = '') { vystup($http, ['ok' => false, 'kod' => $kod, 'zprava' => $zprava]); }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') chyba(405, 'metoda');
if ($origin !== '' && !in_array($origin, $cfg['povolene_origin'], true)) chyba(403, 'origin');

$LIMIT_ZA_HODINU = (int)($cfg['aplikace_limit_hodina'] ?? 10);
$akce = $_POST['akce'] ?? '';

if ($akce === 'sparovat') {
  $pdo = db();
  $ip = klient_ip(); $ted = time();
  $st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='app_sparovat' AND cas>?");
  $st->execute([$ip, $ted - 3600]);
  if ((int)$st->fetchColumn() >= $LIMIT_ZA_HODINU) chyba(429, 'limit', 'Příliš mnoho pokusů, zkuste to za hodinu.');
  $pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'app_sparovat', ?)")->execute([$ip, $ted]);

  $cislo = mb_substr(trim((string)($_POST['cislo'] ?? '')), 0, 40);
  $tel4 = (string)($_POST['tel4'] ?? '');
  if ($cislo === '' || !preg_match('/^\d{4}$/', $tel4)) chyba(400, 'data', 'Zadejte číslo objednávky a 4 číslice telefonu.');

  // nejnovější záznam objednávky s tímto číslem (bez ohledu na velikost písmen a mezery)
  $norm = function ($s) { return strtoupper(preg_replace('/\s+/', '', (string)$s)); };
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ IN ('objednavka_interni','objednavka') AND REPLACE(UPPER(cislo),' ','')=? ORDER BY id DESC LIMIT 10");
  $st->execute([$norm($cislo)]);
  $nalez = null;
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true);
    if (!is_array($d)) continue;
    $tel = preg_replace('/\D/', '', (string)($d['telefon'] ?? ''));
    if (strlen($tel) >= 4 && hash_equals(substr($tel, -4), $tel4)) { $nalez = [$r, $d]; break; }
  }
  if (!$nalez) chyba(404, 'nenalezeno', 'Objednávku jsme nenašli.');
  [$r, $d] = $nalez;
  $termin = (string)($d['termin'] ?? ''); // plánovaný termín montáže – skutečné datum předání potvrdí zákazník v aplikaci
  vystup(200, ['ok' => true, 'dvere' => [
      'cislo' => (string)($d['obj_cislo'] ?? $r['cislo']),
      'model_kod' => (string)($d['model_kod'] ?? ''), 'model' => (string)($d['model'] ?? ''),
      'odstin' => (string)($d['odstin'] ?? ''), 'zaruka' => (string)($d['zaruka'] ?? ''),
      'termin' => mb_substr($termin, 0, 60),
      // skutečné údaje z kartotéky (potvrzené předání technikem) – aplikace je ukáže místo vlastního odhadu
      'predano' => (string)($r['predano_dne'] ?? ''), 'zaruka_do' => (string)($r['zaruka_do'] ?? ''), 'prodlouzeno_let' => (int)($r['zaruka_prodl_let'] ?? 0),
    ],
    // odkaz na zákaznickou stránku „Moje dveře“ na NAS (záruka, servis, prodloužení záruky s QR platbou) – jediný zdroj pravdy
    'moje_url' => !empty($r['predano_dne']) ? zaruka_url($cfg, zaruka_token($pdo, $r)) : '',
    'kontakt' => ['jmeno' => (string)($d['jmeno'] ?? ''), 'prijmeni' => (string)($d['prijmeni'] ?? '')]]);
}

chyba(400, 'akce');
