<?php
// Pravidelná údržba dveří (od 1. 11. 2026) – patří na NAS do /docker/formulare/app/lib/udrzba.php.
// • Každých 6 měsíců od předání pošleme zákazníkovi e-mail s připomínkou údržby (návod: bezpecne-dvere.cz/udrzba-dveri.html).
// • Zákazník v aplikaci Moje dveře potvrdí „Údržbu jsem provedl/a“ → záznam v servisní knížce (rodný list) i v kartotéce.
// • Připomínky si může v aplikaci vypnout a znovu zapnout; vypnutí i zapnutí se zapisuje do servisní knížky s datem.
// Netýká se interiérových dveří. Spouští plánovač: php /var/www/html/udrzba-pripominky.php (nejdřív --suchy).
const UDRZBA_MESICU = 6;
const UDRZBA_NAVOD_URL = 'https://bezpecne-dvere.cz/udrzba-dveri.html';
const UDRZBA_APP_URL = 'https://bezpecne-dvere.cz/aplikace/#moje';

function udrzba_tabulky(PDO $pdo): void {
  $sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  foreach (['udrzba_vypnuto_dne', 'udrzba_mail_pro'] as $c) if (!in_array($c, $sl, true)) $pdo->exec("ALTER TABLE zadosti ADD COLUMN $c TEXT");
  $pdo->exec('CREATE TABLE IF NOT EXISTS udrzba_zaznamy (id INTEGER PRIMARY KEY AUTOINCREMENT, zadost_id INTEGER NOT NULL, datum TEXT NOT NULL, typ TEXT NOT NULL, zdroj TEXT, cas INTEGER)');
}
function udrzba_plati(array $z, array $d): bool {   // jen předané bezpečnostní dveře (ne interiér)
  if (empty($z['predano_dne']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$z['predano_dne'])) return false;
  return !preg_match('/interi|vnit[rř]n/iu', (string)($d['model_kod'] ?? '') . ' ' . (string)($d['model'] ?? ''));
}
function udrzba_plus(string $ymd, int $mesicu): string { return (new DateTimeImmutable($ymd))->modify("+$mesicu months")->format('Y-m-d'); }
function udrzba_posledni(PDO $pdo, int $id): string {
  $st = $pdo->prepare("SELECT MAX(datum) FROM udrzba_zaznamy WHERE zadost_id=? AND typ='provedeno'"); $st->execute([$id]);
  return (string)($st->fetchColumn() ?: '');
}
// termín příští údržby: 6 měsíců od poslední potvrzené (jinak od předání); propadlé termíny se posouvají po 6 měsících až k dnešku
function udrzba_termin(PDO $pdo, array $z, string $dnes = ''): string {
  $dnes = $dnes ?: date('Y-m-d');
  $zaklad = max((string)$z['predano_dne'], udrzba_posledni($pdo, (int)$z['id']));
  $t = udrzba_plus($zaklad, UDRZBA_MESICU);
  while (udrzba_plus($t, UDRZBA_MESICU) <= $dnes) $t = udrzba_plus($t, UDRZBA_MESICU);
  return $t;
}
function udrzba_stav(PDO $pdo, array $z): array {
  udrzba_tabulky($pdo); $d = json_decode((string)$z['data_json'], true) ?: [];
  if (!udrzba_plati($z, $d)) return ['plati' => false];
  $em = filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL);
  return ['plati' => true, 'mesicu' => UDRZBA_MESICU, 'pristi' => udrzba_termin($pdo, $z), 'posledni' => udrzba_posledni($pdo, (int)$z['id']),
    'pripominky' => empty($z['udrzba_vypnuto_dne']), 'vypnuto_dne' => (string)($z['udrzba_vypnuto_dne'] ?? ''), 'email' => $em ? true : false, 'navod' => UDRZBA_NAVOD_URL];
}
function udrzba_zapis(PDO $pdo, int $id, string $typ, string $zdroj): void {
  $pdo->prepare('INSERT INTO udrzba_zaznamy (zadost_id, datum, typ, zdroj, cas) VALUES (?,?,?,?,?)')->execute([$id, date('Y-m-d'), $typ, $zdroj, time()]);
}
// akce z aplikace: 'hotovo' | 'vypnout' | 'zapnout'. Vrací [ok, zpráva].
function udrzba_akce(PDO $pdo, array $z, string $co, string $zdroj = 'aplikace'): array {
  udrzba_tabulky($pdo); $d = json_decode((string)$z['data_json'], true) ?: []; $id = (int)$z['id'];
  if (!udrzba_plati($z, $d)) return [false, 'Údržba se počítá od předání dveří.'];
  if ($co === 'hotovo') {
    $st = $pdo->prepare("SELECT COUNT(*) FROM udrzba_zaznamy WHERE zadost_id=? AND typ='provedeno' AND datum=?"); $st->execute([$id, date('Y-m-d')]);
    if (!(int)$st->fetchColumn()) udrzba_zapis($pdo, $id, 'provedeno', $zdroj);
    return [true, 'Díky! Údržbu jsme zapsali do servisní knížky. Další připomeneme ' . date('j. n. Y', strtotime(udrzba_termin($pdo, $z))) . '.'];
  }
  if ($co === 'vypnout') {
    if (empty($z['udrzba_vypnuto_dne'])) { $pdo->prepare('UPDATE zadosti SET udrzba_vypnuto_dne=? WHERE id=?')->execute([date('Y-m-d'), $id]); udrzba_zapis($pdo, $id, 'vypnuto', $zdroj); }
    return [true, 'Připomínky údržby jsou vypnuté. Údržbu prosím provádějte i tak – každých ' . UDRZBA_MESICU . ' měsíců podle návodu.'];
  }
  if ($co === 'zapnout') {
    if (!empty($z['udrzba_vypnuto_dne'])) { $pdo->prepare('UPDATE zadosti SET udrzba_vypnuto_dne=NULL WHERE id=?')->execute([$id]); udrzba_zapis($pdo, $id, 'zapnuto', $zdroj); }
    return [true, 'Připomínky údržby jsou znovu zapnuté.'];
  }
  return [false, 'Neznámá akce.'];
}
// záznamy do servisní knížky (rodný list)
function udrzba_rodny_list(PDO $pdo, int $id): array {
  udrzba_tabulky($pdo); $e = [];
  $txt = ['provedeno' => 'Pravidelná údržba provedena (potvrdil zákazník)', 'vypnuto' => 'Zákazník vypnul připomínky údržby', 'zapnuto' => 'Zákazník znovu zapnul připomínky údržby', 'pripominka' => 'Odeslána připomínka údržby e-mailem'];
  $st = $pdo->prepare('SELECT datum, typ FROM udrzba_zaznamy WHERE zadost_id=? ORDER BY datum, id'); $st->execute([$id]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $e[] = ['datum' => (string)$r['datum'], 'typ' => 'udrzba', 'text' => $txt[$r['typ']] ?? $r['typ']];
  return $e;
}
function udrzba_mail_text(array $z, array $d, string $termin): string {
  $cislo = (string)($d['obj_cislo'] ?? $z['cislo']); $model = trim((string)($d['model'] ?? 'vaše dveře'));
  return "Dobrý den,\n\nje čas na pravidelnou údržbu dveří $model (objednávka č. $cislo). Děláme ji každých " . UDRZBA_MESICU . " měsíců, zabere pár minut:\n\n"
    . "• těsnění dveří a zárubně ošetřete silikonovým mazivem,\n"
    . "• promažte zamykací čepy a krycí misku vložky olejem nebo WD-40 (do samotné vložky jen teflonový nebo grafitový sprej),\n"
    . "• povrch umyjte teplou vodou s trochou saponátu a měkkou houbou – bez drhnutí, rozpouštědel a abrazivních prostředků,\n"
    . "• vyzkoušejte, že dveře samy dovřou a zámek jde lehce.\n\nSeřízení a promazání dveří děláme zdarma do 6 měsíců od předání, potom jako placenou servisní službu; oprava vady dveří je v záruce vždy zdarma.\n\n"
    . "Celý návod: " . UDRZBA_NAVOD_URL . "\n\n"
    . "Až budete hotovi, potvrďte to prosím v aplikaci Moje dveře tlačítkem „Údržbu jsem provedl/a“ – zapíše se do servisní knížky dveří:\n" . UDRZBA_APP_URL . "\n\n"
    . "Pravidelná údržba podle návodu je důležitá i pro záruku – na vady způsobené zanedbanou údržbou se záruka nevztahuje. Připomínky si můžete v aplikaci vypnout i znovu zapnout.\n\n"
    . "Kdyby něco nešlo, napište nebo zavolejte: +420 725 559 235.\n\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
}
