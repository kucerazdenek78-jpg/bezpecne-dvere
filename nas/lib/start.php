<?php
// Společný základ: konfigurace, databáze (SQLite), pomocné funkce.
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Prague');
$DATA = getenv('FORMULARE_DATA') ?: '/data';
if (!is_file($DATA . '/config.php')) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); exit("Chybí $DATA/config.php"); }
$cfg = require $DATA . '/config.php';
// 10. 11. 2026: přepis příjemců e-mailů, účetní a účtu ze Správy (Nastavení e-mailů) – /data/nastaveni.json, viz lib/nastaveni.php
if (is_file(__DIR__ . '/nastaveni.php')) { require_once __DIR__ . '/nastaveni.php'; try { nast_merge($cfg); } catch (Throwable $e) {} }

function db() {
  global $DATA; static $pdo = null; if ($pdo) return $pdo;
  $pdo = new PDO('sqlite:' . $DATA . '/formulare.sqlite');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA busy_timeout = 5000');
  $pdo->exec("CREATE TABLE IF NOT EXISTS zadosti (
    id INTEGER PRIMARY KEY AUTOINCREMENT, vytvoreno TEXT NOT NULL, typ TEXT NOT NULL, cislo TEXT, predmet TEXT NOT NULL,
    telo TEXT NOT NULL, email TEXT, data_json TEXT, prilohy TEXT, stav TEXT NOT NULL DEFAULT 'nove', poznamka TEXT, ip TEXT)");
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name'); // doplnění sloupců pro potvrzení přijaté zálohy
  foreach (['zaloha_prijata_dne' => 'TEXT', 'zaloha_prijata_kc' => 'REAL', 'sklad_stav' => 'TEXT', 'sklad_davka' => 'INTEGER', 'predano_dne' => 'TEXT', 'zaruka_od' => 'TEXT', 'zaruka_do' => 'TEXT', 'zaruka_prodlouzeno' => 'TEXT', 'zaruka_prodl_let' => 'INTEGER', 'zaruka_prodl_dne' => 'TEXT', 'zaruka_prodl_kc' => 'REAL', 'nabidka3_dne' => 'TEXT', 'nabidka5_dne' => 'TEXT', 'pu_posledni' => 'TEXT'] as $sloupec => $typ_s) if (!in_array($sloupec, $sl, true)) $pdo->exec("ALTER TABLE zadosti ADD COLUMN $sloupec $typ_s");
  $pdo->exec('CREATE INDEX IF NOT EXISTS zadosti_typ ON zadosti(typ)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS zadosti_vytvoreno ON zadosti(vytvoreno)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS servis (id INTEGER PRIMARY KEY AUTOINCREMENT, zadost_id INTEGER NOT NULL, datum TEXT, druh TEXT, poznamka TEXT, dalsi_kontrola TEXT, vytvoreno TEXT)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS servis_zadost ON servis(zadost_id)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS sklad_davky (id INTEGER PRIMARY KEY AUTOINCREMENT, vytvoreno TEXT, poznamka TEXT)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS pokusy (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT, typ TEXT, cas INTEGER)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS pokusy_ip_cas ON pokusy(ip, cas)');
  return $pdo;
}
// požadavek přišel přes veřejný tunel (Cloudflare přidává tyto hlavičky; z vnitřní sítě je nemá)
function pres_tunel() { return isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_CF_RAY']); }
function klient_ip() { return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''; }
// Od 3. 11. 2026 běží kartotéka bez Cloudflare Access: přihlašuje se formulářem (lib/uzivatele.php, zámek po 5 chybách na 10 minut).
// Stránky přes tunel dál kontrolují přítomnost hlavičky Cf-Access-Jwt-Assertion, proto ji pro hostitele kartotéky doplníme (funguje s Access i bez něj).
if (pres_tunel() && empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])
    && strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) === strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'))) {
  $_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] = 'prihlaseni-formularem';
}
// 3. 11. 2026: oprava mobilního zobrazení – široké tabulky (příjemci na úvodu, seznamy) už nerozšíří celou stránku, ale posouvají se do strany.
// Vloží krátké CSS do HTML stránek (jen při běžném otevření v prohlížeči; JSON, PDF, videa a ostatní odpovědi se nemění).
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !isset($_SERVER['HTTP_RANGE']) && stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false) {
  ob_start(function ($html) {
    if (strlen($html) > 3000000 || stripos($html, '</head>') === false) return false;
    foreach (headers_list() as $h) if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) return false;
    $css = '<style>/*bd-mobil*/@media (max-width:700px){table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}code,pre{overflow-wrap:anywhere}img{max-width:100%;height:auto}}</style>';
    return preg_replace('~</head>~i', $css . '</head>', $html, 1);
  });
}
