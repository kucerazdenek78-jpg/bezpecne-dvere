<?php
// HTML podoba e-mailů zákazníkům (17. 11. 2026, přání Zdeňka: „e-maily ne jako prostý text, ale HTML“, vtipný text a krátké video).
// Volá ji posli_mail (lib/posta.php) jen u e-mailů zákazníkům: mailh_uprav() podle předmětu
//   - akceptace objednávky (podepsaná objednávka po přijetí zálohy): veselý text + animace „roboti ve výrobě“,
//   - potvrzení / změna termínu montáže: upozornění na prach + animace „buldoček při montáži“ + PDF cedulka pro sousedy v příloze,
//   - ostatní e-maily: prostý text zabalí do jednotné šablony (zelená hlavička).
// Obrázky a videa leží na webu v userFiles/email/. Podpis obchodníka dál přidává lib/podpis.php (pod šablonu).
// Když cokoli selže, e-mail odejde v původní podobě. Patří na NAS do /docker/formulare/app/lib/mail-html.php.

function mailh_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Jednotná šablona: zelená hlavička (nadtitul + nadpis) a obsah ve sloupci 600 px. Podpis se vkládá před </body>.
function mailh_sablona(string $nadtitul, string $nadpis, string $obsah): string {
  return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . mailh_h($nadpis) . '</title></head>'
    . '<body style="margin:0;padding:0;background:#ffffff">'
    . '<div style="max-width:600px;margin:0 auto;padding:14px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.55;color:#1d2b22">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="background:#123d27;border-radius:12px;padding:20px 24px">'
    . ($nadtitul !== '' ? '<div style="font-size:13px;letter-spacing:.08em;color:#b9d3c2;text-transform:uppercase">' . mailh_h($nadtitul) . '</div>' : '')
    . '<div style="font-size:23px;line-height:1.25;font-weight:bold;color:#ffffff;margin-top:4px">' . mailh_h($nadpis) . '</div>'
    . '</td></tr></table>'
    . '<div style="padding:18px 4px 0">' . $obsah . '</div>'
    . '</div></body></html>';
}

// Prostý text → odstavce, odrážky („- “), nadpisy (řádek VELKÝMI PÍSMENY) a klikací odkazy.
function mailh_text_html(string $telo): string {
  $link = fn($s) => preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" style="color:#1d5fb8">$1</a>', mailh_h($s));
  $out = '';
  foreach (preg_split('/\n{2,}/', trim(str_replace("\r", '', $telo))) as $blok) {
    $radky = explode("\n", trim($blok)); $p = []; $li = [];
    $vypis = function () use (&$p, &$li, &$out) {
      if ($p) { $out .= '<p style="margin:0 0 12px">' . implode('<br>', $p) . '</p>'; $p = []; }
      if ($li) { $out .= '<ul style="margin:0 0 12px 18px;padding:0">' . implode('', array_map(fn($x) => '<li style="margin:0 0 6px">' . $x . '</li>', $li)) . '</ul>'; $li = []; }
    };
    foreach ($radky as $r) {
      $r = rtrim($r);
      if (preg_match('/^[-•]\s+(.*)$/u', $r, $m)) { if ($p) $vypis(); $li[] = $link($m[1]); }
      elseif ($r !== '' && mb_strlen($r) <= 60 && preg_match('/\p{Lu}/u', $r) && mb_strtoupper($r) === $r && !preg_match('~https?://~', $r)) { $vypis(); $out .= '<div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#c2410c;font-weight:bold;margin:14px 0 4px">' . mailh_h($r) . '</div>'; }
      else { if ($li) $vypis(); $p[] = $link($r); }
    }
    $vypis();
  }
  return $out;
}

// Animace (GIF) s odkazem na video na webu a vtipným popiskem.
function mailh_video(string $web, string $soubor, string $alt, string $popisHtml): string {
  $u = $web . '/userFiles/email/' . $soubor;
  return '<div style="margin:6px 0 16px"><a href="' . mailh_h($u . '.mp4') . '" style="text-decoration:none"><img src="' . mailh_h($u . '.gif') . '" width="592" alt="' . mailh_h($alt) . '" style="display:block;width:100%;max-width:592px;height:auto;border:0;border-radius:10px"></a>'
    . '<div style="font-size:13.5px;line-height:1.45;color:#4f6a5b;padding:8px 2px 0;text-align:center">' . $popisHtml . '</div></div>';
}

