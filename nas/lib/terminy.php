<?php
// Hlídání termínů (3. 11. 2026):
//  1) poptávka, na kterou se zákazníkovi slibuje reakce do 48 hodin od přijetí → upozornění na úvodní stránce + e-mail firmě, když zbývá 12 h nebo termín uplynul
//  2) nabídka poslaná zákazníkovi, která zůstala bez odezvy → e-mail zákazníkovi (po 3 dnech a 2 dny před koncem platnosti nabídky, pole nabidka_do)
//  3) v e-mailu zákazník hodnotí nabídku (srozumitelnost, spokojenost 1–5) na veřejné stránce hodnoceni.php; výsledek se ukáže na úvodu a přijde e-mailem
// Hlídá se, dokud záznam není Vyřízeno / Storno. Plánované e-maily posílá CLI terminy-pripominky.php (Plánovač úloh na NAS, ideálně každou hodinu).
// 4. 11. 2026: vlastní zaměření v terénu (poptávka se zdrojem „zamereni-teren“, zamereni-nove.php) se nehlídá – zákazníkovi se tam reakce do 48 h neslibuje.
// 5. 11. 2026 (přání Zdeňka):
//  - v boxu „Nabídky bez odpovědi“ je vidět, co systém udělal (připomenuto kdy, další připomínka kdy) + tlačítka „Poslat připomínku teď“ a „Nepřipomínat“
//  - zákazník v e-mailu jedním klikem: chci upravit / zavolejte mi / nemám zájem (+ důvod) → hodnoceni.php; upravit a zavolat = úkol na úvodu, nemám zájem = Storno a konec připomínek
//  - stejný zákazník se stejným číslem nabídky (duplicitní záznam) dostane každou připomínku jen jednou
//  - 3. (poslední) zpráva po skončení platnosti nabídky: „co rozhodlo?“
//  - dotaz na spokojenost s dveřmi a montáží 5–30 dní po předání (hodnoceni.php?m=…; 4–5 hvězdiček → odkaz na recenzi Google)
//  - poptávky z e-mailu (poptavky-z-mailu.php) se hlídají od data původního e-mailu (data_json „prijato“)
const TERM_POPTAVKA_HODIN = 48;
const TERM_VAROVANI_HODIN = 12;
const TERM_GOOGLE_RECENZE = 'https://search.google.com/local/writereview?placeid=ChIJESw78t4hCkcRR1ygmiDY8ac';
const TERM_MONTAZ_OD = '2026-11-01';   // dotaz na spokojenost jen u dveří předaných od tohoto dne

