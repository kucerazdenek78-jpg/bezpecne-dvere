<?php
// Termín montáže zákazníkovi e-mailem: potvrzení, změna, zrušení. Volá se z predani.php až po tom, co obchodník zaškrtne „domluveno telefonicky“.
// Okno příjezdu (rozhodnutí Zdeňka 5. 11. 2026): do 40 km od pobočky (Obchodní 36, Most) 8:30–9:15, nad 40 km o 30 minut později (9:00–9:45).
// Vzdálenost = doprava_km uložené v objednávce (Mapy.com, trasa po silnici od vzorkovny k místu instalace), jinak ruční volba montaz_vzd = do|nad.
// Odhad délky montáže 4–6 hodin (v kalendáři se bere horní hranice). Jméno montéra se v e-mailu neuvádí.
// Patří na NAS do /docker/formulare/app/lib/montaz-mail.php. Podpis obchodníka doplňuje posli_mail (lib/posta.php).

const MONTAZ_LIMIT_KM = 40;
const MONTAZ_PRIPRAVA_URL = 'https://bezpecne-dvere.cz/priprava-na-montaz.html';

// Okno příjezdu: ['od'=>'08:30','do'=>'09:15','km'=>float|null,'zdroj'=>'km'|'rucne'|''] nebo null, když vzdálenost není známa.
function montaz_okno(array $d): ?array {
  $km = isset($d['doprava_km']) && is_numeric($d['doprava_km']) ? (float)$d['doprava_km'] : null;
  $nad = null; $zdroj = '';
  if ($km !== null) { $nad = $km > MONTAZ_LIMIT_KM; $zdroj = 'km'; }
  elseif (in_array($d['montaz_vzd'] ?? '', ['do', 'nad'], true)) { $nad = $d['montaz_vzd'] === 'nad'; $zdroj = 'rucne'; }
  if ($nad === null) return null;
  return $nad ? ['od' => '09:00', 'do' => '09:45', 'km' => $km, 'zdroj' => $zdroj] : ['od' => '08:30', 'do' => '09:15', 'km' => $km, 'zdroj' => $zdroj];
}

function montaz_den_cs(string $dne): string {
  $dny = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];
  $t = strtotime($dne); return $dny[(int)date('w', $t)] . ' ' . date('j. n. Y', $t);
}
function montaz_hm(string $hhmm): string { return ltrim(substr($hhmm, 0, 2), '0') . ':' . substr($hhmm, 3, 2); }   // 08:30 -> 8:30

// Údaje události z objednávky; vrací null, když chybí datum nebo okno.
function montaz_udaje(array $z, array $d): ?array {
  $dne = (string)(($d['montaz_dne'] ?? '') ?: ($d['termin_datum'] ?? ''));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dne)) return null;
  $okno = montaz_okno($d); if (!$okno) return null;
  $adresa = implode(', ', array_filter([$d['ulice'] ?? '', trim(($d['psc'] ?? '') . ' ' . ($d['mesto'] ?? ''))]));
  return ['dne' => $dne, 'od' => $okno['od'], 'do' => $okno['do'], 'adresa' => $adresa, 'cislo' => (string)($d['obj_cislo'] ?? $z['cislo'] ?? ''), 'id' => (int)$z['id']];
}

// Je odeslaný termín stejný jako uložený? (den, okno, adresa)
function montaz_shoduje_se(array $odeslano, array $u): bool {
  return ($odeslano['dne'] ?? '') === $u['dne'] && ($odeslano['od'] ?? '') === $u['od'] && ($odeslano['do'] ?? '') === $u['do'] && ($odeslano['adresa'] ?? '') === $u['adresa'];
}

