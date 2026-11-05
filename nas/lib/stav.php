<?php
// Automatické stavy objednávek (bez zásahu uživatele). Pořadí životního cyklu:
//  Nové → (otevření detailu) Rozpracováno → (přijatá záloha) Potvrzená → (sklad / dodavatel „objednáno“) Objednáno u výrobce
//  → („dodáno“ / „vyzvednuto“) Skladem – bez termínu / s termínem → (zadané datum montáže) Domluven termín
//  → (datum montáže už minulo) Namontováno → (zaznamenané předání dveří) Vyřízeno
// Pravidla:
//  - otevření poptávky nebo objednávky v kartotéce (kartoteka.php?id=…) ve stavu Nové → Rozpracováno
//  - záloha přijata (platba z banky, převodem nebo hotově) u Nové / Rozpracováno / E-mail se neodeslal → Potvrzená (5. 11. 2026: i „E-mail se neodeslal“)
//  - sklad / dodavatel „objednáno“ → Objednáno u výrobce; „vyzvednuto/dodáno“ → Skladem – s termínem (je-li datum montáže), jinak bez termínu
//  - datum montáže zadané a zboží nemusí čekat na sklad (Nové / Rozpracováno / Potvrzená) → Domluven termín
//  - Domluven termín nebo Skladem – s termínem + datum montáže už minulo → Namontováno
//  - zaznamenané předání dveří (predano_dne) → Vyřízeno
//  - (30. 10. 2026) poptávka, ze které vznikla nabídka nebo objednávka, a nabídka, ze které vznikla objednávka → Vyřízeno
//    (vazba podle čísla v z_poptavky / z_nabidky odeslané nabídky či objednávky; mění se jen Nové / Rozpracováno / Odeslána)
//  - (5. 11. 2026) navíc vazba podle zákazníka (telefon – posledních 9 číslic – nebo e-mail), když objednávka / nabídka nemá číslo zdroje:
//    1 : 1, každá uzavře jen nejbližší starší poptávku (nabídku) vzniklou po předchozí nabídce / objednávce téhož zákazníka
//  - (31. 10. 2026) nabídka poslaná zákazníkovi (má e-mail) ve stavu Nové → Odeslána
// Storno a Vyřízeno se nikdy nepřepisují; E-mail se neodeslal jen přijatou zálohou; stav se vrací zpět jen při vrácení zboží ze skladu.
// Volá se při každém otevření kartotéky / stránky Dodavatel a po spárování platby (platby.php).
function bd_stavy_sync(PDO $pdo, bool $znovu = false): void {
  static $hotovo = false; if ($hotovo && !$znovu) return; $hotovo = true;
  $meni = ['nove', 'rozpracovano', 'potvrzena', 'objednano', 'termin', 'skladem_bez', 'skladem_s', 'namontovano'];
  try {
    $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $colPredano = in_array('predano_dne', $sl, true) ? 'predano_dne' : "'' AS predano_dne";
    $up = $pdo->prepare('UPDATE zadosti SET stav=? WHERE id=?');
    $dnes = date('Y-m-d');
    // přijatá záloha u objednávky, které se nepodařilo odeslat e-mail → Potvrzená (jinak by stav „E-mail se neodeslal“ zůstal navždy)
    foreach ($pdo->query("SELECT id FROM zadosti WHERE typ='objednavka_interni' AND stav='chyba_mailu' AND zaloha_prijata_dne IS NOT NULL AND zaloha_prijata_dne<>''")->fetchAll(PDO::FETCH_COLUMN) as $idz) {
      $up->execute(['potvrzena', $idz]);
      if (function_exists('hist_pis')) { try { hist_pis($pdo, (int)$idz, 'stav automaticky změněn na Potvrzená', 'záloha přijata'); } catch (Throwable $e) {} }
    }
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
    // nabídka poslaná zákazníkovi (zákazník má e-mail, odeslat.php mu ji poslal spolu s interním záznamem) → Odeslána
    foreach ($pdo->query("SELECT id, data_json FROM zadosti WHERE typ='nabidka' AND stav='nove'")->fetchAll(PDO::FETCH_ASSOC) as $z) {
      $d = json_decode((string)$z['data_json'], true);
      if (is_array($d) && filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL)) $up->execute(['odeslana', $z['id']]);
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
// Klíče zákazníka pro vazbu bez čísla: posledních 9 číslic telefonu a e-mail (malými písmeny).
function bd_zak_klice(array $d, string $email = ''): array {
  $k = [];
  $t = preg_replace('/\D+/', '', (string)($d['telefon'] ?? '')); if (strlen($t) >= 9) $k[] = 't' . substr($t, -9);
  $e = mb_strtolower(trim((string)(($d['email'] ?? '') ?: $email))); if (filter_var($e, FILTER_VALIDATE_EMAIL)) $k[] = 'e' . $e;
  return $k;
}

// Poptávka, ze které vznikla nabídka nebo objednávka (z_poptavky / z_nabidky = její číslo), a nabídka, ze které vznikla objednávka → Vyřízeno.
// Když číslo chybí (nabídka / objednávka nevznikla tlačítkem ze zdroje), páruje se podle zákazníka (telefon / e-mail) 1 : 1:
// každá nabídka / objednávka uzavře nejvýš JEDEN zdroj – ten nejbližší starší, který vznikl až po předchozí nabídce / objednávce téhož zákazníka.
// Starší poptávky téhož zákazníka se tak nikdy hromadně neuzavřou (5. 11. 2026, upřesnění od Zdeňka).
// Mění se jen Nové / Rozpracováno / Odeslána (ručně nastavený jiný stav, Storno apod. zůstává).
function bd_stavy_navaznost(PDO $pdo, PDOStatement $up): void {
  $vse = $pdo->query("SELECT id, typ, cislo, email, stav, vytvoreno, data_json FROM zadosti WHERE typ IN ('poptavka','nabidka','objednavka_interni') ORDER BY vytvoreno, id")->fetchAll(PDO::FETCH_ASSOC);
  $odkud = []; $zaznamy = [];
  foreach ($vse as $r) {
    $d = json_decode((string)$r['data_json'], true); if (!is_array($d)) $d = [];
    $r['cis'] = (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']); $r['n'] = bd_cislo_norm($r['cis']);
    $r['klice'] = bd_zak_klice($d, (string)$r['email']); $r['odkaz'] = false;
    if ($r['typ'] !== 'poptavka') foreach (['z_poptavky', 'z_nabidky'] as $k) {
      $n = bd_cislo_norm($d[$k] ?? ''); if ($n === '') continue;
      $r['odkaz'] = true;
      if (!isset($odkud[$n]) || $r['typ'] === 'objednavka_interni') $odkud[$n] = [$r['typ'] === 'nabidka' ? 'nabídka' : 'objednávka', $r['cis']];
    }
    $zaznamy[] = $r;
  }
  $vazby = [];   // id zdroje → [druh, číslo, jak]
  // 1) vazba podle čísla
  foreach ($zaznamy as $r) {
    if (!in_array($r['typ'], ['poptavka', 'nabidka'], true) || $r['n'] === '' || !isset($odkud[$r['n']])) continue;
    [$druh, $cis] = $odkud[$r['n']];
    if ($r['typ'] === 'nabidka' && $druh !== 'objednávka') continue;   // nabídka je vyřízená až objednávkou
    $vazby[$r['id']] = [$druh, $cis, ''];
  }
  // 2) vazba podle zákazníka 1 : 1 (jen nabídky / objednávky bez čísla zdroje)
  //    poptávky: uzavírá je nabídka i objednávka; nabídky: uzavírá je jen objednávka
  foreach ([['poptavka', ['nabidka', 'objednavka_interni']], ['nabidka', ['objednavka_interni']]] as [$zdrojTyp, $cilTypy]) {
    $pouzite = [];
    foreach ($zaznamy as $i => $c) {
      if (!in_array($c['typ'], $cilTypy, true) || $c['stav'] === 'storno' || !$c['klice']) continue;
      // předchozí nabídka / objednávka téhož zákazníka (hranice okna)
      $od = '';
      for ($j = $i - 1; $j >= 0; $j--) { $p = $zaznamy[$j]; if (in_array($p['typ'], $cilTypy, true) && $p['stav'] !== 'storno' && array_intersect($p['klice'], $c['klice'])) { $od = (string)$p['vytvoreno']; break; } }
      if ($c['odkaz']) continue;   // má číslo zdroje → vazba podle čísla, podle zákazníka nic dalšího neuzavírá
      for ($j = $i - 1; $j >= 0; $j--) {
        $z = $zaznamy[$j];
        if ($od !== '' && strcmp((string)$z['vytvoreno'], $od) < 0) break;   // starší než předchozí nabídka / objednávka – už ne
        if ($z['typ'] !== $zdrojTyp || isset($pouzite[$z['id']]) || !array_intersect($z['klice'], $c['klice'])) continue;
        $pouzite[$z['id']] = true;
        if (!isset($vazby[$z['id']])) { $spol = array_values(array_intersect($z['klice'], $c['klice']))[0]; $vazby[$z['id']] = [$c['typ'] === 'nabidka' ? 'nabídka' : 'objednávka', $c['cis'], ' (stejný zákazník – ' . ($spol[0] === 't' ? 'telefon' : 'e-mail') . ')']; }
        break;   // jen jeden zdroj
      }
    }
  }
  foreach ($zaznamy as $r) {
    if (!isset($vazby[$r['id']]) || !in_array($r['stav'], ['nove', 'rozpracovano', 'odeslana'], true) || !in_array($r['typ'], ['poptavka', 'nabidka'], true)) continue;
    [$druh, $cis, $jak] = $vazby[$r['id']];
    $up->execute(['vyrizeno', $r['id']]);
    if (function_exists('hist_pis')) { try { hist_pis($pdo, (int)$r['id'], 'stav automaticky změněn na Vyřízeno', 'vznikla z ní ' . $druh . ' ' . $cis . $jak); } catch (Throwable $e) {} }
  }
}