function terminy_tabulky(PDO $pdo): void {
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  foreach (['term_interni_dne', 'nab_pripom1_dne', 'nab_pripom2_dne', 'term_omluva_dne', 'nab_token', 'nab_hodnoceni_dne', 'nab_hodnoceni_srozum', 'nab_hodnoceni_pozn',
    'nab_pripom3_dne', 'nab_stop', 'nab_reakce', 'nab_reakce_dne', 'nab_reakce_pozn', 'nab_reakce_hotovo', 'mont_token', 'mont_dotaz_dne', 'mont_hodnoceni_dne', 'mont_hodnoceni_pozn', 'zal_pripom_dne'] as $c) if (!in_array($c, $sl, true)) $pdo->exec("ALTER TABLE zadosti ADD COLUMN $c TEXT");
  foreach (['nab_hodnoceni', 'mont_hodnoceni'] as $c) if (!in_array($c, $sl, true)) $pdo->exec("ALTER TABLE zadosti ADD COLUMN $c INTEGER");
}
function terminy_base(array $cfg): string { return rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/'); }
// odkaz pro zákazníka na hodnocení nabídky (hodnoceni.php); token se vytvoří při první potřebě
function terminy_hodnoceni_url(PDO $pdo, array $cfg, int $id): string {
  $t = (string)$pdo->query('SELECT nab_token FROM zadosti WHERE id=' . $id)->fetchColumn();
  if (!preg_match('/^[a-f0-9]{32}$/', $t)) { $t = bin2hex(random_bytes(16)); $pdo->prepare('UPDATE zadosti SET nab_token=? WHERE id=?')->execute([$t, $id]); }
  return terminy_base($cfg) . '/hodnoceni.php?t=' . $t;
}
// odkaz pro zákazníka na hodnocení dveří a montáže (po předání)
function terminy_montaz_url(PDO $pdo, array $cfg, int $id): string {
  $t = (string)$pdo->query('SELECT mont_token FROM zadosti WHERE id=' . $id)->fetchColumn();
  if (!preg_match('/^[a-f0-9]{32}$/', $t)) { $t = bin2hex(random_bytes(16)); $pdo->prepare('UPDATE zadosti SET mont_token=? WHERE id=?')->execute([$t, $id]); }
  return terminy_base($cfg) . '/hodnoceni.php?m=' . $t;
}
function terminy_jmeno(array $d): string { return trim(((string)($d['jmeno'] ?? '')) . ' ' . ((string)($d['prijmeni'] ?? ''))); }
function terminy_hist(PDO $pdo, int $id, string $akce, string $popis = ''): void {
  try { if (!function_exists('hist_pis')) { require_once __DIR__ . '/zamek.php'; } zamek_init($pdo); hist_pis($pdo, $id, $akce, $popis); } catch (Throwable $e) {}
}

// otevřené poptávky s termínem reakce; zbývá = sekund do termínu (záporné = po termínu)
function terminy_poptavky(PDO $pdo, ?int $ted = null): array {
  $ted = $ted ?? time(); $o = [];
  $st = $pdo->query("SELECT id, vytvoreno, cislo, stav, email, data_json, term_interni_dne FROM zadosti WHERE typ='poptavka' AND stav NOT IN ('vyrizeno','storno') ORDER BY vytvoreno");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true) ?: [];
    if (($d['zdroj'] ?? '') === 'zamereni-teren') continue;   // vlastní zaměření v terénu – nic se neslibuje
    $t = strtotime((string)(($d['zdroj'] ?? '') === 'email-ai' && !empty($d['prijato']) ? $d['prijato'] : $r['vytvoreno'])); if (!$t) continue;   // poptávka z e-mailu: od data původního e-mailu
    $termin = $t + TERM_POPTAVKA_HODIN * 3600;
    $o[] = ['id' => (int)$r['id'], 'cislo' => (string)($r['cislo'] ?: ($d['cislo'] ?? '')), 'jmeno' => terminy_jmeno($d), 'telefon' => (string)($d['telefon'] ?? ''),
      'prijato' => $t, 'termin' => $termin, 'zbyva' => $termin - $ted, 'upozorneno' => (string)($r['term_interni_dne'] ?? '')];
  }
  usort($o, fn($a, $b) => $a['prijato'] <=> $b['prijato']);
  return $o;
}

