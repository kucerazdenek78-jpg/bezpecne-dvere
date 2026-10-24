<?php
// Servisní požadavky zákazníků navázané na objednávku (dveře). Používá je: moje.php (zákaznická stránka), aplikace-api.php (aplikace Moje dveře)
// a servis-pozadavky.php (kartotéka, přijetí požadavku, termín návštěvy, vyřízení). Vyřízený požadavek se zapíše i do tabulky servis = „rodný list dveří“.
require_once __DIR__ . '/posta.php';

const POZ_STAVY = ['nove' => 'Odesláno – čekáme na potvrzení', 'prijato' => 'Přijato – ozveme se s termínem', 'naplanovano' => 'Termín návštěvy domluven', 'hotovo' => 'Vyřízeno', 'zruseno' => 'Zrušeno'];
const POZ_DRUHY = ['zavada' => 'Hlášení závady', 'prohlidka' => 'Roční prohlídka'];

function poz_tabulky(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS servis_pozadavky (id INTEGER PRIMARY KEY AUTOINCREMENT, zadost_id INTEGER NOT NULL, cislo TEXT, vytvoreno TEXT, upraveno TEXT,
    druh TEXT DEFAULT 'zavada', popis TEXT, fotky TEXT, jmeno TEXT, telefon TEXT, email TEXT, adresa TEXT, preferovany TEXT, stav TEXT DEFAULT 'nove',
    termin_datum TEXT, termin_cas TEXT, zprava TEXT, hotovo_dne TEXT, hotovo_popis TEXT, interni TEXT, zdroj TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS servis_poz_zadost ON servis_pozadavky(zadost_id)');
  $sl = array_column($pdo->query('PRAGMA table_info(servis)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('verejne', $sl, true)) $pdo->exec('ALTER TABLE servis ADD COLUMN verejne TEXT');
  if (!in_array('pozadavek_id', $sl, true)) $pdo->exec('ALTER TABLE servis ADD COLUMN pozadavek_id INTEGER');
}

// objednávka podle zákaznického tokenu (stejný kód jako v moje.php)
function poz_zadost_podle_tokenu(PDO $pdo, string $t): ?array {
  if (!preg_match('/^[a-f0-9]{24}$/', $t)) return null;
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND data_json LIKE ?");
  $st->execute(['%"zakaznik_token":"' . $t . '"%']);
  $z = $st->fetch(PDO::FETCH_ASSOC);
  return $z ?: null;
}
function poz_maska_tel(string $tel): string {
  $c = preg_replace('/\D+/', '', $tel); if (strlen($c) < 6) return '';
  return substr($c, 0, 3) . ' ••• ' . substr($c, -3);
}
function poz_cisti(string $s, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s)), 0, $max); }

