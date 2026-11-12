<?php
// CLI: hlídání termínů (Plánovač úloh na NAS, ideálně každou hodinu mezi 7 a 20 h):
//   docker exec formulare-web php /var/www/html/terminy-pripominky.php          (ostrý běh)
//   docker exec formulare-web php /var/www/html/terminy-pripominky.php --suchy  (jen vypíše, nic neposílá ani nezapisuje)
// 1) poptávka bez vyřízení: když do 48 h od přijetí zbývá 12 h nebo už termín uplynul → jeden souhrnný e-mail firmě (příjemci poptávek z config.php)
// 1b) termín 48 h uplynul a z poptávky nevznikla nabídka ani objednávka → jednou omluvný e-mail zákazníkovi („stále pracujeme“), kopie firmě;
//     zároveň se v Google Kalendáři Zdeňka objeví upozornění na 19:30 (kontakty-sync.php → Apps Script, viz lib/kontakty-skript.gs)
// 2) nabídka (stav Odeslána) bez odezvy → zákazníkovi: a) po 3 dnech od odeslání, b) 2 dny před koncem platnosti nabídky (pole „Nabídka platí do“),
//    c) (5. 11. 2026) poslední zpráva 1–7 dní po skončení platnosti („co rozhodlo?“), u nabídky bez data platnosti 14 dní po odeslání.
//    V e-mailu jsou odkazy jedním klikem (chci upravit / zavolejte mi / nemám zájem) a hodnocení nabídky (hodnoceni.php).
//    Každá připomínka se pošle nejvýš jednou; duplicitní záznamy téže nabídky (stejný e-mail a číslo) dostanou jeden e-mail.
//    Připomínky se zastaví, když zákazník odpoví přes odkaz (upravit / zavolat / nemám zájem) nebo je někdo vypne tlačítkem „Nepřipomínat“ na úvodu.
// 3) (5. 11. 2026) 5–30 dní po předání dveří (od 1. 11. 2026) jednou dotaz na spokojenost s dveřmi a montáží (hodnoceni.php?m=…).
// 4) (10.–12. 11. 2026) objednávka s požadovanou zálohou (převodem), která do nastavené lhůty nedorazila na účet:
//    první upozornění = e-mail účetní (ať zavolá zákazníkovi, kopie firmě) + vlídný e-mail zákazníkovi, že na platbu čekáme;
//    připomínka zákazníkovi se může opakovat. Vše konfigurovatelné ve Správě → E-maily (lib/nastaveni.php, nast_zaloha), logika v lib/terminy-zaloha.php.
// Mimo pracovní dobu (před 7 a po 20 h) se nic neposílá.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found'); }
require __DIR__ . '/lib/start.php'; require __DIR__ . '/lib/posta.php'; require __DIR__ . '/lib/terminy.php'; require __DIR__ . '/lib/terminy-zaloha.php';
$suchy = in_array('--suchy', $argv ?? [], true); $pdo = db(); terminy_tabulky($pdo); zaloha_tabulky($pdo);
if (is_file(__DIR__ . '/lib/stav.php')) { require_once __DIR__ . '/lib/stav.php'; if (!$suchy) bd_stavy_sync($pdo, true); }   // poptávka, ze které vznikla nabídka / objednávka, je Vyřízeno a nehlídá se
$log = fn($s) => print(date('Y-m-d H:i:s') . ' ' . ($suchy ? '[suchý] ' : '') . $s . "\n");
$hod = (int)date('G'); if (!$suchy && ($hod < 7 || $hod >= 20)) { $log('mimo pracovní dobu, nic se neposílá'); exit; }
$ted = time();

