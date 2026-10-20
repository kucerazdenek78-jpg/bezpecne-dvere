<?php
// Bonus k základní záruce (od 20. 10. 2026), vše z aplikace / stránky Moje dveře:
//   • registrace dveří = +6 měsíců (automaticky, do 30 dnů od předání),
//   • recenze = další +6 měsíců (zákazník potvrdí, že ji napsal; prodavač ji v kartotéce – Servisní požadavky – schválí).
// Jen pokud má zákazník pouze základní záruku (neobjednal při objednávce prodlouženou, neplatil prodloužení) a nejde o interiérové dveře.
require_once __DIR__ . '/zaruka.php'; require_once __DIR__ . '/posta.php';
const BONUS_MESICU = 6; const BONUS_LHUTA_REG_DNI = 30;
function bonus_tabulky(PDO $pdo): void {
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  foreach (['bonus_reg_dne', 'bonus_rec_dne', 'bonus_rec_schv_dne'] as $c) if (!in_array($c, $sl, true)) $pdo->exec("ALTER TABLE zadosti ADD COLUMN $c TEXT");
}
function bonus_stav(array $z, array $d): array {
  $dnes = date('Y-m-d'); $predano = !empty($z['predano_dne']);
  $interier = (bool)preg_match('/interi|vnit[rř]n/iu', (string)($d['model_kod'] ?? '') . ' ' . (string)($d['model'] ?? ''));
  $zakladni = !preg_match('/Ano,\s*na/u', (string)($d['zaruka'] ?? '')) && empty($z['zaruka_prodl_let']) && empty($z['zaruka_prodlouzeno']);
  $mozne = $predano && !$interier && $zakladni && !empty($z['zaruka_do']);
  $lhuta = $predano ? date('Y-m-d', strtotime($z['predano_dne'] . ' +' . BONUS_LHUTA_REG_DNI . ' days')) : '';
  $reg = (string)($z['bonus_reg_dne'] ?? ''); $rec = (string)($z['bonus_rec_dne'] ?? ''); $schv = (string)($z['bonus_rec_schv_dne'] ?? '');
  return ['mozne' => $mozne, 'reg' => $reg, 'reg_lhuta' => $lhuta, 'lze_reg' => $mozne && $reg === '' && $dnes <= $lhuta,
    'rec' => $rec, 'rec_schvaleno' => $schv, 'lze_rec' => $mozne && $rec === '' && $dnes <= $lhuta, 'rec_prosla' => $mozne && $rec === '' && $dnes > $lhuta, 'mesicu' => BONUS_MESICU,
    'reg_prosla' => $mozne && $reg === '' && $dnes > $lhuta];
}
function bonus_pridej_mesice(PDO $pdo, array $z): string { $nove = date('Y-m-d', strtotime($z['zaruka_do'] . ' +' . BONUS_MESICU . ' months')); $pdo->prepare('UPDATE zadosti SET zaruka_do=? WHERE id=?')->execute([$nove, $z['id']]); return $nove; }
function bonus_zadost(PDO $pdo, int $id): array { $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$id]); return $st->fetch(PDO::FETCH_ASSOC) ?: []; }
// druh: 'reg' | 'recenze'. Vrací [ok, zpráva].
function bonus_akce(PDO $pdo, array $cfg, string $DATA, array $z, string $druh): array {
  bonus_tabulky($pdo); $z = bonus_zadost($pdo, (int)$z['id']); $d = json_decode((string)$z['data_json'], true) ?: []; $s = bonus_stav($z, $d); $dnes = date('Y-m-d');
  if ($druh === 'reg') {
    if (!$s['lze_reg']) return [false, $s['reg'] !== '' ? 'Dveře už jsou zaregistrované.' : 'Registrace už není možná.'];
    $nove = bonus_pridej_mesice($pdo, $z); $pdo->prepare('UPDATE zadosti SET bonus_reg_dne=? WHERE id=?')->execute([$dnes, $z['id']]);
    return [true, 'Děkujeme, dveře jsou zaregistrované. Záruka je prodloužena o ' . BONUS_MESICU . ' měsíců, nově do ' . date('j. n. Y', strtotime($nove)) . '.'];
  }
  if ($druh === 'recenze') {
    if (!$s['lze_rec']) return [false, $s['rec'] !== '' ? 'Recenzi už evidujeme.' : 'Lhůta 30 dnů od předání pro recenzi už uplynula.'];
    $pdo->prepare('UPDATE zadosti SET bonus_rec_dne=? WHERE id=?')->execute([$dnes, $z['id']]);
    $cislo = (string)($d['obj_cislo'] ?? $z['cislo']);
    $komu = (array)($cfg['prijemci']['poptavka']['to'] ?? [$cfg['from'] ?? '']);
    $telo = "Zákazník potvrdil, že napsal recenzi na Google – objednávka $cislo (" . ($d['model'] ?? '') . ").\n\nZkontrolujte recenzi a schvalte bonus +" . BONUS_MESICU . " měsíců záruky v kartotéce:\nhttps://" . ($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app') . "/servis-pozadavky.php (sekce Recenze ke schválení).";
    posli_mail($cfg, $DATA, array_filter($komu), (array)($cfg['prijemci']['poptavka']['cc'] ?? []), $cfg['from'] ?? '', "Recenze ke schválení – objednávka $cislo", $telo);
    return [true, 'Děkujeme! Jakmile recenzi ověříme, prodloužíme záruku o dalších ' . BONUS_MESICU . ' měsíců.'];
  }
  return [false, 'Neznámá akce.'];
}
// schválení prodavačem; pokud zákazník mezitím koupil prodloužení záruky, měsíce se nepřičítají (jen se schválení zaeviduje)
function bonus_schval_recenzi(PDO $pdo, int $id): string {
  bonus_tabulky($pdo); $z = bonus_zadost($pdo, $id); if (!$z || empty($z['bonus_rec_dne']) || !empty($z['bonus_rec_schv_dne'])) return 'Není co schvalovat.';
  $pdo->prepare('UPDATE zadosti SET bonus_rec_schv_dne=? WHERE id=?')->execute([date('Y-m-d'), $id]);
  if (!empty($z['zaruka_prodl_let']) || empty($z['zaruka_do'])) return 'Schváleno (zákazník už má prodlouženou záruku, měsíce se nepřičítají).';
  return 'Schváleno, záruka prodloužena do ' . date('j. n. Y', strtotime(bonus_pridej_mesice($pdo, $z))) . '.';
}
function bonus_zamitni_recenzi(PDO $pdo, int $id): void { bonus_tabulky($pdo); $pdo->prepare("UPDATE zadosti SET bonus_rec_dne=NULL WHERE id=? AND (bonus_rec_schv_dne IS NULL OR bonus_rec_schv_dne='')")->execute([$id]); }
// nabídka prodloužení pro zákazníka (aplikace / web): délky, ceny, údaje pro QR platbu
function bonus_nabidka_platba(array $cfg, array $z, array $d): array {
  if (!empty($z['zaruka_prodl_let']) || empty($z['predano_dne'])) return ['lhuta' => '', 'moznosti' => []];
  $lhuta = zaruka_lhuta($z); if (date('Y-m-d') > $lhuta) return ['lhuta' => $lhuta, 'moznosti' => []];
  $cislo = (string)($d['obj_cislo'] ?? $z['cislo']); $o = [];
  foreach (zaruka_nabidka($d) as $let => $kc) $o[] = ['let' => $let, 'kc' => $kc, 'spd' => zaruka_spd(zaruka_iban($cfg), $cislo, $kc), 'vs' => zaruka_vs($cislo), 'ucet' => zaruka_ucet($cfg)];
  return ['lhuta' => $lhuta, 'moznosti' => $o];
}

// ── Doporuč a získej přímo z aplikace (zákazník je poznán podle tajného kódu dveří, číslo faktury se nevyžaduje) ──
function dop_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS doporuceni (id INTEGER PRIMARY KEY AUTOINCREMENT, zadost_id INTEGER NOT NULL, vytvoreno TEXT, jmeno TEXT, telefon TEXT, poznamka TEXT, stav TEXT DEFAULT 'nove', upraveno TEXT)");
}
const DOP_STAVY = ['nove' => 'Nové', 'kontaktovan' => 'Kontaktován', 'objednano' => 'Objednáno', 'namontovano' => 'Namontováno – vyplatit odměnu', 'vyplaceno' => 'Odměna vyplacena', 'zruseno' => 'Zrušeno'];
function dop_seznam(PDO $pdo, int $zid): array {
  dop_tabulka($pdo); $st = $pdo->prepare('SELECT jmeno, vytvoreno, stav FROM doporuceni WHERE zadost_id=? ORDER BY id DESC LIMIT 20'); $st->execute([$zid]);
  return array_map(fn($r) => ['jmeno' => $r['jmeno'], 'datum' => substr((string)$r['vytvoreno'], 0, 10), 'stav' => $r['stav'], 'stav_text' => DOP_STAVY[$r['stav']] ?? $r['stav']], $st->fetchAll(PDO::FETCH_ASSOC));
}
// vrací [ok, zpráva]
function dop_vytvor(PDO $pdo, array $cfg, string $DATA, array $z, string $jmeno, string $telefon, bool $souhlas): array {
  dop_tabulka($pdo); $d = json_decode((string)$z['data_json'], true) ?: [];
  $jmeno = trim(preg_replace('/[\x00-\x1F<>]/u', '', mb_substr($jmeno, 0, 80))); $tel = preg_replace('/[^0-9+ ]/', '', mb_substr($telefon, 0, 20));
  if (mb_strlen($jmeno) < 3 || strlen(preg_replace('/\D/', '', $tel)) < 9) return [false, 'Vyplňte prosím jméno a telefon doporučené osoby.'];
  if (!$souhlas) return [false, 'Potvrďte prosím, že doporučená osoba souhlasí s předáním kontaktu.'];
  $st = $pdo->prepare('SELECT COUNT(*) FROM doporuceni WHERE zadost_id=?'); $st->execute([$z['id']]); if ((int)$st->fetchColumn() >= 10) return [false, 'Dosáhli jste maximálního počtu odeslaných doporučení. Zavolejte nám prosím.'];
  $ted = date('Y-m-d H:i:s'); $pdo->prepare('INSERT INTO doporuceni (zadost_id, vytvoreno, jmeno, telefon, stav, upraveno) VALUES (?,?,?,?,?,?)')->execute([$z['id'], $ted, $jmeno, $tel, 'nove', $ted]);
  $cislo = (string)($d['obj_cislo'] ?? $z['cislo']); $komu = (array)($cfg['prijemci']['poptavka']['to'] ?? [$cfg['from'] ?? '']);
  $telo = "Nové doporučení z aplikace Moje dveře (Doporuč a získej).\n\nDoporučuje: " . trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')) . ", tel. " . ($d['telefon'] ?? '') . ", objednávka $cislo (" . ($d['model'] ?? '') . ")\nDoporučený: $jmeno, tel. $tel\n\nZpracujte v kartotéce: Servisní požadavky → Doporučení.";
  posli_mail($cfg, $DATA, array_filter($komu), (array)($cfg['prijemci']['poptavka']['cc'] ?? []), $cfg['from'] ?? '', "Doporučení z aplikace – objednávka $cislo", $telo);
  return [true, 'Děkujeme! Doporučenému se ozveme a po namontování dveří vyplatíme odměnu podle pravidel akce.'];
}
