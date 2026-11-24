<?php
// Hlídání objednávek se zaplacenou zálohou, které ještě nejsou objednané u dodavatele (zakázky mimo náš sklad PREMIUM 60).
// 23. 11. 2026 (přání Zdeňka): když od přijetí zálohy uběhne víc než DOD_PRAH_DNI dní a objednávka pořád není objednaná u dodavatele,
//   upozorní box na úvodu kartotéky (dodavatel_box_html) + denní e-mail Zdeňkovi (blok 5 v terminy-pripominky.php). Připomíná dál, dokud se neobjedná.
// Samostatný soubor (jako lib/terminy-zaloha.php), ať se nemusí editovat velký lib/terminy.php. Shoduje se s výběrem „k objednání" v dodavatel.php
// (typ objednavka_interni, stav mimo storno/namontovano/vyrizeno, ne PREMIUM 60, ne zkušební, sklad_stav='' = ještě neobjednáno).

const DOD_PRAH_DNI = 7;   // kolik dní od přijetí zálohy čekáme, než začneme upozorňovat (přání Zdeňka: „do týdne"); změna = jen tady

// sloupce pro evidenci připomínek (vytvoří se samy při prvním použití)
function dodavatel_tabulky(PDO $pdo): void {
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('dod_pripom_dne', $sl, true)) $pdo->exec('ALTER TABLE zadosti ADD COLUMN dod_pripom_dne TEXT');
  if (!in_array('dod_pripom_pocet', $sl, true)) $pdo->exec('ALTER TABLE zadosti ADD COLUMN dod_pripom_pocet INTEGER');
}

// objednávky: zaplacená záloha, ještě neobjednané u dodavatele, mimo sklad PREMIUM 60 a mimo zkušební; $dnu = dní od přijetí zálohy
function terminy_dodavatel_cekajici(PDO $pdo, ?int $ted = null): array {
  $ted = $ted ?? time(); $o = [];
  try { dodavatel_tabulky($pdo); } catch (Throwable $e) {}
  try {
    $st = $pdo->query("SELECT id, cislo, sklad_stav, zaloha_prijata_dne, dodavatel, dod_pripom_dne, dod_pripom_pocet, data_json FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','namontovano','vyrizeno') AND (sklad_stav IS NULL OR sklad_stav='') AND zaloha_prijata_dne IS NOT NULL AND zaloha_prijata_dne<>'' ORDER BY zaloha_prijata_dne");
  } catch (Throwable $e) { return []; }
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true) ?: [];
    $cis = (string)($d['obj_cislo'] ?? $r['cislo']);
    if (($d['model_kod'] ?? '') === 'premium60' || stripos((string)($d['model'] ?? ''), 'premium 60') !== false) continue;   // PREMIUM 60 má vlastní sklad (sklad.php)
    if (preg_match('/test/i', $cis)) continue;                                                                                // zkušební záznamy nehlídáme
    $zdne = substr((string)$r['zaloha_prijata_dne'], 0, 10); $zt = strtotime($zdne); if (!$zt) continue;
    $dod = trim((string)($r['dodavatel'] ?? ''));
    if ($dod === '') {   // výchozí dodavatel (stejně jako dodavatel.php), když ještě není vybraný
      $kod = (string)($d['model_kod'] ?? '');
      if ($kod === 'extreme54') $dod = 'Svět dveří (B2B)';
      elseif (($d['kategorie'] ?? '') === 'dum' || stripos((string)($d['model'] ?? ''), 'erkado') !== false) $dod = 'ERKADO';
    }
    $montIso = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['montaz_dne'] ?? '')) ? (string)$d['montaz_dne'] : (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['termin_datum'] ?? '')) ? (string)$d['termin_datum'] : '');
    $o[] = ['id' => (int)$r['id'], 'cislo' => $cis, 'jmeno' => trim(((string)($d['jmeno'] ?? '')) . ' ' . ((string)($d['prijmeni'] ?? ''))), 'mesto' => (string)($d['mesto'] ?? ''),
      'model' => (string)($d['model'] ?? ($d['model_kod'] ?? '')), 'dodavatel' => $dod, 'zaloha_dne' => $zdne,
      'dnu' => (int)floor(($ted - $zt) / 86400), 'montaz' => $montIso ? date('j. n. Y', strtotime($montIso)) : '',
      'last' => (string)($r['dod_pripom_dne'] ?? ''), 'pocet' => (int)($r['dod_pripom_pocet'] ?? 0)];
  }
  usort($o, fn($a, $b) => $b['dnu'] <=> $a['dnu']);
  return $o;
}

// HTML box na úvod kartotéky (prázdný řetězec, když není co hlásit); ukáže jen objednávky starší než DOD_PRAH_DNI dní od zálohy
function dodavatel_box_html0(PDO $pdo, ?int $ted = null): string {
  $vse = array_values(array_filter(terminy_dodavatel_cekajici($pdo, $ted), fn($z) => $z['dnu'] >= DOD_PRAH_DNI));
  if (!$vse) return '';
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $radky = '';
  foreach ($vse as $z) {
    $cr = $z['dnu'] >= DOD_PRAH_DNI + 7;
    $radky .= '<div style="padding:7px 10px;margin:4px 0;border-radius:8px;border-left:5px solid ' . ($cr ? '#dc2626' : '#d97706') . ';background:' . ($cr ? '#fef2f2' : '#fffbeb') . '">'
      . '<a href="kartoteka.php?id=' . (int)$z['id'] . '" style="color:#0f2747;text-decoration:none;font-weight:600">Objednávka ' . $h($z['cislo']) . '</a> ' . $h($z['jmeno'])
      . '<div style="color:#475569;font-size:13px">' . $h($z['model']) . ($z['dodavatel'] !== '' ? ' · ' . $h($z['dodavatel']) : '') . ' · záloha zaplacena před ' . (int)$z['dnu'] . ' dny'
      . ($z['montaz'] !== '' ? ' · montáž ' . $h($z['montaz']) : '') . ' · <a href="dodavatel.php" style="color:#1f5fbf">objednat u dodavatele</a></div></div>';
  }
  return '<div style="background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(15,39,71,.12);padding:10px 14px;margin:0 0 14px;font:14px/1.4 system-ui,Arial,sans-serif">'
    . '<h3 style="margin:2px 0 6px;font-size:15px;color:#0f2747">📦 Zaplaceno, ještě neobjednáno u dodavatele (' . count($vse) . ')</h3>' . $radky . '</div>';
}

// 24. 11. 2026: kartoteka.php volá dodavatel_box_html() nad seznamem; přidává se k němu i zvýraznění objednávek
// s nezaplacenou zálohou (lib/platba-termin.php). Původní box je beze změny v dodavatel_box_html0().
function dodavatel_box_html(PDO $pdo, ?int $ted = null): string {
  $h = dodavatel_box_html0($pdo, $ted);
  try { require_once __DIR__ . '/platba-termin.php'; $h .= platba_termin_seznam_html($pdo, $ted); } catch (Throwable $e) {}
  try { require_once __DIR__ . '/tral-prenos.php'; $h .= tral_seznam_html($pdo); } catch (Throwable $e) {}   // tlačítko „Do TRALu“ u objednávek ERKADO
  return $h;
}
