<?php
// Odpověď na dotaz „Uzavřít poptávky k nové objednávce / nabídce?“ (lib/navaznost.php). Jen POST, přihlášený uživatel kartotéky; pak zpět na stránku, odkud přišel.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  if ($host !== strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app')) || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php';
if (!($UZ = uziv_overit($_SERVER['PHP_AUTH_USER'] ?? '', $_SERVER['PHP_AUTH_PW'] ?? ''))) { header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit; }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: index.php'); exit; }
$or = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($or !== '' && strtolower((string)parse_url($or, PHP_URL_HOST)) !== strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0])) { http_response_code(403); exit('Neplatný požadavek'); }
require_once __DIR__ . '/lib/stav.php'; require_once __DIR__ . '/lib/zamek.php'; require_once __DIR__ . '/lib/navaznost.php';
$akce = ($_POST['akce'] ?? '') === 'uzavrit' ? 'uzavrit' : 'nic';
vz_zpracuj(db(), (int)($_POST['cil'] ?? 0), (array)($_POST['zdroj'] ?? []), $akce, function_exists('uziv_jmeno') ? uziv_jmeno() : '');
$zpet = (string)($_POST['zpet'] ?? 'index.php');
if (!preg_match('~^/?[a-z0-9_-]+\.php(\?[^\r\n]*)?$~i', $zpet)) $zpet = 'index.php';   // jen stránka kartotéky, žádná cizí adresa
header('Location: ' . (str_starts_with($zpet, '/') ? $zpet : '/' . $zpet)); exit;
