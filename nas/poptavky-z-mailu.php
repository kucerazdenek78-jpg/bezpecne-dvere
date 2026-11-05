<?php
// CLI: poptávky přeposlané na poptavky-ai@bezpecne-dvere.cz → poptávka v kartotéce (vyplní AI). Podrobnosti v lib/poptavky-mail.php.
// Plánovač úloh na NAS, např. každých 15 minut:
//   docker exec formulare-web php /var/www/html/poptavky-z-mailu.php          (ostrý běh)
//   docker exec formulare-web php /var/www/html/poptavky-z-mailu.php --suchy  (jen vypíše, co by se založilo; AI nevolá, nic nezapisuje ani neposílá)
// Čte schránku z config.php 'imap' (stejnou jako platby.php), jen zprávy adresované na poptavky-ai@ za poslední 3 dny; zprávy nemaže ani neoznačuje jako přečtené.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found'); }
require __DIR__ . '/lib/start.php'; require __DIR__ . '/lib/banka.php'; require __DIR__ . '/lib/posta.php'; require_once __DIR__ . '/lib/terminy.php';
require_once __DIR__ . '/lib/pomocnik.php'; require_once __DIR__ . '/lib/navrh-nabidky.php'; require_once __DIR__ . '/lib/poptavky-mail.php';
$suchy = in_array('--suchy', $argv ?? [], true);
$log = fn($s) => print(date('Y-m-d H:i:s') . ' ' . ($suchy ? '[suchý] ' : '') . $s . "\n");
$nas = pm_nastaveni($cfg); if (!$nas['zapnuto']) { $log('vypnuto v config.php (poptavky_ai.zapnuto)'); exit; }
$c = $cfg['imap'] ?? null;
if (!$c || empty($c['user']) || empty($c['pass'])) { fwrite(STDERR, "Chybí nastavení imap (host, user, pass) v config.php\n"); exit(1); }
$pdo = db(); pm_tabulka($pdo); terminy_tabulky($pdo);
try {
  $im = new MiniImap($c['host'], (int)($c['port'] ?? 993), $c['secure'] ?? 'ssl', $c['user'], $c['pass']);
  $im->vyber($c['slozka'] ?? 'INBOX');
  $adr = $nas['adresa'];
  $uids = $im->hledej('SINCE ' . date('j-M-Y', time() - 3 * 86400) . ' OR TO "' . $adr . '" CC "' . $adr . '"');
  $uids = array_slice($uids, -60);
  $zn = $pdo->prepare('SELECT stav FROM poptavky_mail WHERE msgid=?'); $nove = 0;
  foreach ($uids as $uid) {
    $raw = $im->zprava($uid); if ($raw === '') continue;
    $mid = mime_id($raw); $zn->execute([$mid]); $byl = $zn->fetchColumn(); if ($byl !== false && $byl !== 'chyba') continue;
    [$h] = pm_rozdel($raw); $predmet = pm_dek_hl(pm_h($h, 'subject'));
    try { $r = pm_zpracuj($pdo, $cfg, $DATA, $raw, $suchy, $log); }
    catch (Throwable $e) { $r = ['stav' => 'chyba', 'poznamka' => $e->getMessage()]; }
    $nove++;
    $log("zpráva „{$predmet}“ od " . pm_adresa(pm_h($h, 'from')) . ": {$r['stav']}" . (!empty($r['poznamka']) ? ' – ' . $r['poznamka'] : ''));
    if ($suchy) { if (!empty($r['telo'])) echo "  ── návrh záznamu ──\n  " . str_replace("\n", "\n  ", mb_substr($r['telo'], 0, 1500)) . "\n"; continue; }
    if (!empty($r['znovu'])) break;   // denní strop – zbytek zítra
    if (in_array($r['stav'], ['zalozeno', 'duplicita'], true)) { try { if (!pm_odpoved($cfg, $DATA, $r, $predmet)) $log('  odpověď prodejci se nepodařilo odeslat'); } catch (Throwable $e) { $log('  odpověď prodejci: ' . $e->getMessage()); } }
    if ($r['stav'] === 'chyba' && pm_adresa(pm_h($h, 'from')) !== '' && in_array(pm_adresa(pm_h($h, 'from')), $nas['povoleni'], true) && $byl === false)
      posli_mail($cfg, $DATA, [pm_adresa(pm_h($h, 'from'))], [], $cfg['from'], 'Poptávku se nepodařilo založit: ' . $predmet, "Z přeposlaného e-mailu („{$predmet}“) se poptávku nepodařilo založit: {$r['poznamka']}\n\nZkusím to znovu při dalším běhu. Když to nepůjde, založte ji prosím ručně.");
    $pdo->prepare('INSERT INTO poptavky_mail (msgid, zpracovano, od, predmet, stav, zadost_id, poznamka) VALUES (?,?,?,?,?,?,?) ON CONFLICT(msgid) DO UPDATE SET zpracovano=excluded.zpracovano, stav=excluded.stav, zadost_id=excluded.zadost_id, poznamka=excluded.poznamka')
      ->execute([$mid, date('Y-m-d H:i:s'), pm_adresa(pm_h($h, 'from')), mb_substr($predmet, 0, 200), $r['stav'], $r['zadost_id'] ?? null, mb_substr((string)($r['poznamka'] ?? ''), 0, 300)]);
  }
  $im->konec();
  if ($nove || $suchy) $log(count($uids) . ' zpráv pro ' . $adr . ', nově zpracováno ' . $nove);
} catch (Throwable $e) { fwrite(STDERR, 'CHYBA: ' . $e->getMessage() . "\n"); exit(2); }
