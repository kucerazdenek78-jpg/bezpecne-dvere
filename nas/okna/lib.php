<?php
// SERVIS OKEN – samostatná aplikace (2. 11. 2026), oddělená od systému dveří. Běží na kartoteka.dvere.app/okna/ (stejné přihlášení
// jako kartotéka: Cloudflare Access + jméno a heslo), ale má VLASTNÍ databázi $DATA/okna.sqlite, vlastní zákazníky a vlastní menu.
// Se systémem dveří sdílí jen přihlášení, odesílání e-mailů (lib/posta.php) a čtení poptávek „Servis oken“ z webu (jen ke převzetí).
// Fotky, podpisy a protokoly: $DATA/okna/<id zápisu SVJ>/ a $DATA/okna/j<id servisu jednotlivce>/.
// OK_APP = složka aplikace kartotéky (/var/www/html); kód aplikace může ležet i v datech (stub v okna/ ho načte, viz okna/README)
if (!defined('OK_APP')) define('OK_APP', dirname(__DIR__));
require OK_APP . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once OK_APP . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');

// vlastní databáze aplikace (oddělená od kartotéky dveří)
function ok_db(): PDO {
  global $DATA; static $pdo = null; if ($pdo) return $pdo;
  $pdo = new PDO('sqlite:' . $DATA . '/okna.sqlite');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA busy_timeout=5000');
  return $pdo;
}

// horní lišta aplikace Servis oken (vlastní, bez menu dveří)
function ok_bar(string $akt = ''): void {
  $pol = [['index.php', 'Jednotlivci', 'jed', '🏠'], ['svj.php', 'SVJ a družstva', 'svj', '🏢'], ['zakaznici.php', 'Zákazníci', 'zak', '👥'], ['poptavky.php', 'Poptávky z webu', 'popt', '📨']];
  $n = 0; try { $n = count(ok_poptavky_nove()); } catch (Throwable $x) {}
  echo '<style>.okbar{position:sticky;top:0;z-index:60;display:flex;flex-wrap:wrap;gap:4px;align-items:center;background:#0e4f5c;padding:8px 12px}.okbar b.ap{color:#fff;font:700 16px system-ui,Arial,sans-serif;margin-right:10px}'
     . '.okbar a{color:#d8f0f3;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}.okbar a:hover{background:#13697a}.okbar a.on{background:#0891b2;color:#fff}'
     . '.okbar i.n{font-style:normal;background:#e11d48;color:#fff;border-radius:999px;padding:1px 7px;margin-left:5px;font-size:12px}@media print{.okbar{display:none!important}}</style><nav class="okbar"><b class="ap">🪟 Servis oken</b>';
  foreach ($pol as [$h, $t, $k, $ik]) echo '<a href="' . $h . '" class="' . ($k === $akt ? 'on' : '') . '">' . $ik . ' ' . htmlspecialchars($t) . ($k === 'popt' && $n ? '<i class="n">' . $n . '</i>' : '') . '</a>';
  if (function_exists('uziv_jmeno') && uziv_jmeno() !== '') echo '<span style="margin-left:auto;color:#a9d6dd;font:13px system-ui,Arial,sans-serif;padding:0 8px">👤 ' . htmlspecialchars(uziv_jmeno()) . '</span>';
  echo '</nav>';
}

