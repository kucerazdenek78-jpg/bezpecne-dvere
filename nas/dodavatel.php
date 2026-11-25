<?php
// Objednávky dveří, které se NEobjednávají přes náš sklad PREMIUM 60, ale u dodavatele (EXTREME 54 přes B2B Svět dveří, ERKADO, interiérové dveře od Světa dveří nebo ERKADO…).
// Evidence: k objednání → objednáno u dodavatele (dodavatel, číslo, datum objednání, termín dodání) → dodáno k nám (datum). Samotné objednání se dělá ručně u dodavatele.
// Jedna zakázka = jedna objednávka u dodavatele (rozhodl Zdeněk 24. 10. 2026). Stejná ochrana jako kartoteka.php / sklad.php.
// 2. 11. 2026: nahoře odkazy na objednávkové systémy dodavatelů (Tral = objednávkový systém ERKADO, https://tral.cz/login).
// 4. 11. 2026: akceptace objednávky = zaplacená záloha → zakázky bez přijaté zálohy (zadosti.zaloha_prijata_dne) jsou ve všech seznamech
//   výrazně označené „ZÁLOHA NEZAPLACENA“ a před označením za objednané se zobrazí potvrzení.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$pdo = db(); require_once __DIR__ . '/lib/stav.php'; bd_stavy_sync($pdo);
require_once __DIR__ . '/lib/menu.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$sl = array_column($pdo->query('PRAGMA table_info(zadosti)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['dodavatel_ref', 'dodavatel', 'dod_objednano', 'dod_termin', 'dod_dodano'] as $sloupec)
  if (!in_array($sloupec, $sl, true)) $pdo->exec('ALTER TABLE zadosti ADD COLUMN ' . $sloupec . ' TEXT');