// ICS příloha. $metoda = PUBLISH (potvrzení i změna – stejné UID, vyšší SEQUENCE přepíše událost) nebo CANCEL.
function montaz_ics(array $u, int $seq, string $metoda = 'PUBLISH'): string {
  $esc = fn($s) => str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], (string)$s);
  $fold = function (string $l): string {
    $out = ''; $cur = ''; $len = 0;
    foreach (preg_split('//u', $l, -1, PREG_SPLIT_NO_EMPTY) as $ch) { $b = strlen($ch); if ($len + $b > 74) { $out .= $cur . "\r\n "; $cur = ''; $len = 1; } $cur .= $ch; $len += $b; }
    return $out . $cur;
  };
  $tz = new DateTimeZone('Europe/Prague');
  $s = new DateTime($u['dne'] . ' ' . $u['od'], $tz); $k = (clone $s)->modify('+6 hours');
  $popis = "Montáž dveří – objednávka " . $u['cislo'] . "\nPříjezd: mezi " . montaz_hm($u['od']) . " a " . montaz_hm($u['do']) . "\nMontáž trvá odhadem 4–6 hodin podle složitosti.\nJak se připravit: " . MONTAZ_PRIPRAVA_URL . "\nTelefon: +420 725 559 235";
  $L = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Bezpecne dvere//Termin montaze//CS', 'CALSCALE:GREGORIAN', 'METHOD:' . $metoda,
    'BEGIN:VTIMEZONE', 'TZID:Europe/Prague',
    'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
    'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000', 'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD',
    'END:VTIMEZONE', 'BEGIN:VEVENT',
    'UID:montaz-zakaznik-' . $u['id'] . '@bezpecne-dvere.cz', 'SEQUENCE:' . $seq, 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART;TZID=Europe/Prague:' . $s->format('Ymd\THis'), 'DTEND;TZID=Europe/Prague:' . $k->format('Ymd\THis'),
    'SUMMARY:' . $esc('Montáž dveří – Bezpečné dveře'), 'DESCRIPTION:' . $esc($popis), 'URL:' . MONTAZ_PRIPRAVA_URL];
  if ($u['adresa'] !== '') $L[] = 'LOCATION:' . $esc($u['adresa']);
  $L[] = 'STATUS:' . ($metoda === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'); $L[] = 'TRANSP:OPAQUE';
  if ($metoda !== 'CANCEL') { $L[] = 'BEGIN:VALARM'; $L[] = 'TRIGGER:-P1D'; $L[] = 'ACTION:DISPLAY'; $L[] = 'DESCRIPTION:' . $esc('Zítra montáž dveří – okno příjezdu ' . montaz_hm($u['od']) . '–' . montaz_hm($u['do'])); $L[] = 'END:VALARM'; }
  $L[] = 'END:VEVENT'; $L[] = 'END:VCALENDAR';
  return implode("\r\n", array_map($fold, $L)) . "\r\n";
}

function montaz_google_odkaz(array $u): string {
  $tz = new DateTimeZone('Europe/Prague');
  $s = new DateTime($u['dne'] . ' ' . $u['od'], $tz); $k = (clone $s)->modify('+6 hours');
  $det = "Montáž dveří – objednávka " . $u['cislo'] . "\nPříjezd: mezi " . montaz_hm($u['od']) . " a " . montaz_hm($u['do']) . "\nMontáž trvá odhadem 4–6 hodin.\nJak se připravit: " . MONTAZ_PRIPRAVA_URL;
  return 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => 'Montáž dveří – Bezpečné dveře', 'dates' => $s->format('Ymd\THis') . '/' . $k->format('Ymd\THis'), 'ctz' => 'Europe/Prague', 'details' => $det, 'location' => $u['adresa']], '', '&', PHP_QUERY_RFC3986);
}
function montaz_outlook_odkaz(array $u): string {
  $tz = new DateTimeZone('Europe/Prague');
  $s = new DateTime($u['dne'] . ' ' . $u['od'], $tz); $k = (clone $s)->modify('+6 hours');
  return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query(['path' => '/calendar/action/compose', 'rru' => 'addevent', 'subject' => 'Montáž dveří – Bezpečné dveře', 'startdt' => $s->format('Y-m-d\TH:i:sP'), 'enddt' => $k->format('Y-m-d\TH:i:sP'), 'location' => $u['adresa'], 'body' => 'Příjezd: mezi ' . montaz_hm($u['od']) . ' a ' . montaz_hm($u['do']) . '. Montáž trvá odhadem 4–6 hodin. Jak se připravit: ' . MONTAZ_PRIPRAVA_URL], '', '&', PHP_QUERY_RFC3986);
}

