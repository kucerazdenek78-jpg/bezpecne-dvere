<?php
// Poptávky z e-mailu přes AI (5. 11. 2026, přání Zdeňka):
// Prodejce přepošle e-mail s poptávkou na poptavky-ai@bezpecne-dvere.cz (alias / přeposlání do schránky kartoteka-dvere@, kterou čte i platby.php).
// CLI poptavky-z-mailu.php zprávu přečte (IMAP, lib/banka.php MiniImap, jen BODY.PEEK – nic nemaže ani neoznačuje), Claude (lib/pomocnik.php, stejný klíč a limit)
// z ní vyplní náš formulář poptávky a v kartotéce vznikne poptávka P …/RRRR (stav Nové, zdroj „email-ai“) s přílohami a původním e-mailem.
// Pojistky: jen od povolených odesílatelů (config.php 'poptavky_ai' => ['povoleni' => [...]], jinak výchozí seznam níže), kontakty jen když jsou doslova v e-mailu,
// stejná zpráva (Message-ID) se nezpracuje dvakrát, stejný zákazník se stejným předmětem do 14 dní = upozornění místo nové poptávky.
// Zákazník dostane potvrzení přijetí (jako u poptávky z webu), prodejce odpověď se shrnutím a odkazem. Hlídání 48 h běží od data původního e-mailu (lib/terminy.php).
// Když AI není k dispozici (bez klíče, limit, výpadek), poptávka se založí i tak – s původním textem k ručnímu doplnění.

const PM_ADRESA = 'poptavky-ai@bezpecne-dvere.cz';
const PM_POVOLENI = ['kucerazd@kksystem.cz', 'chci@bezpecne-dvere.cz'];
const PM_NASE_DOMENY = ['bezpecne-dvere.cz', 'kksystem.cz', 'dvere.app'];
const PM_NASE_TELEFONY = ['725559235', '471008994'];
const PM_KATEGORIE = ['byt' => 'Dveře do bytu', 'dum' => 'Dveře do domu', 'interier' => 'Interiérové dveře', 'jine' => 'Jiné'];

function pm_nastaveni(array $cfg): array {
  $c = (array)($cfg['poptavky_ai'] ?? []);
  $pov = array_values(array_filter(array_map(fn($a) => mb_strtolower(trim((string)$a)), (array)($c['povoleni'] ?? PM_POVOLENI))));
  return ['adresa' => mb_strtolower(trim((string)($c['adresa'] ?? PM_ADRESA))), 'povoleni' => $pov ?: PM_POVOLENI, 'zapnuto' => ($c['zapnuto'] ?? true) !== false,
    'potvrzeni' => ($c['potvrzeni_zakaznikovi'] ?? true) !== false, 'den_max' => max(1, (int)($c['den_max'] ?? 40))];
}
function pm_tabulka(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS poptavky_mail (id INTEGER PRIMARY KEY AUTOINCREMENT, msgid TEXT UNIQUE, zpracovano TEXT, od TEXT, predmet TEXT, stav TEXT, zadost_id INTEGER, poznamka TEXT)');
}

