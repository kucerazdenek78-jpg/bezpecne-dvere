<?php
// jednorázově: protokoly „Servisní protokol pro seřízení oken“ z Jotformu (formulář 253133038171044) → Servis oken (9. 11. 2026)
if (($_GET['t'] ?? '') !== '8d2f61c0a9e4b7d3') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
$DATA = getenv('FORMULARE_DATA') ?: '/data';
$pdo = new PDO('sqlite:' . $DATA . '/okna.sqlite'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$hot = $pdo->query("SELECT hodnota FROM ok_nastaveni WHERE klic='import_jotform'")->fetchColumn();
if ($hot) exit("Už importováno: $hot\n");
// ARES – ověření IČ z protokolu
$ico = '28750519'; $ares = null;
$c = curl_init('https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/' . $ico); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
$x = curl_exec($c); $kod = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
if ($kod === 200 && ($j = json_decode((string)$x, true))) $ares = ['nazev' => (string)($j['obchodniJmeno'] ?? ''), 'sidlo' => (string)($j['sidlo']['textovaAdresa'] ?? '')];
echo 'ARES ', $ico, ': ', $ares ? $ares['nazev'] . ' | ' . $ares['sidlo'] : 'nenalezeno (HTTP ' . $kod . ')', "\n";
$ted = date('Y-m-d H:i:s');
$klic = function (string $tel, string $jm) { $t = preg_replace('/\D+/', '', $tel); if (strlen($t) >= 9) return 't' . substr($t, -9); return 'j' . md5(mb_strtolower(trim($jm)) . '|most'); };
$J = 'Z Jotformu („Servisní protokol pro seřízení oken“), odeslání ';
// [jotform id, datum, čas, jméno, telefon pro klíč, telefon k zápisu, ulice, výsledek, popis, fotky, stav, poznámka navíc]
$P = [
  ['6385959392384095772', '2025-11-10', '15:31', 'Vasko', '', '', 'tř. Budovatelů 3075', 'Nutná oprava', 'Levý horní převod. Utržený přenašeč viz foto. Dále horní nůžky ohnuté. Okno oblepené těsněním, tudíž nepřiléhá k rámu a čepy kroutí celé kování.', 7, 'nova', 'Následná oprava zatím není zapsaná – ověřit, zda proběhla.'],
  ['6385978612381241675', '2025-11-10', '16:15', 'Baláž', '739-035-758', '', 'tř. Budovatelů 3070', 'Nutná oprava', 'Balkonové dveře – výměna převodovky a kliky.', 4, 'hotovo', 'Opraveno 16. 12. 2025 (navazující protokol).'],
  ['6400530365708343282', '2025-11-27', '12:40', 'Janda', '602-415-815', '', '', 'Nutná oprava', 'Nouzové otevření okna. Nutná výměna okenních nůžek viz foto. Okno zavřeno. Raději nepoužívat.', 6, 'hotovo', 'V protokolu jen příjmení bez adresy a telefonu – přiřazeno k Jandovi, tř. Budovatelů 3069, jen podle jména (ověřit). Zda výměna nůžek proběhla 18. 12. 2025, z protokolu nevyplývá.'],
  ['6400583246057281019', '2025-11-27', '14:10', 'Dvořáček', '', '', 'č. p. 3121', 'Seřízeno', 'Přeskočený převod. Demontáž kování, kontrola. Výměna kliky a seřízení balkónových dveří.', 3, 'hotovo', 'V protokolu jen „Dvořáček 3121“ – pravděpodobně Komořanská 3121 (Blok 701), ověřit.'],
  ['6417017021019681094', '2025-12-16', '14:04', 'Baláž', '739-035-758', '739 035 758', 'tř. Budovatelů 3070', 'Seřízeno', 'Oprava balkónových dveří ze servisního protokolu dne 10. 11. 2025: výměna převodovky a kliky. Vše plně funkční.', 1, 'hotovo', ''],
  ['6418801701215793687', '2025-12-18', '15:52', 'Janda', '602-415-815', '602 415 815', 'tř. Budovatelů 3069', 'Seřízeno', 'Výměna kování viz foto. Seřízení.', 1, 'hotovo', ''],
  ['6466146610134413299', '2026-02-11', '11:14', 'Trinh Huu Quan', '776-568-688', '776 568 688', 'tř. Budovatelů 3076/4p', 'Seřízeno', 'Oprava a seřízení dvou okenních křídel. Výměna okenních klik.', 0, 'hotovo', ''],
  ['6521485821929185568', '2026-04-13', '11:31', 'Scheitauerová', '723-682-884', '723 682 884', 'tř. Budovatelů 3075/6', 'Nutná oprava – objednání dílů', 'Vysoká oxidace horního vedení, rozlámané vedení. Provizorní oprava pro zavření. Nutná výměna dílů.', 9, 'hotovo', 'Díly vyměněny 13. 5. 2026 (navazující protokol).'],
  ['6544677141317873590', '2026-05-13', '09:46', 'Scheitauerová', '723-682-884', '', 'tř. Budovatelů 3075/6', 'Seřízeno', 'Oprava kování: horizontální vrchní lišta výměna plus roh, plus OS (v protokolu „OS fybGU“). Seřízení, vyčištění celého kování a promazání. Vše OK.', 5, 'hotovo', 'V protokolu adresa „Budovatelů 30“ bez telefonu – přiřazeno k Scheitauerové 3075/6 podle jména (ověřit).'],
];
$pdo->beginTransaction();
// družstvo Rybárna
$aresOk = $ares && $ares['nazev'] !== '' && mb_stripos($ares['nazev'], 'ryb') !== false;
$nazev = $aresOk ? $ares['nazev'] : 'Bytové družstvo Rybárna Most';
if ($ares && !$aresOk) { echo "POZOR: IČ $ico patří v ARES jinému subjektu – IČ neukládám.\n"; }
$svj = (int)$pdo->query("SELECT id FROM so_svj WHERE ic='$ico'")->fetchColumn();
if (!$svj) {
  $pdo->prepare('INSERT INTO so_svj (nazev, typ, ic, adresa, kontakt, telefon, email, poznamka, slozka, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$nazev, 'druzstvo', ($ares && !$aresOk) ? '' : $ico, 'tř. Budovatelů 3069, 3070, 3075, 3076, Most', '', '', '', 'V protokolech z Jotformu uvedeno „Rybárna Most“, IČ ' . $ico . ($aresOk ? ' (ověřeno v ARES, sídlo: ' . $ares['sidlo'] . ')' : ($ares ? ' – v ARES ale patří subjektu „' . $ares['nazev'] . '“, ověřit' : ' (v ARES neověřeno)')) . '. Vchody podle adres v protokolech.', '', $ted, $ted]);
  $svj = (int)$pdo->lastInsertId(); echo "Založeno družstvo: $nazev (id $svj)\n";
}
$blok701 = (int)$pdo->query("SELECT id FROM so_svj WHERE ic='28742699'")->fetchColumn();
$ins = $pdo->prepare('INSERT INTO so_jed (klic, jmeno, telefon, ulice, mesto, predchozi_id, stav, termin, cas, zavada, oprava, provedeno, poznamka, vytvoril, vytvoreno, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$insH = $pdo->prepare('INSERT INTO so_historie (svj_id, datum, druh, popis, dokument, vytvoreno, kdo) VALUES (?,?,?,?,?,?,?)');
$ids = [];
foreach ($P as $p) {
  [$jid, $d, $cas, $jm, $telK, $tel, $ul, $vys, $popis, $fot, $stav, $pozn] = $p;
  $opr = str_starts_with($vys, 'Nutná') ? '' : $popis; $zav = str_starts_with($vys, 'Nutná') ? $popis : '';
  $pred = null; if ($jid === '6417017021019681094') $pred = $ids['6385978612381241675'] ?? null; if ($jid === '6544677141317873590') $pred = $ids['6521485821929185568'] ?? null;
  $poz = $J . $jid . '. Výsledek: ' . $vys . '. Fotky (' . $fot . ') a podpis zákazníka jsou v Jotformu.' . ($jid !== '6400583246057281019' ? ' Bytové družstvo Rybárna Most.' : '') . ($pozn ? ' ' . $pozn : '');
  $ins->execute([$klic($telK, $jm), $jm, $tel, $ul, 'Most', $pred, $stav, $d, $cas, $zav, $opr, $stav === 'hotovo' ? $d : null, $poz, 'import Jotform', $ted, $ted]);
  $ids[$jid] = (int)$pdo->lastInsertId();
  $cil = $jid === '6400583246057281019' ? $blok701 : $svj;
  if ($cil) $insH->execute([$cil, $d, str_starts_with($vys, 'Nutná') ? 'zavady' : 'servis', 'Byt ' . $jm . ($ul ? ', ' . $ul : '') . ' – ' . $vys . ': ' . $popis . ($jid === '6400583246057281019' ? ' (ověřit, že jde o Blok 701)' : ''), 'Jotform odeslání ' . $jid, $ted, 'import Jotform']);
  echo "so_jed {$ids[$jid]}: $d $jm – $vys\n";
}
$pdo->prepare("INSERT INTO ok_nastaveni (klic, hodnota) VALUES ('import_jotform', ?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota")->execute([$ted . ' (9 protokolů)']);
$pdo->commit();
echo "Hotovo.\n";