function mailh_ramecek(string $nadpis, string $html): string {
  return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6faf7;border:1px solid #d0e0d5;border-radius:10px;margin:0 0 16px"><tr><td style="padding:14px 16px;font-size:15px;line-height:1.6">'
    . '<div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#c2410c;font-weight:bold;margin-bottom:4px">' . mailh_h($nadpis) . '</div>' . $html . '</td></tr></table>';
}

// PDF cedulka pro sousedy: podklad (JPEG z webu, userFiles/email/cedulka-sousede.jpg, 1240×1754) + dopsané datum a čas příjezdu.
// Vrací cestu k dočasnému souboru nebo null (podklad se nepodařilo stáhnout). Text jen číslice a znaky z WinAnsi (písmo Helvetica-Bold).
function mailh_cedulka_pdf(string $DATA, string $web, string $datum, string $cas): ?string {
  $cache = $DATA . '/cache/cedulka-sousede-v1.jpg';
  $jpg = is_file($cache) && filesize($cache) > 20000 ? (string)@file_get_contents($cache) : '';
  if ($jpg === '') {
    $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'kartoteka-bezpecne-dvere']]);
    $jpg = (string)@file_get_contents($web . '/userFiles/email/cedulka-sousede.jpg', false, $ctx);
    if (strlen($jpg) < 20000 || substr($jpg, 0, 2) !== "\xFF\xD8") return null;
    @mkdir($DATA . '/cache', 0775, true); @file_put_contents($cache, $jpg, LOCK_EX);
  }
  $i = @getimagesizefromstring($jpg);
  if (!$i || $i[2] !== IMAGETYPE_JPEG || ($i['channels'] ?? 3) !== 3) return null;
  $W = 595.28; $H = 841.89; $k = $W / 1240;                       // px podkladu → body PDF
  $sir = function (string $t): float { $w = 0; foreach (str_split($t) as $c) $w += ctype_digit($c) ? 556 : ($c === "\x96" ? 556 : ($c === ':' || $c === '-' ? 333 : 278)); return $w; };
  $text = function (string $t, float $px, float $py, float $pw, float $ph, float $vel) use ($k, $H, $sir): string {   // vystředěně do okénka (px)
    $t = preg_replace('/[^0-9 .:\x96-]/', '', $t); $w = $sir($t) * $vel / 1000;
    $x = $px * $k + ($pw * $k - $w) / 2; $y = $H - ($py + $ph / 2) * $k - $vel * 0.36;
    return sprintf("BT /F1 %.1f Tf 0.086 0.149 0.11 rg %.2f %.2f Td (%s) Tj ET\n", $vel, $x, $y, $t);
  };
  $obsah = sprintf("q %.2f 0 0 %.2f 0 0 cm /Im1 Do Q\n", $W, $H)
    . $text($datum, 519, 389, 600, 96, 30) . $text(str_replace('–', "\x96", $cas), 519, 507, 330, 96, 26);
  $o = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /XObject << /Im1 4 0 R >> /Font << /F1 6 0 R >> >> /Contents 5 0 R >>', $W, $H),
    sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $i[0], $i[1], strlen($jpg)) . $jpg . "\nendstream",
    sprintf("<< /Length %d >>\nstream\n", strlen($obsah)) . $obsah . 'endstream',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
  ];
  $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
  foreach ($o as $n => $telo) { $off[] = strlen($pdf); $pdf .= ($n + 1) . " 0 obj\n" . $telo . "\nendobj\n"; }
  $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($o) + 1) . "\n0000000000 65535 f \n";
  foreach ($off as $x) $pdf .= sprintf("%010d 00000 n \n", $x);
  $pdf .= "trailer\n<< /Size " . (count($o) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
  $tmp = tempnam(sys_get_temp_dir(), 'ced'); if ($tmp === false || file_put_contents($tmp, $pdf) === false) return null;
  register_shutdown_function(fn() => @unlink($tmp));
  return $tmp;
}