// známí dodavatelé (nabídka v políčku; jde napsat i jiný). Výchozí dodavatel podle modelu / druhu dveří – doplnit, až budou známé (MAGNUM 56, SECURIDO 65).
$DODAVATELE = ['ERKADO', 'Svět dveří (B2B)'];
$VYCHOZI_MODEL = ['extreme54' => 'Svět dveří (B2B)'];
// odkazy na objednávkové systémy dodavatelů (otevírají se v novém okně)
$ODKAZY_DOD = [['https://tral.cz/login', '🔗 Tral – objednávkový systém ERKADO'], ['https://konfigurator.svetdveri.sk/', '🔗 Konfigurátor Svět dveří']];
function dd_vychozi(array $d, array $VYCHOZI_MODEL): string {
  $kod = (string)($d['model_kod'] ?? '');
  if (isset($VYCHOZI_MODEL[$kod])) return $VYCHOZI_MODEL[$kod];
  if (($d['kategorie'] ?? '') === 'dum' || stripos((string)($d['model'] ?? ''), 'erkado') !== false) return 'ERKADO';
  return '';   // interiérové dveře: Svět dveří nebo ERKADO – vybere se při objednání
}
function dd_datum($d) { $t = strtotime((string)$d); return ($d && $t) ? date('j. n. Y', $t) : ''; }
function dd_iso($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? (string)$d : ''; }
// Celý popis dveří: šířka + strana + směr, např. „80 Pravé dovnitř" (ze sire + otevirani; bez závorky s klikou).
function dd_cele_dvere(array $d): string {
  $s = trim((string)($d['sire'] ?? '')); $o = trim((string)($d['otevirani'] ?? ''));
  if ($o !== '') { $o = trim(explode('(', $o)[0]); if ($o !== '') $o = mb_strtoupper(mb_substr($o, 0, 1)) . mb_substr($o, 1); }
  if ($s === '' || mb_strtolower($s) === 'nevím') $s = '';
  return trim($s . ' ' . $o);
}
function dd_radek(array $z, array $VYCHOZI_MODEL): array {
  $d = json_decode((string)$z['data_json'], true) ?: [];
  $kod = (string)($d['model_kod'] ?? '');
  $ot = (string)($d['otevirani'] ?? '');
  $montazIso = dd_iso($d['montaz_dne'] ?? '') ?: dd_iso($d['termin_datum'] ?? '');
  $dod = trim((string)($z['dodavatel'] ?? ''));
  $dvere = preg_match('/^\s*prav/iu', $ot) ? 'Pravé' : (preg_match('/^\s*lev/iu', $ot) ? 'Levé' : $ot);
  return ['id' => (int)$z['id'], 'kod' => $kod, 'model' => (string)($d['model'] ?? $kod),
    'dodavatel' => $dod !== '' ? $dod : dd_vychozi($d, $VYCHOZI_MODEL), 'dod_ulozen' => $dod !== '',
    'dvere' => $dvere, 'popis' => (dd_cele_dvere($d) ?: $dvere),
    'odstin' => (string)($d['odstin'] ?? ''), 'zarubne' => (string)($d['zarubne'] ?? ''), 'kovani' => (string)($d['kovani'] ?? ''), 'sirka' => (string)($d['sire'] ?? ''),
    'rozpocet' => is_array($d['rozpocet'] ?? null) ? trim('rozpočet ' . ($d['rozpocet']['cislo'] ?? '')) : '',
    'cislo' => (string)($d['obj_cislo'] ?? $z['cislo']), 'zakaznik' => trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? '')), 'mesto' => (string)($d['mesto'] ?? ''),
    'montaz_iso' => $montazIso, 'montaz' => dd_datum($montazIso), 'zaloha' => !empty($z['zaloha_prijata_dne']) ? 'ano' : 'ne',
    'zaloha_dne' => (string)($z['zaloha_prijata_dne'] ?? ''),
    'stav' => (string)$z['stav'], 'sklad_stav' => (string)($z['sklad_stav'] ?? ''), 'ref' => (string)($z['dodavatel_ref'] ?? ''),
    'objednano' => (string)($z['dod_objednano'] ?? ''), 'termin' => (string)($z['dod_termin'] ?? ''), 'dodano' => (string)($z['dod_dodano'] ?? '')];
}
function dd_nacti($pdo, array $VYCHOZI_MODEL): array {
  $out = [];
  foreach ($pdo->query("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','namontovano','vyrizeno') ORDER BY id") as $z) {
    $d = json_decode((string)$z['data_json'], true) ?: [];
    if (($d['model_kod'] ?? '') === 'premium60' || stripos((string)($d['model'] ?? ''), 'premium 60') !== false) continue;   // PREMIUM 60 má vlastní sklad
    if (preg_match('/test/i', (string)($d['obj_cislo'] ?? $z['cislo'])) && empty($_GET['testy'])) continue;            // zkušební záznamy jen s ?testy=1
    $out[] = dd_radek($z, $VYCHOZI_MODEL);
  }
  usort($out, fn($a, $b) => [$a['dodavatel'], $a['montaz_iso'] ?: '9999', $a['cislo']] <=> [$b['dodavatel'], $b['montaz_iso'] ?: '9999', $b['cislo']]);
  return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
  $akce = $_POST['akce'] ?? '';
  $txt = fn($k, $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
  $dat = fn($k) => dd_iso($_POST[$k] ?? '');
  if ($akce === 'objednano' && $ids) {
    $dod = $txt('dodavatel', 80);
    $sel = $pdo->prepare("SELECT dodavatel, data_json FROM zadosti WHERE id=?");
    $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='objednano', dodavatel_ref=?, dod_objednano=?, dod_termin=?, dodavatel=? WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) {
      $sel->execute([$i]); $z = $sel->fetch(PDO::FETCH_ASSOC); if (!$z) continue;
      $efekt = $dod !== '' ? $dod : (trim((string)$z['dodavatel']) !== '' ? (string)$z['dodavatel'] : dd_vychozi(json_decode((string)$z['data_json'], true) ?: [], $VYCHOZI_MODEL));   // uloží se i výchozí dodavatel
      $up->execute([$txt('ref', 120), $dat('objednano') ?: date('Y-m-d'), $dat('termin') ?: null, $efekt !== '' ? $efekt : null, $i]);
    }
  } elseif ($akce === 'termin' && $ids) {
    $up = $pdo->prepare("UPDATE zadosti SET dod_termin=? WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) $up->execute([$dat('termin') ?: null, $i]);
  } elseif ($akce === 'dodano' && $ids) {
    $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='vyzvednuto', dod_dodano=? WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) $up->execute([$dat('dodano') ?: date('Y-m-d'), $i]);
  } elseif ($akce === 'zpet' && $ids) {
    $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='', dodavatel_ref=NULL, dod_objednano=NULL, dod_termin=NULL, dod_dodano=NULL WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) $up->execute([$i]);
  } elseif ($akce === 'zpet_objednano' && $ids) {
    $up = $pdo->prepare("UPDATE zadosti SET sklad_stav='objednano', dod_dodano=NULL WHERE id=? AND typ='objednavka_interni'");
    foreach ($ids as $i) $up->execute([$i]);
  }
  header('Location: dodavatel.php' . (!empty($_POST['f']) ? '?dod=' . urlencode((string)$_POST['f']) : '')); exit;
}
$vse = dd_nacti($pdo, $VYCHOZI_MODEL);
$fDod = (string)($_GET['dod'] ?? '');
$vsichniDod = array_values(array_unique(array_merge($DODAVATELE, array_filter(array_column($vse, 'dodavatel')))));
if ($fDod !== '') $vse = array_values(array_filter($vse, fn($r) => ($fDod === '-' ? $r['dodavatel'] === '' : $r['dodavatel'] === $fDod)));
$k_obj = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === ''));
$objednano = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === 'objednano'));
$dodano = array_values(array_filter($vse, fn($r) => $r['sklad_stav'] === 'vyzvednuto'));
$dnes = date('Y-m-d');
$za = fn($dny) => date('Y-m-d', strtotime("+$dny days"));
// upozornění u řádku: [třída, text]
function dd_varovani(array $r, string $dnes, callable $za): array {
  if ($r['sklad_stav'] === '') {
    if ($r['montaz_iso'] && $r['montaz_iso'] <= $za(14)) return ['cer', 'montáž ' . $r['montaz'] . ' – ještě neobjednáno!'];
    if ($r['montaz_iso'] && $r['montaz_iso'] <= $za(35)) return ['ora', 'objednat brzy (montáž ' . $r['montaz'] . ')'];
    return ['', ''];
  }
  if ($r['sklad_stav'] === 'objednano') {
    if ($r['termin'] && $r['termin'] < $dnes) return ['cer', 'po termínu dodání (' . dd_datum($r['termin']) . ')'];
    if ($r['termin'] && $r['montaz_iso'] && $r['montaz_iso'] < $r['termin']) return ['cer', 'montáž je dřív než termín dodání'];
    if (!$r['termin']) return ['ora', 'chybí termín dodání od dodavatele'];
    if ($r['montaz_iso'] && $r['montaz_iso'] <= $za(3) ) return ['ora', 'montáž do 3 dnů'];
    return ['', ''];
  }
  if ($r['montaz_iso'] === '') return ['ora', 'dodáno – domluvit termín montáže'];
  return ['', ''];
}
$pocetCer = 0; foreach ($vse as $r) if (dd_varovani($r, $dnes, $za)[0] === 'cer') $pocetCer++;
// zakázky, u kterých ještě není přijatá záloha (akceptace objednávky = zaplacená záloha); relevantní, dokud nejsou dodané
$bezZalohy = fn(array $rows) => count(array_filter($rows, fn($r) => $r['zaloha'] !== 'ano'));
$pocetBezZalohyK = $bezZalohy($k_obj);
$pocetBezZalohyO = $bezZalohy($objednano);

