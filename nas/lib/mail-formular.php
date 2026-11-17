<?php
// Přehledná HTML podoba e-mailu zákazníkovi z webových formulářů (17. 11. 2026). Volá odeslat.php; pomocné funkce (šablona, video, rámeček)
// jsou v lib/mail-html.php. Patří na NAS do /docker/formulare/app/lib/mail-formular.php.
require_once __DIR__ . '/mail-html.php';

// ── E-mail zákazníkovi z webových formulářů (odeslat.php, typ objednavka_zakaznik: objednávka nebo cenová nabídka) ──
// Tělo přichází jako řádky „Popisek: hodnota“. Vrací přehledné HTML (platba s tlačítkem, položky v kartách, u objednávky
// video se zloději) nebo '' (neznámý tvar → odeslat.php pošle původní podobu). Řádek „Příjmení: …“ slouží jen pro vtip a nezobrazuje se.
function mailh_formular(array $cfg, string $predmet, string $telo, array $obr = []): string {
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  $obj = (bool)preg_match('~^Objednávka\s+(\S+)~u', $predmet, $m); $nab = !$obj && preg_match('~^Cenová nabídka\s+(.+?)\s+–~u', $predmet, $m);
  if (!$obj && !$nab) return '';
  $cislo = trim($m[1]);
  $radky = [];
  foreach (explode("\n", str_replace("\r", '', $telo)) as $l) {
    if (preg_match('/^([^:\n]{1,60}): ?(.*)$/u', $l, $x) && !preg_match('~^https?$~', $x[1])) $radky[] = [trim($x[1]), trim($x[2])];
    elseif ($radky) $radky[count($radky) - 1][1] .= "\n" . $l;
  }
  if (count($radky) < 2) return '';
  $link = fn($s) => nl2br(preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" style="color:#1d5fb8">$1</a>', mailh_h($s)));
  $btn = fn($url, $txt) => '<a href="' . mailh_h($url) . '" style="display:inline-block;background:#e8650a;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:11px 20px;border-radius:22px;margin:8px 0 2px">' . mailh_h($txt) . '</a>';
  $url = fn($s) => preg_match('~https?://\S+~u', $s, $u) ? rtrim($u[0], '.,;') : '';
  $prijmeni = ''; $uvod = ''; $platba = ''; $dulezite = ''; $polozky = ''; $moje = ''; $ostatni = '';
  foreach ($radky as [$k, $v]) {
    if ($k === 'Příjmení') { $prijmeni = mb_substr(strip_tags($v), 0, 40); continue; }
    if ($k === 'Dobrý den') { $uvod = $v; continue; }
    if ($k === 'Objednávka' || $k === 'Nabídka') continue;                              // číslo je v hlavičce
    if ($k === 'Důležité') { $dulezite = $v; continue; }
    if (preg_match('~^(Záloha|Platba)~u', $k)) {
      $u = $url($v); $bez = trim(preg_replace('~\.?\s*QR kód a údaje k platbě:\s*https?://\S+~u', '', $v));
      $platba .= '<div style="margin-bottom:4px"><b>' . mailh_h($k) . ':</b> ' . mailh_h($bez) . '</div>' . ($u !== '' ? '<div>' . $btn($u, 'Zaplatit zálohu: QR kód a údaje k platbě') . '</div>' : '');
      continue;
    }
    if (preg_match('~^(Doplatek|Celkem|Cena celkem)~u', $k)) { $platba .= '<div><b>' . mailh_h($k) . ':</b> ' . mailh_h($v) . '</div>'; continue; }
    if (preg_match('~^Položka (\d+)$~u', $k, $pn)) {
      $ob = $obr[(int)$pn[1]] ?? null; $obH = '';
      if ($ob) { $ow = 120; $oh = (int)round($ow * max(1, (int)$ob['h']) / max(1, (int)$ob['w'])); if ($oh > 250) { $oh = 250; $ow = (int)round($oh * $ob['w'] / max(1, (int)$ob['h'])); }
        $obH = '<td valign="top" width="' . $ow . '" style="padding:12px 0 12px 12px;width:' . $ow . 'px"><img src="cid:' . mailh_h($ob['cid']) . '" width="' . $ow . '" height="' . $oh . '" alt="' . ($ob['ilu'] ? 'Ilustrační obrázek' : 'Vaše dveře') . '" style="display:block;border:0;border-radius:6px;width:' . $ow . 'px;height:' . $oh . 'px">'
          . ($ob['ilu'] ? '<div style="font-size:11px;line-height:1.3;color:#6b7c71;text-align:center;padding-top:4px">ilustrační obrázek</div>' : '') . '</td>'; }
      $cena = ''; if (preg_match('~^(.*):\s*([\d\s\x{00A0}]+Kč)\s*$~us', $v, $c)) { $v = $c[1]; $cena = trim($c[2]); }
      $cast = preg_split('~\s+–\s+~u', $v, 2); $nazev = trim($cast[0]); $det = array_filter(array_map('trim', preg_split('~;\s+|\s+·\s+~u', (string)($cast[1] ?? ''))));
      $polozky .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #d0e0d5;border-radius:10px;margin:0 0 10px"><tr>' . $obH . '<td valign="top" style="padding:12px 14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="font-weight:bold;font-size:16px;color:#123d27">' . mailh_h($nazev) . '</td>'
        . ($cena !== '' && $cena !== '0 Kč' ? '<td align="right" style="font-weight:bold;font-size:16px;color:#c2410c;white-space:nowrap;padding-left:10px">' . mailh_h($cena) . '</td>' : '') . '</tr></table>'
        . ($det ? '<div style="font-size:14px;line-height:1.5;color:#33463a;margin-top:6px">' . implode('<br>', array_map('mailh_h', $det)) . '</div>' : '')
        . '</td></tr></table>';
      continue;
    }
    if (preg_match('~^Moje dveře~u', $k) && $url($v) !== '') { $moje .= '<div style="margin:0 0 6px"><b>Vaše dveře v mobilu:</b> záruka, návod na údržbu a servis na jednom místě.</div><div>' . $btn($url($v), 'Otevřít Moje dveře') . '</div>'; continue; }
    if (preg_match('~^Aplikace Moje dveře~u', $k) && $nab && preg_match('~^(.*?)\s*Podrobnosti:\s*(https?://\S+)\s*$~us', $v, $am)) { $moje .= '<div style="margin:0 0 6px">' . mailh_h($am[1]) . '</div><div>' . $btn(rtrim($am[2], '.,;'), 'Podívat se na aplikaci') . '</div>'; continue; }
    if (preg_match('~^Aplikace Moje dveře~u', $k)) { $moje .= '<div style="font-size:14px;color:#33463a;margin-top:8px">' . $link($v) . '</div>'; continue; }
    $ostatni .= '<tr><td valign="top" style="padding:5px 12px 5px 0;color:#4f6a5b;font-size:14px;width:38%">' . mailh_h($k) . '</td><td valign="top" style="padding:5px 0;font-size:14.5px">' . $link($v) . '</td></tr>';
  }
  $o = '<p style="margin:0 0 12px">Dobrý den,</p>'
    . '<p style="margin:0 0 14px">' . ($obj ? 'děkujeme za objednávku! Posíláme ji v příloze v PDF a níže najdete to hlavní: jak zaplatit zálohu a co jste si vybrali.'
        : ($uvod !== '' ? $link(mb_strtolower(mb_substr($uvod, 0, 1)) . mb_substr($uvod, 1)) : 'děkujeme za zájem. V příloze posíláme cenovou nabídku v PDF.')) . '</p>';
  if ($obj) $o .= mailh_video($web, 'zlodeji-to-vzdali', 'Krátké video: dva zloději stojí bezradně před bezpečnostními dveřmi a odcházejí',
      '<b>Mezitím někde na poradě zlodějů:</b> ' . ($prijmeni !== '' ? 'u jména „' . mailh_h($prijmeni) . '“ si právě udělali křížek.' : 'Vaši adresu si právě škrtli ze seznamu.') . ' Prý je to v tahu, tam už nejdou. Nám jich líto není.');
  if ($nab) $o .= mailh_video($web, 'buldok-kontrolor', 'Krátké video: šedý francouzský buldoček v přilbě si prohlíží dveře ve vzorkovně',
      '<b>Výstupní kontrola proběhla.</b> Nabídku Vám očichal náš nejpřísnější kontrolor a neměl námitek. Za podpis tlapkou se omlouváme, propisku neudrží.');
  if ($platba !== '') $o .= mailh_ramecek($obj ? 'Platba zálohy' : 'Cena', $platba . ($dulezite !== '' ? '<div style="font-size:13px;line-height:1.45;color:#7c2d0c;margin-top:8px">' . mailh_h($dulezite) . '</div>' : ''));
  if ($polozky !== '') $o .= '<div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#c2410c;font-weight:bold;margin:4px 0 6px">' . ($obj ? 'Co jste si objednali' : 'Co Vám nabízíme') . '</div>' . $polozky;
  if ($ostatni !== '') $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 14px;border-top:1px solid #e3ebe6">' . $ostatni . '</table>';
  if ($moje !== '') $o .= mailh_ramecek('Aplikace Moje dveře v ceně', $moje);
  $o .= '<p style="margin:10px 0 0">📎 <b>V příloze:</b> ' . ($obj ? 'objednávka' : 'cenová nabídka') . ' č. ' . mailh_h($cislo) . ' v PDF.' . ($obj ? '' : ' Pokud Vám vyhovuje, ozvěte se a převedeme ji na objednávku.') . '</p>'
    . '<p style="margin:8px 0 0">Kdyby cokoli, stačí odpovědět na tento e-mail.</p>';
  return mailh_sablona(($obj ? 'Objednávka č. ' : 'Cenová nabídka č. ') . $cislo, $obj ? 'Děkujeme za objednávku!' : 'Vaše cenová nabídka', $o);
}