// nabídky poslané zákazníkovi, na které zatím nikdo nereagoval (stav Odeslána)
// klic = e-mail + číslo nabídky: dva záznamy se stejným klíčem jsou tatáž nabídka (duplicita) → připomínka jen jednou
function terminy_nabidky(PDO $pdo, ?string $dnes = null): array {
  $dnes = $dnes ?? date('Y-m-d'); $o = [];
  $st = $pdo->query("SELECT id, vytvoreno, cislo, data_json, nab_pripom1_dne, nab_pripom2_dne, nab_pripom3_dne, nab_stop FROM zadosti WHERE typ='nabidka' AND stav='odeslana' ORDER BY vytvoreno");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d = json_decode((string)$r['data_json'], true) ?: [];
    $em = filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $do = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['nabidka_do'] ?? '')) ? (string)$d['nabidka_do'] : '';
    $odesl = substr((string)$r['vytvoreno'], 0, 10);
    $dnu = (int)floor((strtotime($dnes) - strtotime($odesl)) / 86400);
    $cis = (string)($d['cislo'] ?? $r['cislo']);
    $o[] = ['id' => (int)$r['id'], 'cislo' => $cis, 'jmeno' => terminy_jmeno($d), 'email' => $em ?: '', 'telefon' => (string)($d['telefon'] ?? ''), 'do' => $do, 'dnu' => $dnu, 'odeslano' => $odesl,
      'do_dnu' => $do ? (int)floor((strtotime($do) - strtotime($dnes)) / 86400) : null, 'p1' => (string)($r['nab_pripom1_dne'] ?? ''), 'p2' => (string)($r['nab_pripom2_dne'] ?? ''),
      'p3' => (string)($r['nab_pripom3_dne'] ?? ''), 'stop' => (string)($r['nab_stop'] ?? ''), 'prodavac' => (string)($d['prodavac'] ?? ''),
      'klic' => $em ? mb_strtolower($em) . '|' . preg_replace('/\s+/', '', mb_strtolower($cis)) : 'id' . $r['id']];
  }
  return $o;
}
// nabídky seskupené podle klíče (duplicity): [klic => [x, x, …]] – připomínky se počítají ze záznamů dohromady (co jde jednomu, platí pro všechny)
function terminy_nabidky_skupiny(array $n): array {
  $g = []; foreach ($n as $x) $g[$x['klic']][] = $x;
  foreach ($g as $k => $s) {
    usort($s, fn($a, $b) => $b['id'] <=> $a['id']);   // nejnovější záznam první (text e-mailu se bere z něj)
    $h = $s[0];
    foreach (['p1', 'p2', 'p3', 'stop'] as $f) foreach ($s as $x) if ($x[$f] !== '' && $h[$f] === '') $h[$f] = $x[$f];
    $h['ids'] = array_column($s, 'id');
    $g[$k] = $h;
  }
  return $g;
}
// které kolo připomínky je na řadě (0 = žádné). $hned = tlačítko „Poslat připomínku teď“ (bez čekání na lhůtu)
function terminy_dalsi_kolo(array $x, bool $hned = false): int {
  if ($x['email'] === '' || $x['stop'] !== '') return 0;
  $do = $x['do']; $z = $x['do_dnu'];
  if ($do !== '' && $z < 0) return ($x['p3'] === '' && ($hned || $z >= -7)) ? 3 : 0;        // platnost skončila → poslední zpráva (nejvýš 7 dní po konci)
  if ($do === '' && $x['p1'] !== '' && $x['p3'] === '' && $x['dnu'] >= 14 && $x['dnu'] <= 30) return 3;   // nabídka bez data platnosti: poslední zpráva po 14 dnech
  if ($do !== '' && $z <= 2 && $x['p2'] === '') return 2;                                     // blíží se konec fixace
  if ($x['p1'] === '' && $x['p2'] === '' && ($hned || ($x['dnu'] >= 3 && $x['dnu'] <= 21)) && ($do === '' || $z > 2)) return 1;   // 3 dny ticho (starší než 3 týdny už ne)
  if ($hned) { if ($x['p1'] === '' && $x['p2'] === '') return 1; if ($do !== '' && $x['p2'] === '') return 2; if ($x['p3'] === '') return 3; }
  return 0;
}
// odhad, kdy půjde další automatická připomínka ('' = žádná)
function terminy_dalsi_kdy(array $x): string {
  if ($x['email'] === '' || $x['stop'] !== '') return '';
  $t = strtotime($x['odeslano']);
  if ($x['p1'] === '' && $x['p2'] === '' && $x['dnu'] <= 21 && ($x['do'] === '' || $x['do_dnu'] > 2)) return date('Y-m-d', max(strtotime(date('Y-m-d')), $t + 3 * 86400));
  if ($x['do'] !== '' && $x['p2'] === '' && $x['do_dnu'] >= 0) return date('Y-m-d', max(strtotime(date('Y-m-d')), strtotime($x['do']) - 2 * 86400));
  if ($x['do'] !== '' && $x['p3'] === '' && $x['do_dnu'] >= -7) return date('Y-m-d', max(strtotime(date('Y-m-d')), strtotime($x['do']) + 86400));
  if ($x['do'] === '' && $x['p3'] === '' && $x['dnu'] <= 30) return date('Y-m-d', max(strtotime(date('Y-m-d')), $t + 14 * 86400));
  return '';
}
// pošle připomínku nabídky (kolo 1–3) a zapíše ji ke všem záznamům skupiny; vrací true/false
function terminy_poslat_nabidku(PDO $pdo, array $cfg, string $DATA, array $x, int $kolo, bool $suchy = false): bool {
  require_once __DIR__ . '/posta.php';
  $url = $suchy ? terminy_base($cfg) . '/hodnoceni.php?t=(zkouška)' : terminy_hodnoceni_url($pdo, $cfg, $x['id']);
  [$predmet, $telo] = $kolo === 1 ? terminy_text_nabidka_1($x, $url) : ($kolo === 2 ? terminy_text_nabidka_2($x, $url) : terminy_text_nabidka_3($x, $url));
  $cc = $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [];
  $ok = $suchy ? true : posli_mail($cfg, $DATA, [$x['email']], $cc, $cfg['from'], $predmet, $telo, [], '', $x['prodavac']);
  if ($ok && !$suchy) {
    $dnes = date('Y-m-d');
    foreach ((array)($x['ids'] ?? [$x['id']]) as $i) {
      $pdo->prepare('UPDATE zadosti SET nab_pripom' . $kolo . '_dne=? WHERE id=?')->execute([$dnes, $i]);
      terminy_hist($pdo, (int)$i, $kolo === 3 ? 'automatická poslední zpráva zákazníkovi (platnost skončila)' : "automatická připomínka zákazníkovi ($kolo)", $x['email']);
    }
  }
  return $ok;
}

function terminy_zbyva_txt(int $s): string {
  $a = abs($s); $h = (int)floor($a / 3600); $m = (int)floor(($a % 3600) / 60);
  $txt = $h >= 24 ? floor($h / 24) . ' d ' . ($h % 24) . ' h' : ($h > 0 ? "$h h $m min" : "$m min");
  return $s < 0 ? "po termínu o $txt" : "zbývá $txt";
}

