<?php
// CLI: e-mailové připomínky pravidelné údržby dveří (každých 6 měsíců od předání / od poslední potvrzené údržby).
// Patří na NAS do /docker/formulare/app/ (vedle nabidky.php), knihovna do app/lib/udrzba.php.
// Spuštění (plánovač jednou denně):  docker exec formulare-web-1 php /var/www/html/udrzba-pripominky.php
// Zkouška bez odeslání:               docker exec formulare-web-1 php /var/www/html/udrzba-pripominky.php --suchy [--dnes=2027-05-10]
// Pošle jen jednou na každý termín (sloupec udrzba_mail_pro), nikdy dřív než od 1. 11. 2026, ne zákazníkům s vypnutými připomínkami.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib/start.php'; require_once __DIR__ . '/lib/posta.php'; require_once __DIR__ . '/lib/udrzba.php';
$suchy = in_array('--suchy', $argv, true);
$dnes = date('Y-m-d');
foreach ($argv as $a) if (preg_match('/^--dnes=(\d{4}-\d{2}-\d{2})$/', $a, $m) && $suchy) $dnes = $m[1];   // jen pro zkoušku: --suchy --dnes=2027-05-10
if ($dnes < '2026-11-01') { echo "Připomínky údržby začínají 1. 11. 2026.\n"; exit; }
$pdo = db(); udrzba_tabulky($pdo);
$st = $pdo->query("SELECT * FROM zadosti WHERE typ IN ('objednavka_interni','objednavka') AND predano_dne IS NOT NULL AND predano_dne<>'' ORDER BY id");
$poslano = 0; $preskoc = 0;
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $z) {
  $d = json_decode((string)$z['data_json'], true) ?: [];
  if (!udrzba_plati($z, $d) || !empty($z['udrzba_vypnuto_dne'])) continue;
  $termin = udrzba_termin($pdo, $z, $dnes);
  if ($termin > $dnes || (string)($z['udrzba_mail_pro'] ?? '') === $termin) continue;
  $em = filter_var(trim((string)($d['email'] ?? '')), FILTER_VALIDATE_EMAIL);
  $cislo = (string)($d['obj_cislo'] ?? $z['cislo']);
  if (!$em) { $preskoc++; echo "bez e-mailu: $cislo\n"; continue; }
  if ($suchy) { echo "[suchy] $cislo → $em (termín $termin)\n"; $poslano++; continue; }
  $ok = posli_mail($cfg, $DATA, [$em], [], $cfg['from'] ?? '', "Čas na údržbu dveří – objednávka $cislo", udrzba_mail_text($z, $d, $termin));
  if ($ok) {
    $pdo->prepare('UPDATE zadosti SET udrzba_mail_pro=? WHERE id=?')->execute([$termin, $z['id']]);
    udrzba_zapis($pdo, (int)$z['id'], 'pripominka', 'e-mail');
    $poslano++; echo "odesláno: $cislo → $em\n";
  } else echo "CHYBA odeslání: $cislo → $em\n";
}
echo ($suchy ? 'Zkouška: odeslalo by se ' : 'Odesláno: ') . "$poslano, bez e-mailu: $preskoc\n";
