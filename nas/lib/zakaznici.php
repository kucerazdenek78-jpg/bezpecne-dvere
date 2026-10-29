<?php
// Zákazníci – společné funkce pro zakaznici.php (kartotéka) a kontakty-sync.php (synchronizace do Google Kontaktů).
// Zákazník = sloučené záznamy ze zadosti (stejný telefon → e-mail → jméno+obec) + ruční úpravy a ruční zákazníci v tabulce zakaznik_info.
// Patří na NAS do /docker/formulare/app/lib/zakaznici.php.

function zk_pole(): array {
  return ['jmeno' => 'Jméno a příjmení', 'firma' => 'Firma', 'telefon' => 'Telefon', 'email' => 'E-mail', 'ulice' => 'Ulice a číslo', 'mesto' => 'Obec', 'psc' => 'PSČ'];
}
function zk_tabulka(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS zakaznik_info (klic TEXT PRIMARY KEY, stitky TEXT, poznamka TEXT, dalsi_kontakt TEXT, upraveno TEXT)');
  $sloupce = array_column($pdo->query('PRAGMA table_info(zakaznik_info)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  foreach (array_merge(array_keys(zk_pole()), ['smazano', 'rucne', 'zdroj']) as $c) if (!in_array($c, $sloupce, true)) $pdo->exec('ALTER TABLE zakaznik_info ADD COLUMN ' . $c . ' TEXT');
}

function zk_klic(array $d, array $r): string {
  $tel = preg_replace('/\D+/', '', (string)($d['telefon'] ?? ''));
  if (strlen($tel) >= 9) return 't' . substr($tel, -9);
  $em = strtolower(trim((string)($d['email'] ?? $r['email'] ?? '')));
  if ($em !== '') return 'e' . $em;
  $jm = mb_strtolower(trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')));
  if ($jm !== '') return 'j' . md5($jm . '|' . mb_strtolower((string)($d['mesto'] ?? '')));
  return '';
}

// všichni zákazníci sestavení ze záznamů (nejnovější záznam určuje zobrazené kontakty)
function zk_nacti(PDO $pdo): array {
  $z = [];
  $q = $pdo->query('SELECT id, vytvoreno, typ, cislo, predmet, email, data_json, stav, zaloha_prijata_dne, predano_dne, zaruka_od, zaruka_do FROM zadosti ORDER BY vytvoreno ASC, id ASC');
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)($r['data_json'] ?? ''), true); if (!is_array($d)) $d = [];
    $k = zk_klic($d, $r); if ($k === '') continue;
    $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''));
    $zz = &$z[$k];
    if (!isset($zz)) $zz = ['klic' => $k, 'jmeno' => '', 'firma' => '', 'telefon' => '', 'email' => '', 'mesto' => '', 'ulice' => '', 'psc' => '', 'zaznamy' => []];
    foreach (['telefon' => $d['telefon'] ?? '', 'email' => $d['email'] ?? $r['email'] ?? '', 'mesto' => $d['mesto'] ?? '', 'ulice' => $d['ulice'] ?? '', 'psc' => $d['psc'] ?? '', 'firma' => $d['fak_jmeno'] ?? ''] as $f => $v) {
      if (trim((string)$v) !== '') $zz[$f] = trim((string)$v);
    }
    if ($jmeno !== '') $zz['jmeno'] = $jmeno;
    $zz['zaznamy'][] = ['id' => (int)$r['id'], 'datum' => $r['vytvoreno'], 'typ' => $r['typ'], 'cislo' => (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']),
      'predmet' => (string)$r['predmet'], 'cena' => $d['cena_celkem'] ?? null, 'stav' => (string)$r['stav'], 'zaloha' => $r['zaloha_prijata_dne'],
      'predano' => $r['predano_dne'], 'zaruka_od' => $r['zaruka_od'], 'zaruka_do' => $r['zaruka_do']];
    unset($zz);
  }
  return $z;
}

function zk_info(PDO $pdo): array {
  $o = [];
  foreach ($pdo->query('SELECT * FROM zakaznik_info')->fetchAll(PDO::FETCH_ASSOC) as $r) $o[$r['klic']] = $r;
  return $o;
}

// Zákazníci ze záznamů + ruční (z telefonu / přidaní ručně), s úpravami z zakaznik_info. Smazaní se vynechají,
// pokud od smazání nepřišel nový záznam ($i['smazano'] = datum a čas smazání).
function zk_vsichni(PDO $pdo, array $info, bool $vcSmazanych = false): array {
  $ZK_POLE = zk_pole();
  $vse = zk_nacti($pdo);
  foreach ($info as $k => $i) if (!isset($vse[$k]) && !empty($i['rucne'])) $vse[$k] = ['klic' => $k, 'jmeno' => '', 'firma' => '', 'telefon' => '', 'email' => '', 'mesto' => '', 'ulice' => '', 'psc' => '', 'zaznamy' => []];
  foreach ($vse as $k => &$c) {
    $i = $info[$k] ?? [];
    foreach (array_keys($ZK_POLE) as $f) if (isset($i[$f]) && $i[$f] !== null && $i[$f] !== '') $c[$f] = (string)$i[$f];
    $c['upraveny'] = (bool)array_filter(array_keys($ZK_POLE), fn($f) => ($i[$f] ?? '') !== '');
    $c['rucne'] = !empty($i['rucne']); $c['zdroj'] = (string)($i['zdroj'] ?? '');
    $posl = $c['zaznamy'] ? end($c['zaznamy'])['datum'] : '';
    $c['smazany'] = !empty($i['smazano']) && ($posl === '' || strcmp((string)$posl, (string)$i['smazano']) <= 0);
    if ($c['smazany'] && !$vcSmazanych) unset($vse[$k]);
  }
  unset($c);
  return $vse;
}
