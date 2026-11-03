<?php
// Jednorázový import zákazníků servisu oken ze složek na firemním NASu: /Firma/0 KKSYSTEM SHAREPOINT/7 SERVIS/ZÁKAZNÍCI (3. 11. 2026).
// Údaje jsou jen ty, které jsou v dokumentech (nabídky, smlouvy, faktury, zápisy). Data „(datum souboru)“ jsou data uložení souboru, ne datum v dokumentu.
// Smlouvy: podepsaná je doložena jen u Bloku 266 (jednorázová, 26. 6. 2024). Ostatní jsou návrhy → stav „ověřit“ / „návrh“ – potvrdí se ručně.
// Spouští se z lib.php jen jednou (ok_nastaveni import_nas); zákazník se stejnou složkou se znovu nezaloží.
$Z = '/Firma/0 KKSYSTEM SHAREPOINT/7 SERVIS/ZÁKAZNÍCI/';
$zak = [
  ['BLOK 8', 'SV bl. 8 – SNP 2649, 2650', 'svj', '', 'SNP 2649, 2650, Most', 'Ing. Jana Nemétová', '', '', [
    ['2025-01-22', 'nabidka', 'Nabídky 25NA00001 (jednorázový), 25NA00002 (3× opakování), 25NA00003 (5× opakování) (datum souboru)', 'Nabídka_25NA00003-5opakování.pdf'],
    ['2025-04-09', 'nabidka', 'Nabídka 25NA00007 – příloha návrhu smlouvy, 5× opakovaný servis', 'SMLOUVA/Nabídka_25NA00007.pdf'],
    ['2025-06-18', 'smlouva', 'Servisní smlouva okna a dveře ver. 2 – návrh (120 měsíců) (datum souboru)', 'SMLOUVA/Servisní smlouva okna a dveře ver.2 - návrh.docx', 'S'],
    ['2025-11-14', 'servis', 'Servis oken 13.–14. 11. 2025 – zápis o provedení servisu (datum podle protokolů v 2025/)', '2025/zápis blok 8 provedení servisu .pdf', 'S'],
  ], ['od' => '2025-06-18', 'pocet' => 5, 'interval' => 2, 'stav' => 'overit', 'nabidka' => '25NA00007', 'dokument' => 'SMLOUVA/Servisní smlouva okna a dveře ver.2 - návrh.docx',
      'poznamka' => 'Na NASu je jen návrh ver. 2 (120 měsíců, příloha nabídka 25NA00007, 5×). Ověřit podpis a datum podpisu; 5 servisů za 10 let = jednou za 2 roky.']],
  ['BLOK 78', 'SVJ Blok 78 – Antonína Dvořáka 2216, 2217', 'svj', '', 'Antonína Dvořáka 2216, 2217, Most', 'Ing. Petra Komanová', '721 551 336', '', [
    ['2025-08-11', 'nabidka', 'Nabídka 25NA00008', 'Nabídka_25NA00008.pdf'],
    ['2025-10-14', 'servis', 'Servis – předávací protokol vlastníci a nájemníci 202500008 (datum souboru)', 'předavací protokol vlastníci a nájemníci 202500008.pdf'],
  ]],
  ['BLOK 82 MOST Žilková', 'SVJ Blok 82', 'svj', '', 'Josefa Skupy, Most', 'Žilková', '', '', [
    ['2020-02-10', 'nabidka', 'Nabídka 20NA00002', 'Nabídka_20NA00002.pdf'],
  ]],
  ['Blok 263A Most', 'SV bl. 263A – č. p. 2546–2548', 'svj', '', 'č. p. 2546–2548 (u Zd. Fibicha), Most', '', '', '', [
    ['2013-10-29', 'faktura', 'Faktura a dodací list 130100039', 'Faktura_130100039.pdf'],
  ]],
  ['BLOK 266 Most', 'SV bl. 266 – tř. Budovatelů 2915, 2916, 2917', 'svj', '25467514', 'tř. Budovatelů 2915, 2916, 2917, Most (sídlo Zdeňka Štěpánka 425/32)', 'Ing. František Koudela (předseda), Ondřej Mentlík', '', '', [
    ['2024-04-16', 'nabidka', 'Nabídka 24NA00004 + Seznámení s průběhem servisu', 'Nabídka_24NA00004.pdf'],
    ['2024-06-26', 'smlouva', 'Servisní smlouva (jednorázový servis) podepsaná objednatelem 26. 6. 2024', '2024/Okna 2024 Smlouva k podpisu.pdf', 'S'],
    ['2024-10-01', 'servis', 'Servis oken 1. 10. 2024 – zápis ze servisu', '2024/Fakturace a vyúčtování 2024/Zápis ze servisu dne 01-10-2024.pdf', 'S'],
    ['2024-10-01', 'faktura', 'Faktura a dodací list 240100182', '2024/Fakturace a vyúčtování 2024/Faktura_240100182.pdf'],
  ], ['od' => '2024-06-26', 'pocet' => 1, 'interval' => 1, 'stav' => 'podepsana', 'nabidka' => '24NA00004', 'dokument' => '2024/Okna 2024 Smlouva k podpisu.pdf',
      'poznamka' => 'Jednorázový servis – splněno 1. 10. 2024. Nabídnout smlouvu na opakovaný servis.']],
  ['BLOK 381 Lancová', 'Bytové družstvo 381', 'druzstvo', '', 'J. Průchy 2909, 2910, 2911, Most', 'Lenka Lancová (předsedkyně)', '', 'Ve složce je i „návrh servisní smlouva okna.pdf“, který je ale na SVJ č. p. 424–427 Zd. Štěpánka (Linhartová) – patří spíš k Bloku 624.', [
    ['2018-09-18', 'nabidka', 'Nabídkové dopisy 18NA00013 (5×), 18NA00014 (3×), 18NA00015 (1×) + ceník 2018, Seznámení s průběhem servisu', 'Nabídkový_dopis_18NA00013 - 5x.pdf'],
    ['2018-12-03', 'servis', 'Servis 2018 – dílčí předávací protokoly vchodů 2909 (23. 11.), 2910 a 2911 (3. 12. 2018) (data souborů)', '2018/PŘEDÁVACÍ PROTOKOLY DÍLČÍ/Předávací protokol 2911-2018 Lancová.pdf'],
  ]],
  ['BLOK 504 Most', 'Blok 504 Most', 'dum', '', 'Most', '', '', '', [
    ['2017-11-28', 'jine', 'Objednávka těsnění okna (datum souboru)', 'objednávka těsnění okna.pdf'],
  ]],
  ['BLOK 514 MOST', 'Blok 514 Most', 'dum', '', 'vchody 1049, 1050, 1051, 1152, Most', '', '', '', [
    ['2023-09-15', 'zavady', 'Soupis zjištěných závad 09/2023', 'Soupis zjištěných závad 09_2023 (version 1).pdf'],
  ]],
  ['Blok 616 Most', 'Blok 616 – družstvo', 'druzstvo', '', 'Česká, Most', 'Vladimír Gřunděl', '', '', [
    ['2014-09-01', 'nabidka', 'Nabídky 14NA00006, 14NA00007 (družstvo), 14NA00016 (datum souboru)', 'Nabídka_14NA00016.pdf'],
  ]],
  ['BLOK 624 Linhartová', 'SVJ bytových jednotek č. p. 424, 425, 426, 427 – Blok 624', 'svj', '28746121', 'Zd. Štěpánka 424, 425, 426, 427, Most', 'Linhartová', '', 'IČ a adresa podle návrhu smlouvy, který je uložený ve složce BLOK 381 Lancová – ověřit, že jde o Blok 624.', [
    ['2018-09-19', 'nabidka', 'Nabídkové dopisy 18NA00016 (1×), 18NA00017 (3×), 18NA00018 (5×) + ceník 2018', 'Nabídkový_dopis_18NA00018 5x.pdf'],
    ['2018-10-02', 'smlouva', 'Servisní smlouva okna a dveře ver. 2 – návrh (datum souboru)', 'Servisní smlouva okna a dveře ver.2 - návrh.doc', 'S'],
  ], ['od' => '2018-10-05', 'pocet' => 5, 'interval' => 1, 'stav' => 'navrh', 'nabidka' => '', 'dokument' => 'Servisní smlouva okna a dveře ver.2 - návrh.doc',
      'poznamka' => 'Jen návrh z roku 2018 (60 měsíců), podepsaná verze na NASu není. Pokud nebyla podepsána, smlouvu zrušte (odškrtnout „platná“).']],
  ['Blok 701', 'Bytové družstvo bl. 701 / SV bl. 701 – Komořanská 3116–3123', 'druzstvo', '28742699', 'Komořanská 3116–3123, Most', 'Ivana Hamplová, Ing. Jana Németová', '', '', [
    ['2013-11-15', 'servis', 'Servis 2013 – předávací protokoly vchodů 3116–3123, faktura 130100038', 'Předávací protokoly 2013/Faktura_130100038.pdf'],
    ['2016-04-27', 'servis', 'Servis 2016 – předávací protokoly 3116–3123, přehled závad s návrhem oprav, faktura 160100012', '2016/Předávací protokoly/Předávací protokol kompletní.pdf'],
    ['2017-07-27', 'servis', 'Servis 2017 – předávací protokoly, servisní zápis, závěr kontroly oken', '2017/Předávací protokoly/Servisní zápis + předávací protokol.pdf'],
    ['2019-10-22', 'servis', 'Servis 2019 – předávací protokoly, servisní zápis s návrhem oprav (náhradní termín 11. 11. 2019)', '2019/Předávací protokoly/Předávací protokoly byty.pdf'],
    ['2022-02-25', 'nabidka', 'Nabídka 22NA00001', '2022/Nabídka_22NA00001.pdf'],
    ['2022-09-05', 'nabidka', 'Nabídka 22NA00002 + Servisní smlouva ver. 3 – návrh s revizemi', '2022/Nabídka_22NA00002.pdf'],
    ['2022-11-16', 'servis', 'Servis 2022 – zápisy po vchodech 3116–3123 (Probehly servis)', '2022/Probehly servis/servis_701_3116_2022.pdf'],
    ['2026-10-05', 'jine', 'Nahlášení servisu komplexně 2026 (vývěska) (datum souboru)', '2026/Nahlášení servisu komplexně.pdf'],
  ], ['od' => '2022-09-05', 'pocet' => 5, 'interval' => 1, 'stav' => 'overit', 'nabidka' => '22NA00002', 'dokument' => '2022/Servisní smlouva okna a dveře ver.3 - návrh s revizemi.pdf',
      'poznamka' => 'Na NASu je jen „ver. 3 – návrh s revizemi“ (2022). Ověřit, zda a kdy byla podepsána, počet servisů a interval; datum „od“ je datum souboru.']],
  ['Blok 702 - Dospra', 'Blok 702 – Komořanská 3112–3115 (správa DOSPRA)', 'dum', '', 'Komořanská 3112–3115, Most', 'DOSPRA spol. s r.o. (správce)', '', 'Dokumenty jsou i ve složce DOSPRA MOST/BLOK 702 MOST (nabídky 17NA00001–3).', [
    ['2017-02-06', 'nabidka', 'Nabídky 17NA00001, 17NA00002, 17NA00003 (složka DOSPRA MOST/BLOK 702 MOST)', ''],
    ['2017-03-01', 'servis', 'Servis 2017 – předávací protokol vchod 3115, servis oken blok 702-2017 (datum souboru)', 'Předávácí protokoly/vchod 3115.pdf'],
  ]],
  ['Bok 316B Most', 'Blok 316B Most', 'dum', '', 'vchody 2705, 2706, 2707, 2708, Most', '', '', '', [
    ['2013-11-14', 'jine', 'Vývěsky servis oken vchody 2705–2708 (datum souboru)', 'Vývěska servis oken vchod 2705 a 2706.pdf'],
  ]],
  ['BYTOVE DRUZSTVO 123', 'Bytové družstvo 123', 'druzstvo', '', 'SNP 2604/70, Most', 'Jiří Bartásek', '', '', [
    ['2017-05-10', 'nabidka', 'Nabídkový dopis 17NA00023', 'Nabídkový_dopis_17NA00023.pdf'],
  ]],
  ['DIAKONIE MOST', 'Diakonie Most', 'instituce', '', 'Most', '', '', '', [
    ['2019-10-30', 'jine', 'Objednávka seřízení dveří (datum souboru)', 'Objednávka seřízení dveří.pdf'],
  ]],
  ['DOSPRA MOST', 'DOSPRA spol. s r.o.', 'firma', '', 'Březenecká 4475, Chomutov', '', '', 'Správce domů – Blok 536 a Blok 702 jsou založené zvlášť.', [
    ['2017-02-16', 'jine', 'Servis 48 – ceník 2017 partner Dospra (datum souboru)', 'Servis 48 - ceník 2017 partner Dospra.pdf'],
    ['2017-03-31', 'nabidka', 'Nabídkový dopis 17NA00018', 'BLOK 536 MOST/Nabídkový_dopis_17NA00018.pdf'],
  ]],
  ['DOSPRA MOST/BLOK 536 MOST', 'Blok 536 Most (správa DOSPRA)', 'dum', '', 'Most', 'Vaňková (kontakt v souboru Vaňková.contact)', '', '', [
    ['2017-03-31', 'nabidka', 'Nabídkové dopisy 17NA00017, 17NA00018', 'Nabídkový_dopis_17NA00017.pdf'],
    ['2017-04-10', 'nabidka', 'Nabídka 17NA00021 – dveřní lišta (objednávka od zákazníka 11. 4. 2017)', 'Nabídka_17NA00021-dveřní lišta.pdf'],
    ['2017-05-04', 'servis', 'Servis 2017 – předávací protokol, závěr kontroly oken (datum souboru)', 'SERVIS 2017/Závěr kontroly oken.xlsx'],
  ]],
  ['Družstvo 96', 'Družstvo 96', 'druzstvo', '', '', '', '', '', [
    ['2018-04-27', 'faktura', 'Dodací list (datum souboru)', 'dodaci list druzstvo 96.pdf'],
  ]],
  ['Finanční úřad Most', 'Finanční úřad pro Ústecký kraj – ÚzP v Mostě', 'instituce', '', '2. náměstí 3300, Most', 'Ing. Romana Šlechtová', '', '', [
    ['2020-04-28', 'nabidka', 'Servis oken a dveří – příloha poptávky, nabídka (datum souboru)', 'Servis oken a dveří_příloha poptávky – nabídka.xlsx'],
    ['2021-04-08', 'nabidka', 'Nabídka 21NA00005', 'Nabídka_21NA00005.pdf'],
    ['2023-12-07', 'nabidka', 'Servis oken a dveří – příloha nabídka 12/2023', '2023/Servis oken a dveří_příloha nabídka 12_2023.pdf'],
    ['2024-08-27', 'jine', 'Servis oken a dveří – příloha poptávky 2024 (datum souboru)', '2024/Servis oken a dveří_příloha poptávky 2024.pdf'],
  ]],
  ['J. Ježka 168 Most', 'BD Jaroslava Ježka 168/6', 'dum', '', 'Jaroslava Ježka 168/6, Most', '', '', '', [
    ['2014-08-14', 'nabidka', 'Nabídky 14NA00001–14NA00004 (srpen 2014)', 'Nabídka_14NA00004.pdf'],
  ]],
  ['Maxim 585 Most', 'Družstvo MAXIM 585 – Maxima Gorkého 1263, 1264', 'druzstvo', '25035959', 'Maxima Gorkého 1263, 1264, Most', 'Romana Musilová (předsedkyně)', '', '', [
    ['2013-11-07', 'smlouva', 'Servisní smlouva okna a dveře – blok 585 (60 měsíců, roční údržba) (datum souboru)', 'Servisní smlouva okna a dveře - blok 585.pdf', 'S'],
    ['2013-11-14', 'servis', 'Servis 2013 – servisní listy 1263 a 1264, faktura 130100040', '2013 fakturace/Faktura_130100040.pdf', 'S'],
    ['2016-04-27', 'servis', 'Servis 2016 – nahlášení servisu, kalendář, závěr kontroly oken (datum souboru)', '2016/Závěr kontroly oken.xls', 'S'],
  ], ['od' => '2013-11-07', 'pocet' => 5, 'interval' => 1, 'stav' => 'overit', 'nabidka' => '', 'dokument' => 'Servisní smlouva okna a dveře - blok 585.pdf', 'aktivni' => 0,
      'poznamka' => 'Smlouva na 60 měsíců z roku 2013 – platnost skončila. Na NASu jsou doklady jen o servisech 2013 a 2016.']],
  ['NAPROTI', 'NAPROTI', 'firma', '', '', 'Gřunděl, Zuda, Ulvr', '', '', [
    ['2018-03-08', 'servis', 'Servis (servis naproti Gřunděl Zuda Ulvr) + seznam v hodinách (datum souboru)', 'servis naproti Gřunděl Zuda Ulvr.pdf'],
  ]],
  ['Nemocnice Most', 'Nemocnice Most', 'instituce', '', 'Most', '', '', '', [
    ['2014-01-21', 'nabidka', 'Nabídka 576-NAB-2014 (datum souboru)', 'Nabídka 576-NAB-2014.pdf'],
  ]],
  ['Nemocnice Usti', 'Nemocnice Ústí nad Labem (Krajská zdravotní)', 'instituce', '', 'Ústí nad Labem', '', '', 'Oddělení 2017: chirurgie, diabetologie, urologie, gynekologie, transfuzní oddělení.', [
    ['2016-02-11', 'nabidka', 'Nabídka 973-NAB-2016 (datum souboru)', 'Nabidka 973-NAB-2016 Nemocnice Usti KZ.pdf'],
    ['2016-12-15', 'jine', 'Objednávka přetěsnění oken – transfuzní oddělení', '2017/Transfusní oddělení/2016-12-15-obj-kksystem-pretesnenioken-TRANSF.pdf'],
    ['2017-01-23', 'nabidka', 'Nabídky 1178, 1180, 1183-NAB-2017 (urologie, diabetologie, chirurgie) (datum souboru)', ''],
    ['2017-02-09', 'nabidka', 'Nabídka 1196-NAB-2017 – transfuzní oddělení 2. etapa (datum souboru)', '2017/Transfusní oddělení/Nabidka 1196-NAB-2017 transfusni oddeleni 2 etapa.pdf'],
  ]],
  ['Obec Braňany', 'Obec Braňany', 'instituce', '', 'Braňany', '', '', 'Nabídky 2018 vystavené jako ZASKLÍME-BALKONY.CZ.', [
    ['2018-10-10', 'nabidka', 'Nabídky 44, 45, 46-ZASK-2018 (svobodárna, domy s balkonovými okny) (datum souboru)', '2018/Nabídka 44-ZASK-2018 SVOBODÁRNA.pdf'],
    ['2018-12-17', 'servis', 'Servis 2018 – předávací protokoly 138–143 (svobodárny), 138 (datum souboru)', '2018/PŘEDÁVACÍ PROTOKOLY DÍLČÍ/Předávací protokol 138-143-svobodarny - Braňany.pdf'],
    ['2019-05-06', 'servis', 'Servis 2019 – SERVIS - BRAŇANY (datum souboru)', '2019/protahuje/SERVIS - BRAŇANY.pdf'],
    ['2019-05-15', 'servis', 'Výměna těsnění č. p. 142 – revizní list (datum souboru)', '2019/142 těsnění/revizní list.pdf'],
  ]],
  ['Okresní Soud MOST', 'Okresní soud v Mostě', 'instituce', '', 'Moskevská 2, Most', 'Stanislava Trollerová', '', '', [
    ['2026-07-14', 'zavady', 'Návrh oprav 2026 OS Most (datum souboru)', '2026/Návrh oprav 2026 OS MOST.pdf'],
  ]],
  ['Rejzek Společenství vlastníků Zd. Fibicha', 'SV Zd. Fibicha 2622', 'svj', '', 'Zdeňka Fibicha 2622, Most', 'Josef Rejzek', '', '', [
    ['2022-11-21', 'nabidka', 'Nabídky 22NA00004 (3×), 22NA00005 (5×), 22NA00006 (jednorázový) + ceník 2022, nevyplněný návrh smlouvy ver. 3', 'Nabídka_22NA00005 5opakovani.pdf'],
  ]],
  ['ROS czech Most', 'ROS czech Most', 'firma', '', 'Most', '', '', '', [
    ['2015-03-04', 'nabidka', 'Nabídka 831-NAB-2015 (datum souboru)', 'Nabídka 831-NAB-2015.pdf'],
    ['2015-04-14', 'nabidka', 'Nabídka 860-NAB-2015 (datum souboru)', 'Nabídka 860-NAB-2015.pdf'],
  ]],
  ['SVJ Kapitána Nálepky 2230 Louny', 'SVJ Kpt. Nálepky 2230, Louny', 'svj', '', 'Kpt. Nálepky 2230, Louny', 'Petrlík', '', '', [
    ['2017-03-29', 'nabidka', 'Nabídkový dopis 17NA00016', 'Nabídkový_dopis_17NA00016.pdf'],
  ]],
  ['Tabák skupovka Reichert', 'Tabák Skupova – Reichert', 'firma', '', 'Most', 'Reichert', '', '', [
    ['2014-05-28', 'jine', 'Fotky (datum souboru)', ''],
  ]],
  ['Zakladni škola Jirkov Mgr Rousek', 'Základní škola Jirkov', 'instituce', '', 'Jirkov', 'Mgr. Rousek', '', '', [
    ['2014-09-23', 'nabidka', 'Nabídka 723-NAB-2014 (datum souboru)', 'Nabídka č. 723-NAB-2014.pdf'],
  ]],
];
$ted = date('Y-m-d H:i:s');
$pdo->beginTransaction();
$najdi = $pdo->prepare('SELECT id FROM so_svj WHERE slozka=?');
$insZ = $pdo->prepare('INSERT INTO so_svj (nazev, typ, ic, adresa, kontakt, telefon, email, poznamka, slozka, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
$insS = $pdo->prepare('INSERT INTO so_smlouva (svj_id, cislo, od, interval_let, pocet, nabidka, poznamka, aktivni, stav_podpisu, dokument, vytvoreno) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
$insH = $pdo->prepare('INSERT INTO so_historie (svj_id, smlouva_id, datum, druh, popis, dokument, vytvoreno, kdo) VALUES (?,?,?,?,?,?,?,?)');
$n = 0;
foreach ($zak as $z) {
  [$sl, $nazev, $typ, $ic, $adr, $kont, $tel, $pozn, $hist] = $z; $sml = $z[9] ?? null;
  $cesta = $Z . $sl; $najdi->execute([$cesta]); if ($najdi->fetchColumn()) continue;
  // už založený ručně (stejné IČ nebo stejné číslo bloku v názvu) → doplní se, nezakládá se znovu
  $id = 0; $blok = preg_match('/\b(?:blok|bl\.?|bok)\s*(\d+[a-z]?)\b/iu', $sl . ' ' . $nazev, $m) ? mb_strtolower($m[1]) : '';
  foreach ($pdo->query("SELECT * FROM so_svj WHERE IFNULL(slozka,'')=''")->fetchAll(PDO::FETCH_ASSOC) as $x) {
    if (($ic !== '' && trim((string)$x['ic']) === $ic) || ($blok !== '' && preg_match('/\b(?:blok|bl\.?|bok)\s*' . preg_quote($blok, '/') . '\b/iu', (string)$x['nazev']))) { $id = (int)$x['id']; break; }
  }
  if ($id) {
    $x = so_svj($pdo, $id); $d = fn($k, $v) => trim((string)$x[$k]) !== '' ? $x[$k] : $v;
    $pdo->prepare('UPDATE so_svj SET typ=?, ic=?, adresa=?, kontakt=?, telefon=?, poznamka=?, slozka=?, upraveno=? WHERE id=?')
      ->execute([$typ, $d('ic', $ic), $d('adresa', $adr), $d('kontakt', $kont), $d('telefon', $tel), trim($x['poznamka'] . ($pozn !== '' ? "\n" . $pozn : '')), $cesta, $ted, $id]);
    if ($sml && (int)$pdo->query('SELECT COUNT(*) FROM so_smlouva WHERE svj_id=' . $id)->fetchColumn() > 0) $sml = null;   // smlouvu už má – nepřidávat
  } else { $insZ->execute([$nazev, $typ, $ic, $adr, $kont, $tel, '', $pozn, $cesta, $ted, $ted]); $id = (int)$pdo->lastInsertId(); }
  $n++;
  $smId = null;
  if ($sml) {
    $insS->execute([$id, '', $sml['od'], $sml['interval'], $sml['pocet'], $sml['nabidka'], $sml['poznamka'], $sml['aktivni'] ?? 1, $sml['stav'], $sml['dokument'] !== '' ? $cesta . '/' . $sml['dokument'] : '', $ted]);
    $smId = (int)$pdo->lastInsertId();
  }
  foreach ($hist as $h) $insH->execute([$id, ($h[4] ?? '') === 'S' ? $smId : null, $h[0], $h[1], $h[2], $h[3] !== '' ? $cesta . '/' . $h[3] : '', $ted, 'import z NAS']);
}
$pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota')->execute(['import_nas', $ted . ' (' . $n . ' zákazníků)']);
$pdo->commit();