// 1) poptávky
$nove = []; foreach (terminy_poptavky($pdo, $ted) as $p) if ($p['zbyva'] <= TERM_VAROVANI_HODIN * 3600 && $p['upozorneno'] === '') $nove[] = $p;
$komu = array_values(array_filter((array)($cfg['prijemci']['poptavka']['to'] ?? []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
if ($nove && $komu) {
  $radky = ''; foreach ($nove as $p) $radky .= '• ' . ($p['cislo'] !== '' ? $p['cislo'] : '#' . $p['id']) . ' ' . $p['jmeno'] . ($p['telefon'] !== '' ? ' (' . $p['telefon'] . ')' : '') . ' – reakce do ' . date('j. n. Y H:i', $p['termin']) . ', ' . terminy_zbyva_txt($p['zbyva']) . "\n";
  $web = terminy_base($cfg);
  $telo = "Hlídání termínů: u těchto poptávek se blíží (nebo už uplynul) slíbený termín reakce do 48 hodin:\n\n$radky\nOtevřít kartotéku: $web/kartoteka.php?typ=poptavka\n";
  $ok = $suchy ? true : posli_mail($cfg, $DATA, $komu, [], $cfg['from'], 'Pozor na termín: poptávky čekají na reakci (' . count($nove) . ')', $telo);
  $log('poptávky: ' . count($nove) . ' → ' . implode(', ', $komu) . ($ok ? ': odesláno' : ': SELHALO'));
  if ($ok && !$suchy) foreach ($nove as $p) $pdo->prepare('UPDATE zadosti SET term_interni_dne=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $p['id']]);
} elseif ($nove) $log('poptávky k upozornění: ' . count($nove) . ', ale v config.php chybí příjemci poptávek (prijemci.poptavka.to)');

// 1b) omluva zákazníkovi: slíbený termín (48 h) uplynul a z poptávky zatím nevznikla nabídka ani objednávka (poptávka není Vyřízeno) → jednou e-mail „pracujeme na tom“
//     (jen u poptávek, které prošvihly termín nejvýš před 7 dny; firma dostane kopii)
foreach (terminy_poptavky($pdo, $ted) as $p) {
  if ($p['zbyva'] >= 0 || $p['zbyva'] < -7 * 86400) continue;
  $r = $pdo->query('SELECT email, data_json, term_omluva_dne FROM zadosti WHERE id=' . (int)$p['id'])->fetch(PDO::FETCH_ASSOC); if (!$r || (string)$r['term_omluva_dne'] !== '') continue;
  $d = json_decode((string)$r['data_json'], true) ?: [];
  $em = filter_var(trim((string)($d['email'] ?? $r['email'])), FILTER_VALIDATE_EMAIL); if (!$em) continue;
  $cis = $p['cislo'] !== '' ? ' č. ' . $p['cislo'] : '';
  // 5. 11. 2026: text varianta B (vybral Zdeněk)
  $telo = "Dobrý den,\n\ntento týden jako by se všichni rozhodli vyměnit dveře najednou. Máme radost, ale nabídka k Vaší poptávce$cis se kvůli tomu trochu zdržela a nestihli jsme slíbené dva dny. Omlouváme se.\n\n"
    . "Nezapadla, je rozpracovaná na stole a dostanete ji co nejdřív. Kdybyste chtěli něco doplnit nebo upřesnit, odpovězte na tento e-mail nebo volejte +420 725 559 235.\n\nDíky, že to s námi vydržíte.";
  $ok = $suchy ? true : posli_mail($cfg, $DATA, [$em], $komu, $cfg['from'], 'Vaše nabídka se trochu zdržela' . ($cis !== '' ? ' (poptávka' . $cis . ')' : ''), $telo);
  $log("poptávka " . ($p['cislo'] !== '' ? $p['cislo'] : '#' . $p['id']) . ": omluva zákazníkovi → $em: " . ($ok ? 'odesláno' : 'SELHALO'));
  if ($ok && !$suchy) $pdo->prepare('UPDATE zadosti SET term_omluva_dne=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $p['id']]);
}

// 2) nabídky zákazníkům (duplicitní záznamy téže nabídky = jedna skupina = jeden e-mail)
foreach (terminy_nabidky_skupiny(terminy_nabidky($pdo, date('Y-m-d'))) as $x) {
  $kolo = terminy_dalsi_kolo($x); if (!$kolo) continue;
  $ok = terminy_poslat_nabidku($pdo, $cfg, $DATA, $x, $kolo, $suchy);
  $log("nabídka {$x['cislo']}: " . ($kolo === 3 ? 'poslední zpráva' : "připomínka $kolo") . " → {$x['email']}" . (count($x['ids']) > 1 ? ' (záznamy ' . implode(', ', $x['ids']) . ')' : '') . ': ' . ($ok ? 'odesláno' : 'SELHALO'));
}

// 3) spokojenost s dveřmi a montáží: 5–30 dní po předání, jednou
$od = date('Y-m-d', $ted - 30 * 86400); $do = date('Y-m-d', $ted - 5 * 86400);
$st = $pdo->prepare("SELECT id, cislo, email, data_json, predano_dne FROM zadosti WHERE typ='objednavka_interni' AND predano_dne IS NOT NULL AND predano_dne<>'' AND substr(predano_dne,1,10) BETWEEN ? AND ? AND substr(predano_dne,1,10) >= ? AND (mont_dotaz_dne IS NULL OR mont_dotaz_dne='') AND stav<>'storno'");
$st->execute([$od, $do, TERM_MONTAZ_OD]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $z) {
  $d = json_decode((string)$z['data_json'], true) ?: []; $cislo = (string)($d['obj_cislo'] ?? $z['cislo']);
  if (stripos($cislo, 'test') !== false || preg_match('/^\d{4}-T\d+/i', $cislo)) continue;
  $em = filter_var(trim((string)($d['email'] ?? $z['email'])), FILTER_VALIDATE_EMAIL); if (!$em) continue;
  $url = $suchy ? terminy_base($cfg) . '/hodnoceni.php?m=(zkouška)' : terminy_montaz_url($pdo, $cfg, (int)$z['id']);
  [$predmet, $telo] = terminy_text_montaz($cislo, $url);
  $ok = $suchy ? true : posli_mail($cfg, $DATA, [$em], [], $cfg['from'], $predmet, $telo, [], '', (string)($d['prodavac'] ?? ''));
  $log("objednávka $cislo: dotaz na spokojenost s montáží → $em: " . ($ok ? 'odesláno' : 'SELHALO'));
  if ($ok && !$suchy) { $pdo->prepare('UPDATE zadosti SET mont_dotaz_dne=? WHERE id=?')->execute([date('Y-m-d'), $z['id']]); terminy_hist($pdo, (int)$z['id'], 'dotaz na spokojenost s montáží odeslán', $em); }
}

// 4) nezaplacená záloha: do nastavené lhůty nedorazila → poprvé účetní (ať zavolá) + vlídný e-mail zákazníkovi; připomínka zákazníkovi se dle nastavení opakuje
$zcfg = nast_zaloha($cfg);
$ucetni = array_values(array_filter(nast_ucetni($cfg), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
$ucet = nast_ucet($cfg);
$firma = array_values(array_filter((array)($cfg['prijemci']['objednavka_interni']['to'] ?? []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
$web = terminy_base($cfg);
foreach (terminy_zaloha_cekajici($pdo, $zcfg, $ted) as $z) {
  $okU = null;
  if ($z['faze'] === 'prvni') {
    // první upozornění: e-mail účetní (kopie firmě), ať zavolá zákazníkovi
    if (!$ucetni) { $log("záloha {$z['cislo']}: {$z['dnu']} dní bez platby, ale chybí e-mail účetní (Nastavení e-mailů) – přeskočeno"); continue; }
    $telU = "Dobrý den,\n\nu objednávky č. {$z['cislo']} nám zatím nedorazila záloha a od objednání uběhlo {$z['dnu']} dní.\n\n"
      . "Zákazník: " . ($z['jmeno'] !== '' ? $z['jmeno'] : '(bez jména)') . ($z['telefon'] !== '' ? "\nTelefon: {$z['telefon']}" : '') . ($z['email'] !== '' ? "\nE-mail: {$z['email']}" : '') . "\n"
      . "Požadovaná záloha: " . number_format($z['zaloha'], 0, ',', ' ') . " Kč, VS {$z['vs']}, účet $ucet\n"
      . "Objednáno: " . date('j. n. Y', $z['objednano']) . "\n\n"
      . "Zavolejte prosím zákazníkovi, ať víme, jestli platbu chystá, nebo jestli něco nebrání. Zákazníkovi jsme zároveň poslali vlídný e-mail, že na platbu čekáme.\n\n"
      . "Otevřít v kartotéce: $web/kartoteka.php?id={$z['id']}\n";
    $okU = $suchy ? true : posli_mail($cfg, $DATA, $ucetni, $firma, $cfg['from'], '⚠ Nezaplacená záloha – objednávka č. ' . $z['cislo'] . ' (zavolejte zákazníkovi)', $telU);
  }
  // vlídný e-mail zákazníkovi (poprvé i při opakování)
  $okZ = null;
  if ($z['email'] !== '') {
    [$predmet, $telo] = terminy_text_zaloha_zakaznik($z, $ucet);
    $okZ = $suchy ? true : posli_mail($cfg, $DATA, [$z['email']], [], $cfg['from'], $predmet, $telo, [], '', $z['prodavac']);
  }
  $poslano = ($z['faze'] === 'prvni' ? ($okU || $okZ) : (bool)$okZ);   // za odeslané bereme, když aspoň jeden e-mail prošel
  if ($z['faze'] === 'prvni') $log("záloha {$z['cislo']}: 1. upozornění – účetní → " . implode(', ', $ucetni) . ($okU ? ' (odesláno)' : ' (SELHALO)') . '; zákazník ' . ($z['email'] !== '' ? $z['email'] . ($okZ ? ' (odesláno)' : ' (SELHALO)') : '(bez e-mailu)'));
  else $log("záloha {$z['cislo']}: připomínka zákazníkovi (" . ($z['pocet'] + 1) . ".) → " . ($z['email'] !== '' ? $z['email'] . ($okZ ? ' (odesláno)' : ' (SELHALO)') : '(bez e-mailu)'));
  if ($poslano && !$suchy) {
    $pdo->prepare('UPDATE zadosti SET zal_pripom_dne=?, zal_pripom_pocet=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $z['pocet'] + 1, $z['id']]);
    terminy_hist($pdo, (int)$z['id'], $z['faze'] === 'prvni' ? ('upozornění na nezaplacenou zálohu (účetní' . ($z['email'] !== '' ? ' + zákazník' : '') . ')') : ('připomínka nezaplacené zálohy zákazníkovi (' . ($z['pocet'] + 1) . '.)'), $z['faze'] === 'prvni' ? implode(', ', $ucetni) : $z['email']);
  }
}
