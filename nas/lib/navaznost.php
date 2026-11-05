<?php
// Návaznost poptávek a nabídek podle zákazníka – s potvrzením (5. 11. 2026, přání Zdeňka):
// Když nabídka / objednávka nevznikla tlačítkem ze zdroje (nemá z_poptavky / z_nabidky), systém nic sám neuzavírá,
// ale zeptá se: „K objednávce 83/2026 (Zuzana Růžičková) jsou otevřené poptávky téhož zákazníka – které uzavřít?“
// Zákazník = stejný telefon (posledních 9 číslic) nebo e-mail. Předvybraná je jen nejbližší starší poptávka (nabídka) vzniklá po předchozí
// nabídce / objednávce téhož zákazníka; starší jsou v seznamu nezaškrtnuté. Po odpovědi (Uzavřít vybrané / Nic neuzavírat) se dotaz už neukáže.
// Box vypisuje úvodní stránka (terminy_banner v lib/terminy.php); odpověď zpracuje navaznost.php. Ptá se jen u nabídek / objednávek z posledních 60 dní.

function vz_tabulka(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS navaznost_vyreseno (cil_id INTEGER PRIMARY KEY, cas TEXT, kdo TEXT, uzavreno TEXT)');
}
function vz_klice(array $d, string $email = ''): array {
  if (function_exists('bd_zak_klice')) return bd_zak_klice($d, $email);
  $k = []; $t = preg_replace('/\D+/', '', (string)($d['telefon'] ?? '')); if (strlen($t) >= 9) $k[] = 't' . substr($t, -9);
  $e = mb_strtolower(trim((string)(($d['email'] ?? '') ?: $email))); if (filter_var($e, FILTER_VALIDATE_EMAIL)) $k[] = 'e' . $e;
  return $k;
}
// [[cil => záznam, kandidati => [[záznam, predvybrano bool], …]], …]
function vz_navrhy(PDO $pdo, int $jenId = 0): array {
  vz_tabulka($pdo);
  $hotovo = array_flip(array_map('intval', $pdo->query('SELECT cil_id FROM navaznost_vyreseno')->fetchAll(PDO::FETCH_COLUMN)));
  $z = [];
  foreach ($pdo->query("SELECT id, typ, cislo, email, stav, vytvoreno, data_json FROM zadosti WHERE typ IN ('poptavka','nabidka','objednavka_interni') ORDER BY vytvoreno, id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true); if (!is_array($d)) $d = [];
    $r['cis'] = (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']); $r['klice'] = vz_klice($d, (string)$r['email']);
    $r['jmeno'] = trim(((string)($d['jmeno'] ?? '')) . ' ' . ((string)($d['prijmeni'] ?? ''))); $r['model'] = (string)($d['model'] ?? '');
    $r['odkaz'] = $r['typ'] !== 'poptavka' && (trim((string)($d['z_poptavky'] ?? '')) !== '' || trim((string)($d['z_nabidky'] ?? '')) !== '');
    unset($r['data_json']); $z[] = $r;
  }
  $od60 = date('Y-m-d', strtotime('-60 days')); $out = [];
  foreach ($z as $i => $c) {
    if (!in_array($c['typ'], ['nabidka', 'objednavka_interni'], true) || $c['stav'] === 'storno' || $c['odkaz'] || !$c['klice'] || isset($hotovo[$c['id']])) continue;
    if ($jenId && $c['id'] !== $jenId) continue;
    if (strcmp((string)$c['vytvoreno'], $od60) < 0) continue;
    $zdroje = $c['typ'] === 'objednavka_interni' ? ['poptavka', 'nabidka'] : ['poptavka'];
    $kand = []; $prev = [];   // prev[typ zdroje] = čas předchozí nabídky/objednávky téhož zákazníka, která takový zdroj uzavírá
    foreach ($zdroje as $zt) {
      $cil = $zt === 'poptavka' ? ['nabidka', 'objednavka_interni'] : ['objednavka_interni'];
      $prev[$zt] = '';
      for ($j = $i - 1; $j >= 0; $j--) { $p = $z[$j]; if (in_array($p['typ'], $cil, true) && $p['stav'] !== 'storno' && array_intersect($p['klice'], $c['klice'])) { $prev[$zt] = (string)$p['vytvoreno']; break; } }
    }
    $predvyb = [];
    for ($j = $i - 1; $j >= 0; $j--) {
      $s = $z[$j];
      if (!in_array($s['typ'], $zdroje, true) || !in_array($s['stav'], ['nove', 'rozpracovano', 'odeslana'], true) || !array_intersect($s['klice'], $c['klice'])) continue;
      $vyb = !isset($predvyb[$s['typ']]) && ($prev[$s['typ']] === '' || strcmp((string)$s['vytvoreno'], $prev[$s['typ']]) >= 0);
      if ($vyb) $predvyb[$s['typ']] = true;
      $kand[] = [$s, $vyb];
    }
    if ($kand) $out[] = ['cil' => $c, 'kandidati' => $kand];
  }
  return $out;
}
function vz_druh(string $typ): string { return ['poptavka' => 'Poptávka', 'nabidka' => 'Nabídka', 'objednavka_interni' => 'Objednávka'][$typ] ?? $typ; }

// Box s dotazem (úvod a kartotéka). $jenId = jen pro jednu nabídku / objednávku (detail v kartotéce).
function vz_box(int $jenId = 0): void {
  try { $n = vz_navrhy(db(), $jenId); } catch (Throwable $e) { return; }
  if (!$n) return;
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $dm = fn($v) => date('j. n. Y', strtotime((string)$v));
  $STAV = ['nove' => 'Nové', 'rozpracovano' => 'Rozpracováno', 'odeslana' => 'Odeslána'];
  $zpet = (string)($_SERVER['REQUEST_URI'] ?? 'index.php');
  echo '<style>.vzb{max-width:1100px;margin:12px auto;padding:0 12px;font:14px/1.45 system-ui,Arial,sans-serif}.vzb-k{background:#fff7ed;border:1px solid #fdba74;border-radius:12px;padding:10px 14px}.vzb h3{margin:2px 0 4px;font-size:15px;color:#9a3412}.vzb p{margin:0 0 6px;color:#7c2d12}'
     . '.vzb form{background:#fff;border-radius:10px;padding:8px 12px;margin:8px 0;border:1px solid #fed7aa}.vzb label{display:block;padding:3px 0;cursor:pointer}.vzb a{color:#0f2747}.vzb small{color:#64748b}'
     . '.vzb button{font:600 13px system-ui,Arial,sans-serif;border:0;border-radius:8px;padding:7px 12px;margin:6px 6px 0 0;cursor:pointer;background:#16a34a;color:#fff}.vzb button.sek{background:#e2e8f0;color:#1e293b}</style>'
     . '<div class="vzb"><div class="vzb-k"><h3>🔗 Uzavřít poptávky k nové objednávce / nabídce?</h3><p>Tyto objednávky a nabídky nevznikly tlačítkem z poptávky, ale zákazník (stejný telefon nebo e-mail) má otevřené starší poptávky. Zaškrtněte ty, které jsou tím vyřízené.</p>';
  foreach ($n as $x) {
    $c = $x['cil'];
    echo '<form method="post" action="navaznost.php"><input type="hidden" name="cil" value="' . (int)$c['id'] . '"><input type="hidden" name="zpet" value="' . $h($zpet) . '">'
       . '<b><a href="kartoteka.php?id=' . (int)$c['id'] . '">' . $h(vz_druh($c['typ']) . ' ' . $c['cis']) . '</a></b> ' . $h($c['jmeno']) . ' <small>' . $h($dm($c['vytvoreno']) . ($c['model'] !== '' ? ' · ' . $c['model'] : '')) . '</small>';
    foreach ($x['kandidati'] as [$s, $vyb]) {
      echo '<label><input type="checkbox" name="zdroj[]" value="' . (int)$s['id'] . '"' . ($vyb ? ' checked' : '') . '> ' . $h(vz_druh($s['typ'])) . ' <a href="kartoteka.php?id=' . (int)$s['id'] . '">' . $h($s['cis'] !== '' ? $s['cis'] : '#' . $s['id']) . '</a> '
         . '<small>' . $h($dm($s['vytvoreno']) . ($s['model'] !== '' ? ' · ' . $s['model'] : '') . ' · ' . ($STAV[$s['stav']] ?? $s['stav'])) . '</small></label>';
    }
    echo '<button name="akce" value="uzavrit">✓ Uzavřít vybrané (Vyřízeno)</button><button name="akce" value="nic" class="sek">Nic neuzavírat</button></form>';
  }
  echo '</div></div>';
}

// Zpracování odpovědi (volá navaznost.php po přihlášení). Vrací počet uzavřených.
function vz_zpracuj(PDO $pdo, int $cilId, array $zdroje, string $akce, string $kdo): int {
  vz_tabulka($pdo);
  $nav = vz_navrhy($pdo, $cilId); if (!$nav) return 0;
  $c = $nav[0]['cil']; $povolene = []; foreach ($nav[0]['kandidati'] as [$s]) $povolene[(int)$s['id']] = $s;
  $uz = [];
  if ($akce === 'uzavrit') {
    if (!function_exists('hist_pis')) require_once __DIR__ . '/zamek.php';
    if (function_exists('zamek_init')) zamek_init($pdo);
    foreach ($zdroje as $id) {
      $id = (int)$id; if (!isset($povolene[$id])) continue;
      $u = $pdo->prepare("UPDATE zadosti SET stav='vyrizeno' WHERE id=? AND stav IN ('nove','rozpracovano','odeslana')"); $u->execute([$id]);
      if ($u->rowCount()) { $uz[] = $povolene[$id]['cis'];
        if (function_exists('hist_pis')) { try { hist_pis($pdo, $id, 'stav změněn na Vyřízeno (potvrzeno ručně)', 'navazuje ' . mb_strtolower(vz_druh($c['typ'])) . ' ' . $c['cis']); } catch (Throwable $e) {} } }
    }
  }
  $pdo->prepare('INSERT OR REPLACE INTO navaznost_vyreseno (cil_id, cas, kdo, uzavreno) VALUES (?,?,?,?)')->execute([$cilId, date('Y-m-d H:i:s'), $kdo, implode(', ', $uz)]);
  return count($uz);
}