// vytvoření požadavku. $in: popis, druh, preferovany, telefon, email (volitelné přepsání kontaktu). $soubory: pole [tmp_name, name] max 3. Vrací [id, cislo].
function poz_vytvor(PDO $pdo, array $cfg, string $DATA, array $z, array $in, array $soubory, string $zdroj): array {
  poz_tabulky($pdo);
  $d = json_decode((string)$z['data_json'], true) ?: [];
  $druh = isset(POZ_DRUHY[$in['druh'] ?? '']) ? $in['druh'] : 'zavada';
  $popis = poz_cisti((string)($in['popis'] ?? ''), 3000);
  if ($druh === 'zavada' && mb_strlen($popis) < 5) throw new InvalidArgumentException('popis');
  if ($druh === 'prohlidka' && $popis === '') $popis = 'Objednávka roční prohlídky dveří.';
  $tel = poz_cisti((string)($in['telefon'] ?? ''), 40); if ($tel === '') $tel = (string)($d['telefon'] ?? '');
  $mail = filter_var(trim((string)($in['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: (filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '');
  $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''));
  $adresa = trim(implode(', ', array_filter([$d['ulice'] ?? '', trim(($d['psc'] ?? '') . ' ' . ($d['mesto'] ?? ''))])));
  $ted = date('Y-m-d H:i:s');
  $pdo->prepare('INSERT INTO servis_pozadavky (zadost_id, vytvoreno, upraveno, druh, popis, jmeno, telefon, email, adresa, preferovany, stav, zdroj) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$z['id'], $ted, $ted, $druh, $popis, $jmeno, $tel, $mail, $adresa, poz_cisti((string)($in['preferovany'] ?? ''), 200), 'nove', $zdroj]);
  $id = (int)$pdo->lastInsertId(); $cislo = 'S-' . date('Y') . '-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
  $ulozene = [];
  foreach (array_slice($soubory, 0, 3) as $i => $f) {
    if (!is_uploaded_file($f[0]) && empty($GLOBALS['POZ_TEST'])) continue;
    $velikost = @filesize($f[0]); if (!$velikost || $velikost > 8 * 1048576) continue;
    $inf = @getimagesize($f[0]); if (!$inf || !in_array($inf[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) continue;
    $ext = $inf[2] === IMAGETYPE_PNG ? 'png' : ($inf[2] === IMAGETYPE_WEBP ? 'webp' : 'jpg');
    $jm = 'S' . $id . '_' . bin2hex(random_bytes(4)) . '_foto' . ($i + 1) . '.' . $ext;
    @mkdir($DATA . '/ulozeno', 0775, true);
    if (copy($f[0], $DATA . '/ulozeno/' . $jm)) $ulozene[] = $jm;
  }
  $pdo->prepare('UPDATE servis_pozadavky SET cislo=?, fotky=? WHERE id=?')->execute([$cislo, implode("\n", $ulozene), $id]);
  // upozornění nám
  $cisloObj = (string)($d['obj_cislo'] ?? $z['cislo']);
  $telo = "Nový servisní požadavek $cislo (" . POZ_DRUHY[$druh] . ") – zdroj: $zdroj.\n\n"
    . "Objednávka: $cisloObj · " . ($d['model'] ?? '') . (!empty($d['odstin']) ? ' · ' . $d['odstin'] : '') . "\n"
    . "Zákazník: $jmeno\nTelefon: $tel" . ($mail ? "\nE-mail: $mail" : '') . "\nAdresa: $adresa\n"
    . "Popis: $popis\n" . ($in['preferovany'] ?? '' ? 'Vhodný termín: ' . poz_cisti((string)$in['preferovany'], 200) . "\n" : '') . 'Fotky: ' . count($ulozene) . " ks\n\n"
    . "Přijmout a domluvit termín: " . rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/') . '/servis-pozadavky.php';
  $komu = (array)($cfg['prijemci']['poptavka']['to'] ?? [$cfg['from'] ?? '']);
  posli_mail($cfg, $DATA, array_filter($komu), (array)($cfg['prijemci']['poptavka']['cc'] ?? []), $mail ?: ($cfg['from'] ?? ''), "Servis $cislo – $cisloObj – " . POZ_DRUHY[$druh], $telo);
  return [$id, $cislo];
}

function poz_datum(?string $v): string { $t = $v ? strtotime($v) : false; return $t ? date('j. n. Y', $t) : ''; }

// požadavky pro zákazníka (bez kontaktů a interních poznámek)
function poz_pro_zakaznika(PDO $pdo, int $zid): array {
  poz_tabulky($pdo); $out = [];
  $st = $pdo->prepare("SELECT * FROM servis_pozadavky WHERE zadost_id=? AND stav<>'zruseno' ORDER BY id DESC"); $st->execute([$zid]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[] = ['cislo' => $r['cislo'], 'druh' => $r['druh'], 'druh_text' => POZ_DRUHY[$r['druh']] ?? $r['druh'], 'vytvoreno' => substr((string)$r['vytvoreno'], 0, 10), 'popis' => $r['popis'],
      'stav' => $r['stav'], 'stav_text' => POZ_STAVY[$r['stav']] ?? $r['stav'], 'termin_datum' => (string)$r['termin_datum'], 'termin_cas' => (string)$r['termin_cas'],
      'zprava' => (string)$r['zprava'], 'hotovo_dne' => (string)$r['hotovo_dne'], 'hotovo_popis' => (string)$r['hotovo_popis'], 'fotek' => count(array_filter(explode("\n", (string)$r['fotky'])))];
  }
  return $out;
}

// „rodný list dveří“: události od objednávky po poslední servis (veřejná podoba – bez cen, kontaktů a interních poznámek)
function poz_rodny_list(PDO $pdo, array $z): array {
  poz_tabulky($pdo);
  $d = json_decode((string)$z['data_json'], true) ?: []; $e = [];
  $ob = (string)($d['obj_datum'] ?? substr((string)$z['vytvoreno'], 0, 10));
  if ($ob) $e[] = ['datum' => $ob, 'typ' => 'objednavka', 'text' => 'Objednávka dveří ' . ($d['model'] ?? '') . (!empty($d['odstin']) ? ', ' . $d['odstin'] : '')];
  if (!empty($z['predano_dne'])) $e[] = ['datum' => $z['predano_dne'], 'typ' => 'predani', 'text' => 'Dveře namontovány a předány'];
  if (!empty($z['zaruka_do'])) $e[] = ['datum' => $z['zaruka_od'] ?: $z['predano_dne'], 'typ' => 'zaruka', 'text' => 'Začátek záruky, platí do ' . poz_datum($z['zaruka_do']) . (!empty($z['zaruka_prodlouzeno']) ? ' (včetně prodloužení)' : '')];
  $st = $pdo->prepare('SELECT * FROM servis WHERE zadost_id=? ORDER BY datum, id'); $st->execute([$z['id']]);
  $druhy = ['kontrola' => 'Roční kontrola', 'servis' => 'Servis', 'reklamace' => 'Reklamace'];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $q) $e[] = ['datum' => (string)$q['datum'], 'typ' => 'servis', 'text' => ($druhy[$q['druh']] ?? $q['druh']) . (!empty($q['verejne']) ? ': ' . $q['verejne'] : '')];
  $st = $pdo->prepare("SELECT * FROM servis_pozadavky WHERE zadost_id=? AND stav='naplanovano' ORDER BY termin_datum"); $st->execute([$z['id']]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $e[] = ['datum' => (string)$r['termin_datum'], 'typ' => 'termin', 'text' => 'Naplánovaná návštěva' . ($r['termin_cas'] ? ' (' . $r['termin_cas'] . ')' : ''), 'budouci' => true];
  if (function_exists('udrzba_rodny_list')) $e = array_merge($e, udrzba_rodny_list($pdo, (int)$z['id'])); // pravidelná údržba (lib/udrzba.php)
  usort($e, fn($a, $b) => strcmp((string)$a['datum'], (string)$b['datum']));
  return $e;
}

// e-mail zákazníkovi, když prodejce potvrdí termín návštěvy
function poz_mail_termin(array $cfg, string $DATA, array $r, string $cisloObj): bool {
  $em = filter_var(trim((string)$r['email']), FILTER_VALIDATE_EMAIL); if (!$em) return false;
  $telo = "Dobrý den,\n\nvaši žádost " . $r['cislo'] . " k objednávce č. $cisloObj jsme přijali. Návštěvu máme domluvenou na " . poz_datum($r['termin_datum']) . ($r['termin_cas'] ? ' (' . $r['termin_cas'] . ')' : '') . ".\n"
    . ($r['zprava'] ? "\n" . $r['zprava'] . "\n" : '') . "\nStav požadavku najdete v aplikaci Moje dveře nebo na stránce Moje dveře (QR kód na předávacím listu). Kdyby se termín nehodil, ozvěte se: +420 725 559 235.\n\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
  return posli_mail($cfg, $DATA, [$em], (array)($cfg['prijemci']['objednavka_zakaznik']['cc'] ?? []), $cfg['from'], 'Termín návštěvy – ' . $r['cislo'], $telo);
}
