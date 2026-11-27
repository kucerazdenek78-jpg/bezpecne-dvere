<?php
// Úkoly modulu Servis oken pro úvodní box kartotéky (box „📞 Zákazníci čekají na naši reakci“).
// Čte okna.sqlite (ok_navrh přes data/okna-app/navrh.php): objednané zmapování závady + žádosti
// o telefonát, dokud je někdo neoznačí jako vyřízené (✓ Vyřízeno → ok_navrh.vyrizeno). Volá lib/terminy.php.
function okna_ukoly_db(): ?PDO {
  global $DATA; $d = ($DATA ?? '') !== '' ? $DATA : '/data';
  $np = $d . '/okna-app/navrh.php'; if (!is_file($np)) return null;
  require_once $np;
  if (!function_exists('ok_navrh_ukoly')) return null;
  try { $p = new PDO('sqlite:' . $d . '/okna.sqlite'); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $p->exec('PRAGMA busy_timeout=5000'); return $p; }
  catch (Throwable $e) { return null; }
}
// POST „✓ Vyřízeno“ u okna úkolu (term_akce=okna_hotovo). Vrací hlášku nebo ''.
function okna_ukoly_akce(string $kdo = ''): string {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['term_akce'] ?? '') !== 'okna_hotovo') return '';
  $id = (int)($_POST['id'] ?? 0); if ($id <= 0) return '';
  $p = okna_ukoly_db(); if (!$p) return '';
  try { ok_navrh_ukol_vyrizeno($p, $id, $kdo); } catch (Throwable $e) { return ''; }
  return 'Úkol servisu oken označen jako vyřízený.';
}
// HTML řádky do boxu (stejné třídy jako terminy.php). $h = escaper, $tl = generátor tlačítka (id, akce, text).
function okna_ukoly_html(callable $h, callable $tl): array {
  $p = okna_ukoly_db(); if (!$p) return [];
  try { $ukoly = ok_navrh_ukoly($p); } catch (Throwable $e) { return []; }
  $out = [];
  foreach ($ukoly as $r) {
    $zm = ($r['druh'] ?? '') === 'zmapovani'; $zav = ($r['stav'] ?? '') === 'zavolat';
    $kc = $zm ? 'zmapování závady' : (($r['druh'] ?? '') === 'vyuctovani' ? 'vyúčtování' : (($r['druh'] ?? '') === 'zjisteni' ? 'zjištění závady' : 'návrhu oprav'));
    $stitek = $zav ? '📞 žádá o telefonát k ' . $kc : '🔧 objednáno zmapování závady – spojte se a zmapujte';
    $tel = (string)($r['reakce_tel'] ?? ''); $kdy = $r['reakce_dne'] ? date('j. n. H:i', strtotime((string)$r['reakce_dne'])) : '';
    $out[] = '<div class="or"><a href="okna/svj.php?svj=' . (int)$r['svj_id'] . '#navrhy"><b>Servis oken</b> ' . $h($r['nazev']) . '</a> <span>' . $stitek
      . ($tel !== '' ? ' · ☎ <a href="tel:' . $h(preg_replace('/[^\d+]/', '', $tel)) . '">' . $h($tel) . '</a>' : '')
      . ((string)($r['reakce_pozn'] ?? '') !== '' ? ' · „' . $h(mb_substr((string)$r['reakce_pozn'], 0, 160)) . '“' : '')
      . ($kdy !== '' ? ' · ' . $h($kdy) : '') . '</span><div class="bdt-t">' . $tl($r['id'], 'okna_hotovo', '✓ Vyřízeno') . '</div></div>';
  }
  return $out;
}