// Texty příprav (jediné místo pro e-mail; stejný obsah je na webu v priprava-na-montaz.html).
function montaz_priprava(): array {
  return [
    'priprava' => [
      'Montáž trvá odhadem 4–6 hodin podle složitosti.',
      'Při bourání a osazování se práší. Zakryjte prosím nábytek a věci v okolí dveří a uvolněte prostor kolem nich.',
      'Zajistěte, prosím, abychom se v domluvený čas dostali do bytu nebo domu.',
    ],
    'soused' => [
      'Informujte sousedy, zejména v bytovém domě, že po dobu montáže bude hluk a prach a že se mohou po společných prostorách pohybovat montéři s materiálem.',
      'Cedulku, kterou můžete vyvěsit na dveře nebo do chodby, najdete na stránce s přípravou.',
    ],
  ];
}

// Sestaví e-mail: ['predmet','text','html'] podle typu potvrdit|zmena|zrusit. $puvodni = dříve odeslaný termín (pro změnu a zrušení).
function montaz_zprava(string $typ, array $u, ?array $puvodni): array {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $den = montaz_den_cs($u['dne']); $okno = montaz_hm($u['od']) . ' a ' . montaz_hm($u['do']); $text = montaz_priprava();
  $stary = $puvodni ? montaz_den_cs($puvodni['dne']) . ', mezi ' . montaz_hm($puvodni['od']) . ' a ' . montaz_hm($puvodni['do']) : '';
  $google = montaz_google_odkaz($u); $outlook = montaz_outlook_odkaz($u);

  if ($typ === 'zrusit') {
    $predmet = 'Zrušení termínu montáže dveří – ' . montaz_den_cs($puvodni['dne'] ?? $u['dne']);
    $uvod = "Dobrý den,\n\ntermín montáže dveří (objednávka č. " . $u['cislo'] . "), který jsme Vám potvrdili na " . $stary . ", rušíme. Nový termín s Vámi domluvíme telefonicky a pošleme Vám ho e-mailem.\n\nPřiložený soubor kalendáře původní termín z Vašeho kalendáře odstraní.\n\nKdybyste cokoli potřebovali, ozvěte se: +420 725 559 235, chci@bezpecne-dvere.cz";
    $html = '<p>Dobrý den,</p><p>termín montáže dveří (objednávka č. ' . $h($u['cislo']) . '), který jsme Vám potvrdili na <b>' . $h($stary) . '</b>, rušíme. Nový termín s Vámi domluvíme telefonicky a pošleme Vám ho e-mailem.</p><p>Přiložený soubor kalendáře původní termín z Vašeho kalendáře odstraní.</p><p>Kdybyste cokoli potřebovali, ozvěte se: <a href="tel:+420725559235">+420 725 559 235</a>, <a href="mailto:chci@bezpecne-dvere.cz">chci@bezpecne-dvere.cz</a></p>';
    return ['predmet' => $predmet, 'text' => $uvod, 'html' => $html];
  }

  $zmena = $typ === 'zmena';
  $predmet = ($zmena ? 'ZMĚNA termínu montáže dveří – nově ' : 'Potvrzení termínu montáže dveří – ') . $den;
  $hlavni = 'Termín montáže: ' . $den . ($u['adresa'] !== '' ? "\nMísto: " . $u['adresa'] : '') . "\nPříjezd: mezi " . $okno . "\nOdhad trvání: 4–6 hodin podle složitosti";
  $uvod = $zmena
    ? "Dobrý den,\n\nna základě naší telefonické domluvy se mění termín montáže dveří (objednávka č. " . $u['cislo'] . ").\nPůvodní termín: " . $stary . ".\n\nNOVÝ TERMÍN\n" . $hlavni
    : "Dobrý den,\n\npotvrzujeme termín montáže dveří, který jsme spolu domluvili telefonicky (objednávka č. " . $u['cislo'] . ").\n\n" . $hlavni;
  $t = $uvod . "\n\nPŘIDAT DO KALENDÁŘE\nV příloze je soubor .ics (iPhone, Outlook, Apple Kalendář). Pro Google Kalendář: " . $google . "\nPro Outlook.com: " . $outlook
    . ($zmena ? "\n(Soubor původní událost ve Vašem kalendáři přepíše novým termínem. Pokud ji máte jinde, upravte ji prosím ručně.)" : '')
    . "\n\nJAK SE PŘIPRAVIT\n- " . implode("\n- ", $text['priprava'])
    . "\n\nINFORMUJTE SOUSEDY\n- " . implode("\n- ", $text['soused'])
    . "\n\nPodrobnosti a cedulka pro sousedy: " . MONTAZ_PRIPRAVA_URL
    . "\n\nKdyby se Vám termín nehodil nebo se cokoli změnilo, zavolejte nám prosím: +420 725 559 235, nebo napište na chci@bezpecne-dvere.cz.";

  $btn = fn($url, $txt, $bg) => '<a href="' . $h($url) . '" style="display:inline-block;background:' . $bg . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:10px 16px;border-radius:20px;margin:0 6px 6px 0">' . $txt . '</a>';
  $li = fn($a) => '<ul style="margin:6px 0 0 18px;padding:0">' . implode('', array_map(fn($x) => '<li style="margin:0 0 6px">' . $h($x) . '</li>', $a)) . '</ul>';
  $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1d2b22;max-width:600px">'
    . '<p>Dobrý den,</p>'
    . ($zmena ? '<p>na základě naší telefonické domluvy se <b>mění termín montáže</b> dveří (objednávka č. ' . $h($u['cislo']) . '). Původní termín: <s>' . $h($stary) . '</s>.</p>'
              : '<p>potvrzujeme termín montáže dveří, který jsme spolu domluvili telefonicky (objednávka č. ' . $h($u['cislo']) . ').</p>')
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="background:#f3f7f4;border-left:5px solid #e8650a;border-radius:8px;margin:12px 0;width:100%"><tr><td style="padding:14px 16px">'
    . '<div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4b5d52">' . ($zmena ? 'Nový termín montáže' : 'Termín montáže') . '</div>'
    . '<div style="font-size:22px;font-weight:bold;color:#123d27;margin:2px 0 6px">' . $h($den) . '</div>'
    . '<div>Příjezd: <b>mezi ' . $h($okno) . '</b></div>'
    . '<div>Odhad trvání: <b>4–6 hodin</b> podle složitosti</div>'
    . ($u['adresa'] !== '' ? '<div>Místo: ' . $h($u['adresa']) . '</div>' : '')
    . '</td></tr></table>'
    . '<div style="font-weight:bold;margin-top:14px">Přidat do kalendáře</div>'
    . '<div style="margin-top:6px">' . $btn($google, 'Google Kalendář', '#1d5fb8') . $btn($outlook, 'Outlook.com', '#0f6cbd') . '</div>'
    . '<div style="font-size:13px;color:#4b5d52">Pro iPhone, Apple Kalendář a Outlook otevřete přiložený soubor <b>termin-montaze.ics</b>.'
    . ($zmena ? ' Soubor původní událost ve Vašem kalendáři přepíše novým termínem. Pokud ji máte jinde, upravte ji prosím ručně.' : '') . '</div>'
    . '<div style="font-weight:bold;margin-top:16px">Jak se připravit</div>' . $li($text['priprava'])
    . '<div style="font-weight:bold;margin-top:16px">Informujte sousedy</div>' . $li($text['soused'])
    . '<p style="margin-top:14px"><a href="' . $h(MONTAZ_PRIPRAVA_URL) . '" style="color:#1d5fb8">Podrobnosti a cedulka pro sousedy</a></p>'
    . '<p>Kdyby se Vám termín nehodil nebo se cokoli změnilo, zavolejte nám prosím na <a href="tel:+420725559235">+420 725 559 235</a> nebo napište na <a href="mailto:chci@bezpecne-dvere.cz">chci@bezpecne-dvere.cz</a>.</p></div>';
  return ['predmet' => $predmet, 'text' => $t, 'html' => $html];
}

