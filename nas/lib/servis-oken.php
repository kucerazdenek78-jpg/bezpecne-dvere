<?php
// Servis oken (zkušební verze 1. 11. 2026): SVJ / družstva, vchody, byty, servisní smlouvy, servisy (prohlídky domu),
// zápisy z bytů (kontroly, stav, závady, podpis, fotky) a report pro SVJ. Používá servis-oken.php (po přihlášení).
// Vlastní tabulky so_*; do ostatních tabulek kartotéky nezasahuje. Fotky a podpisy: $DATA/servis-oken/<id zápisu>/.
// Patří na NAS do /docker/formulare/app/lib/servis-oken.php.

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

function so_dir(int $zid): string { global $DATA; return $DATA . '/servis-oken/' . $zid; }
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
