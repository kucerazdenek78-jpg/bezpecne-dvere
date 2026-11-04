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
  $pol = [['index.php', 'Jednotlivci', 'jed', '🏠'], ['svj.php', 'SVJ, družstva a firmy', 'svj', '🏢'], ['zakaznici.php', 'Zákazníci', 'zak', '👥'], ['poptavky.php', 'Poptávky z webu', 'popt', '📨'], ['nabidky.php', 'Nabídky', 'nab', '📄'], ['cenik.php', 'Ceník', 'cen', '💰']];
  $n = 0; try { $n = count(ok_poptavky_nove()); } catch (Throwable $x) {}
  $ns = 0; try { $ns = count(so_pripominky(ok_db())['termin']); } catch (Throwable $x) {}
  echo '<style>.okbar{position:sticky;top:0;z-index:60;display:flex;flex-wrap:wrap;gap:4px;align-items:center;background:#0e4f5c;padding:8px 12px}.okbar b.ap{color:#fff;font:700 16px system-ui,Arial,sans-serif;margin-right:10px}'
     . '.okbar a{color:#d8f0f3;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}.okbar a:hover{background:#13697a}.okbar a.on{background:#0891b2;color:#fff}'
     . '.okbar i.n{font-style:normal;background:#e11d48;color:#fff;border-radius:999px;padding:1px 7px;margin-left:5px;font-size:12px}.oksw{display:inline-flex;gap:2px;background:#0a3a45;border-radius:999px;padding:3px;margin-right:12px}.okbar .oksw a{padding:8px 16px;border-radius:999px;font-size:15px}.okbar .oksw a.on{background:#fff;color:#0e4f5c}@media print{.okbar{display:none!important}}</style><nav class="okbar"><span class="oksw" role="group" aria-label="Servis: okna nebo dveře"><a href="../servis.php">🚪 Dveře</a><a href="index.php" class="on">🪟 Okna</a></span>';   // 4. 11. 2026: přepínač Okna / Dveře místo nápisu Servis oken
  foreach ($pol as [$h, $t, $k, $ik]) echo '<a href="' . $h . '" class="' . ($k === $akt ? 'on' : '') . '">' . $ik . ' ' . htmlspecialchars($t) . ($k === 'popt' && $n ? '<i class="n">' . $n . '</i>' : '') . ($k === 'svj' && $ns ? '<i class="n" title="Servis podle smlouvy – blíží se nebo prošel termín">⏰ ' . $ns . '</i>' : '') . '</a>';
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
// Počítají se servisy založené v aplikaci i servisy zapsané do historie (např. ze starších let podle dokumentů na NASu).
function so_smlouva_stav(PDO $pdo, array $s): array {
  $st = $pdo->prepare('SELECT COUNT(*) FROM so_servis WHERE smlouva_id=?'); $st->execute([$s['id']]); $hotovo = (int)$st->fetchColumn();
  $st = $pdo->prepare("SELECT COUNT(*) FROM so_historie WHERE smlouva_id=? AND druh='servis'"); $st->execute([$s['id']]); $hotovo += (int)$st->fetchColumn();
  $pocet = max(1, (int)$s['pocet']); $int = max(1, (int)$s['interval_let']);
  $od = strtotime((string)$s['od']) ?: time();
  $plat = trim((string)($s['platnost_do'] ?? '')) !== '' ? (string)$s['platnost_do'] : date('Y-m-d', strtotime('+' . ($pocet * $int) . ' years', $od));
  $z = ['hotovo' => $hotovo, 'pocet' => $pocet, 'platnost_do' => $plat, 'vyprsela' => strtotime($plat) < strtotime('today'), 'podepsana' => ($s['stav_podpisu'] ?? 'podepsana') === 'podepsana'];
  if ($hotovo >= $pocet) return $z + ['dalsi' => null, 'do' => '', 'konec' => true, 'dni' => null];
  $do = $hotovo === 0 ? strtotime('+6 months', $od) : strtotime('+' . ($hotovo * $int) . ' years', $od);
  return $z + ['dalsi' => $hotovo + 1, 'do' => date('Y-m-d', $do), 'konec' => false, 'dni' => (int)floor(($do - strtotime('today')) / 86400)];
}