function dd_tabulka(array $rows, string $sekce, $e, string $dnes, callable $za, string $fDod): void { ?>
  <form method="post"><input type="hidden" name="f" value="<?= $e($fDod) ?>"><div class="tw"><table>
    <tr><th><input type="checkbox" onclick="for(const c of this.closest('table').querySelectorAll('input[name=&quot;ids[]&quot;]'))c.checked=this.checked" title="Vybrat vše" style="width:18px;height:18px"></th><th>Č. obj.</th><th>Zákazník</th><th>Dodavatel</th><th>Dveře</th><th class="hide-m">Provedení</th>
      <?php if ($sekce !== 'k'): ?><th>Č. u dodavatele</th><th>Objednáno</th><?php endif; ?><?php if ($sekce !== 'd'): ?><th>Termín dodání</th><?php else: ?><th>Dodáno</th><?php endif; ?><th>Montáž</th><th>Záloha</th></tr>
    <?php foreach ($rows as $r): [$tr, $tt] = dd_varovani($r, $dnes, $za);
      $bezZ = $r['zaloha'] !== 'ano' && $sekce !== 'd';   // u již dodaných dveří záloha nevadí pro objednání, jen se zobrazí ve sloupci ?>
      <tr class="<?= $e(trim($tr . ($bezZ ? ' bezz' : ''))) ?>"><td><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" data-zaloha="<?= $e($r['zaloha']) ?>" data-cislo="<?= $e($r['cislo']) ?>" style="width:20px;height:20px"></td>
      <td><a href="kartoteka.php?id=<?= $r['id'] ?>"><b><?= $e($r['cislo']) ?></b></a></td>
      <td><?= $e($r['zakaznik']) ?><div class="mut"><?= $e($r['mesto']) ?></div></td>
      <td><?= $r['dodavatel'] !== '' ? $e($r['dodavatel']) . (!$r['dod_ulozen'] && $sekce === 'k' ? ' <span class="mut">(výchozí)</span>' : '') : '<span class="mut">vybrat</span>' ?></td>
      <td><?= $e($r['model']) ?><?= $r['rozpocet'] ? '<div class="mut">' . $e($r['rozpocet']) . '</div>' : '' ?><?php if ($bezZ): ?><div class="zal-ne">⛔ ZÁLOHA NEZAPLACENA<?= $sekce === 'k' ? ' – zatím neobjednávat' : '' ?></div><?php endif; ?><?= $tt ? '<div class="var ' . $tr . '">⚠ ' . $e($tt) . '</div>' : '' ?></td>
      <td class="hide-m"><?= $e(implode(' · ', array_filter([$r['popis'], $r['odstin'], $r['zarubne'], $r['kovani']]))) ?: '–' ?></td>
      <?php if ($sekce !== 'k'): ?><td><?= $e($r['ref'] ?: '–') ?></td><td><?= $e(dd_datum($r['objednano']) ?: '–') ?></td><?php endif; ?>
      <?php if ($sekce !== 'd'): ?><td><?= $e(dd_datum($r['termin']) ?: '–') ?></td><?php else: ?><td><?= $e(dd_datum($r['dodano']) ?: '–') ?></td><?php endif; ?>
      <td><?= $e($r['montaz'] ?: '–') ?></td>
      <td><?php if ($r['zaloha'] === 'ano'): ?><span class="zal-ano">✔ zaplacena</span><?= $r['zaloha_dne'] ? '<div class="mut">' . $e(dd_datum($r['zaloha_dne'])) . '</div>' : '' ?><?php else: ?><span class="zal-badge">NEZAPLACENA</span><?php endif; ?></td></tr>
    <?php endforeach; ?></table></div>
    <div class="akce">
    <?php if ($sekce === 'k'): ?>
      <label>Dodavatel<input name="dodavatel" list="dodlist" placeholder="ponechat u každé výchozí"></label>
      <label>Č. objednávky u dodavatele<input name="ref" placeholder="volitelné"></label>
      <label>Objednáno dne<input type="date" name="objednano" value="<?= $e($dnes) ?>"></label>
      <label>Termín dodání<input type="date" name="termin"></label>
      <button name="akce" value="objednano" onclick="return bdZalohaOk(this.form)">Označit vybrané jako objednané</button>
    <?php elseif ($sekce === 'o'): ?>
      <label>Dodáno dne<input type="date" name="dodano" value="<?= $e($dnes) ?>"></label><button name="akce" value="dodano">Dodáno k nám</button>
      <label>Nový termín dodání<input type="date" name="termin"></label><button name="akce" value="termin" class="sec">Uložit termín</button>
      <button name="akce" value="zpet" class="sec" onclick="return confirm('Vrátit vybrané mezi „k objednání“? Smaže se číslo objednávky a termíny u dodavatele.')">Vrátit mezi „k objednání“</button>
    <?php else: ?>
      <button name="akce" value="zpet_objednano" class="sec">Vrátit mezi „objednáno“</button>
    <?php endif; ?>
    </div></form>
<?php }
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="manifest" href="kartoteka-manifest.php" crossorigin="use-credentials"><link rel="icon" href="kartoteka-192.png"><title>Objednávky u dodavatele – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
main{max-width:1250px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
.tw{overflow-x:auto}table{border-collapse:collapse;width:100%}th,td{padding:7px 8px;border-bottom:1px solid #e3e8f0;text-align:left;vertical-align:top}th{font-size:13px;color:#556}
tr.cer td{background:#fff1f1}tr.ora td{background:#fff8ec}.var{font-size:13px;font-weight:600;margin-top:2px}.var.cer{color:#b42318}.var.ora{color:#a15c00}
tr.bezz td:first-child{box-shadow:inset 4px 0 0 #b42318}
.zal-ne{display:inline-block;margin-top:3px;background:#b42318;color:#fff;font-size:12px;font-weight:700;letter-spacing:.2px;padding:2px 8px;border-radius:6px}
.zal-badge{display:inline-block;background:#fde8e6;color:#b42318;border:1px solid #f1a9a3;font-size:12px;font-weight:700;padding:2px 8px;border-radius:6px;white-space:nowrap}
.zal-ano{color:#17803d;font-weight:600;white-space:nowrap}
.mut{color:#667;font-size:13px}
button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:9px 14px;font:inherit;cursor:pointer}button.sec{background:#e6ebf3;color:#1b2430}
.akce{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:flex-end;margin-top:10px}.akce label{display:flex;flex-direction:column;font-size:13px;color:#556}
.akce input{padding:8px;border:1px solid #c6cfdd;border-radius:8px;font:inherit;min-width:150px}
.filtr{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}.filtr a{padding:6px 12px;border-radius:999px;background:#e6ebf3;color:#1b2430;text-decoration:none;font-size:14px}.filtr a.on{background:#1f5fbf;color:#fff}
.odkazy{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}.odkazy a{background:#16a34a;color:#fff;text-decoration:none;font-weight:600;padding:10px 16px;border-radius:8px}
a{color:#1f5fbf}h1{margin:0 0 10px}h2{margin:0 0 8px;font-size:18px}.hint{background:#fff7e0;border:1px solid #f0d98a;border-radius:10px;padding:10px 14px;margin-bottom:14px}
.zal-hint{background:#fff1f1;border:1px solid #f1a9a3;color:#7a1a12;border-radius:10px;padding:10px 14px;margin:0 0 10px;font-weight:600}
.souhrn{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}.souhrn div{background:#fff;border-radius:10px;padding:10px 14px;box-shadow:0 1px 3px #0002;font-size:14px}.souhrn b{font-size:20px;display:block}
@media(max-width:700px){.hide-m{display:none}}
</style></head><body><?php bd_bar('dodavatel'); ?>
<main><h1>Objednávky u dodavatele</h1>
<div class="odkazy"><?php foreach ($ODKAZY_DOD as [$ou, $ot]): ?><a href="<?= $e($ou) ?>" target="_blank" rel="noopener"><?= $e($ot) ?></a><?php endforeach; ?></div>
<datalist id="dodlist"><?php foreach ($DODAVATELE as $dn): ?><option value="<?= $e($dn) ?>"><?php endforeach; ?></datalist>
<div class="souhrn"><div><b><?= count($k_obj) ?></b>k objednání</div><div><b><?= count($objednano) ?></b>objednáno, čeká na dodání</div><div><b><?= count($dodano) ?></b>dodáno, čeká na montáž</div><?php if ($pocetCer): ?><div style="color:#b42318"><b><?= $pocetCer ?></b>vyžaduje pozornost</div><?php endif; ?><?php if ($pocetBezZalohyK + $pocetBezZalohyO): ?><div style="color:#b42318"><b><?= $pocetBezZalohyK + $pocetBezZalohyO ?></b>bez zaplacené zálohy</div><?php endif; ?></div>
<div class="filtr"><a class="<?= $fDod === '' ? 'on' : '' ?>" href="dodavatel.php">Všichni dodavatelé</a><?php foreach ($vsichniDod as $dn): ?><a class="<?= $fDod === $dn ? 'on' : '' ?>" href="?dod=<?= urlencode($dn) ?>"><?= $e($dn) ?></a><?php endforeach; ?><a class="<?= $fDod === '-' ? 'on' : '' ?>" href="?dod=-">Dodavatel neurčen</a></div>
<div class="hint">Zakázky mimo náš sklad PREMIUM 60 (EXTREME 54 přes B2B Svět dveří, ERKADO, interiérové dveře od Světa dveří nebo ERKADO…). Samotná objednávka se zadává u dodavatele, tady se eviduje: dodavatel, číslo objednávky, datum objednání, termín dodání a datum dodání. Stav se propisuje do kartotéky (objednáno u výrobce → skladem). Červeně: neobjednáno a montáž do 14 dnů, po termínu dodání, nebo montáž dřív než dodání. Oranžově: objednat brzy, chybí termín dodání, dodáno bez termínu montáže. Akceptací objednávky je zaplacená záloha: zakázky bez přijaté zálohy mají červený štítek „ZÁLOHA NEZAPLACENA“ (záloha se eviduje v kartotéce u zakázky, nebo se spáruje z platby).</div>
<div class="box"><h2>K objednání u dodavatele (<?= count($k_obj) ?>)</h2>
  <?php if ($pocetBezZalohyK): ?><div class="zal-hint">⛔ U <?= $pocetBezZalohyK ?> zakázek ještě není zaplacená záloha – zatím je neobjednávejte (štítek v řádku).</div><?php endif; ?>
  <?php if (!$k_obj): ?><p>Nic k objednání.</p><?php else: dd_tabulka($k_obj, 'k', $e, $dnes, $za, $fDod); endif; ?></div>
<div class="box"><h2>Objednáno u dodavatele – čeká na dodání (<?= count($objednano) ?>)</h2>
  <?php if ($pocetBezZalohyO): ?><div class="zal-hint">⛔ U <?= $pocetBezZalohyO ?> už objednaných zakázek stále není zaplacená záloha.</div><?php endif; ?>
  <?php if (!$objednano): ?><p>Nic.</p><?php else: dd_tabulka($objednano, 'o', $e, $dnes, $za, $fDod); endif; ?></div>
<div class="box"><h2>Dodáno k nám – skladem, čeká na montáž (<?= count($dodano) ?>)</h2>
  <?php if (!$dodano): ?><p>Nic.</p><?php else: dd_tabulka($dodano, 'd', $e, $dnes, $za, $fDod); endif; ?></div>
</main>
<script>
// před označením za objednané: když je mezi vybranými zakázka bez zaplacené zálohy, vyžádá si potvrzení
function bdZalohaOk(f) {
  var bez = [].filter.call(f.querySelectorAll('input[name="ids[]"]:checked'), function (c) { return c.dataset.zaloha !== 'ano'; })
    .map(function (c) { return c.dataset.cislo; });
  if (!bez.length) return true;
  return confirm('Pozor: u zakázek ' + bez.join(', ') + ' není zaplacená záloha (akceptace objednávky).\n\nOpravdu je označit jako objednané?');
}
</script>
</body></html>