// tlačítka v boxu na úvodu (POST na index.php): poslat připomínku teď, nepřipomínat / znovu připomínat, reakce zákazníka vyřízena
function terminy_akce(PDO $pdo): string {
  global $cfg, $DATA;
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['term_akce'])) return '';
  $ak = (string)$_POST['term_akce']; $id = (int)($_POST['id'] ?? 0); if ($id <= 0) return '';
  $kdo = function_exists('uziv_jmeno') ? uziv_jmeno() : '';
  if ($ak === 'hotovo') { $pdo->prepare('UPDATE zadosti SET nab_reakce_hotovo=? WHERE id=?')->execute([date('Y-m-d H:i:s') . ($kdo !== '' ? " $kdo" : ''), $id]); terminy_hist($pdo, $id, 'reakce zákazníka vyřízena'); return 'Označeno jako vyřízené.'; }
  $sk = null; foreach (terminy_nabidky_skupiny(terminy_nabidky($pdo)) as $g) if (in_array($id, $g['ids'], true)) $sk = $g;
  if (!$sk) return 'Nabídka už není ve stavu Odeslána.';
  if ($ak === 'stop' || $ak === 'znovu') {
    foreach ($sk['ids'] as $i) { $pdo->prepare('UPDATE zadosti SET nab_stop=? WHERE id=?')->execute([$ak === 'stop' ? 'ručně ' . date('Y-m-d') . ($kdo !== '' ? " $kdo" : '') : '', $i]); terminy_hist($pdo, $i, $ak === 'stop' ? 'automatické připomínky vypnuty' : 'automatické připomínky znovu zapnuty'); }
    return $ak === 'stop' ? 'Připomínky k nabídce ' . $sk['cislo'] . ' jsou vypnuté.' : 'Připomínky k nabídce ' . $sk['cislo'] . ' jsou znovu zapnuté.';
  }
  if ($ak === 'poslat') {
    if ($sk['email'] === '') return 'Nabídka nemá e-mail zákazníka.';
    if (in_array(date('Y-m-d'), [$sk['p1'], $sk['p2'], $sk['p3']], true)) return 'Dnes už zákazník připomínku dostal.';
    $sk['stop'] = ''; $kolo = terminy_dalsi_kolo($sk, true);
    if (!$kolo) return 'Všechny připomínky k této nabídce už odešly.';
    return terminy_poslat_nabidku($pdo, $cfg, $DATA, $sk, $kolo) ? 'Připomínka (' . $kolo . ') odeslána na ' . $sk['email'] . '.' : 'Připomínku se nepodařilo odeslat (chyba e-mailu).';
  }
  return '';
}