// Odešle e-mail zákazníkovi. Vrací ['ok'=>bool,'chyba'=>string,'odeslano'=>array] (odeslano = nový snapshot k uložení do data_json).
function montaz_posli(array $cfg, string $DATA, array $z, array $d, string $typ, string $kdo = ''): array {
  if (!in_array($typ, ['potvrdit', 'zmena', 'zrusit'], true)) return ['ok' => false, 'chyba' => 'Neznámá akce.'];
  $em = trim((string)($d['email'] ?? ''));
  if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'chyba' => 'Zákazník nemá platný e-mail.'];
  $puv = is_array($d['montaz_odeslano'] ?? null) && empty($d['montaz_odeslano']['zruseno']) ? $d['montaz_odeslano'] : null;
  if ($typ === 'zrusit') {
    if (!$puv) return ['ok' => false, 'chyba' => 'Zákazníkovi dosud nebyl odeslán žádný termín.'];
    $u = ['dne' => $puv['dne'], 'od' => $puv['od'], 'do' => $puv['do'], 'adresa' => $puv['adresa'] ?? '', 'cislo' => (string)($d['obj_cislo'] ?? $z['cislo'] ?? ''), 'id' => (int)$z['id']];
  } else {
    $u = montaz_udaje($z, $d);
    if (!$u) return ['ok' => false, 'chyba' => 'Chybí datum termínu nebo vzdálenost (okno příjezdu).'];
    if ($typ === 'zmena' && !$puv) $typ = 'potvrdit';
  }
  $seq = (int)($d['montaz_odeslano']['seq'] ?? -1) + 1;   // SEQUENCE roste i po zrušení, aby kalendář zákazníka nový termín nepřeskočil
  $zprava = montaz_zprava($typ, $u, $puv);
  $ics = montaz_ics($u, $seq, $typ === 'zrusit' ? 'CANCEL' : 'PUBLISH');
  $tmp = tempnam(sys_get_temp_dir(), 'mtz'); file_put_contents($tmp, $ics);
  require_once __DIR__ . '/posta.php';
  $ok = posli_mail($cfg, $DATA, [$em], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], $zprava['predmet'], $zprava['text'], [[$tmp, 'termin-montaze.ics']], $zprava['html'], $kdo);
  @unlink($tmp);
  if (!$ok) return ['ok' => false, 'chyba' => 'E-mail se nepodařilo odeslat.'];
  $nove = $typ === 'zrusit'
    ? ['dne' => $u['dne'], 'od' => $u['od'], 'do' => $u['do'], 'adresa' => $u['adresa'], 'seq' => $seq, 'zruseno' => true, 'cas' => date('Y-m-d H:i:s'), 'typ' => 'zrusit', 'komu' => $em]
    : ['dne' => $u['dne'], 'od' => $u['od'], 'do' => $u['do'], 'adresa' => $u['adresa'], 'seq' => $seq, 'cas' => date('Y-m-d H:i:s'), 'typ' => $typ, 'komu' => $em];
  if ($kdo !== '') $nove['kdo'] = mb_substr($kdo, 0, 60);
  return ['ok' => true, 'chyba' => '', 'odeslano' => $nove];
}
