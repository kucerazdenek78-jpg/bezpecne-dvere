<?php
// Odeslání e-mailu z kartotéky (stejné nastavení jako odeslat.php). Vrací true/false.
// E-mail zákazníkovi (aspoň jeden příjemce není firemní adresa) dostane podpis: jméno obchodníka ($podpisJmeno, jinak přihlášený
// uživatel kartotéky), malé logo a firemní údaje (lib/podpis.php). Interní e-maily firmě jsou bez podpisu.
function posli_mail(array $cfg, string $DATA, array $to, array $cc, string $reply, string $predmet, string $telo, array $prilohy = [], string $htmlTelo = '', string $podpisJmeno = ''): bool {
  try {
    $sPodpisem = false;
    if (is_file(__DIR__ . '/podpis.php')) { require_once __DIR__ . '/podpis.php'; $sPodpisem = podpis_pro_zakaznika($cfg, $to); }
    if ($sPodpisem) $telo = podpis_ocisti($telo);
    $telo_html = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1">$1</a>', htmlspecialchars($telo, ENT_QUOTES, 'UTF-8'));
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;white-space:pre-wrap">' . $telo_html . '</div>';
    if ($sPodpisem) {
      $web = (string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz');
      $html .= podpis_html($podpisJmeno, $web);
      if ($htmlTelo !== '') $htmlTelo = podpis_do_html($htmlTelo, $podpisJmeno, $web);
      $telo .= podpis_text($podpisJmeno);
    }
    if (($cfg['mail_rezim'] ?? 'smtp') === 'soubor') { // zkušební režim: nic se neposílá
      @mkdir($DATA . '/odeslano-test', 0775, true);
      file_put_contents($DATA . '/odeslano-test/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json',
        json_encode(['to' => $to, 'cc' => $cc, 'reply_to' => $reply, 'predmet' => $predmet, 'telo' => $telo, 'prilohy' => array_map(fn($x) => $x[1], $prilohy)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
      return true;
    }
    require_once '/var/www/vendor/autoload.php';
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    $m->CharSet = 'UTF-8'; $m->isSMTP();
    $m->Host = $cfg['smtp_host']; $m->Port = (int)$cfg['smtp_port']; $m->Timeout = 25;
    $m->SMTPAuth = ($cfg['smtp_user'] ?? '') !== '';
    $m->Username = $cfg['smtp_user'] ?? ''; $m->Password = $cfg['smtp_pass'] ?? '';
    $m->SMTPSecure = ($cfg['smtp_secure'] ?? 'tls') === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $m->setFrom($cfg['from'], 'Bezpečné dveře');
    foreach ($to as $a) $m->addAddress($a);
    foreach ($cc as $a) $m->addCC($a);
    $m->addReplyTo($reply);
    foreach ($prilohy as $x) $m->addAttachment($x[0], $x[1]);
    $m->isHTML(true); $m->Subject = $predmet; $m->Body = $htmlTelo !== '' ? $htmlTelo : $html; $m->AltBody = $telo;
    $m->send();
    return true;
  } catch (Throwable $e) { error_log('E-mail selhal (' . $predmet . '): ' . $e->getMessage()); return false; }
}

// Potvrzení přijaté zálohy zákazníkovi (použito při automatickém spárování platby z banky).
function posli_potvrzeni_zalohy(array $cfg, string $DATA, string $email, string $cislo, float $castka, string $dne): bool {
  $telo = "Dobrý den,\n\nděkujeme, evidujeme přijetí zálohy (rezervačního poplatku) ve výši " . number_format($castka, 0, ',', ' ') . " Kč k objednávce č. $cislo, připsané " . date('j. n. Y', strtotime($dne)) . ".\n"
    . "Vaše objednávka bezpečnostních dveří je tímto potvrzena a bude dále zpracovávána. O termínu montáže Vás budeme informovat.\n\n"
    . "Děkujeme za důvěru.\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
  return posli_mail($cfg, $DATA, [$email], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], "Potvrzení přijaté zálohy – objednávka $cislo", $telo);
}

// Potvrzení zaplaceného prodloužení záruky zákazníkovi.
function posli_potvrzeni_prodlouzeni(array $cfg, string $DATA, string $email, string $cislo, int $let, string $do): bool {
  $telo = "Dobrý den,\n\nděkujeme, platbu za prodloužení záruky k objednávce č. $cislo jsme přijali. Záruka na Vaše dveře je nyní prodloužena na $let let, tedy do " . date('j. n. Y', strtotime($do)) . ".\n\n"
    . "Záruční podmínky a stav záruky kdykoli najdete na stránce Moje dveře (QR kód na předávacím listu).\n\nDěkujeme za důvěru.\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
  return posli_mail($cfg, $DATA, [$email], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], "Záruka prodloužena – objednávka $cislo", $telo);
}

// Kopie podepsaného předávacího listu zákazníkovi (PDF v příloze).
function posli_predani_kopii(array $cfg, string $DATA, string $email, string $cislo, string $pdfCesta): bool {
  $telo = "Dobrý den,\n\nděkujeme za Vaši důvěru. V příloze zasíláme kopii předávacího a záručního listu k objednávce č. $cislo.\n"
    . "Na listu najdete i QR kód ke stránce Vašich dveří (záruka, servis, prodloužení záruky). Prosíme, list si uschovejte.\n\n"
    . "Kdybyste cokoli potřebovali, ozvěte se: +420 725 559 235, chci@bezpecne-dvere.cz\n\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
  return posli_mail($cfg, $DATA, [$email], $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [], $cfg['from'], "Předávací a záruční list – objednávka $cislo", $telo, [[$pdfCesta, 'predavaci-list-' . preg_replace('/[^A-Za-z0-9_-]+/', '', $cislo) . '.pdf']]);
}

// Poděkování po předání dveří: HTML e-mail (grafika jako web) + prostý text jako záloha
function podekovani_html(string $web, string $cislo, string $token): string {
  $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  $img = $web . '/userFiles/email/';
  $app = $web . '/aplikace/#moje?t=' . rawurlencode($token);   // odkaz otevře aplikaci a dveře rovnou spáruje
  $navod = $web . '/udrzba-dveri.html';
  $google = 'https://search.google.com/local/writereview?placeid=ChIJESw78t4hCkcRR1ygmiDY8ac';
  $fb = 'https://www.facebook.com/BezpecneDvere';
  $btn = fn($url, $txt, $bg) => '<a href="' . $h($url) . '" style="display:inline-block;background:' . $bg . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 22px;border-radius:24px">' . $txt . '</a>';
  $radek = fn($ikona, $nadpis, $popis, $url, $akce, $bg) => '<tr><td style="padding:0 28px 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f7f4;border-radius:12px"><tr>'
    . '<td width="56" style="padding:14px 0 14px 16px;font-size:28px;line-height:32px" valign="top">' . $ikona . '</td>'
    . '<td style="padding:14px 12px" valign="top"><div style="font-size:16px;font-weight:bold;color:#123d27">' . $nadpis . '</div><div style="font-size:14px;color:#4b5d52;line-height:1.45;padding-top:2px">' . $popis . '</div></td>'
    . '<td style="padding:14px 16px 14px 0;white-space:nowrap" valign="middle" align="right"><a href="' . $h($url) . '" style="color:' . $bg . ';font-weight:bold;font-size:14px;text-decoration:none">' . $akce . ' &rsaquo;</a></td></tr></table></td></tr>';
  $o = '<!DOCTYPE html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Děkujeme</title></head>'
    . '<body style="margin:0;padding:0;background:#e9efea;font-family:Arial,Helvetica,sans-serif;color:#1d2b22">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9efea"><tr><td align="center" style="padding:20px 10px">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:14px;overflow:hidden">'
    // hlavička
    . '<tr><td align="center" style="background:#123d27;padding:22px 20px 18px"><a href="' . $h($web) . '"><img src="' . $h($img) . 'logo-bila.png" width="220" alt="Bezpečné dveře" style="display:block;border:0;width:220px;max-width:100%;height:auto"></a></td></tr>'
    . '<tr><td style="background:#e8650a;height:4px;line-height:4px;font-size:0">&nbsp;</td></tr>'
    // úvod
    . '<tr><td style="padding:28px 28px 8px"><div style="font-size:26px;line-height:1.2;font-weight:bold;color:#123d27">Děkujeme za důvěru!</div>'
    . '<p style="font-size:16px;line-height:1.55;margin:12px 0 0;color:#2c3d33">Vaše nové dveře z objednávky č. ' . $h($cislo) . ' jsou předané a my doufáme, že Vám budou dobře sloužit. Aby všechno hladce fungovalo i v dalších letech, přinášíme pár užitečných odkazů.</p></td></tr>'
    // aplikace
    . '<tr><td style="padding:20px 28px 0"><a href="' . $h($app) . '"><img src="' . $h($img) . 'aplikace-banner.jpg" width="544" alt="Aplikace Moje dveře – vyzkoušejte dveře na fotce, záruka, servis a roční prohlídka" style="display:block;border:0;width:100%;max-width:544px;height:auto;border-radius:12px"></a></td></tr>'
    . '<tr><td style="padding:14px 28px 0"><div style="font-size:19px;font-weight:bold;color:#123d27">Aplikace Moje dveře – zdarma ve Vašem telefonu</div>'
    . '<p style="font-size:15px;line-height:1.5;margin:6px 0 0;color:#2c3d33">Záruka, roční prohlídka, servis i prodloužení záruky na jednom místě. Otevřete ji v telefonu a přidejte si ji na plochu. Dveře se v ní spárují samy.</p></td></tr>'
    . '<tr><td style="padding:14px 28px 4px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
    . '<td width="33%" align="center" style="padding:0 4px"><img src="' . $h($img) . 'moje-dvere-1.jpg" width="170" alt="Přehled dveří" style="display:block;border:0;width:100%;max-width:170px;height:auto;border-radius:10px"></td>'
    . '<td width="33%" align="center" style="padding:0 4px"><img src="' . $h($img) . 'moje-dvere-2.jpg" width="170" alt="Vizualizace dveří" style="display:block;border:0;width:100%;max-width:170px;height:auto;border-radius:10px"></td>'
    . '<td width="33%" align="center" style="padding:0 4px"><img src="' . $h($img) . 'moje-dvere-3.jpg" width="170" alt="Roční prohlídka" style="display:block;border:0;width:100%;max-width:170px;height:auto;border-radius:10px"></td>'
    . '</tr></table></td></tr>'
    . '<tr><td align="center" style="padding:16px 28px 26px">' . $btn($app, 'Otevřít aplikaci Moje dveře', '#c2410c') . '</td></tr>'
    // odkazy
    . $radek('🛠️', 'Návod k užívání a údržbě', 'Jak o dveře pečovat, aby sloužily dlouho.', $navod, 'Zobrazit', '#123d27')
    . $radek('⭐', 'Ohodnoťte nás na Googlu', 'Budeme rádi za Vaše hodnocení na Google Mapách.', $google, 'Ohodnotit', '#c2410c')
    . $radek('📸', 'Pochlubte se na Facebooku', 'Sledujte nás a ukažte fotku svých nových dveří.', $fb, 'Facebook', '#1d4ed8')
    // patička
    . '<tr><td style="padding:14px 28px 26px;border-top:1px solid #e1e8e3"><p style="font-size:14px;line-height:1.55;margin:12px 0 0;color:#4b5d52">Kdybyste cokoli potřebovali, ozvěte se: <a href="tel:+420725559235" style="color:#123d27;font-weight:bold;text-decoration:none">+420 725 559 235</a>, <a href="mailto:chci@bezpecne-dvere.cz" style="color:#123d27;font-weight:bold">chci@bezpecne-dvere.cz</a></p>'
    . '<p style="font-size:13px;line-height:1.5;margin:10px 0 0;color:#6b7c71">Bezpečné dveře · KK SYSTEM · Obchodní 36, Most<br><a href="' . $h($web) . '" style="color:#6b7c71">www.bezpecne-dvere.cz</a></p></td></tr>'
    . '</table></td></tr></table></body></html>';
  return $o;
}

// Poděkování po předání dveří (odkazy: aplikace, stránka dveří, návod k údržbě, hodnocení na Google, Facebook)
function posli_podekovani(array $cfg, string $DATA, string $email, string $cislo, string $token): bool {
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  $telo = "Dobrý den,\n\nděkujeme, že jste si dveře koupili u nás (objednávka č. $cislo). Doufáme, že Vám budou dobře sloužit.\n\n"
    . "Co se Vám bude hodit:\n\n"
    . "• Aplikace Moje dveře (záruka, servis, roční prohlídka) – otevřete v telefonu a přidejte si ji na plochu (dveře se spárují samy):\n$web/aplikace/#moje?t=$token\n\n"
    . "• Návod k užívání a údržbě dveří:\n$web/udrzba-dveri.html\n\n"
    . "• Budeme rádi za Vaše hodnocení na Google Mapách:\nhttps://search.google.com/local/writereview?placeid=ChIJESw78t4hCkcRR1ygmiDY8ac\n\n"
    . "• Sledujte nás na Facebooku a pochlubte se fotkou nových dveří:\nhttps://www.facebook.com/BezpecneDvere\n\n"
    . "Kdybyste cokoli potřebovali, ozvěte se: +420 725 559 235, chci@bezpecne-dvere.cz\n\nBezpečné dveře · KK SYSTEM\nwww.bezpecne-dvere.cz";
  return posli_mail($cfg, $DATA, [$email], [], $cfg['from'], "Děkujeme – objednávka $cislo", $telo, [], podekovani_html($web, $cislo, $token));
}