// ── MIME ──
function pm_dek_hl(string $s): string {
  if (!preg_match('/=\?[^?]+\?[bqBQ]\?/', $s)) return trim($s);
  $r = function_exists('iconv_mime_decode') ? @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;
  if ($r === false || $r === '') $r = @mb_decode_mimeheader($s);
  return trim((string)$r);
}
// [hlavičky (malá písmena => [hodnoty]), tělo]
function pm_rozdel(string $raw): array {
  $p = preg_split("/\r?\n\r?\n/", $raw, 2); $hl = preg_replace("/\r?\n[ \t]+/", ' ', (string)$p[0]); $h = [];
  foreach (preg_split("/\r?\n/", $hl) as $l) if (preg_match('/^([\w-]+):\s?(.*)$/', $l, $m)) $h[strtolower($m[1])][] = $m[2];
  return [$h, (string)($p[1] ?? '')];
}
function pm_h(array $h, string $k): string { return (string)($h[strtolower($k)][0] ?? ''); }
function pm_param(string $hodnota, string $jm): string {
  if (preg_match('/' . $jm . '\*=(?:([\w-]+)\'[^\']*\')?([^;]+)/i', $hodnota, $m)) { $v = rawurldecode(trim($m[2], " \"")); return ($m[1] ?? '') !== '' && strtolower($m[1]) !== 'utf-8' ? (string)@mb_convert_encoding($v, 'UTF-8', $m[1]) : $v; }
  if (preg_match('/' . $jm . '\s*=\s*"([^"]*)"/i', $hodnota, $m) || preg_match('/' . $jm . '\s*=\s*([^;\s]+)/i', $hodnota, $m)) return pm_dek_hl($m[1]);
  return '';
}
function pm_html_text(string $s): string {
  $s = preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is', ' ', $s);
  $s = preg_replace('~<br\s*/?>|</(p|div|tr|li|h\d|blockquote|table)>~i', "\n", $s);
  $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $s = preg_replace('/[ \t\x{00A0}]+/u', ' ', $s);
  return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $s));
}
// rozloží zprávu na části: ['druh'=>'text'|'soubor', 'text'=>…, 'nazev'=>…, 'mime'=>…, 'data'=>…]
function pm_casti(string $raw, int $hl = 0): array {
  if ($hl > 6) return [];
  [$h, $b] = pm_rozdel($raw);
  $ct = pm_h($h, 'content-type') ?: 'text/plain'; $mime = strtolower(trim(explode(';', $ct)[0]));
  $te = strtolower(trim(pm_h($h, 'content-transfer-encoding')));
  $disp = pm_h($h, 'content-disposition');
  if (str_starts_with($mime, 'multipart/')) {
    $hr = pm_param($ct, 'boundary'); if ($hr === '') return [['druh' => 'text', 'text' => $b]];
    $kusy = preg_split('/\r?\n--' . preg_quote($hr, '/') . '(?:--)?[ \t]*(?=\r?\n|$)/', "\n" . $b);
    array_shift($kusy); $deti = [];
    foreach ($kusy as $k) { $k = preg_replace('/^\r?\n/', '', $k); if (trim($k) === '') continue; $deti[] = pm_casti($k, $hl + 1); }
    if ($mime === 'multipart/alternative') {   // vybrat jednu variantu: prostý text, jinak HTML
      $vyber = null; foreach ($deti as $d) foreach ($d as $c) if ($c['druh'] === 'text' && ($c['mime'] ?? '') === 'text/plain' && trim($c['text']) !== '') { $vyber = $d; break 2; }
      return $vyber ?? ($deti[count($deti) - 1] ?? []);
    }
    return array_merge(...($deti ?: [[]]));
  }
  $data = $te === 'base64' ? (string)base64_decode(preg_replace('/\s+/', '', $b)) : ($te === 'quoted-printable' ? quoted_printable_decode($b) : $b);
  if ($mime === 'message/rfc822') {   // přeposláno jako příloha
    [$h2] = pm_rozdel($data);
    $hlav = "---------- Přeposlaná zpráva (příloha) ----------\nOd: " . pm_dek_hl(pm_h($h2, 'from')) . "\nDatum: " . pm_h($h2, 'date') . "\nPředmět: " . pm_dek_hl(pm_h($h2, 'subject')) . "\n";
    return array_merge([['druh' => 'text', 'mime' => 'text/plain', 'text' => $hlav, 'puvodni_datum' => pm_h($h2, 'date'), 'puvodni_id' => pm_h($h2, 'message-id')]], pm_casti($data, $hl + 1),
      [['druh' => 'soubor', 'nazev' => 'preposlana-zprava.eml', 'mime' => 'message/rfc822', 'data' => $data]]);
  }
  $nazev = pm_param($disp, 'filename') ?: pm_param($ct, 'name');
  if (str_starts_with($mime, 'text/') && $nazev === '' && stripos($disp, 'attachment') === false) {
    $cs = pm_param($ct, 'charset') ?: 'UTF-8';
    if (strtolower($cs) !== 'utf-8' && strtolower($cs) !== 'us-ascii') $data = (string)@mb_convert_encoding($data, 'UTF-8', $cs);
    elseif (!mb_check_encoding($data, 'UTF-8')) $data = (string)@mb_convert_encoding($data, 'UTF-8', 'Windows-1250');
    return [['druh' => 'text', 'mime' => $mime, 'text' => $mime === 'text/html' ? pm_html_text($data) : str_replace("\r\n", "\n", $data)]];
  }
  return [['druh' => 'soubor', 'nazev' => $nazev !== '' ? $nazev : 'priloha', 'mime' => $mime, 'data' => $data]];
}
function pm_adresa(string $hodnota): string { $hodnota = pm_dek_hl($hodnota); return preg_match('/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/u', $hodnota, $m) ? mb_strtolower($m[0]) : ''; }
function pm_nase(string $em): bool { $em = mb_strtolower($em); foreach (PM_NASE_DOMENY as $d) if (str_ends_with($em, '@' . $d) || str_ends_with($em, '.' . $d)) return true; return false; }
function pm_robot(string $em): bool { return (bool)preg_match('/^(no-?reply|noreply|mailer-daemon|postmaster|notifications?|info@firmy|robot)/i', $em); }

