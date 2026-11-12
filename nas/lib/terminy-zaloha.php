<?php
// Hlídání nezaplacené zálohy (10.–12. 11. 2026): objednávky placené převodem, u kterých do nastavené lhůty
// nedorazila na účet záloha. Volá se z CLI terminy-pripominky.php. Vše konfigurovatelné ve Správě → E-maily
// (lib/nastaveni.php, nast_zaloha): lhůta první připomínky, opakování připomínky zákazníkovi (zap/vyp, po kolika
// dnech, max kolikrát), e-mail účetní, číslo účtu, příjemci interního upozornění (objednavka_interni.to).
//  - první upozornění: e-mail účetní (ať zavolá zákazníkovi, kopie firmě) + vlídný e-mail zákazníkovi s údaji k platbě.
//  - připomínka zákazníkovi se může opakovat; účetní/firma se upozorní jen poprvé, ať to nespamuje.
// Sloupce v tabulce zadosti: zal_pripom_dne (poslední připomínka zákazníkovi), zal_pripom_pocet (kolik jich odešlo).

// doplní sloupce pro hlídání zálohy, když ještě nejsou (SQLite nemá ADD COLUMN IF NOT EXISTS)
function zaloha_tabulky(PDO $pdo): void {
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('zal_pripom_dne', $sl, true)) $pdo->exec('ALTER TABLE zadosti ADD COLUMN zal_pripom_dne TEXT');
  if (!in_array('zal_pripom_pocet', $sl, true)) $pdo->exec('ALTER TABLE zadosti ADD COLUMN zal_pripom_pocet INTEGER');
}

// objednávky čekající na zálohu, kterým se má teď poslat připomínka. $z = nast_zaloha($cfg).
// Bere objednávky typu objednavka_interni, které nejsou uzavřené a mají požadovanou zálohu (data_json.zaloha > 0).
// 'faze' => 'prvni' (první upozornění + účetní/firma) nebo 'opak' (opakovaná připomínka jen zákazníkovi).
function terminy_zaloha_cekajici(PDO $pdo, array $z, ?int $ted = null): array {
  $ted = $ted ?? time();
  $lhuta = max(1, (int)($z['lhuta'] ?? 4));
  $opak = !empty($z['opak']); $opakDnu = max(1, (int)($z['opak_dnu'] ?? 2)); $opakMax = max(0, (int)($z['opak_max'] ?? 2));
  $o = [];
  $st = $pdo->query("SELECT id, vytvoreno, cislo, email, data_json, zaloha_prijata_dne, zal_pripom_dne, zal_pripom_pocet, stav FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','vyrizeno','namontovano','skladem_s','skladem_bez')");
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if ((string)($r['zaloha_prijata_dne'] ?? '') !== '') continue;                 // záloha už dorazila
    $d = json_decode((string)$r['data_json'], true) ?: [];
    $zal = (float)($d['zaloha'] ?? 0); if ($zal <= 0) continue;                     // bez požadované zálohy (hotově apod.)
    $cis = (string)($d['obj_cislo'] ?? $r['cislo']); if ($cis === '' || preg_match('/test|^\d{4}-[TP]\d/i', $cis)) continue;
    $t = strtotime((string)$r['vytvoreno']); if (!$t) continue;
    $dnu = (int)floor(($ted - $t) / 86400);
    $pdne = (string)($r['zal_pripom_dne'] ?? ''); $pocet = (int)($r['zal_pripom_pocet'] ?? 0);
    if ($pdne === '') {                                                             // ještě jsme neupozornili → první připomínka
      if ($dnu < $lhuta || $dnu > $lhuta + 4) continue;                             // moc brzy / moc staré (když Plánovač pár dní neběžel, dožene se, ale starými se nezahltíme)
      $faze = 'prvni';
    } else {                                                                        // už jsme jednou upozornili → případné opakování zákazníkovi
      if (!$opak || $pocet >= 1 + $opakMax) continue;                               // opakování vypnuté nebo vyčerpané (1 první + opak_max opakování)
      $odPosl = (int)floor(($ted - (strtotime($pdne) ?: $ted)) / 86400);
      if ($odPosl < $opakDnu) continue;                                             // od poslední připomínky ještě neuplynul nastavený interval
      $faze = 'opak';
    }
    $o[] = ['id' => (int)$r['id'], 'cislo' => $cis, 'jmeno' => terminy_jmeno($d), 'telefon' => (string)($d['telefon'] ?? ''),
      'email' => filter_var(trim((string)($d['email'] ?? $r['email'])), FILTER_VALIDATE_EMAIL) ?: '', 'zaloha' => $zal,
      'prodavac' => (string)($d['prodavac'] ?? ''), 'objednano' => $t, 'dnu' => $dnu,
      'vs' => substr(ltrim(preg_replace('/\D+/', '', $cis), '0'), 0, 10),           // stejně jako vs() v platba/platba.js na webu (shodné s QR kódem objednávky)
      'faze' => $faze, 'pocet' => $pocet];
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
