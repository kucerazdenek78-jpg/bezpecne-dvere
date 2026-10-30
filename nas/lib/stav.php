<?php
// Automatické stavy objednávek (bez zásahu uživatele). Pořadí životního cyklu:
//  Nové → (otevření detailu) Rozpracováno → (přijatá záloha) Potvrzená → (sklad / dodavatel „objednáno“) Objednáno u výrobce
//  → („dodáno“ / „vyzvednuto“) Skladem – bez termínu / s termínem → (zadané datum montáže) Domluven termín
//  → (datum montáže už minulo) Namontováno → (zaznamenané předání dveří) Vyřízeno
// Pravidla:
//  - otevření poptávky nebo objednávky v kartotéce (kartoteka.php?id=…) ve stavu Nové → Rozpracováno
//  - záloha přijata (platba z banky nebo hotově) u Nové / Rozpracováno → Potvrzená
//  - sklad / dodavatel „objednáno“ → Objednáno u výrobce; „vyzvednuto/dodáno“ → Skladem – s termínem (je-li datum montáže), jinak bez termínu
//  - datum montáže zadané a zboží nemusí čekat na sklad (Nové / Rozpracováno / Potvrzená) → Domluven termín
//  - Domluven termín nebo Skladem – s termínem + datum montáže už minulo → Namontováno
//  - zaznamenané předání dveří (predano_dne) → Vyřízeno
//  - (30. 10. 2026) poptávka, ze které vznikla nabídka nebo objednávka, a nabídka, ze které vznikla objednávka → Vyřízeno
//    (vazba podle čísla v z_poptavky / z_nabidky odeslané nabídky či objednávky; mění se jen Nové / Rozpracováno)
// Storno, E-mail se neodeslal a Vyřízeno se nikdy nepřepisují; stav se vrací zpět jen při vrácení zboží ze skladu.
// Volá se při každém otevření kartotéky / stránky Dodavatel a po spárování platby (platby.php).
function bd_stavy_sync(PDO $pdo, bool $znovu = false): void {
  static $hotovo = false; if ($hotovo && !$znovu) return; $hotovo = true;
  $meni = ['nove', 'rozpracovano', 'potvrzena', 'objednano', 'termin', 'skladem_bez', 'skladem_s', 'namontovano'];
  try {
    $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $colPredano = in_array('predano_dne', $sl, true) ? 'predano_dne' : "'' AS predano_dne";
    $up = $pdo->prepare('UPDATE zadosti SET stav=? WHERE id=?');
    $dnes = date('Y-m-d');
    foreach ($pdo->query("SELECT id, stav, sklad_stav, zaloha_prijata_dne, $colPredano, data_json FROM zadosti WHERE typ='objednavka_interni'")->fetchAll(PDO::FETCH_ASSOC) as $z) {
      if (!in_array($z['stav'], $meni, true)) continue;
      $d = json_decode((string)$z['data_json'], true);
      $dne = is_array($d) ? (string)(($d['montaz_dne'] ?? '') ?: ($d['termin_datum'] ?? '')) : '';
      $termin = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dne);   // skutečné datum montáže (volný text „termin“ se nepočítá)
      $sk = (string)($z['sklad_stav'] ?? '');
      $novy = $z['stav'];
      if (!empty($z['predano_dne'])) $novy = 'vyrizeno';
      elseif ($z['stav'] === 'namontovano') continue;   // namontováno se bez předání samo nemění
      else {
        if (in_array($z['stav'], ['nove', 'rozpracovano'], true) && !empty($z['zaloha_prijata_dne'])) $novy = 'potvrzena';
        if ($sk === 'vyzvednuto') $novy = $termin ? 'skladem_s' : 'skladem_bez';
        elseif ($sk === 'objednano') $novy = ($z['stav'] === 'termin') ? 'termin' : 'objednano';
        elseif (in_array($z['stav'], ['skladem_bez', 'skladem_s'], true)) $novy = $termin ? 'termin' : 'objednano'; // vráceno ze skladu
        elseif ($termin && in_array($novy, ['nove', 'rozpracovano', 'potvrzena'], true)) $novy = 'termin';       // domluven termín montáže
        if ($termin && $dne < $dnes && in_array($novy, ['termin', 'skladem_s'], true)) $novy = 'namontovano';     // termín montáže už minul
      }
      if ($novy !== $z['stav']) {
        $up->execute([$novy, $z['id']]);
        if (function_exists('hist_pis')) {
          $nazvy = ['potvrzena' => 'Potvrzená', 'termin' => 'Domluven termín', 'namontovano' => 'Namontováno', 'vyrizeno' => 'Vyřízeno'];
          if (isset($nazvy[$novy])) { try { hist_pis($pdo, (int)$z['id'], 'stav automaticky změněn na ' . $nazvy[$novy], ''); } catch (Throwable $e) {} }
        }
      }
    }
    // poptávka → nabídka / objednávka a nabídka → objednávka: zdrojový záznam je vyřízený
    bd_stavy_navaznost($pdo, $up);
    // otevření detailu v kartotéce: Nové → Rozpracováno (jen poptávka a objednávka; až po výše uvedeném přepočtu, takže Potvrzená zůstane)
    if (PHP_SAPI !== 'cli' && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'kartoteka.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
      $pdo->prepare("UPDATE zadosti SET stav='rozpracovano' WHERE id=? AND stav='nove' AND typ IN ('poptavka','objednavka_interni')")->execute([(int)$_GET['id']]);
    }
  } catch (Throwable $e) { error_log('stavy sync: ' . $e->getMessage()); }
}