// ── Claude ──
function pm_nastroj(): array {
  $s = ['type' => 'string'];
  return ['name' => 'poptavka_z_emailu', 'description' => 'Vyplní formulář poptávky z přeposlaného e-mailu zákazníka.', 'input_schema' => ['type' => 'object', 'properties' => [
    'je_poptavka' => ['type' => 'boolean', 'description' => 'true = zákazník poptává dveře, montáž nebo servis; false = jiný e-mail (faktura, reklama, spam…)'],
    'kontakt' => ['type' => 'object', 'description' => 'Kontakt ZÁKAZNÍKA přesně tak, jak je napsaný v e-mailu (ne údaje firmy Bezpečné dveře / KK SYSTEM ani prodejce, který e-mail přeposlal). Co v textu není, vynech.',
      'properties' => ['jmeno' => $s, 'prijmeni' => $s, 'telefon' => $s, 'email' => $s, 'ulice' => ['type' => 'string', 'description' => 'ulice a číslo místa montáže'], 'mesto' => $s, 'psc' => $s]],
    'kategorie' => ['type' => 'string', 'enum' => array_keys(PM_KATEGORIE), 'description' => 'byt = bezpečnostní dveře do bytu, dum = vchodové dveře do domu, interier = interiérové dveře, jine = něco jiného (servis, kování…)'],
    'polozky' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['co' => ['type' => 'string', 'description' => 'model nebo druh dveří, jak ho zákazník popsal (když model zná, napiš ho přesně jako v katalogu)'],
      'odstin' => $s, 'ks' => ['type' => 'integer'], 'rozmer' => ['type' => 'string', 'description' => 'rozměr otvoru / šířka křídla, jen když je v e-mailu']], 'required' => ['co']]],
    'termin' => ['type' => 'string', 'description' => 'kdy zákazník dveře potřebuje, jen když to napsal'],
    'pozadavek' => ['type' => 'string', 'description' => 'co zákazník chce, 1–4 věty vlastními slovy (bez kontaktů)'],
    'poznamka_prodejce' => ['type' => 'string', 'description' => 'poznámka, kterou nad přeposlanou zprávu připsal prodejce, doslova (prázdné, když žádná není)'],
    'datum_puvodni' => ['type' => 'string', 'description' => 'datum a čas původního e-mailu od zákazníka ve tvaru RRRR-MM-DD HH:MM, jen když je v přeposlané hlavičce vidět'],
    'kanal' => ['type' => 'string', 'description' => 'odkud poptávka přišla, když to jde poznat (e-mail, Firmy.cz, Facebook, jiný web…)'],
    'overit' => ['type' => 'array', 'items' => $s, 'description' => 'co chybí nebo je nejisté a prodejce musí ověřit / se zeptat zákazníka (krátce, česky)'],
  ], 'required' => ['je_poptavka', 'kategorie', 'polozky', 'pozadavek', 'overit']]];
}
function pm_system(): string {
  $k = function_exists('nav_katalog') ? nav_katalog() : [];
  $byt = implode(', ', array_map(fn($m) => $m['nazev'] . ' (' . implode(', ', $m['odstiny']) . ')', (array)($k['byt'] ?? [])));
  $dum = implode(', ', (array)($k['dum'] ?? []));
  $int = implode(', ', array_map(fn($m) => $m['model'], (array)($k['interier'] ?? [])));
  return "Jsi asistent firmy Bezpečné dveře (KK SYSTEM, Obchodní 36, Most). Prodejce ti přeposlal e-mail od zákazníka. Vyplň z něj formulář poptávky nástrojem poptavka_z_emailu.\n"
    . "PRAVIDLA:\n- Nic si nevymýšlej. Kontakt, rozměry, termín a modely piš jen tak, jak jsou v e-mailu. Co tam není, vynech a uveď v poli overit.\n"
    . "- Kontakt firmy (Bezpečné dveře, KK SYSTEM, chci@bezpecne-dvere.cz, kucerazd@kksystem.cz, +420 725 559 235, +420 471 008 994, Obchodní 36 Most) ani podpis prodejce NENÍ kontakt zákazníka.\n"
    . "- Zákazník je ten, kdo napsal původní zprávu (v přeposlané části „Od:“ / „From:“), případně kdo je podepsaný pod ní.\n"
    . "- Modely, které prodáváme: dveře do bytu: $byt. Dveře do domu ERKADO: $dum. Interiérové dveře: $int. Když zákazník model nejmenuje, napiš do „co“ obecně (např. „bezpečnostní dveře do bytu“) a do overit „vybrat model“.\n"
    . "- Piš česky, stručně.";
}
// zavolá Claude; vrací ['ok'=>true,'v'=>[…]] nebo ['ok'=>false,'chyba'=>…]
function pm_ai(PDO $pdo, string $DATA, string $text, array $fotky): array {
  if (!function_exists('pom_api')) return ['ok' => false, 'chyba' => 'chybí lib/pomocnik.php'];
  pom_tabulky($pdo); $n = pom_nastaveni($DATA);
  if (empty($n['zapnuto'])) return ['ok' => false, 'chyba' => 'pomocník je vypnutý v nastavení'];
  if (pom_dnes_pocet($pdo) >= max(1, (int)$n['limit_den'])) return ['ok' => false, 'chyba' => 'vyčerpaný denní limit dotazů pomocníka'];
  $model = (string)$n['model'];
  $obsah = array_merge($fotky, [['type' => 'text', 'text' => "PŘEPOSLANÝ E-MAIL:\n\n" . $text . "\n\nVyplň poptávku nástrojem poptavka_z_emailu."]]);
  $r = pom_api($n, ['model' => $model, 'max_tokens' => 2000, 'system' => pm_system() . (trim((string)$n['pokyny']) !== '' ? "\n\nDOPLŇUJÍCÍ POKYNY OD ZDEŇKA (mají přednost):\n" . mb_substr(trim((string)$n['pokyny']), 0, 4000) : ''),
    'tools' => [pm_nastroj()], 'tool_choice' => ['type' => 'tool', 'name' => 'poptavka_z_emailu'], 'messages' => [['role' => 'user', 'content' => $obsah]]], 120);
  if (!$r['ok']) { pom_zapis($pdo, 'poptavka-mail', $model, 0, 0, $r['chyba'] . ' ' . ($r['detail'] ?? '')); return $r; }
  $in = (int)($r['data']['usage']['input_tokens'] ?? 0); $out = (int)($r['data']['usage']['output_tokens'] ?? 0);
  $v = null; foreach ((array)($r['data']['content'] ?? []) as $b) if (($b['type'] ?? '') === 'tool_use') $v = (array)$b['input'];
  pom_zapis($pdo, 'poptavka-mail', $model, $in, $out, $v ? '' : 'bez výsledku');
  return $v ? ['ok' => true, 'v' => $v] : ['ok' => false, 'chyba' => 'AI nevrátila vyplněný formulář'];
}
// kontakt jen když je doslova v e-mailu a není to náš (firemní) údaj
function pm_kontakt(array $k, string $zdroj, string $preposlal): array {
  $norm = fn($x) => mb_strtolower(preg_replace('/[\s.,;:\/-]+/u', '', (string)$x));
  $nz = $norm($zdroj); $cz = preg_replace('/\D+/', '', $zdroj); $o = [];
  foreach (['jmeno', 'prijmeni', 'ulice', 'mesto'] as $f) { $v = trim((string)($k[$f] ?? '')); if ($v !== '' && mb_strlen($v) <= 80 && str_contains($nz, $norm($v))) $o[$f] = $v; }
  if (isset($o['ulice']) && preg_match('/obchodn[ií]\s*36/iu', $o['ulice'])) unset($o['ulice'], $o['mesto']);
  $tel = preg_replace('/\D+/', '', (string)($k['telefon'] ?? '')); $t9 = substr($tel, -9);
  if (strlen($t9) === 9 && str_contains($cz, $t9) && !in_array($t9, PM_NASE_TELEFONY, true)) $o['telefon'] = trim((string)$k['telefon']);
  $em = mb_strtolower(trim((string)($k['email'] ?? '')));
  if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && str_contains(mb_strtolower($zdroj), $em) && !pm_nase($em) && $em !== mb_strtolower($preposlal)) $o['email'] = $em;
  $psc = preg_replace('/\D+/', '', (string)($k['psc'] ?? '')); if (strlen($psc) === 5 && str_contains($cz, $psc)) $o['psc'] = substr($psc, 0, 3) . ' ' . substr($psc, 3);
  return $o;
}
// když AI nejede: aspoň e-mail a telefon zákazníka z textu (první cizí adresa / číslo)
function pm_kontakt_nouze(string $text, string $preposlal): array {
  $o = [];
  if (preg_match_all('/[\w.+\-]+@[\w\-]+(\.[\w\-]+)+/u', $text, $m)) foreach ($m[0] as $em) { $em = mb_strtolower($em); if (!pm_nase($em) && $em !== $preposlal && !pm_robot($em)) { $o['email'] = $em; break; } }
  if (preg_match_all('/(?:\+?420[ ]?)?\d{3}[ ]?\d{3}[ ]?\d{3}\b/', $text, $m)) foreach ($m[0] as $t) { $t9 = substr(preg_replace('/\D+/', '', $t), -9); if (!in_array($t9, PM_NASE_TELEFONY, true)) { $o['telefon'] = trim($t); break; } }
  return $o;
}

