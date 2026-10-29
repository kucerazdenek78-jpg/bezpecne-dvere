<?php
// Podpis e-mailů zákazníkům: jméno obchodníka (přihlášený v kartotéce / vybraný ve formuláři), malé logo a firemní údaje.
// Používá odeslat.php (nabídky a objednávky zákazníkům) a lib/posta.php (vše, co odchází z kartotéky zákazníkům).
// Interní e-maily firmě (poptávky, kopie objednávek) podpis nedostávají.
// Logo: https://bezpecne-dvere.cz/userFiles/email/logo-podpis.png (z logo.jpg, 360 px, zobrazené 150 px).
// Patří na NAS do /docker/formulare/app/lib/podpis.php.

function podpis_jmeno(string $jmeno): string {
  $j = trim(preg_replace('/\s+/u', ' ', $jmeno));
  if ($j === '' && function_exists('uziv_jmeno')) $j = trim((string)uziv_jmeno());   // přihlášený uživatel kartotéky
  return mb_substr(strip_tags($j), 0, 60);
}

function podpis_text(string $jmeno = ''): string {
  $j = podpis_jmeno($jmeno);
  return "\n\n--\n" . ($j !== '' ? $j . "\n" : '')
    . "BEZPEČNÉ DVEŘE CZ, KKSYSTEM\nPobočka: Most, Obchodní 36\nSklady: Most, Obchodní 36\nE-shop: obchod.bezpecne-dvere.cz\n\n"
    . "INFOLINKA:\n+420 725 559 235\n+420 471 008 994\ninfo@kksystem.cz\nchci@bezpecne-dvere.cz\nwww.bezpecne-dvere.cz";
}

function podpis_html(string $jmeno = '', string $web = 'https://bezpecne-dvere.cz'): string {
  $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $j = podpis_jmeno($jmeno); $web = rtrim($web, '/');
  $a = fn($href, $txt) => '<a href="' . $h($href) . '" style="color:#1d5fb8;text-decoration:underline">' . $h($txt) . '</a>';
  return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;border-top:1px solid #d6dde6;padding-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45;color:#1d2b22"><tr>'
    . '<td valign="top" style="padding:14px 16px 0 0"><a href="' . $h($web) . '"><img src="' . $h($web) . '/userFiles/email/logo-podpis.png" width="150" alt="Bezpečné dveře" style="display:block;border:0;width:150px;height:auto;border-radius:6px"></a></td>'
    . '<td valign="top" style="padding-top:14px">'
    . ($j !== '' ? '<div style="font-size:15px;font-weight:bold;margin-bottom:4px">' . $h($j) . '</div>' : '')
    . '<div style="font-weight:bold">BEZPEČNÉ DVEŘE CZ, KKSYSTEM</div>'
    . '<div><b>Pobočka:</b> Most, Obchodní 36</div><div><b>Sklady:</b> Most, Obchodní 36</div>'
    . '<div><b>E-shop:</b> ' . $a('https://obchod.bezpecne-dvere.cz', 'obchod.bezpecne-dvere.cz') . '</div>'
    . '<div style="margin-top:8px;font-weight:bold">INFOLINKA:</div>'
    . '<div>' . $a('tel:+420725559235', '+420 725 559 235') . '</div><div>' . $a('tel:+420471008994', '+420 471 008 994') . '</div>'
    . '<div>' . $a('mailto:info@kksystem.cz', 'info@kksystem.cz') . '</div><div>' . $a('mailto:chci@bezpecne-dvere.cz', 'chci@bezpecne-dvere.cz') . '</div>'
    . '<div style="font-weight:bold">' . $a($web, 'www.bezpecne-dvere.cz') . '</div>'
    . '</td></tr></table>';
}

// Je e-mail určený zákazníkovi? (aspoň jeden příjemce není firemní adresa z nastavení příjemců formulářů)
function podpis_pro_zakaznika(array $cfg, array $to): bool {
  $firma = [];
  foreach ((array)($cfg['prijemci'] ?? []) as $p) foreach ((array)($p['to'] ?? []) as $a) $firma[] = strtolower(trim((string)$a));
  foreach ((array)($cfg['prijemci'] ?? []) as $p) foreach ((array)($p['cc'] ?? []) as $a) $firma[] = strtolower(trim((string)$a));
  $firma[] = strtolower((string)($cfg['from'] ?? ''));
  foreach ($to as $a) if (!in_array(strtolower(trim((string)$a)), $firma, true)) return true;
  return false;
}

// Odstraní starý jednoduchý podpis „Bezpečné dveře · KK SYSTEM …“ z konce textu (nový podpis ho nahrazuje).
function podpis_ocisti(string $text): string {
  return preg_replace('/\n+Bezpečné dveře · KK SYSTEM(\n[^\n]*){0,2}\s*$/u', '', rtrim($text));
}
// Vloží HTML podpis do celého HTML dokumentu (před </body>) nebo ho připojí na konec fragmentu.
function podpis_do_html(string $html, string $jmeno, string $web): string {
  $p = podpis_html($jmeno, $web);
  return stripos($html, '</body>') !== false ? preg_replace('~</body>~i', '<div style="max-width:600px;margin:0 auto;padding:0 10px 20px">' . $p . '</div></body>', $html, 1) : $html . $p;
}