// Číslo záznamu bez mezer a velkých písmen („P 25/2026“ = „p25/2026“).
function bd_cislo_norm($c): string { return preg_replace('/\s+/', '', mb_strtolower(trim((string)$c))); }

// Poptávka, ze které vznikla nabídka nebo objednávka (z_poptavky / z_nabidky = její číslo), a nabídka, ze které vznikla objednávka → Vyřízeno.
// Mění se jen Nové / Rozpracováno (ručně nastavený jiný stav, Storno apod. zůstává).
function bd_stavy_navaznost(PDO $pdo, PDOStatement $up): void {
  $odkud = [];   // normalizované číslo zdroje → [druh, číslo] záznamu, který z něj vznikl
  foreach ($pdo->query("SELECT typ, cislo, data_json FROM zadosti WHERE typ IN ('nabidka','objednavka_interni')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true); if (!is_array($d)) continue;
    $cis = (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']);
    foreach (['z_poptavky', 'z_nabidky'] as $k) {
      $n = bd_cislo_norm($d[$k] ?? ''); if ($n === '') continue;
      if (!isset($odkud[$n]) || $r['typ'] === 'objednavka_interni') $odkud[$n] = [$r['typ'] === 'nabidka' ? 'nabídka' : 'objednávka', $cis];
    }
  }
  if (!$odkud) return;
  foreach ($pdo->query("SELECT id, typ, cislo, stav, data_json FROM zadosti WHERE typ IN ('poptavka','nabidka') AND stav IN ('nove','rozpracovano')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true); if (!is_array($d)) $d = [];
    $n = bd_cislo_norm($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']); if ($n === '' || !isset($odkud[$n])) continue;
    [$druh, $cis] = $odkud[$n];
    if ($r['typ'] === 'nabidka' && $druh !== 'objednávka') continue;   // nabídka je vyřízená až objednávkou
    $up->execute(['vyrizeno', $r['id']]);
    if (function_exists('hist_pis')) { try { hist_pis($pdo, (int)$r['id'], 'stav automaticky změněn na Vyřízeno', 'vznikla z ní ' . $druh . ' ' . $cis); } catch (Throwable $e) {} }
  }
}