// ── zpracování jedné zprávy; vrací ['stav'=>'zalozeno'|'duplicita'|'ignorovano'|'chyba', 'zadost_id'=>…, 'poznamka'=>…] ──
function pm_zpracuj(PDO $pdo, array $cfg, string $DATA, string $raw, bool $suchy = false, ?callable $log = null): array {
  $log = $log ?? fn($s) => null; $nas = pm_nastaveni($cfg);
  [$h] = pm_rozdel($raw);
  $od = pm_adresa(pm_h($h, 'from')); $predmet = pm_dek_hl(pm_h($h, 'subject'));
  $auth = mb_strtolower(implode(' ', (array)($h['authentication-results'] ?? [])));
  if (!in_array($od, $nas['povoleni'], true)) return ['stav' => 'ignorovano', 'poznamka' => "odesílatel $od není mezi povolenými"];
  if ($auth !== '' && (str_contains($auth, 'dmarc=fail') || str_contains($auth, 'spf=fail'))) return ['stav' => 'ignorovano', 'poznamka' => "podvržený odesílatel? ($od, kontrola SPF/DMARC selhala)"];
  $casti = pm_casti($raw);
  $text = ''; $soubory = []; $puvDatum = '';
  foreach ($casti as $c) {
    if ($c['druh'] === 'text') { $text .= rtrim((string)$c['text']) . "\n\n"; if (!empty($c['puvodni_datum']) && $puvDatum === '') $puvDatum = (string)$c['puvodni_datum']; }
    elseif (strlen((string)$c['data']) > 0 && strlen((string)$c['data']) <= 12 * 1048576 && count($soubory) < 12) $soubory[] = $c;
  }
  $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));
  $textAI = mb_substr($text, 0, 20000);
  // fotky pro AI (jen JPG/PNG do 3,5 MB, max 3)
  $fotky = [];
  foreach ($soubory as $s) { if (in_array($s['mime'], ['image/jpeg', 'image/jpg', 'image/png'], true) && strlen($s['data']) <= 3.5 * 1048576 && count($fotky) < 3) $fotky[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $s['mime'] === 'image/png' ? 'image/png' : 'image/jpeg', 'data' => base64_encode($s['data'])]]; }
  $dnes = $pdo->prepare("SELECT COUNT(*) FROM poptavky_mail WHERE stav='zalozeno' AND zpracovano>=?"); $dnes->execute([date('Y-m-d')]);
  if ((int)$dnes->fetchColumn() >= $nas['den_max']) return ['stav' => 'chyba', 'poznamka' => 'denní strop poptávek z e-mailu (' . $nas['den_max'] . ') – zkusí se znovu zítra', 'znovu' => true];

  $ai = $suchy && !getenv('POMOCNIK_API_URL') ? ['ok' => false, 'chyba' => 'suchý běh – bez volání AI'] : pm_ai($pdo, $DATA, $textAI, $fotky);
  $v = $ai['ok'] ? $ai['v'] : [];
  $overit = array_values(array_filter(array_map(fn($x) => trim((string)$x), (array)($v['overit'] ?? []))));
  $kontakt = $ai['ok'] ? pm_kontakt((array)($v['kontakt'] ?? []), $text, $od) : pm_kontakt_nouze($text, $od);
  if (!$ai['ok']) array_unshift($overit, 'AI poptávku nevyplnila (' . $ai['chyba'] . ') – doplňte údaje ručně z původního e-mailu níže.');
  foreach ((array)($v['kontakt'] ?? []) as $f => $x) if (trim((string)$x) !== '' && !isset($kontakt[$f]) && in_array($f, ['telefon', 'email', 'prijmeni', 'ulice', 'mesto'], true)) $overit[] = 'Kontakt (' . ['telefon' => 'telefon', 'email' => 'e-mail', 'prijmeni' => 'příjmení', 'ulice' => 'ulice', 'mesto' => 'obec'][$f] . ') nebyl v e-mailu doslova – nepřevzat, ověřte.';
  if (empty($kontakt['telefon']) && empty($kontakt['email'])) $overit[] = 'V e-mailu není telefon ani e-mail zákazníka.';
  if ($ai['ok'] && empty($v['je_poptavka'])) array_unshift($overit, 'AI si myslí, že to není poptávka od zákazníka – zkontrolujte, případně záznam dejte na Storno.');
  $kat = isset(PM_KATEGORIE[$v['kategorie'] ?? '']) ? (string)$v['kategorie'] : 'jine';
  // položky; model_kod u dveří do bytu podle katalogu
  $kod = ''; $pol = [];
  $kat_byt = function_exists('nav_katalog') ? (array)(nav_katalog()['byt'] ?? []) : [];
  foreach ((array)($v['polozky'] ?? []) as $p) {
    $co = trim((string)($p['co'] ?? '')); if ($co === '') continue; $ks = max(1, min(99, (int)($p['ks'] ?? 1))); $od2 = trim((string)($p['odstin'] ?? '')); $roz = trim((string)($p['rozmer'] ?? ''));
    foreach ($kat_byt as $m) if (mb_strtolower($co) === mb_strtolower($m['nazev']) && $kod === '') $kod = (string)$m['model_kod'];
    $pol[] = ['co' => mb_substr($co, 0, 120), 'odstin' => mb_substr($od2, 0, 80), 'ks' => $ks, 'volba' => trim($od2 . ($kat === 'interier' ? ($od2 !== '' ? ', ' : '') . $ks . ' ks' : '')), 'priplatek' => 0, 'info' => mb_substr($roz, 0, 120)];
  }
  // datum původního e-mailu (pro hlídání 48 h): z přílohy, z odpovědi AI, jinak teď
  $prijato = date('Y-m-d H:i:s'); $tp = 0;
  if ($puvDatum !== '') $tp = (int)strtotime($puvDatum);
  if (!$tp && preg_match('/^\d{4}-\d{2}-\d{2}( \d{1,2}:\d{2})?$/', trim((string)($v['datum_puvodni'] ?? '')))) $tp = (int)strtotime(trim((string)$v['datum_puvodni']));
  if ($tp > time() - 60 * 86400 && $tp <= time() + 3600) $prijato = date('Y-m-d H:i:s', $tp);
  // duplicita: stejný zákazník (e-mail nebo telefon) a stejný předmět do 14 dní
  $predCist = trim(preg_replace('/^((fwd?|fw|re|vs|odp|přeposlat)\s*:\s*)+/iu', '', $predmet));
  foreach ($pdo->query("SELECT id, cislo, predmet, data_json FROM zadosti WHERE typ='poptavka' AND vytvoreno>='" . date('Y-m-d', time() - 14 * 86400) . "' AND data_json LIKE '%email-ai%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $d2 = json_decode((string)$r['data_json'], true) ?: [];
    $stejny = (!empty($kontakt['email']) && mb_strtolower((string)($d2['email'] ?? '')) === $kontakt['email']) || (!empty($kontakt['telefon']) && substr(preg_replace('/\D+/', '', (string)($d2['telefon'] ?? '')), -9) === substr(preg_replace('/\D+/', '', $kontakt['telefon']), -9));
    if ($stejny && mb_strtolower((string)($d2['email_predmet'] ?? '')) === mb_strtolower($predCist)) return ['stav' => 'duplicita', 'zadost_id' => (int)$r['id'], 'poznamka' => 'stejná poptávka už je v kartotéce: ' . $r['cislo'], 'cislo' => (string)$r['cislo'], 'od' => $od];
  }
  // číslo poptávky
  require_once __DIR__ . '/cisla.php';
  $rok = (int)date('Y'); $cs = cisla_spocitej($pdo, $cfg, 'poptavka', $rok); $cislo = $cs['pre'] . $cs['dalsi'] . '/' . $rok;
  $model = implode(', ', array_map(fn($p) => $p['co'], $pol)); $odstin = implode(', ', array_values(array_unique(array_filter(array_map(fn($p) => $p['odstin'], $pol)))));
  $ks = array_sum(array_map(fn($p) => $p['ks'], $pol));
  $pozn = trim(implode("\n", array_filter([trim((string)($v['pozadavek'] ?? '')), ($v['termin'] ?? '') !== '' ? 'Termín: ' . $v['termin'] : '',
    implode('; ', array_filter(array_map(fn($p) => $p['info'] !== '' ? $p['co'] . ': ' . $p['info'] : '', $pol))) !== '' ? 'Rozměry: ' . implode('; ', array_filter(array_map(fn($p) => $p['info'] !== '' ? $p['co'] . ': ' . $p['info'] : '', $pol))) : '',
    trim((string)($v['poznamka_prodejce'] ?? '')) !== '' ? 'Poznámka prodejce: ' . trim((string)$v['poznamka_prodejce']) : ''])));
  $d = ['verze' => 1, 'cislo' => $cislo, 'odeslano' => date('c'), 'zdroj' => 'email-ai', 'kategorie' => $kat, 'kategorie_nazev' => PM_KATEGORIE[$kat],
    'model' => $model, 'model_kod' => $kod, 'odstin' => $odstin, 'pocet_ks' => $ks ?: '', 'polozky' => $pol,
    'jmeno' => $kontakt['jmeno'] ?? '', 'prijmeni' => $kontakt['prijmeni'] ?? '', 'telefon' => $kontakt['telefon'] ?? '', 'email' => $kontakt['email'] ?? '',
    'ulice' => $kontakt['ulice'] ?? '', 'mesto' => $kontakt['mesto'] ?? '', 'psc' => $kontakt['psc'] ?? '', 'poznamka' => $pozn, 'bourani' => false, 'kovani' => [],
    'prijato' => $prijato, 'kanal' => mb_substr(trim((string)($v['kanal'] ?? '')), 0, 60), 'preposlal' => $od, 'email_predmet' => mb_substr($predCist, 0, 200), 'ai_overit' => $overit, 'ai' => $ai['ok']];
  $jm = trim($d['jmeno'] . ' ' . $d['prijmeni']);
  $pred = 'Poptávka z e-mailu ' . $cislo . ' – ' . ($model !== '' ? $model : PM_KATEGORIE[$kat]) . ' – ' . ($d['prijmeni'] !== '' ? $d['prijmeni'] : ($jm !== '' ? $jm : ($d['email'] ?: 'neznámý zákazník'))) . ($d['mesto'] !== '' ? ', ' . $d['mesto'] : '');
  $radky = [['Číslo poptávky', $cislo], ['Zdroj', 'přeposlaný e-mail (zpracovala AI)' . ($d['kanal'] !== '' ? ' – ' . $d['kanal'] : '')], ['Přeposlal', $od], ['Původní e-mail přijat', date('j. n. Y H:i', strtotime($prijato))],
    ['Kategorie', PM_KATEGORIE[$kat]], ['Dveře', implode(', ', array_map(fn($p) => ($p['ks'] > 1 ? $p['ks'] . '× ' : '') . $p['co'] . ($p['odstin'] !== '' ? ' ' . $p['odstin'] : '') . ($p['info'] !== '' ? ' (' . $p['info'] . ')' : ''), $pol))],
    ['Jméno', $jm], ['Telefon', $d['telefon']], ['E-mail', $d['email']], ['Adresa montáže', trim(implode(', ', array_filter([$d['ulice'], trim($d['psc'] . ' ' . $d['mesto'])])))], ['Poznámka', $pozn],
    ['Ověřte', $overit ? '• ' . implode("\n• ", $overit) : '']];
  $telo = implode("\n", array_map(fn($x) => $x[0] . ': ' . $x[1], array_filter($radky, fn($x) => trim((string)$x[1]) !== ''))) . "\n\n──────── Původní e-mail ────────\nPředmět: $predmet\n\n" . mb_substr($text, 0, 40000);
  if ($suchy) return ['stav' => 'zalozeno', 'zadost_id' => 0, 'poznamka' => "[suchý] by vznikla $cislo", 'cislo' => $cislo, 'd' => $d, 'od' => $od, 'telo' => $telo];
  $pdo->prepare('INSERT INTO zadosti (vytvoreno,typ,cislo,predmet,telo,email,data_json,prilohy,ip) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([date('Y-m-d H:i:s'), 'poptavka', $cislo, $pred, $telo, $d['email'], json_encode($d, JSON_UNESCAPED_UNICODE), '', 'email-ai']);
  $id = (int)$pdo->lastInsertId();
  // přílohy (fotky, PDF…) + původní e-mail
  $ulozeno = []; @mkdir($DATA . '/ulozeno', 0775, true);
  $soubory[] = ['nazev' => 'puvodni-email.eml', 'data' => $raw];
  foreach ($soubory as $s) {
    $nz = preg_replace('/[^A-Za-z0-9._-]/', '_', basename(str_replace(['\\', '/'], '-', (string)$s['nazev']))); if ($nz === '' || $nz === '.' || $nz === '..') $nz = 'priloha';
    if (preg_match('/\.(php\d?|phtml|phar|htaccess|cgi|sh|exe|js)$/i', $nz)) $nz .= '.txt';
    $jmS = $id . '_' . bin2hex(random_bytes(4)) . '_' . mb_substr($nz, -80);
    if (@file_put_contents($DATA . '/ulozeno/' . $jmS, $s['data']) !== false) $ulozeno[] = $jmS;
  }
  $pdo->prepare('UPDATE zadosti SET prilohy=? WHERE id=?')->execute([implode("\n", $ulozeno), $id]);
  if (function_exists('terminy_hist')) terminy_hist($pdo, $id, 'poptávka založena z přeposlaného e-mailu' . ($ai['ok'] ? ' (vyplnila AI)' : ' (bez AI)'), $od);
  // poptávka je už po termínu 48 h (starý e-mail) → omluvný e-mail zákazníkovi z hlídání termínů neposílat, potvrzení to vyřeší
  $poTerminu = strtotime($prijato) + 48 * 3600 < time();
  if ($poTerminu) $pdo->prepare('UPDATE zadosti SET term_omluva_dne=? WHERE id=?')->execute([date('Y-m-d H:i:s') . ' (nahrazeno potvrzením)', $id]);
  // potvrzení zákazníkovi
  $potvrzeno = '';
  if ($nas['potvrzeni'] && $d['email'] !== '' && !pm_robot($d['email']) && $ai['ok'] && !empty($v['je_poptavka'])) {   // jen když AI potvrdila, že jde o poptávku zákazníka
    require_once __DIR__ . '/posta.php';
    $txt = "Dobrý den,\n\nděkujeme za Vaši poptávku" . ($predCist !== '' ? " („" . $predCist . "“)" : '') . ". Zaevidovali jsme ji pod číslem $cislo a "
      . ($poTerminu ? 'ozveme se Vám co nejdříve' : 'ozveme se Vám nejpozději do dvou pracovních dnů') . " na telefon nebo e-mail, který jste uvedli.\n\n"
      . "Kdybyste chtěli cokoli doplnit, stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235.\n\n"
      . "Bezpečné dveře · KK SYSTEM\nObchodní 36, Most\nwww.bezpecne-dvere.cz";
    $okP = posli_mail($cfg, $DATA, [$d['email']], [], $cfg['from'], "Děkujeme za poptávku č. $cislo", $txt);
    $potvrzeno = $okP ? $d['email'] : 'CHYBA';
    if (function_exists('terminy_hist')) terminy_hist($pdo, $id, $okP ? 'potvrzení přijetí poslané zákazníkovi' : 'potvrzení zákazníkovi se nepodařilo odeslat', $d['email']);
  }
  return ['stav' => 'zalozeno', 'zadost_id' => $id, 'cislo' => $cislo, 'd' => $d, 'od' => $od, 'potvrzeno' => $potvrzeno, 'poznamka' => $cislo . ($ai['ok'] ? '' : ' (bez AI: ' . $ai['chyba'] . ')')];
}

// odpověď prodejci, který e-mail přeposlal
function pm_odpoved(array $cfg, string $DATA, array $r, string $predmet): bool {
  require_once __DIR__ . '/posta.php';
  $web = rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/');
  if ($r['stav'] === 'duplicita') {
    $telo = "Tuto poptávku už v kartotéce máme: {$r['cislo']} (stejný zákazník a předmět). Nová poptávka se nezaložila.\n\nOtevřít: $web/kartoteka.php?id={$r['zadost_id']}\n\nKdyž jde opravdu o novou poptávku, změňte předmět e-mailu a pošlete ho znovu.";
    return posli_mail($cfg, $DATA, [$r['od']], [], $cfg['from'], 'Poptávka už existuje: ' . $r['cislo'], $telo);
  }
  $d = $r['d']; $jm = trim($d['jmeno'] . ' ' . $d['prijmeni']);
  $telo = "Z přeposlaného e-mailu („" . $predmet . "“) jsem založil poptávku {$r['cislo']}.\n\n"
    . "Zákazník: " . ($jm !== '' ? $jm : '(jméno nezjištěno)') . ($d['mesto'] !== '' ? ', ' . $d['mesto'] : '') . "\n"
    . 'Telefon: ' . ($d['telefon'] !== '' ? $d['telefon'] : '–') . ' · E-mail: ' . ($d['email'] !== '' ? $d['email'] : '–') . "\n"
    . 'Dveře: ' . ($d['model'] !== '' ? $d['model'] : PM_KATEGORIE[$d['kategorie']] ?? '') . ($d['odstin'] !== '' ? ' – ' . $d['odstin'] : '') . "\n"
    . ($d['poznamka'] !== '' ? "\n" . $d['poznamka'] . "\n" : '')
    . ($d['ai_overit'] ? "\nOvěřte:\n• " . implode("\n• ", $d['ai_overit']) . "\n" : '')
    . "\n" . (($r['potvrzeno'] ?? '') === 'CHYBA' ? 'Potvrzení zákazníkovi se NEPODAŘILO odeslat.' : (($r['potvrzeno'] ?? '') !== '' ? 'Zákazník dostal potvrzení přijetí na ' . $r['potvrzeno'] . '.' : ($d['email'] === '' ? 'Zákazníkovi nic neodešlo (v e-mailu není jeho adresa).' : 'Zákazníkovi potvrzení neodešlo (AI poptávku nevyplnila nebo ji nevyhodnotila jako poptávku) – ozvěte se mu sami.')))
    . "\nTermín reakce 48 h se hlídá od " . date('j. n. H:i', strtotime($d['prijato'])) . ".\n\nOtevřít poptávku: $web/kartoteka.php?id={$r['zadost_id']}\n(V detailu je tlačítko 🤖 Připravit nabídku.)";
  return posli_mail($cfg, $DATA, [$r['od']], [], $cfg['from'], 'Založeno ' . $r['cislo'] . ': ' . ($jm !== '' ? $jm : 'poptávka z e-mailu'), $telo);
}