// pruh pod horní lištou na úvodní stránce (jen když je co hlídat)
function terminy_banner(): void {
  if (is_file(__DIR__ . '/navaznost.php')) { try { require_once __DIR__ . '/navaznost.php'; vz_box(0); } catch (Throwable $e) {} }   // 5. 11. 2026: dotaz, které poptávky uzavřít (lib/navaznost.php)
  try {
    $pdo = db(); terminy_tabulky($pdo);
    $zprava = terminy_akce($pdo);
    $p = terminy_poptavky($pdo); $n = terminy_nabidky($pdo);
  } catch (Throwable $e) { return; }
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $dm = fn($v) => date('j. n.', strtotime((string)$v));
  $tl = fn($id, $ak, $txt, $styl = '') => '<form method="post" class="bdt-f"><input type="hidden" name="term_akce" value="' . $ak . '"><input type="hidden" name="id" value="' . (int)$id . '"><button' . ($styl ? ' class="' . $styl . '"' : '') . '>' . $txt . '</button></form>';
  $rad = [];
  foreach ($p as $x) {
    $kl = $x['zbyva'] < 0 ? 'cr' : ($x['zbyva'] <= TERM_VAROVANI_HODIN * 3600 ? 'or' : 'ok');
    $rad[] = ['k' => $kl, 'o' => $x['zbyva'], 'h' => '<a href="kartoteka.php?id=' . $x['id'] . '"><b>Poptávka ' . $h($x['cislo'] !== '' ? $x['cislo'] : '#' . $x['id']) . '</b> ' . $h($x['jmeno']) . '</a> <span>' . ($x['telefon'] !== '' ? '☎ ' . $h($x['telefon']) . ' · ' : '') . 'reakce do ' . date('j. n. H:i', $x['termin']) . ' (' . terminy_zbyva_txt($x['zbyva']) . ')</span>'];
  }
  $skup = terminy_nabidky_skupiny($n);
  $nab = [];
  foreach ($n as $x) {
    $cekaT = $x['dnu'] >= 3 || ($x['do_dnu'] !== null && $x['do_dnu'] <= 3) || $x['p1'] !== '';
    if (!$cekaT) continue;
    $g = $skup[$x['klic']]; $dup = count($g['ids']) > 1;
    $kl = ($x['do_dnu'] !== null && $x['do_dnu'] < 0) ? 'cr' : (($x['do_dnu'] !== null && $x['do_dnu'] <= 2) ? 'or' : 'ok');
    $konec = $x['do'] === '' ? 'bez data platnosti' : ($x['do_dnu'] < 0 ? 'platnost skončila ' . $dm($x['do']) : 'platí do ' . $dm($x['do']));
    $co = [];
    if ($g['p1'] !== '') $co[] = 'připomenuto ' . $dm($g['p1']);
    if ($g['p2'] !== '') $co[] = '2. připomínka ' . $dm($g['p2']);
    if ($g['p3'] !== '') $co[] = 'poslední zpráva ' . $dm($g['p3']);
    if ($x['email'] === '') $co[] = 'bez e-mailu – připomenout telefonem';
    elseif ($g['stop'] !== '') $co[] = 'připomínky vypnuté';
    else { $kdy = terminy_dalsi_kdy($g); $co[] = $kdy !== '' ? 'další připomínka ' . ($kdy <= date('Y-m-d') ? 'dnes' : $dm($kdy)) : 'žádná další připomínka'; }
    if ($dup) $co[] = '⚠ stejná nabídka je v kartotéce ' . count($g['ids']) . '× (zákazník dostane e-mail jen jednou)';
    $tlac = '';
    if ($x['email'] !== '') {
      if ($g['stop'] === '') $tlac = (in_array(date('Y-m-d'), [$g['p1'], $g['p2'], $g['p3']], true) ? '' : $tl($x['id'], 'poslat', '📨 Připomenout teď')) . $tl($x['id'], 'stop', 'Nepřipomínat', 'sek');
      else $tlac = $tl($x['id'], 'znovu', 'Znovu připomínat', 'sek');
    }
    $nab[] = '<div class="bdt-' . $kl . '"><a href="kartoteka.php?id=' . $x['id'] . '"><b>Nabídka ' . $h($x['cislo']) . '</b> ' . $h($x['jmeno']) . '</a> <span>bez odpovědi ' . $x['dnu'] . ' dn. · ' . $konec . ' · ' . $h(implode(' · ', $co)) . '</span>' . ($tlac !== '' ? '<div class="bdt-t">' . $tlac . '</div>' : '') . '</div>';
  }
  // zákazník v e-mailu klikl „chci upravit“ / „zavolejte mi“ → úkol, dokud ho někdo neoznačí jako vyřízený
  $ukoly = [];
  try {
    foreach ($pdo->query("SELECT id, cislo, nab_reakce, nab_reakce_dne, nab_reakce_pozn, data_json FROM zadosti WHERE typ='nabidka' AND nab_reakce IN ('upravit','zavolat') AND (nab_reakce_hotovo IS NULL OR nab_reakce_hotovo='') ORDER BY nab_reakce_dne")->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $d = json_decode((string)$r['data_json'], true) ?: []; $tel = (string)($d['telefon'] ?? '');
      $ukoly[] = '<div class="or"><a href="kartoteka.php?id=' . (int)$r['id'] . '"><b>Nabídka ' . $h($r['cislo'] ?: ($d['cislo'] ?? '')) . '</b> ' . $h(terminy_jmeno($d)) . '</a> <span>' . ($r['nab_reakce'] === 'zavolat' ? '📞 chce zavolat / konzultaci' : '✏️ chce nabídku upravit') . ($tel !== '' ? ' · ☎ <a href="tel:' . $h(preg_replace('/[^\d+]/', '', $tel)) . '">' . $h($tel) . '</a>' : '')
        . ((string)$r['nab_reakce_pozn'] !== '' ? ' · „' . $h(mb_substr((string)$r['nab_reakce_pozn'], 0, 160)) . '“' : '') . ' · ' . $h(date('j. n. H:i', strtotime((string)$r['nab_reakce_dne']))) . '</span><div class="bdt-t">' . $tl($r['id'], 'hotovo', '✓ Vyřízeno') . '</div></div>';
    }
  } catch (Throwable $e) {}
  $hod = [];
  try {
    foreach ($pdo->query("SELECT id, cislo, nab_hodnoceni, nab_hodnoceni_dne, nab_hodnoceni_srozum, nab_hodnoceni_pozn, nab_reakce, nab_reakce_pozn, data_json FROM zadosti WHERE typ='nabidka' AND nab_hodnoceni_dne IS NOT NULL AND nab_hodnoceni_dne<>'' AND nab_hodnoceni_dne >= date('now','-30 days') ORDER BY nab_hodnoceni_dne DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $d = json_decode((string)$r['data_json'], true) ?: []; $hv = (int)$r['nab_hodnoceni'];
      $upr = $r['nab_hodnoceni_srozum'] === 'upravit'; $nez = $r['nab_reakce'] === 'nezajem';
      $hod[] = '<div class="' . ($upr || $nez ? 'or' : ($hv && $hv <= 2 ? 'cr' : 'ok')) . '"><a href="kartoteka.php?id=' . (int)$r['id'] . '"><b>Nabídka ' . $h($r['cislo'] ?: ($d['cislo'] ?? '')) . '</b> ' . $h(terminy_jmeno($d)) . '</a> <span>' . ($hv ? str_repeat('★', $hv) . str_repeat('☆', 5 - $hv) : 'bez hvězdiček')
        . ($upr ? ' · chce nabídku upravit' : '') . ($r['nab_reakce'] === 'zavolat' ? ' · chce zavolat' : '') . ($nez ? ' · nemá zájem' . ((string)$r['nab_reakce_pozn'] !== '' ? ' (' . $h(mb_substr((string)$r['nab_reakce_pozn'], 0, 80)) . ')' : '') : '')
        . ((string)$r['nab_hodnoceni_pozn'] !== '' ? ' · „' . $h(mb_substr((string)$r['nab_hodnoceni_pozn'], 0, 160)) . '“' : '') . ' · ' . $h($dm($r['nab_hodnoceni_dne'])) . '</span></div>';
    }
    foreach ($pdo->query("SELECT id, cislo, mont_hodnoceni, mont_hodnoceni_dne, mont_hodnoceni_pozn, data_json FROM zadosti WHERE typ='objednavka_interni' AND mont_hodnoceni IS NOT NULL AND mont_hodnoceni_dne >= date('now','-30 days') ORDER BY mont_hodnoceni_dne DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $d = json_decode((string)$r['data_json'], true) ?: []; $hv = (int)$r['mont_hodnoceni'];
      $hod[] = '<div class="' . ($hv <= 3 ? ($hv <= 2 ? 'cr' : 'or') : 'ok') . '"><a href="kartoteka.php?id=' . (int)$r['id'] . '"><b>Montáž ' . $h($d['obj_cislo'] ?? $r['cislo']) . '</b> ' . $h(terminy_jmeno($d)) . '</a> <span>' . str_repeat('★', $hv) . str_repeat('☆', 5 - $hv)
        . ((string)$r['mont_hodnoceni_pozn'] !== '' ? ' · „' . $h(mb_substr((string)$r['mont_hodnoceni_pozn'], 0, 160)) . '“' : '') . ' · ' . $h($dm($r['mont_hodnoceni_dne'])) . '</span></div>';
    }
  } catch (Throwable $e) {}
  if (!$rad && !$nab && !$hod && !$ukoly && $zprava === '') return;
  usort($rad, fn($a, $b) => $a['o'] <=> $b['o']);
  echo '<style>.bdt{max-width:1100px;margin:12px auto;padding:0 12px;font:14px/1.4 system-ui,Arial,sans-serif}.bdt-box{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(15,39,71,.12);padding:10px 14px}.bdt h3{margin:2px 0 6px;font-size:15px;color:#0f2747}.bdt-box>div,.bdt-l{padding:5px 8px;margin:3px 0;border-radius:8px;border-left:5px solid #16a34a;background:#f0fdf4}'
     . '.bdt .bdt-or,.bdt .or{border-color:#d97706;background:#fffbeb}.bdt .bdt-cr,.bdt .cr{border-color:#dc2626;background:#fef2f2}.bdt a{color:#0f2747;text-decoration:none}.bdt span{color:#475569;display:block}.bdt span a{text-decoration:underline}@media (min-width:700px){.bdt span{display:inline;margin-left:8px}}'
     . '.bdt-t{margin-top:4px}.bdt-f{display:inline;margin:0 6px 0 0}.bdt-f button{font:600 13px system-ui,Arial,sans-serif;border:0;border-radius:8px;padding:6px 10px;background:#1f5fbf;color:#fff;cursor:pointer;margin:2px 0}.bdt-f button.sek{background:#e2e8f0;color:#1e293b}.bdt-z{background:#e0f2fe;color:#075985;border-radius:8px;padding:6px 10px;margin:0 0 6px;font-weight:600}</style><div class="bdt"><div class="bdt-box">';
  if ($zprava !== '') echo '<p class="bdt-z">' . $h($zprava) . '</p>';
  if ($ukoly) { echo '<h3>📞 Zákazníci čekají na naši reakci</h3>' . implode('', $ukoly); }
  if ($rad) { echo '<h3' . ($ukoly ? ' style="margin-top:10px"' : '') . '>⏰ Poptávky – reakce do 48 hodin</h3>'; foreach ($rad as $x) echo '<div class="' . $x['k'] . '">' . $x['h'] . '</div>'; }
  if ($nab) { echo '<h3 style="margin-top:10px">📨 Nabídky bez odpovědi</h3>' . implode('', $nab); }
  if ($hod) { echo '<h3 style="margin-top:10px">⭐ Hodnocení od zákazníků</h3>' . implode('', $hod); }
  echo '</div></div>';
}

// texty připomínek zákazníkovi (lehce vtipné, bez slibů, které nevíme splnit)
function terminy_hodnoceni_vyzva(string $url): string {
  return "\n\nStačí jedno kliknutí, ať víme, na čem jsme:\n"
    . "• Chci nabídku upravit: $url&a=upravit\n"
    . "• Zavolejte mi, chci konzultaci: $url&a=zavolat\n"
    . "• Momentálně nemám zájem: $url&a=nezajem\n\n"
    . "A pokud máte půl minuty, budeme rádi, když nám řeknete, jak jste s naší nabídkou spokojeni (1 až 5):\n$url";
}
function terminy_text_nabidka_1(array $x, string $url = ''): array {
  $c = $x['cislo']; $do = $x['do'] ? date('j. n. Y', strtotime($x['do'])) : '';
  $t = "Dobrý den,\n\nněkolik dní zpátky jsme Vám poslali cenovou nabídku č. $c a od té doby je u nás ticho. Buď Vás zcela pohltilo stěhování, nebo se dveře ještě nerozhodly, jestli jsou ty pravé.\n\n"
    . "Jen jsme se chtěli ujistit, že nabídka dorazila a že na ni nezapadl prach v e-mailu. Máte k ní otázku, chcete jiný model, barvu nebo kování? Stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235, rádi ji upravíme.\n\n"
    . ($do ? "Cenu a podmínky z nabídky Vám držíme do $do.\n\n" : '') . "Dveře počkají, my také. Jen ať to není na věky věků." . ($url !== '' ? terminy_hodnoceni_vyzva($url) : '');
  return ["Nabídka č. $c: dveře se ptají, jak jste dopadli", $t];
}
function terminy_text_nabidka_2(array $x, string $url = ''): array {
  $c = $x['cislo']; $do = date('j. n. Y', strtotime($x['do'])); $z = (int)$x['do_dnu'];
  $kdy = $z <= 0 ? 'dnes' : ($z === 1 ? 'zítra' : 'za ' . $z . ($z <= 4 ? ' dny' : ' dní'));
  $t = "Dobrý den,\n\nmalé zvonění na poplach, ale jen takové přátelské: platnost nabídky č. $c (a tím i cenová fixace) končí $kdy ($do). Pak se z nabídky stane vzpomínka a cenu bude třeba potvrdit znovu.\n\n"
    . "Pokud se Vám nabídka líbí, stačí se ozvat (odpovědí na tento e-mail nebo na +420 725 559 235) a vše s Vámi doladíme. Pokud ne, taky nám napište, ať víme, co upravit. Mlčení dveřím neprospívá, hlavně těm zavřeným." . ($url !== '' ? terminy_hodnoceni_vyzva($url) : '');
  return ["Nabídka č. $c: platnost ceny končí $kdy", $t];
}
// poslední zpráva po skončení platnosti (nebo 14 dní po nabídce bez data platnosti)
function terminy_text_nabidka_3(array $x, string $url = ''): array {
  $c = $x['cislo']; $konec = $x['do'] ? 'platnost nabídky č. ' . $c . ' skončila ' . date('j. n. Y', strtotime($x['do'])) . '.' : 'k nabídce č. ' . $c . ' jsme se zatím nedozvěděli, jak jste se rozhodli.';
  $t = "Dobrý den,\n\n$konec Tímto Vás přestáváme zasypávat připomínkami, slibujeme. 🙂\n\n"
    . "Pokud o dveře stále máte zájem, rádi Vám nabídku obnovíme nebo upravíme (ceny se mezitím mohly změnit), stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235.\n\n"
    . "A pokud jste se rozhodli jinak, pomohlo by nám vědět, co rozhodlo (cena, termín, jiný výrobce…). Stačí jedno kliknutí:\n"
    . ($url !== '' ? "• Napsat, co rozhodlo: $url&a=nezajem\n• Mám zájem, chci nabídku obnovit: $url&a=upravit\n• Zavolejte mi: $url&a=zavolat\n\n" : '')
    . "Děkujeme za Váš čas a přejeme hodně spokojenosti doma, ať už za dveřmi od nás, nebo od kohokoli jiného.";
  return ["Nabídka č. $c: poslední zpráva od nás", $t];
}
// ── hlídání zálohy na výrobu (10. 11. 2026) ──
// objednávky s požadovanou zálohou, která do 4+ dnů od objednání nedorazila (lhůta je 5 dnů) a ještě jsme neupozornili.
// Bere objednávky staré 4–8 dní (když Plánovač pár dní neběžel, dožene se to, ale starými se nezahltíme).
function terminy_zaloha_cekajici(PDO $pdo, ?int $ted = null): array {
  $ted = $ted ?? time(); $o = [];
  $st = $pdo->query("SELECT id, vytvoreno, cislo, email, data_json, zaloha_prijata_dne, zal_pripom_dne, stav FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','vyrizeno','namontovano','skladem_s','skladem_bez')");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if ((string)($r['zaloha_prijata_dne'] ?? '') !== '' || (string)($r['zal_pripom_dne'] ?? '') !== '') continue;   // už zaplaceno / už upozorněno
    $d = json_decode((string)$r['data_json'], true) ?: [];
    $zal = (float)($d['zaloha'] ?? 0); if ($zal <= 0) continue;                                   // bez požadované zálohy (hotově apod.)
    $cis = (string)($d['obj_cislo'] ?? $r['cislo']); if ($cis === '' || preg_match('/test|^\d{4}-[TP]\d/i', $cis)) continue;
    $t = strtotime((string)$r['vytvoreno']); if (!$t) continue;
    $dnu = (int)floor(($ted - $t) / 86400);
    if ($dnu < 4 || $dnu > 8) continue;
    $o[] = ['id' => (int)$r['id'], 'cislo' => $cis, 'jmeno' => terminy_jmeno($d), 'telefon' => (string)($d['telefon'] ?? ''),
      'email' => filter_var(trim((string)($d['email'] ?? $r['email'])), FILTER_VALIDATE_EMAIL) ?: '', 'zaloha' => $zal,
      'prodavac' => (string)($d['prodavac'] ?? ''), 'objednano' => $t, 'dnu' => $dnu,
      'vs' => substr(ltrim(preg_replace('/\D+/', '', $cis), '0'), 0, 10)];   // stejně jako vs() v platba/platba.js na webu (shodné s QR kódem objednávky)
  }
  return $o;
}
// vlídný, lehce vtipný e-mail zákazníkovi: čekáme na zálohu, je všechno v pořádku?
function terminy_text_zaloha_zakaznik(array $z, string $ucet): array {
  $c = $z['cislo']; $castka = number_format($z['zaloha'], 0, ',', ' ') . ' Kč';
  $t = "Dobrý den,\n\nu Vaší objednávky č. $c nám na účet zatím nedorazila záloha a chtěli jsme se jen ujistit, že je všechno v pořádku. Někdy to zdrží shon, jindy se QR kód schová na dně e-mailu a občas má člověk prostě tisíc jiných věcí na práci. 🙂\n\n"
    . "Pokud chcete pokračovat, zálohu $castka pošlete prosím na účet $ucet" . ($z['vs'] !== '' ? ", variabilní symbol {$z['vs']}" : '') . " – nejrychleji přes QR kód, který najdete v e-mailu s objednávkou a v jejím PDF. Jakmile platba dorazí, pustíme se rovnou do příprav Vašich dveří.\n\n"
    . "A kdyby se cokoli zadrhlo – potřebujete poradit, upravit objednávku, posunout termín nebo se jen na něco zeptat – stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235. Rádi pomůžeme a do ničeho Vás netlačíme.\n\nDěkujeme a přejeme hezký den.";
  return ["Objednávka č. $c – čekáme na Vaši zálohu (všechno v pořádku?)", $t];
}

// dotaz na spokojenost po předání dveří
function terminy_text_montaz(string $cislo, string $url): array {
  $t = "Dobrý den,\n\npřed pár dny jsme Vám předali nové dveře (objednávka č. $cislo). Rádi bychom věděli, jak jste spokojeni s dveřmi i s naší montáží.\n\n"
    . "Ohodnoťte nás prosím jedním kliknutím od 1 do 5 (zabere to půl minuty):\n$url\n\n"
    . "Pokud cokoli nefunguje, jak má, napište nám rovnou do hodnocení nebo odpovězte na tento e-mail, případně volejte +420 725 559 235. Rádi to vyřešíme.\n\nDěkujeme!";
  return ["Jak jste spokojeni s novými dveřmi? (objednávka $cislo)", $t];
}