// Hlavní vstup z posli_mail: upraví předmět, text, HTML a přílohy e-mailu zákazníkovi.
function mailh_uprav(array $cfg, string $DATA, string &$predmet, string &$telo, string &$htmlTelo, array &$prilohy): void {
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');

  // poděkování za poptávku a připomínky nabídek (lib/mail-dalsi.php)
  if (is_file(__DIR__ . '/mail-dalsi.php')) { require_once __DIR__ . '/mail-dalsi.php'; if (mailh_dalsi($cfg, $predmet, $telo, $htmlTelo)) return; }

  // 1) podepsaná objednávka po přijetí zálohy (akceptace.php)
  if (preg_match('~^Objednávka č\. (.+?) podepsaná – akceptace~u', $predmet, $m)) {
    $cislo = $m[1]; $kc = preg_match('~záloha ve výši ([\d\s\x{00A0}]+) Kč~u', $telo, $z) ? trim($z[1]) . ' Kč' : '';
    $predmet = "Objednávka č. $cislo: záloha dorazila, posíláme podepsanou objednávku";
    $vtip = 'Takhle teď makáme na Vašich dveřích. No dobře, skoro. Ve skutečnosti u toho nespíme, roboti nám deku nenosí a kafe si vaříme sami. Ale dveře budou stát za to.';
    $telo = "Dobrý den,\n\nVaše záloha" . ($kc !== '' ? " $kc" : '') . " k objednávce č. $cislo k nám v pořádku dorazila. Děkujeme!\n\n"
      . "Objednávku jsme podepsali a posíláme ji v příloze (poslední strana je akceptace zhotovitelem). Vy jste ji potvrdili zaplacením zálohy a my se pouštíme do práce.\n\n"
      . "Důležité: pokud se Vaše dveře vyrábějí na míru (například vchodové dveře ERKADO), zadáváme je teď do výroby a objednávku už nelze zrušit.\n\n"
      . $vtip . "\nKrátké video: $web/userFiles/email/roboti-ve-vyrobe.mp4\n\n"
      . "CO BUDE DÁL\n1. Připravíme Vaše dveře.\n2. Zavoláme Vám a domluvíme přesný termín montáže.\n3. Namontujeme, uklidíme a doplatek vyřídíme hotově po montáži.\n\n"
      . "Kdyby cokoli, stačí odpovědět na tento e-mail.";
    $htmlTelo = mailh_sablona("Objednávka č. $cislo", 'Záloha dorazila. Jde se na to!',
      '<p style="margin:0 0 12px">Dobrý den,</p>'
      . '<p style="margin:0 0 12px">Vaše záloha' . ($kc !== '' ? ' <b>' . mailh_h($kc) . '</b>' : '') . ' k nám v pořádku dorazila. Děkujeme!</p>'
      . '<p style="margin:0 0 12px">Objednávku jsme podepsali a posíláme ji v příloze. Vy jste ji potvrdili zaplacením zálohy a my se pouštíme do práce.</p>'
      . '<p style="margin:0 0 14px;font-size:14.5px;color:#7c2d0c"><b>Důležité:</b> pokud se Vaše dveře vyrábějí na míru (například vchodové dveře ERKADO), zadáváme je teď do výroby a objednávku už nelze zrušit.</p>'
      . mailh_video($web, 'roboti-ve-vyrobe', 'Krátké video: roboti vyrábějí dveře, zatímco my spíme', '<b>Takhle teď makáme na Vašich dveřích.</b> No dobře, skoro. Ve skutečnosti u toho nespíme, roboti nám deku nenosí a kafe si vaříme sami. Ale dveře budou stát za to.')
      . mailh_ramecek('Co bude dál', '<div><b>1.</b> Připravíme Vaše dveře.</div><div><b>2.</b> Zavoláme Vám a domluvíme přesný termín montáže.</div><div><b>3.</b> Namontujeme, uklidíme a doplatek vyřídíme hotově po montáži.</div>')
      . '<p style="margin:0 0 10px">📎 <b>V příloze:</b> objednávka č. ' . mailh_h($cislo) . ' s naším podpisem (PDF, poslední strana je akceptace zhotovitelem).</p>'
      . '<p style="margin:0">Kdyby cokoli, stačí odpovědět na tento e-mail.</p>');
    return;
  }

  // 2) potvrzení nebo změna termínu montáže (lib/montaz-mail.php)
  if (preg_match('~^(Potvrzení termínu montáže dveří|ZMĚNA termínu montáže dveří)~u', $predmet)) {
    $zmena = strpos($predmet, 'ZMĚNA') === 0; $pdf = null;
    $ics = ''; foreach ($prilohy as $x) if (preg_match('/\.ics$/i', (string)($x[1] ?? '')) && is_file((string)$x[0])) $ics = (string)@file_get_contents((string)$x[0]);
    if (preg_match('/DTSTART;TZID=[^:\r\n]*:(\d{4})(\d{2})(\d{2})T/', $ics, $dt)) {
      $datum = (int)$dt[3] . '. ' . (int)$dt[2] . '. ' . $dt[1];
      $cas = preg_match('~Příjezd: mezi (\d{1,2}:\d{2}) a (\d{1,2}:\d{2})~u', $telo, $c) ? $c[1] . '–' . $c[2] : '';
      $pdf = mailh_cedulka_pdf($DATA, $web, $datum, $cas);
      if ($pdf) $prilohy[] = [$pdf, 'Cedulka-pro-sousedy-' . $dt[3] . '-' . $dt[2] . '-' . $dt[1] . '.pdf'];
    }
    $stara = 'Cedulku, kterou můžete vyvěsit na dveře nebo do chodby, najdete na stránce s přípravou.';
    $nova = 'Cedulku pro sousedy s datem a časem posíláme v příloze (PDF). Stačí ji vytisknout a vyvěsit na dveře nebo do chodby. Omluví Vás za nás.';
    $prach = ['Zakryjte nábytek a věci v místnosti u dveří. Stará prostěradla nebo malířská fólie poslouží skvěle.',
      'Co jde, odneste o místnost dál: oblečení z věšáku, boty, obrazy, elektroniku.',
      'Zavřete dveře do ostatních pokojů, ať se prach nerozleze po bytě.'];
    $omluva = 'Omlouváme se, bez prachu a hluku to zatím neumíme. Pracujeme na tom. Po montáži po sobě uklidíme.';
    $telo = ($pdf ? str_replace($stara, $nova, $telo) : $telo);
    $telo = str_replace("\n\nJAK SE PŘIPRAVIT", "\n\nPOZOR, BUDE SE PRÁŠIT\nVýměna dveří se bez bourání neobejde a jemný prach se dostane i tam, kam by ho nikdo nezval.\n- " . implode("\n- ", $prach) . "\n" . $omluva . "\n\nJAK SE PŘIPRAVIT", $telo);
    $blok = mailh_ramecek('Pozor, bude se prášit',
        '<div style="margin-bottom:6px">Výměna dveří se bez bourání neobejde a jemný prach se dostane i tam, kam by ho nikdo nezval. Proto prosíme:</div>'
        . '<ul style="margin:0 0 8px 18px;padding:0">' . implode('', array_map(fn($x) => '<li style="margin:0 0 5px">' . mailh_h($x) . '</li>', $prach)) . '</ul>'
        . '<div><b>' . mailh_h($omluva) . '</b></div>')
      . mailh_video($web, 'buldok-pri-montazi', 'Krátké video: buldoček v přilbě a ochranných brýlích sedí na zakrytém gauči při montáži dveří', '<b>Takhle vypadá dobře připravený byt.</b> Gauč má prostěradlo a stavbyvedoucí brýle. Brýle pro psa nejsou podmínkou.');
    $h = $htmlTelo;
    if ($h !== '') {
      if ($pdf) $h = str_replace(mailh_h($stara), mailh_h($nova), $h);
      $kotva = '<div style="font-weight:bold;margin-top:16px">Jak se připravit</div>';
      $h = strpos($h, $kotva) !== false ? str_replace($kotva, '<div style="margin-top:16px">' . $blok . '</div>' . '<div style="font-weight:bold;margin-top:4px">Jak se připravit</div>', $h) : $h . $blok;
      $h = preg_replace('~<p>Dobrý den,</p>~u', '<p style="margin:0 0 12px">Dobrý den,</p>', $h, 1);
    } else $h = mailh_text_html($telo);
    $htmlTelo = mailh_sablona($zmena ? 'Změna termínu' : 'Termín montáže', $zmena ? 'Montáž Vašich dveří má nový termín' : 'Montáž Vašich dveří je domluvená', $h);
    return;
  }

  // 3) ostatní e-maily zákazníkům: jednotná šablona (hotový HTML dokument se nemění)
  if ($htmlTelo !== '' && stripos($htmlTelo, '<html') !== false) return;
  $nadpis = trim(preg_replace('~\s+[–-]\s+Bezpečné dveře\s*$~u', '', $predmet));
  $htmlTelo = mailh_sablona('Bezpečné dveře', $nadpis !== '' ? $nadpis : 'Zpráva od nás', $htmlTelo !== '' ? $htmlTelo : mailh_text_html($telo));
}
