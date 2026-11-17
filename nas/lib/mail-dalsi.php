<?php
// Další veselé HTML e-maily zákazníkům (17. 11. 2026, přání Zdeňka): poděkování za poptávku, připomínky nabídky, na kterou nikdo nereaguje,
// připomínky servisu a záruky a kopie předávacího listu (animace s buldočkem).
// Volá mailh_uprav (lib/mail-html.php) před obecnou šablonou; vrací true, když e-mail poznala a upravila.
// Texty připomínek zůstávají z lib/terminy.php, tady se jen převedou do HTML: odkazy „• text: adresa“ na tlačítka + animace.
// Patří na NAS do /docker/formulare/app/lib/mail-dalsi.php.
require_once __DIR__ . '/mail-html.php';

function mailh_dalsi(array $cfg, string &$predmet, string &$telo, string &$htmlTelo): bool {
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');

  // 1) poděkování za poptávku (odeslat.php i poptávky z e-mailu)
  if (preg_match('~^Děkujeme za poptávku(?: č\. (.+))?$~u', $predmet, $m)) {
    $cislo = trim((string)($m[1] ?? ''));
    $vtip = 'Takhle to u nás vypadá, když přijde nová poptávka. Konfety jsou trochu přehnané, radost je pravá.';
    // shrnutí poptávky: řádky „Popisek: hodnota“ za nadpisem SHRNUTÍ VAŠÍ POPTÁVKY (přidává odeslat.php) → tabulka; interní řádky a odkazy pro prodejce se vynechají
    $cast = preg_split('~\n+SHRNUTÍ VAŠÍ POPTÁVKY\n~u', str_replace("\r", '', $telo), 2); $uvod = $cast[0]; $tab = '';
    if (isset($cast[1])) {
      $radky = [];
      foreach (explode("\n", $cast[1]) as $l) {
        if (preg_match('/^([^:\n]{1,60}): ?(.*)$/u', $l, $x) && !preg_match('~^https?$~', $x[1])) $radky[] = [trim($x[1]), trim($x[2])];
        elseif ($radky && trim($l) !== '') $radky[count($radky) - 1][1] .= "\n" . $l;
      }
      $n = 0; $ven = [];
      foreach ($radky as [$k, $v]) {
        if ($v === '' || $n >= 30 || preg_match('~^(zdroj|souhlas|vytvořit|odkaz|interní|ip|kartot|systém|poznámka systému)~iu', $k) || preg_match('~kartoteka\.dvere\.app|nabidka-nova\.html|objednavka-(nova|vice)\.html|#[dk]=~u', $v)) { $ven[] = $k; continue; }
        $n++; $tab .= '<tr><td valign="top" style="padding:5px 12px 5px 0;color:#4f6a5b;font-size:14px;width:38%">' . mailh_h($k) . '</td><td valign="top" style="padding:5px 0;font-size:14.5px">' . nl2br(mailh_h(mb_substr($v, 0, 600))) . '</td></tr>';
      }
      if ($tab !== '') $tab = '<div style="font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#c2410c;font-weight:bold;margin:4px 0 6px">Co jste nám poslali</div><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border-top:1px solid #e3ebe6">' . $tab . '</table>';
      if ($ven) $telo = $uvod . "\n\nSHRNUTÍ VAŠÍ POPTÁVKY\n" . implode("\n", array_map(fn($r) => $r[0] . ': ' . $r[1], array_filter($radky, fn($r) => !in_array($r[0], $ven, true))));
    }
    $htmlTelo = mailh_sablona($cislo !== '' ? "Poptávka č. $cislo" : 'Vaše poptávka', 'Poptávka dorazila. Už se na ni vrháme!',
      mailh_text_html($uvod)
      . mailh_video($web, 'buldok-poptavka', 'Krátké video: buldoček u notebooku ve vzorkovně slaví novou poptávku', '<b>Takhle to u nás vypadá, když přijde nová poptávka.</b> Konfety jsou trochu přehnané, radost je pravá.')
      . mailh_ramecek('Co bude dál', '<div><b>1.</b> Projdeme si, co jste nám napsali.</div><div><b>2.</b> Ozveme se Vám na telefon nebo e-mail.</div><div><b>3.</b> Připravíme Vám nabídku na míru.</div>')
      . $tab);
    $telo .= "\n\n" . $vtip . "\nKrátké video: $web/userFiles/email/buldok-poptavka.mp4";
    return true;
  }

  // 2) připomínky nabídky bez reakce (lib/terminy.php: 1. po pár dnech, 2. před koncem platnosti, 3. poslední zpráva)
  if (preg_match('~^Nabídka č\. (.+?): (dveře se ptají|platnost ceny končí|poslední zpráva)~u', $predmet, $m)) {
    $cislo = $m[1]; $kolo = $m[2] === 'dveře se ptají' ? 1 : ($m[2] === 'platnost ceny končí' ? 2 : 3);
    $t = str_replace("\r", '', $telo); $tl = [];
    $t = preg_replace_callback('~^• (.+?): (https?://\S+)[ \t]*\n?~mu', function ($x) use (&$tl) { $tl[] = [$x[1], $x[2]]; return ''; }, $t);
    $hodn = ''; $hodnTxt = '';
    if (preg_match('~\n*(A pokud máte půl minuty[^\n]*?)\s*\(1 až 5\):\s*\n(https?://\S+)~u', $t, $h)) { $hodnTxt = $h[1]; $hodn = $h[2]; $t = str_replace($h[0], '', $t); }
    $t = preg_replace('~\n*Stačí jedno kliknutí[^\n]*:\s*(?=\n|$)~u', "\n", $t);
    $b = fn($url, $txt, $bg) => '<div style="margin:0 0 8px"><a href="' . mailh_h($url) . '" style="display:inline-block;background:' . $bg . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:10px 18px;border-radius:22px">' . mailh_h($txt) . '</a></div>';
    $klik = '';
    foreach ($tl as [$txt, $url]) $klik .= $b($url, $txt, strpos($url, 'a=nezajem') !== false ? '#6b7c71' : (strpos($url, 'a=zavolat') !== false ? '#1d5fb8' : '#e8650a'));
    if ($hodn !== '') $klik .= '<div style="margin:10px 0 6px;font-size:14px;color:#33463a">' . mailh_h($hodnTxt) . ' (1 až 5):</div>' . $b($hodn, 'Ohodnotit nabídku', '#123d27');
    $o = mailh_text_html(trim($t));
    if ($kolo < 3) $o .= mailh_video($web, 'buldok-ceka', 'Krátké video: buldoček leží u telefonu, který nezvoní', '<b>Takhle u nás Vaše nabídka čeká na zprávu.</b> Telefon mlčí, pes vzdychá. Stačí pár slov a oba ožijí.');
    if ($klik !== '') $o .= mailh_ramecek('Stačí jedno kliknutí', $klik);
    $htmlTelo = mailh_sablona("Nabídka č. $cislo", $kolo === 1 ? 'Dorazila k Vám naše nabídka?' : ($kolo === 2 ? 'Platnost ceny se chýlí ke konci' : 'Poslední zpráva, slibujeme'), $o);
    return true;
  }
  // 3) připomínky servisu a záruky (nabidky.php, udrzba-pripominky.php) a kopie předávacího listu po podpisu na displeji (posli_predani_kopii):
  //    text e-mailu beze změny, jen šablona + animace s buldočkem pod úvodním odstavcem. PDF v příloze se nemění.
  $bul = [
    ['~^Prodloužení záruky na Vaše dveře – objednávka (.+)$~u', 'Záruku můžete ještě prodloužit', 'buldok-zaruka', 'Krátké video: buldoček v přilbě a s medailí hlídá dveře',
      '<b>Hlídač záruky je ve službě.</b> Medaili už má, teď hlídá i termín. Prodloužit záruku jde jen do data uvedeného výše.'],
    ['~^Roční kontrola požárního uzávěru – objednávka (.+)$~u', 'Rok utekl. Čas na kontrolu dveří', 'buldok-servis', 'Krátké video: buldoček v přilbě se šroubovákem a kufříkem nářadí u dveří',
      '<b>Servisní technik je připraven.</b> Šroubovák má, přilbu taky. Samotnou kontrolu raději necháme na dvounohých kolezích.'],
    ['~^Čas na údržbu dveří – objednávka (.+)$~u', 'Čas na údržbu dveří', 'buldok-servis', 'Krátké video: buldoček v přilbě se šroubovákem a kufříkem nářadí u dveří',
      '<b>Náš technik už si šroubovák nachystal.</b> Vám bude stačit pár minut, hadřík a trocha maziva.'],
    ['~^Předávací a záruční list – objednávka (.+)$~u', 'Dveře jsou Vaše. Ať dobře slouží!', 'buldok-predani', 'Krátké video: buldoček v přilbě přináší klíče od nových dveří',
      '<b>Klíče předány.</b> Náš vedoucí předávky je pouštěl z tlamy jen nerad. Dveře jsou teď Vaše.'],
  ];
  foreach ($bul as [$re, $nadpis, $soubor, $alt, $popis]) {
    if (!preg_match($re, $predmet, $m)) continue;
    if ($htmlTelo !== '') return false;                                       // hotové HTML se nemění
    $bloky = preg_split('/\n{2,}/', trim(preg_replace('/^[ \t]+•/mu', '•', str_replace("\r", '', $telo))));
    $n = (count($bloky) > 2 && preg_match('~^Dobrý den~u', $bloky[0])) ? 2 : 1;
    if (count($bloky) > $n + 1 && substr(rtrim($bloky[$n - 1]), -1) === ':') $n++;   // úvod končí dvojtečkou → výčet patří k němu
    $htmlTelo = mailh_sablona('Objednávka č. ' . trim($m[1]), $nadpis,
      mailh_text_html(implode("\n\n", array_slice($bloky, 0, $n))) . mailh_video($web, $soubor, $alt, $popis) . mailh_text_html(implode("\n\n", array_slice($bloky, $n))));
    return true;
  }
  return false;
}
