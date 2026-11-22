<?php
// Jednorázový pomocník (22. 11. 2026): stáhne reel „Jak to u nás chodí“ z našeho webu do reklamní sekce kartotéky
// (/data/reklama/06-jak-to-u-nas-chodi/). Zdroj i cíl jsou pevně dané, soubor se ověří otiskem sha256.
// Opakované spuštění nic nepokazí. Po použití lze smazat.
header('Content-Type: text/plain; charset=utf-8'); header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$URL = 'https://bezpecne-dvere.cz/userFiles/reklama/reel-jak-to-u-nas-chodi.mp4';
$SHA = '0395e39633aaad53cfc42c81f9ef54377b7bc7c8eb9ff5a7ca7e376b01c286b9';
$DIR = '/data/reklama/06-jak-to-u-nas-chodi';
$CIL = $DIR . '/reel-jak-to-u-nas-chodi.mp4';
if (!is_dir('/data/reklama')) exit("CHYBA: slozka /data/reklama neexistuje\n");
if (!is_dir($DIR) && !@mkdir($DIR, 0775, true)) exit("CHYBA: nejde zalozit slozku\n");
if (is_file($CIL) && hash_file('sha256', $CIL) === $SHA) { echo "Reel uz na miste je.\n"; }
else {
  $tmp = $CIL . '.cast';
  $fh = fopen($tmp, 'wb'); if (!$fh) exit("CHYBA: nejde zapisovat do slozky\n");
  $ok = false; $info = '';
  if (function_exists('curl_init')) {
    $c = curl_init($URL);
    curl_setopt_array($c, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 120, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
    $ok = curl_exec($c); $info = 'HTTP ' . curl_getinfo($c, CURLINFO_HTTP_CODE) . ' ' . curl_error($c); curl_close($c);
  } else {
    $src = @fopen($URL, 'rb'); if ($src) { $ok = stream_copy_to_stream($src, $fh) > 0; fclose($src); } $info = 'fopen';
  }
  fclose($fh);
  if (!$ok || hash_file('sha256', $tmp) !== $SHA) { $v = @filesize($tmp); @unlink($tmp); exit("CHYBA: stazeni se nepovedlo ($info, $v B)\n"); }
  rename($tmp, $CIL); echo "Reel stazen.\n";
}
file_put_contents($DIR . '/_popis.txt', "Reel „Jak to u nás chodí“ (Falko)\nKrátké video 9:16 (57 s, 1080×1920, s hudbou): od poptávky přes nabídku, objednávku, termín montáže a předání s podpisem na displeji až po aplikaci Moje dveře a servis. Údaje v ukázkách ze systému jsou smyšlené. Hodí se na Facebook / Instagram Reels, Stories i na web.");
file_put_contents($DIR . '/_soubory.txt', "reel-jak-to-u-nas-chodi.mp4 | Reel 9:16, 57 s, s akční hudbou – celé video\n");
echo "Velikost: " . filesize($CIL) . " B\nSlozky v reklamni sekci:\n";
foreach (scandir('/data/reklama') as $s) if ($s[0] !== '.') echo " - $s\n";