// ───── zákazníci (SVJ, družstva, firmy), stav smluv, historie servisů a připomínky ─────
const SO_TYPY_ZAK = ['svj' => 'SVJ / společenství vlastníků', 'druzstvo' => 'Bytové družstvo', 'dum' => 'Dům / blok', 'firma' => 'Firma', 'instituce' => 'Úřad / instituce'];
const SO_PODPIS = ['podepsana' => 'podepsaná', 'overit' => 'ověřit, zda je podepsaná', 'navrh' => 'jen návrh (nepodepsaná)'];
const SO_HIST = ['servis' => '🛠 Servis proveden', 'smlouva' => '✍ Smlouva', 'nabidka' => '📄 Nabídka', 'faktura' => '🧾 Faktura / dodací list', 'zavady' => '🔍 Závady / návrh oprav', 'jine' => '📎 Jiné'];
function so_rozsireni(PDO $pdo): void {
  $sl = fn($t) => array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), 'name');
  $s = $sl('so_svj'); foreach (['typ' => "TEXT DEFAULT 'svj'", 'slozka' => 'TEXT'] as $k => $d) if (!in_array($k, $s, true)) $pdo->exec("ALTER TABLE so_svj ADD COLUMN $k $d");
  $s = $sl('so_smlouva'); foreach (['stav_podpisu' => "TEXT DEFAULT 'podepsana'", 'platnost_do' => 'TEXT', 'dokument' => 'TEXT', 'pripomenout_dni' => 'INTEGER DEFAULT 60'] as $k => $d) if (!in_array($k, $s, true)) $pdo->exec("ALTER TABLE so_smlouva ADD COLUMN $k $d");
  $pdo->exec("CREATE TABLE IF NOT EXISTS so_historie (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER NOT NULL, smlouva_id INTEGER, datum TEXT, druh TEXT DEFAULT 'servis', popis TEXT, dokument TEXT, vytvoreno TEXT, kdo TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS so_historie_svj ON so_historie(svj_id)');
}
function so_historie(PDO $pdo, int $svj): array { $st = $pdo->prepare('SELECT * FROM so_historie WHERE svj_id=? ORDER BY datum DESC, id DESC'); $st->execute([$svj]); return $st->fetchAll(PDO::FETCH_ASSOC); }
// poslední provedený servis: z historie nebo uzavřený servis v aplikaci
function so_posledni_servis(PDO $pdo, int $svj): string {
  $a = (string)$pdo->query("SELECT MAX(datum) FROM so_historie WHERE druh='servis' AND svj_id=" . $svj)->fetchColumn();
  $b = (string)$pdo->query("SELECT MAX(substr(uzavreno,1,10)) FROM so_servis WHERE stav='uzavreno' AND svj_id=" . $svj)->fetchColumn();
  return max($a, $b);
}
// odkaz do File Station v DSM na složku zákazníka na NASu
function so_slozka_odkaz(string $cesta): string {
  $cesta = trim($cesta); if ($cesta === '') return '';
  return '<a href="https://kucerazdmost.quickconnect.to/?launchApp=SYNO.SDS.App.FileStation3.Instance&amp;launchParam=' . htmlspecialchars(rawurlencode('openfile=' . $cesta)) . '" target="_blank" rel="noopener" title="Otevřít ve File Station (DSM)">📂</a>';
}
// připomínky: podepsané platné smlouvy s blížícím se / prošlým termínem, splněné nebo vypršelé (nabídnout novou), návrhy k ověření
function so_pripominky(PDO $pdo): array {
  $o = ['termin' => [], 'nova' => [], 'overit' => []];
  foreach ($pdo->query('SELECT m.*, j.nazev AS svj_nazev FROM so_smlouva m JOIN so_svj j ON j.id=m.svj_id WHERE m.aktivni=1 ORDER BY m.od')->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $ss = so_smlouva_stav($pdo, $m); $x = ['sm' => $m, 'ss' => $ss];
    if (!$ss['podepsana']) { $o['overit'][] = $x; continue; }
    if ($ss['konec'] || $ss['vyprsela']) { $o['nova'][] = $x; continue; }
    if ($ss['dni'] !== null && $ss['dni'] <= max(1, (int)($m['pripomenout_dni'] ?? 60))) $o['termin'][] = $x;
  }
  usort($o['termin'], fn($a, $b) => $a['ss']['dni'] <=> $b['ss']['dni']);
  return $o;
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


// ───── ceník, slevy, příplatky, nabídky (3. 11. 2026) ─────
// Ceník vychází z „Servis 48 – ceník 2022 koncový zákazník“ (NAS 7 SERVIS). Ceny se ukládají BEZ DPH, DPH podle položky.
// Ceny 2022 byly s DPH 15 % (byty) – bez DPH: 550 / 660 / 770 / 880 / 990 / RD 1320, firmy 133 Kč za dvoukřídlé okno (21 %).
const OK_SKUPINY = ['byt' => 'Servis bytu / domu (podle velikosti)', 'okno' => 'Servis po oknech (firmy, jednotlivá okna)', 'prace' => 'Hodinové sazby a práce', 'doprava' => 'Doprava', 'jine' => 'Ostatní'];
const OK_NAST_VYCHOZI = [
  'dph_byt' => 12, 'dph_firma' => 21,
  'sleva_mn1_od' => 3, 'sleva_mn1' => 10, 'sleva_mn2_od' => 7, 'sleva_mn2' => 14,
  'sleva_sml3' => 23, 'sleva_sml5' => 30,
  'priplatek_vecer' => 50, 'priplatek_vikend' => 75, 'priplatek_expres' => 75,
  'platnost_dni' => 30, 'zaokrouhleni' => 1,
  'text_rozsah' => "kontrola rámů a křídel oken\nkontrola těsnících gum a jejich případné ošetření\nkontrola kování, jeho seřízení a promazání\nkontrola odtokových žlábků a jejich případné čištění",
  'text_prubeh' => "📅 Termíny | Podle telefonické dohody Vám pošleme návrh termínů servisu. Poprosíme Vás jen o odsouhlasení zpět e-mailem.\n📌 Vývěsky | Do jednoho dne po odsouhlasení polepíme vchody vývěskami. Máte-li společnou nástěnku nebo web, umístěte je prosím i tam.\n🔧 Servis v bytech | Servisní technik projde okna a dveře: seřízení a promazání kování, kontrola rámů, křídel, těsnění a odtokových žlábků.\n🚪 Nepřítomní | Kdo nebude doma, dostane oznámenku s kontaktem a nabídneme mu jeden náhradní termín.\n🪪 Ověření | Technik má vizitku se jménem a razítkem firmy. Telefon pro ověření je stejný na vývěskách i oznámenkách. Během servisu je na telefonu asistentka, která celou akci koordinuje.\n📋 Zápis a návrh oprav | Po servisu dostanete zápis: kde byl servis proveden, kdo nebyl doma a návrh oprav s maximální cenou.",
  'text_oprava' => "🔧 Servis a prohlídka | Okno projdeme, zkusíme seřídit a prohlédneme – klasický servis. Většinu potíží vyřeší už seřízení a promazání kování.\n📝 Návrh opravy | Pokud okno nejde seřídit a některý díl okna nebo kování nefunguje, navrhneme zmapování závady.\n🔍 Zmapování závady (za příplatek) | Okno vysadíme, rozebereme a zjistíme příčinu. Často jde jen o zaseknutí, například nečistotou po zednických pracích – to odstraníme hned.\n🛠 Provizorní montáž | Okno provizorně smontujeme a vrátíme na místo, aby se dalo zavřít až do opravy.\n🏭 Poptávka dílu | Vadný díl přesně určíme a poptáme u výrobce.\n💰 Nabídka opravy | Pošleme Vám cenu opravy. Opravu provedeme až po Vašem odsouhlasení.",
  'text_podminky_oprava' => "Zmapování závady provádíme jen se souhlasem zákazníka a účtujeme ho podle ceníku (za každé okno).\nProvizorní montáž slouží k uzavření okna do doby opravy a nenahrazuje funkci vadného dílu.\nCena dílů vychází z aktuální nabídky výrobce, dodací lhůta dílů se řídí výrobcem.\nOpravu provedeme až po odsouhlasení nabídky e-mailem nebo písemně.\nPokud je příčinou jen nečistota nebo zaseknutí, odstraníme ji v rámci zmapování a další oprava není potřeba.",
  'text_podminky' => 'Slevy není možné sčítat ani kombinovat se slevovými kupóny – použije se vždy ta vyšší. Na dopravu se slevy nevztahují. Ceny opakovaného servisu platí po podpisu servisní smlouvy.',
];
function ok_cenik_tabulky(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_cenik (id INTEGER PRIMARY KEY AUTOINCREMENT, kod TEXT UNIQUE, nazev TEXT NOT NULL, skupina TEXT DEFAULT 'jine', jednotka TEXT DEFAULT 'ks', cena REAL DEFAULT 0, dph REAL DEFAULT 21, velikost TEXT, poradi INTEGER DEFAULT 0, aktivni INTEGER DEFAULT 1, poznamka TEXT, upraveno TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_nastaveni (klic TEXT PRIMARY KEY, hodnota TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_cenik_historie (id INTEGER PRIMARY KEY AUTOINCREMENT, kdy TEXT, kdo TEXT, popis TEXT, data TEXT)");
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_nabidka (id INTEGER PRIMARY KEY AUTOINCREMENT, cislo TEXT, typ TEXT, svj_id INTEGER, jed_id INTEGER, nazev TEXT, adresa TEXT, ic TEXT, kontakt TEXT, telefon TEXT, email TEXT,
    datum TEXT, platnost TEXT, smlouva INTEGER DEFAULT 0, interval_let INTEGER DEFAULT 1, priplatek TEXT, sleva_rucne REAL, polozky TEXT, poznamka TEXT, stav TEXT DEFAULT 'koncept', vytvoril TEXT, vytvoreno TEXT, upraveno TEXT)");
  $sl = array_column($pdo->query('PRAGMA table_info(so_jed)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('kategorie', $sl, true)) $pdo->exec('ALTER TABLE so_jed ADD COLUMN kategorie TEXT');
  if ((int)$pdo->query('SELECT COUNT(*) FROM ok_cenik')->fetchColumn() === 0) {   // výchozí ceník 2022
    $v = [
      ['B1', 'Servis oken a dveří – byt 1+1 (0+2)', 'byt', 'byt', 550, 'dph_byt', '1+1'], ['B2', 'Servis oken a dveří – byt 2+1 (2+kk)', 'byt', 'byt', 660, 'dph_byt', '2+1'],
      ['B3', 'Servis oken a dveří – byt 3+1 (3+kk)', 'byt', 'byt', 770, 'dph_byt', '3+1'], ['B4', 'Servis oken a dveří – byt 4+1 (4+kk)', 'byt', 'byt', 880, 'dph_byt', '4+1'],
      ['B5', 'Servis oken a dveří – byt 5+1 (5+kk)', 'byt', 'byt', 990, 'dph_byt', '5+1'], ['RD', 'Servis oken a dveří – rodinný dům', 'byt', 'dům', 1320, 'dph_byt', 'RD'],
      ['F2', 'Servis okna dvoukřídlého – firmy', 'okno', 'okno', 133, 'dph_firma', ''],
      ['H2', 'Hodinová sazba – 2 servisní technici', 'prace', 'hod', 1000, 'dph_byt', ''], ['H1', 'Hodinová sazba – 1 servisní technik', 'prace', 'hod', 500, 'dph_byt', ''],
      ['D0', 'Doprava – Most', 'doprava', 'paušál', 165.29, 'dph_firma', ''], ['D5', 'Doprava – do 5 km', 'doprava', 'paušál', 206.61, 'dph_firma', ''],
      ['D10', 'Doprava – 6 až 10 km', 'doprava', 'paušál', 330.58, 'dph_firma', ''], ['D20', 'Doprava – 11 až 20 km', 'doprava', 'paušál', 413.22, 'dph_firma', ''],
      ['D30', 'Doprava – 21 až 30 km', 'doprava', 'paušál', 661.16, 'dph_firma', ''], ['D40', 'Doprava – 31 až 40 km', 'doprava', 'paušál', 826.45, 'dph_firma', ''],
      ['DKM', 'Doprava nad 40 km (tam i zpět)', 'doprava', 'km', 13.22, 'dph_firma', ''],
    ];
    $st = $pdo->prepare('INSERT INTO ok_cenik (kod, nazev, skupina, jednotka, cena, dph, velikost, poradi, upraveno) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($v as $i => $r) $st->execute([$r[0], $r[1], $r[2], $r[3], $r[4], OK_NAST_VYCHOZI[$r[5]], $r[6], $i, date('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT INTO ok_cenik_historie (kdy, kdo, popis, data) VALUES (?,?,?,?)')->execute([date('Y-m-d H:i:s'), 'systém', 'Výchozí ceník podle „Servis 48 – ceník 2022 koncový zákazník“', json_encode(ok_cenik($pdo), JSON_UNESCAPED_UNICODE)]);
  }
}
// Úprava ceníku podle nabídek 2025 (Blok 8 – 25NA00007 z 9. 4. 2025, Blok 78 – 25NA00008 z 11. 8. 2025), schváleno 3. 11. 2026:
// byty 1+1 660, 2+1 790, 3+1 890 (z nabídek), 4+1 1060, 5+1 1190, RD 1580 (dopočteno +20 %), okna a dveře z nabídek, nižší slevy.
function ok_cenik_migrace(PDO $pdo): void {
  $st = $pdo->prepare('SELECT hodnota FROM ok_nastaveni WHERE klic=?'); $st->execute(['cenik_verze']); if ((int)$st->fetchColumn() >= 2) return;
  $ted = date('Y-m-d H:i:s');
  $pdo->prepare('INSERT INTO ok_cenik_historie (kdy, kdo, popis, data) VALUES (?,?,?,?)')->execute([$ted, function_exists('uziv_jmeno') ? uziv_jmeno() : 'systém', 'Před úpravou podle nabídek 2025', json_encode(['cenik' => ok_cenik($pdo), 'nastaveni' => ok_nast($pdo)], JSON_UNESCAPED_UNICODE)]);
  $pdo->beginTransaction();
  $up = $pdo->prepare('UPDATE ok_cenik SET cena=?, nazev=?, upraveno=? WHERE kod=?');
  foreach ([['B1', 660, 'Servis oken a dveří – byt 1+1 (0+2)'], ['B2', 790, 'Servis oken a dveří – byt 2+1 (2+kk)'], ['B3', 890, 'Servis oken a dveří – byt 3+1 (3+kk)'], ['B4', 1060, 'Servis oken a dveří – byt 4+1 (4+kk)'],
    ['B5', 1190, 'Servis oken a dveří – byt 5+1 (5+kk)'], ['RD', 1580, 'Servis oken a dveří – rodinný dům'], ['F2', 220, 'Servis okna dvoukřídlého – firmy, společné prostory']] as [$k, $c, $n]) $up->execute([$c, $n, $ted, $k]);
  $ins = $pdo->prepare('INSERT OR IGNORE INTO ok_cenik (kod, nazev, skupina, jednotka, cena, dph, velikost, poradi, upraveno) VALUES (?,?,?,?,?,?,?,?,?)');
  $dph = (float)(ok_nast($pdo)['dph_byt'] ?? 12);
  foreach ([['O1', 'Seřízení plastového okna 1křídlého, promazání kování', 120, 1], ['O2', 'Seřízení plastového okna 2křídlého, promazání kování', 181, 2], ['O3', 'Seřízení plastového okna 3křídlého, promazání kování', 211, 3],
    ['DV1', 'Seřízení jednokřídlých dveří, promazání kování', 450, 4]] as [$k, $n, $c, $p]) $ins->execute([$k, $n, 'okno', 'ks', $c, $dph, '', $p, $ted]);
  $s = $pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota');
  foreach (['sleva_mn1' => 5, 'sleva_mn2' => 7, 'sleva_sml3' => 20, 'sleva_sml5' => 30, 'cenik_verze' => 2] as $k => $v) $s->execute([$k, (string)$v]);
  $pdo->commit();
  $pdo->prepare('INSERT INTO ok_cenik_historie (kdy, kdo, popis, data) VALUES (?,?,?,?)')->execute([$ted, 'systém', 'Ceník podle nabídek 2025: byty +15,6 až +20 %, okna a dveře, slevy 5 / 7 / 20 / 30 %', json_encode(['cenik' => ok_cenik($pdo), 'nastaveni' => ok_nast($pdo)], JSON_UNESCAPED_UNICODE)]);
}
function ok_cenik_migrace3(PDO $pdo): void {
  $st = $pdo->prepare('SELECT hodnota FROM ok_nastaveni WHERE klic=?'); $st->execute(['cenik_verze']); if ((int)$st->fetchColumn() >= 3) return;
  $ted = date('Y-m-d H:i:s');
  $pdo->prepare('INSERT OR IGNORE INTO ok_cenik (kod, nazev, skupina, jednotka, cena, dph, velikost, poradi, aktivni, poznamka, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(['ZM', 'Zmapování závady – vysazení a rozebrání okna, zjištění příčiny, provizorní zpětná montáž', 'prace', 'okno', 0, (float)(ok_nast($pdo)['dph_byt'] ?? 12), '', 10, 0, 'Doplňte cenu a zapněte „Aktivní“.', $ted]);
  $st->execute(['text_prubeh']); $t = $st->fetchColumn();
  if ($t !== false && strpos((string)$t, '|') === false) $pdo->prepare('DELETE FROM ok_nastaveni WHERE klic=?')->execute(['text_prubeh']);   // starý odstavcový text → nové kroky
  $pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota')->execute(['cenik_verze', '3']);
}
// kroky „Nadpis | popis“ (jeden na řádek) jako vizuální bloky s čísly – do tisku nabídky i do aplikace
function ok_kroky(string $text, string $barva = '#0e7490'): string {
  $o = '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">'; $i = 0;
  foreach (array_filter(array_map('trim', explode("\n", $text))) as $r) {
    [$h, $p] = array_pad(array_map('trim', explode('|', $r, 2)), 2, '');
    $i++; $o .= '<div style="border:1px solid #d6e3e7;border-left:5px solid ' . $barva . ';border-radius:10px;padding:9px 11px;break-inside:avoid;page-break-inside:avoid;background:#fbfdfd">'
      . '<div style="display:flex;gap:8px;align-items:center;margin-bottom:3px"><span style="flex:0 0 24px;height:24px;border-radius:50%;background:' . $barva . ';color:#fff;font:700 13px/24px Arial,sans-serif;text-align:center;-webkit-print-color-adjust:exact;print-color-adjust:exact">' . $i . '</span>'
      . '<b style="font-size:14px">' . htmlspecialchars($h) . '</b></div><div style="font-size:12.5px;color:#334">' . htmlspecialchars($p) . '</div></div>';
  }
  return $o . '</div>';
}
function ok_nast(PDO $pdo): array {
  $o = OK_NAST_VYCHOZI;
  foreach ($pdo->query('SELECT klic, hodnota FROM ok_nastaveni')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) if (array_key_exists($k, $o)) $o[$k] = is_numeric($o[$k]) ? (float)$v : (string)$v;
  return $o;
}
function ok_cenik(PDO $pdo, bool $jenAktivni = false): array {
  return $pdo->query('SELECT * FROM ok_cenik' . ($jenAktivni ? ' WHERE aktivni=1' : '') . ' ORDER BY skupina, poradi, id')->fetchAll(PDO::FETCH_ASSOC);
}
function ok_kc($n, int $des = 0): string { return ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, $des, ',', ' ') . ' Kč'; }
function ok_zaokr(float $v, float $krok): float { return $krok > 0 ? round($v / $krok) * $krok : round($v, 2); }
// velikost bytu z textu („2+1“, „3+kk“, „1:3“ …) → kód položky ceníku; vlastní převody (ok_nastaveni velikost:<text>) mají přednost
function ok_velikost_kod(PDO $pdo, string $vel): string {
  $t = mb_strtolower(trim(preg_replace('/\s+/u', '', $vel))); if ($t === '') return '';
  $st = $pdo->prepare('SELECT hodnota FROM ok_nastaveni WHERE klic=?'); $st->execute(['velikost:' . $t]); $x = $st->fetchColumn(); if ($x !== false) return (string)$x;
  if (preg_match('/^([1-5])\+(1|kk)$/', $t, $m)) return 'B' . $m[1];
  if ($t === '0+2' || $t === '1+0' || $t === 'garsoniera') return 'B1';
  if (preg_match('/^rd|dum|dům/u', $t)) return 'RD';
  return '';   // neznámý zápis (např. „1:3“) – vybere se ručně a uloží se
}
// výpočet nabídky: položky [kod, nazev, jednotka, mnozstvi, cena (bez DPH), dph, slevy (0/1)] → řádky a součty
function ok_spocitej(array $polozky, float $slevaPct, float $priplatekPct): array {
  $r = []; $sum = ['bez' => 0, 'dph' => 0, 's' => 0, 'sleva' => 0];
  foreach ($polozky as $p) {
    $mn = (float)($p['mnozstvi'] ?? 0); if ($mn <= 0) continue;
    $c = (float)($p['cena'] ?? 0); $slevaAno = !empty($p['slevy']);
    $j = $c * (1 + ($slevaAno ? $priplatekPct : 0) / 100);          // příplatek jen na servis/práci, ne na dopravu
    $jc = $slevaAno ? $j * (1 - $slevaPct / 100) : $j;
    $bez = round($jc * $mn, 2); $dph = round($bez * (float)$p['dph'] / 100, 2);
    $r[] = $p + ['jedn_po' => round($jc, 2), 'bez' => $bez, 'dph_kc' => $dph, 's' => round($bez + $dph, 2)];
    $sum['bez'] += $bez; $sum['dph'] += $dph; $sum['s'] += $bez + $dph; $sum['sleva'] += $slevaAno ? round(($j - $jc) * $mn, 2) : 0;
  }
  return ['radky' => $r, 'sum' => array_map(fn($x) => round($x, 2), $sum)];
}
// sleva podle počtu bytů a smlouvy – slevy se nesčítají, platí vyšší
function ok_sleva(array $n, int $pocetBytu, int $smlouva, ?float $rucne = null): array {
  $kand = [];
  if ($pocetBytu >= (int)$n['sleva_mn2_od']) $kand[] = [(float)$n['sleva_mn2'], 'množstevní sleva od ' . (int)$n['sleva_mn2_od'] . ' bytů'];
  elseif ($pocetBytu >= (int)$n['sleva_mn1_od']) $kand[] = [(float)$n['sleva_mn1'], 'množstevní sleva ' . (int)$n['sleva_mn1_od'] . '–' . ((int)$n['sleva_mn2_od'] - 1) . ' bytů'];
  if ($smlouva === 3) $kand[] = [(float)$n['sleva_sml3'], 'servisní smlouva – 3× opakovaný servis'];
  if ($smlouva === 5) $kand[] = [(float)$n['sleva_sml5'], 'servisní smlouva – 5× opakovaný servis'];
  if ($rucne !== null) return [$rucne, 'sleva podle dohody'];
  usort($kand, fn($a, $b) => $b[0] <=> $a[0]);
  return $kand[0] ?? [0.0, ''];
}
function ok_nabidka_cislo(PDO $pdo): string {
  $r = date('Y'); $n = (int)$pdo->query("SELECT COUNT(*) FROM ok_nabidka WHERE cislo LIKE 'NO-$r-%'")->fetchColumn() + 1;
  do { $c = sprintf('NO-%s-%03d', $r, $n++); $st = $pdo->prepare('SELECT 1 FROM ok_nabidka WHERE cislo=?'); $st->execute([$c]); } while ($st->fetchColumn());
  return $c;
}

$pdo = ok_db(); so_tabulky($pdo); sj_tabulka($pdo); ok_cenik_tabulky($pdo); ok_cenik_migrace($pdo); ok_cenik_migrace3($pdo); so_rozsireni($pdo);
// jednorázový import zákazníků ze složek 7 SERVIS/ZÁKAZNÍCI na firemním NASu (3. 11. 2026)
if (is_file(__DIR__ . '/import-nas.php')) { $st = $pdo->prepare('SELECT hodnota FROM ok_nastaveni WHERE klic=?'); $st->execute(['import_nas']); if (!$st->fetchColumn()) require __DIR__ . '/import-nas.php'; }
