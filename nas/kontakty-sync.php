<?php
// Zákazníci z kartotéky pro synchronizaci do Google Kontaktů (skript Google Apps Script v Google účtu Zdeňka si je stahuje každých 30 min).
// Veřejné na formulare.dvere.app, ale data vrací jen s tajným klíčem (?t=…, 40 hex znaků) uloženým v /data/kontakty-sync.json.
// Klíč se zakládá / mění v kartotéce: Zákazníci → „Google Kontakty“ (zakaznici.php?google=1). Starý klíč po změně přestane platit.
// Vrací jen kontaktní údaje (jméno, firma, telefon, e-mail, adresa, čísla posledních záznamů), žádné ceny.
// Patří na NAS do /docker/formulare/app/kontakty-sync.php.
require __DIR__ . '/lib/start.php';
require_once __DIR__ . '/lib/zakaznici.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
function ks_konec(int $kod, array $o) { http_response_code($kod); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') ks_konec(405, ['ok' => false]);
$pdo = db();

// ochrana: nejvýš 30 dotazů za hodinu z jedné adresy (i se špatným klíčem)
$ip = klient_ip();
$st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='kontakty' AND cas>?"); $st->execute([$ip, time() - 3600]);
if ((int)$st->fetchColumn() >= 30) ks_konec(429, ['ok' => false, 'chyba' => 'limit']);
$pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'kontakty', ?)")->execute([$ip, time()]);

$soubor = $DATA . '/kontakty-sync.json';
$nast = is_file($soubor) ? (json_decode((string)file_get_contents($soubor), true) ?: []) : [];
$t = (string)($_GET['t'] ?? '');
if (empty($nast['token']) || !preg_match('/^[0-9a-f]{40}$/', $t) || !hash_equals((string)$nast['token'], $t)) { usleep(300000); ks_konec(403, ['ok' => false, 'chyba' => 'klic']); }

zk_tabulka($pdo);
$TYPY = ['poptavka' => 'poptávka', 'objednavka_interni' => 'objednávka', 'objednavka_zakaznik' => 'objednávka', 'nabidka' => 'nabídka'];
$out = [];
foreach (zk_vsichni($pdo, zk_info($pdo)) as $k => $c) {
  if (trim($c['telefon']) === '' && trim($c['email']) === '') continue;
  $cs = preg_split('/\s+/u', trim($c['jmeno'])); $prij = count($cs) > 1 ? array_pop($cs) : '';
  $zn = array_reverse($c['zaznamy']);
  $pozn = 'Zákazník Bezpečné dveře (kartotéka).';
  if ($zn) $pozn .= ' Poslední: ' . ($TYPY[$zn[0]['typ']] ?? $zn[0]['typ']) . ' ' . $zn[0]['cislo'] . ', ' . date('j. n. Y', strtotime((string)$zn[0]['datum'])) . '.';
  $cisla = array_values(array_filter(array_unique(array_column($zn, 'cislo'))));
  if (count($cisla) > 1) $pozn .= ' Záznamy: ' . implode(', ', array_slice($cisla, 0, 10)) . '.';
  $out[] = ['klic' => $k, 'jmeno' => implode(' ', $cs), 'prijmeni' => $prij, 'firma' => $c['firma'], 'telefon' => $c['telefon'], 'email' => $c['email'],
    'ulice' => $c['ulice'], 'mesto' => $c['mesto'], 'psc' => $c['psc'], 'poznamka' => $pozn];
}
$nast['stazeno'] = date('Y-m-d H:i:s'); $nast['pocet'] = count($out);
@file_put_contents($soubor, json_encode($nast, JSON_UNESCAPED_UNICODE), LOCK_EX);
ks_konec(200, ['ok' => true, 'cas' => date('c'), 'zakaznici' => $out]);