// poptávky „Servis oken“ z webu: ukládají se do kartotéky dveří (tabulka zadosti) – tady se jen čtou a převezmou
function ok_poptavky(): array {
  $o = [];
  foreach (db()->query("SELECT id, vytvoreno, cislo, stav, email, data_json FROM zadosti WHERE typ='poptavka' AND (data_json LIKE '%servis_oken%' OR data_json LIKE '%servis-oken%') ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true) ?: [];
    if (($d['druh'] ?? '') !== 'servis_oken' && ($d['model_kod'] ?? '') !== 'servis-oken') continue;
    $r['d'] = $d; $o[] = $r;
  }
  return $o;
}
function ok_poptavky_nove(): array {   // ještě nepřevzaté a ne vyřízené / stornované
  $vz = array_map('intval', ok_db()->query('SELECT zadost_id FROM so_jed WHERE zadost_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
  return array_values(array_filter(ok_poptavky(), fn($r) => !in_array((int)$r['id'], $vz, true) && !in_array($r['stav'], ['vyrizeno', 'storno'], true)));
}

// klíč zákazníka (stejné pravidlo jako v kartotéce: telefon → e-mail → jméno + obec), ale vlastní seznam zákazníků
function ok_klic(string $tel, string $em, string $jm, string $mesto): string {
  $t = preg_replace('/\D+/', '', $tel); if (strlen($t) >= 9) return 't' . substr($t, -9);
  $em = strtolower(trim($em)); if ($em !== '') return 'e' . $em;
  $jm = mb_strtolower(trim($jm)); return $jm !== '' ? 'j' . md5($jm . '|' . mb_strtolower(trim($mesto))) : '';
}

// ikona mapy u adresy (Google Mapy)
function ok_mapa(string $adresa): string {
  $q = trim(preg_replace('/\s+/u', ' ', $adresa), " ,"); if ($q === '') return '';
  return ' <a href="https://www.google.com/maps/search/?api=1&amp;query=' . htmlspecialchars(rawurlencode($q)) . '" target="_blank" rel="noopener" title="Ukázat na mapě" style="text-decoration:none">🗺️</a>';
}

// společný vzhled stránek
function ok_hlava(string $titul): void {
  echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0e4f5c"><title>' . htmlspecialchars($titul) . ' – Servis oken</title><style>' . OK_CSS . '</style></head><body>';
}
const OK_CSS = 'body{font:16px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:1000px;margin:0 auto;padding:14px}'
 . '.box{background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{margin:4px 0 10px;font-size:24px}h2{margin:16px 0 8px;font-size:19px}h3{margin:6px 0 8px;font-size:17px}'
 . '.mut{color:#5f6b7a;font-size:14px}a{color:#0e7490}.zk{background:#fff4d6;border:1px solid #f0cf7a;color:#6b4a00;border-radius:10px;padding:8px 12px;margin-bottom:12px;font-size:14px}'
 . 'button,.btn{display:inline-block;background:#0e7490;color:#fff;border:0;border-radius:10px;padding:10px 16px;font:600 16px system-ui,Arial,sans-serif;cursor:pointer;text-decoration:none}'
 . 'button.sec,.btn.sec{background:#e6ebf3;color:#1b2430}button.del{background:#fde2e2;color:#9b1c1c}button.ok,.btn.ok{background:#15803d}button:disabled{opacity:.6}'
 . 'input,select,textarea{font:inherit;padding:8px 10px;border:1px solid #c9d1dc;border-radius:9px;background:#fff;box-sizing:border-box;max-width:100%}textarea{width:100%;min-height:70px}'
 . '.ln{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:flex-end;margin:8px 0}.ln label{display:flex;flex-direction:column;font-size:13px;color:#556;gap:3px}.ln label.w{flex:1 1 260px}.ln label.w input{width:100%}'
 . 'table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e3e8ef;text-align:left;vertical-align:top;font-size:15px}th{font-size:13px;color:#556;background:#f6f8fb}'
 . '.msg{background:#e6f6ea;border:1px solid #9bd2a8;border-radius:10px;padding:10px 14px;margin-bottom:14px}.err{background:#fde2e2;border-color:#f3a7a7}'
 . '.st{display:inline-block;font-size:13px;border-radius:999px;padding:3px 10px;background:#e6ebf3;white-space:nowrap}.st-hotovo{background:#d9f2e0;color:#14653a}.st-nova,.st-nebyl,.st-nahradni{background:#fff0cc;color:#7a5200}.st-zruseno,.st-odmitl{background:#fde2e2;color:#9b1c1c}'
 . '.bar{height:10px;background:#e6ebf3;border-radius:6px;overflow:hidden}.bar i{display:block;height:100%;background:#15803d}'
 . '.byty a.byt{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:13px 12px;border-bottom:1px solid #e3e8ef;text-decoration:none;color:inherit}.byty a.byt:active{background:#eef3fb}'
 . '.stavy{display:flex;flex-wrap:wrap;gap:8px}.stavy label{flex:1 1 140px;border:2px solid #c9d1dc;border-radius:12px;padding:12px;text-align:center;font-weight:600;cursor:pointer}.stavy input{display:none}.stavy label:has(input:checked){border-color:#0e7490;background:#e3f4f7}'
 . '.chk label{display:flex;align-items:center;gap:10px;padding:9px 0;font-size:17px}.chk input{width:24px;height:24px}'
 . '.okna{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:8px}.okna label{display:flex;flex-direction:column;font-size:13px;color:#556}.okna input{width:100%}'
 . 'canvas#podpis{width:100%;height:180px;border:2px dashed #9aa7b8;border-radius:12px;background:#fff;touch-action:none}'
 . 'details>summary{cursor:pointer;color:#0e7490;font-weight:600;margin:6px 0}.r{text-align:right}.nowrap{white-space:nowrap}'
 . '#vysl{border:1px solid #c9d1dc;border-radius:10px;margin-top:4px;background:#fff;max-height:300px;overflow:auto}#vysl div{padding:9px 12px;border-bottom:1px solid #eef1f5;cursor:pointer}#vysl div:hover{background:#eef3fb}'
 . '@media print{.noprint{display:none!important}body{background:#fff;font-size:12px}main{max-width:none;padding:0}.box{box-shadow:none;padding:0;margin-bottom:10px}th,td{font-size:11px;padding:3px 5px;border:1px solid #999}h1{font-size:18px}h2{font-size:15px}.pb{page-break-before:always}}';

const SO_STAVY = ['' => 'Zatím nic', 'hotovo' => 'Hotovo', 'nebyl' => 'Nebyl doma', 'odmitl' => 'Odmítl', 'nahradni' => 'Náhradní termín'];
const SO_ZKRATKY = ['O' => 'Okapničky', 'P' => 'Krytky pantů', 'BD' => 'Balkonové dveře', 'PARAPET' => 'Prohnutý parapet', 'KLIKA' => 'Povolená klika'];
const SO_KONTROLY = ['k_kovani' => 'Kování – seřízení', 'k_ramy' => 'Rámy a křídla', 'k_tesneni' => 'Těsnění – promazání', 'k_zlabky' => 'Odtokové žlábky – čištění'];
const SO_OKNA = ['o3' => '3křídlé', 'o2' => '2křídlé', 'o1' => '1křídlé', 'bd' => 'Balk. dveře', 'vk' => 'Výklopky', 'dv' => 'Dveře'];
const SO_TYPY = ['byt' => 'Byt', 'chodba' => 'Chodba', 'ostatni' => 'Ostatní (sklep, prodejna…)'];

function so_tabulky(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_svj (id INTEGER PRIMARY KEY AUTOINCREMENT, nazev TEXT NOT NULL, ic TEXT, adresa TEXT, kontakt TEXT, telefon TEXT, email TEXT, poznamka TEXT, vytvoreno TEXT, upraveno TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_vchod (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER NOT NULL, cp TEXT NOT NULL, ulice TEXT, poradi INTEGER DEFAULT 0)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_byt (id INTEGER PRIMARY KEY AUTOINCREMENT, vchod_id INTEGER NOT NULL, typ TEXT NOT NULL DEFAULT 'byt', patro TEXT, cislo TEXT, velikost TEXT, jmeno TEXT, vztah TEXT, najemce TEXT, telefon TEXT, poznamka TEXT,
    o3 INTEGER DEFAULT 0, o2 INTEGER DEFAULT 0, o1 INTEGER DEFAULT 0, bd INTEGER DEFAULT 0, vk INTEGER DEFAULT 0, dv INTEGER DEFAULT 0, poradi INTEGER DEFAULT 0, aktivni INTEGER DEFAULT 1)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_smlouva (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER NOT NULL, cislo TEXT, od TEXT, interval_let INTEGER DEFAULT 1, pocet INTEGER DEFAULT 1,
    limit_opravy REAL, pokuta REAL, havarie_h INTEGER, zaruka_mes INTEGER, nahradni INTEGER DEFAULT 1, nabidka TEXT, poznamka TEXT, aktivni INTEGER DEFAULT 1, vytvoreno TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_servis (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER NOT NULL, smlouva_id INTEGER, nazev TEXT, stav TEXT DEFAULT 'probiha', vytvoreno TEXT, uzavreno TEXT, poznamka TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_plan (id INTEGER PRIMARY KEY AUTOINCREMENT, servis_id INTEGER NOT NULL, vchod_id INTEGER NOT NULL, datum TEXT, cas TEXT, UNIQUE(servis_id, vchod_id))");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_zaznam (id INTEGER PRIMARY KEY AUTOINCREMENT, servis_id INTEGER NOT NULL, byt_id INTEGER NOT NULL, stav TEXT DEFAULT '',
    k_kovani INTEGER DEFAULT 0, k_ramy INTEGER DEFAULT 0, k_tesneni INTEGER DEFAULT 0, k_zlabky INTEGER DEFAULT 0, zkratky TEXT, zavada TEXT, oprava TEXT, max_cena REAL, opraveno_hned TEXT,
    podpis TEXT, podepsal TEXT, technik TEXT, kdy TEXT, upraveno TEXT, UNIQUE(servis_id, byt_id))");
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_byt_vchod ON so_byt(vchod_id)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_vchod_svj ON so_vchod(svj_id)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_zaznam_servis ON so_zaznam(servis_id)');
}

function so_dir(int $zid): string { global $DATA; return $DATA . '/okna/' . $zid; }
function so_fotky(int $zid): array {
  $o = [];
  foreach (glob(so_dir($zid) . '/*.jpg') ?: [] as $f) { $n = basename($f); if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) $o[] = $n; }
  sort($o); return $o;
}
function so_datum(?string $d): string { $t = $d ? strtotime($d) : false; return $t ? date('j. n. Y', $t) : ''; }
function so_cislo_sort(string $s): string { return preg_match('/^\d+/', $s, $m) ? str_pad($m[0], 6, '0', STR_PAD_LEFT) . $s : 'zzzzzz' . $s; }

function so_svj(PDO $pdo, int $id): ?array { $st = $pdo->prepare('SELECT * FROM so_svj WHERE id=?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function so_vchody(PDO $pdo, int $svj): array { $st = $pdo->prepare('SELECT * FROM so_vchod WHERE svj_id=? ORDER BY poradi, cp'); $st->execute([$svj]); return $st->fetchAll(PDO::FETCH_ASSOC); }
function so_byty(PDO $pdo, int $vchod): array {
  $st = $pdo->prepare('SELECT * FROM so_byt WHERE vchod_id=? AND aktivni=1'); $st->execute([$vchod]); $r = $st->fetchAll(PDO::FETCH_ASSOC);
  $typ = ['byt' => 0, 'chodba' => 1, 'ostatni' => 2];
  usort($r, fn($a, $b) => [$typ[$a['typ']] ?? 3, (int)$a['poradi'], so_cislo_sort((string)$a['cislo']), (string)$a['patro']] <=> [$typ[$b['typ']] ?? 3, (int)$b['poradi'], so_cislo_sort((string)$b['cislo']), (string)$b['patro']]);
  return $r;
}
function so_smlouvy(PDO $pdo, int $svj): array { $st = $pdo->prepare('SELECT * FROM so_smlouva WHERE svj_id=? ORDER BY aktivni DESC, od DESC, id DESC'); $st->execute([$svj]); return $st->fetchAll(PDO::FETCH_ASSOC); }
function so_servisy(PDO $pdo, int $svj): array { $st = $pdo->prepare('SELECT * FROM so_servis WHERE svj_id=? ORDER BY id DESC'); $st->execute([$svj]); return $st->fetchAll(PDO::FETCH_ASSOC); }
function so_byt_nazev(array $b): string {
  if ($b['typ'] === 'chodba') return 'Chodba ' . trim((string)$b['patro']);
  if ($b['typ'] === 'ostatni') return trim((string)$b['jmeno']) !== '' ? (string)$b['jmeno'] : 'Ostatní';
  return 'Byt ' . trim((string)$b['cislo']);
}

// Stav smlouvy: kolikátý servis je další a do kdy (první do 6 měsíců od podpisu, další po intervalu let od data podpisu).
function so_smlouva_stav(PDO $pdo, array $s): array {
  $st = $pdo->prepare('SELECT COUNT(*) FROM so_servis WHERE smlouva_id=?'); $st->execute([$s['id']]); $hotovo = (int)$st->fetchColumn();
  $pocet = max(1, (int)$s['pocet']); $int = max(1, (int)$s['interval_let']);
  if ($hotovo >= $pocet) return ['hotovo' => $hotovo, 'pocet' => $pocet, 'dalsi' => null, 'do' => '', 'konec' => true];
  $od = strtotime((string)$s['od']) ?: time();
  $do = $hotovo === 0 ? strtotime('+6 months', $od) : strtotime('+' . ($hotovo * $int) . ' years', $od);
  return ['hotovo' => $hotovo, 'pocet' => $pocet, 'dalsi' => $hotovo + 1, 'do' => date('Y-m-d', $do), 'konec' => false];
}

// Souhrn servisu po vchodech: počty bytů podle stavu, okna hotových bytů a chodeb (podle počtů u bytu), úspěšnost.
function so_souhrn(PDO $pdo, array $servis): array {
  $out = [];
  $zst = $pdo->prepare('SELECT * FROM so_zaznam WHERE servis_id=?'); $zst->execute([$servis['id']]);
  $z = []; foreach ($zst->fetchAll(PDO::FETCH_ASSOC) as $r) $z[(int)$r['byt_id']] = $r;
  foreach (so_vchody($pdo, (int)$servis['svj_id']) as $v) {
    $s = ['vchod' => $v, 'bytu' => 0, 'hotovo' => 0, 'nebyl' => 0, 'odmitl' => 0, 'nahradni' => 0, 'nic' => 0, 'okna' => array_fill_keys(array_keys(SO_OKNA), 0), 'okna_chodby' => array_fill_keys(array_keys(SO_OKNA), 0), 'nepritomni' => [], 'zavady' => [], 'byty' => []];
    foreach (so_byty($pdo, (int)$v['id']) as $b) {
      $r = $z[(int)$b['id']] ?? null; $stav = $r['stav'] ?? '';
      $s['byty'][] = ['byt' => $b, 'z' => $r];
      if ($b['typ'] === 'byt') {
        $s['bytu']++;
        if ($stav === 'hotovo') $s['hotovo']++; elseif ($stav === 'nebyl') $s['nebyl']++; elseif ($stav === 'odmitl') $s['odmitl']++; elseif ($stav === 'nahradni') $s['nahradni']++; else $s['nic']++;
        if (in_array($stav, ['nebyl', 'nahradni'], true)) $s['nepritomni'][] = $b;
      }
      if ($stav === 'hotovo') foreach (SO_OKNA as $k => $_) { if ($b['typ'] === 'byt') $s['okna'][$k] += (int)$b[$k]; else $s['okna_chodby'][$k] += (int)$b[$k]; }
      if ($r && (trim((string)$r['zavada']) !== '' || trim((string)$r['oprava']) !== '' || trim((string)$r['zkratky']) !== '')) $s['zavady'][] = ['byt' => $b, 'z' => $r];
    }
    $s['uspesnost'] = $s['bytu'] ? round(100 * $s['hotovo'] / $s['bytu'], 1) : 0;
    $out[] = $s;
  }
  return $out;
}

// ───── jednotlivci (tabulka so_jed) ─────
const SJ_STAVY = ['nova' => 'K vyřízení', 'hotovo' => 'Hotovo', 'zruseno' => 'Zrušeno'];
const SJ_OPAKOVAT = [0 => 'neopakovat', 1 => 'za 1 rok', 2 => 'za 2 roky'];

function sj_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_jed (id INTEGER PRIMARY KEY AUTOINCREMENT, klic TEXT, jmeno TEXT NOT NULL, telefon TEXT, email TEXT, ulice TEXT, mesto TEXT, psc TEXT,
    zadost_id INTEGER, predchozi_id INTEGER, popis TEXT, stav TEXT DEFAULT 'nova', termin TEXT, cas TEXT,
    k_kovani INTEGER DEFAULT 0, k_ramy INTEGER DEFAULT 0, k_tesneni INTEGER DEFAULT 0, k_zlabky INTEGER DEFAULT 0, zkratky TEXT,
    o3 INTEGER DEFAULT 0, o2 INTEGER DEFAULT 0, o1 INTEGER DEFAULT 0, bd INTEGER DEFAULT 0, vk INTEGER DEFAULT 0, dv INTEGER DEFAULT 0,
    zavada TEXT, oprava TEXT, max_cena REAL, cena REAL, opraveno_hned TEXT, podpis TEXT, podepsal TEXT, technik TEXT, provedeno TEXT,
    opakovat INTEGER DEFAULT 0, protokol TEXT, protokol_poslan TEXT, poznamka TEXT, vytvoril TEXT, vytvoreno TEXT, upraveno TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_jed_klic ON so_jed(klic)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_jed_zadost ON so_jed(zadost_id)');
}
function sj_dir(int $id): string { global $DATA; return $DATA . '/okna/j' . $id; }
function sj_fotky(int $id): array {
  $o = [];
  foreach (glob(sj_dir($id) . '/*.jpg') ?: [] as $f) { $n = basename($f); if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) $o[] = $n; }
  sort($o); return $o;
}
function sj_nacti(PDO $pdo, int $id): ?array { $st = $pdo->prepare('SELECT * FROM so_jed WHERE id=?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: null; }
function sj_dalsi(array $r): string {   // kdy je čas na další servis (jen hotové s opakováním)
  if ($r['stav'] !== 'hotovo' || (int)$r['opakovat'] <= 0) return '';
  $t = strtotime((string)($r['provedeno'] ?: $r['termin'] ?: $r['upraveno'])); return $t ? date('Y-m-d', strtotime('+' . (int)$r['opakovat'] . ' years', $t)) : '';
}
function sj_adresa(array $r): string { return implode(', ', array_filter([trim((string)$r['ulice']), trim(trim((string)$r['psc']) . ' ' . trim((string)$r['mesto']))])); }
function sj_jdi(string $url): void { header('Location: ' . $url); exit; }
function sj_json(array $x): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($x, JSON_UNESCAPED_UNICODE); exit; }
// stav poptávky v kartotéce (jen když nejde o storno / vyřízeno)
function sj_poptavka_stav(PDO $pdo, ?int $zid, string $stav): void {
  if (!$zid) return;
  try {
    $pdo = db();   // poptávky jsou v kartotéce dveří
    $st = $pdo->prepare('SELECT stav FROM zadosti WHERE id=?'); $st->execute([$zid]); $s = (string)$st->fetchColumn();
    if ($s === '' || in_array($s, ['storno', 'vyrizeno'], true) || $s === $stav) return;
    if ($stav === 'rozpracovano' && $s !== 'nove') return;
    $pdo->prepare('UPDATE zadosti SET stav=? WHERE id=?')->execute([$stav, $zid]);
    if (function_exists('hist_pis')) hist_pis($pdo, $zid, 'servis oken: stav ' . ($stav === 'vyrizeno' ? 'Vyřízeno' : 'Rozpracováno'), function_exists('uziv_jmeno') ? uziv_jmeno() : '');
  } catch (Throwable $x) {}
}

// vlastní zákazníci aplikace: sestavení ze servisů jednotlivců podle klíče (poslední servis určuje kontakty)
function ok_zakaznici(PDO $pdo): array {
  $o = [];
  foreach ($pdo->query("SELECT * FROM so_jed WHERE klic IS NOT NULL AND klic<>'' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['klic']; $c = $o[$k] ?? ['klic' => $k, 'servisy' => []];
    foreach (['jmeno', 'telefon', 'email', 'ulice', 'mesto', 'psc'] as $f) if (trim((string)$r[$f]) !== '') $c[$f] = (string)$r[$f];
    $c['servisy'][] = $r; $o[$k] = $c + ['jmeno' => '', 'telefon' => '', 'email' => '', 'ulice' => '', 'mesto' => '', 'psc' => ''];
  }
  return $o;
}

$pdo = ok_db(); so_tabulky($pdo); sj_tabulka($pdo);
