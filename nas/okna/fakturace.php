<?php
// Servis oken (samostatná aplikace /okna/) – PODKLADY K FAKTURACI. Hotové servisy (jednotlivci a byty v SVJ / družstvech) seskupené podle odběratele:
// platí SVJ → jedna faktura na SVJ s řádkem za každý byt, platí nájemník / jednotlivec → faktura na zákazníka. Fakturu dál vystavuje účetní v Pohodě –
// odtud jde přehled (tisk, CSV pro Excel, e-mail účetní) a označení „předáno k fakturaci“, aby se nic nefakturovalo dvakrát.
require __DIR__ . '/lib.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$sl = array_column($pdo->query('PRAGMA table_info(so_jed)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['fakturace' => 'TEXT', 'plati' => 'TEXT', 'svj_id' => 'INTEGER', 'byt' => 'TEXT', 'polozky' => 'TEXT'] as $k => $d) if (!in_array($k, $sl, true)) $pdo->exec("ALTER TABLE so_jed ADD COLUMN $k $d");
$ted = date('Y-m-d H:i:s');
$cen = []; foreach (ok_cenik($pdo) as $c) $cen[$c['kod']] = $c;
$svjN = []; foreach ($pdo->query('SELECT * FROM so_svj')->fetchAll(PDO::FETCH_ASSOC) as $j) $svjN[(int)$j['id']] = $j;
$st = $pdo->prepare("SELECT hodnota FROM ok_nastaveni WHERE klic='fakt_email'"); $st->execute(); $faktEmail = (string)($st->fetchColumn() ?: 'kucerovadn@kksystem.cz');

// filtr: měsíc provedení (YYYY-MM) nebo „vse“ = všechny dosud nepředané
$mes = preg_match('/^\d{4}-\d{2}$/', (string)($_REQUEST['m'] ?? '')) ? $_REQUEST['m'] : 'vse';
$iVyf = !empty($_REQUEST['i']);   // zobrazit i předané
$radky = function () use ($pdo, $mes, $iVyf, $cen, $svjN) {
  // servisy, které ještě čekají na dokončení opravy, se zatím nefakturují (fakturuje se celek až s opravou)
  $maChild = [];
  foreach ($pdo->query("SELECT DISTINCT predchozi_id FROM so_jed WHERE predchozi_id IS NOT NULL AND stav='hotovo'")->fetchAll(PDO::FETCH_COLUMN) as $pid) $maChild[(int)$pid] = true;
  $datum = [];
  foreach ($pdo->query("SELECT id, provedeno FROM so_jed")->fetchAll(PDO::FETCH_ASSOC) as $x) $datum[(int)$x['id']] = (string)$x['provedeno'];
  $w = "stav='hotovo'" . ($mes !== 'vse' ? " AND substr(provedeno,1,7)=" . $pdo->quote($mes) : '') . ($iVyf ? '' : " AND IFNULL(fakturace,'')=''");
  $sk = []; $ceka = [];
  foreach ($pdo->query("SELECT * FROM so_jed WHERE $w ORDER BY provedeno, id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (in_array($r['vysledek'] ?? '', ['oprava', 'dily'], true) && empty($maChild[(int)$r['id']]) && empty($r['fakturace'])) { $ceka[] = $r; continue; }
    $naSvj = ($r['plati'] ?? '') === 'svj' && isset($svjN[(int)$r['svj_id']]);
    $klic = $naSvj ? 's' . (int)$r['svj_id'] : 'j' . $r['klic'];
    if (!isset($sk[$klic])) {
      $j = $naSvj ? $svjN[(int)$r['svj_id']] : null;
      $sk[$klic] = ['klic' => $klic, 'svj' => $naSvj, 'nazev' => $naSvj ? $j['nazev'] : $r['jmeno'], 'ic' => $naSvj ? (string)$j['ic'] : '',
        'adresa' => $naSvj ? (string)$j['adresa'] : sj_adresa($r), 'kontakt' => $naSvj ? trim($j['kontakt'] . ' ' . $j['telefon']) : (string)$r['telefon'], 'email' => $naSvj ? (string)$j['email'] : (string)$r['email'], 'r' => []];
    }
    $nav = $r['predchozi_id'] && isset($datum[(int)$r['predchozi_id']]) ? so_datum($datum[(int)$r['predchozi_id']]) : '';
    $text = trim(implode('; ', array_filter([(string)$r['opraveno_hned'], (string)$r['oprava'], (string)$r['zavada']])));
    $pol = json_decode((string)($r['polozky'] ?? ''), true);
    if (is_array($pol) && $pol) {   // servis s více položkami → jeden řádek za položku (každá se svou sazbou DPH)
      $prvni = true;
      foreach ($pol as $it) {
        if (!is_array($it)) continue;
        $mn = (float)($it['mn'] ?? 1); $cj = (float)($it['cena'] ?? 0); $sazba = in_array((int)($it['dph'] ?? 12), [0, 12, 15, 21], true) ? (float)$it['dph'] : 12.0; $s = round($mn * $cj, 2);
        $mnTxt = rtrim(rtrim(number_format($mn, 2, ',', ' '), '0'), ',');
        $sk[$klic]['r'][] = $r + ['_polozka' => (string)($it['nazev'] ?? 'Servis') . ($mn != 1.0 ? ' (' . $mnTxt . '×' . ($it['jedn'] ? ' ' . $it['jedn'] : '') . ')' : ''),
          '_sazba' => $sazba, '_dph_odhad' => false, '_s' => $s, '_z' => round($s / (1 + $sazba / 100), 2), '_text' => $prvni ? $text : '', '_navazuje' => $prvni ? $nav : '', '_prvni' => $prvni];
        $prvni = false;
      }
    } else {   // starší servis bez položek: jedna cena podle kategorie
      $c = $cen[(string)$r['kategorie']] ?? null; $sazba = $c ? (float)$c['dph'] : 12.0; $s = is_numeric($r['cena']) ? (float)$r['cena'] : null;
      $sk[$klic]['r'][] = $r + ['_polozka' => $c ? $c['nazev'] : 'Servis oken', '_sazba' => $sazba, '_dph_odhad' => !$c, '_s' => $s,
        '_z' => $s === null ? null : round($s / (1 + $sazba / 100), 2), '_text' => $text, '_navazuje' => $nav, '_prvni' => true];
    }
  }
  return ['sk' => $sk, 'ceka' => $ceka];
};
$csv = function (array $sk): string {
  $o = "\xEF\xBB\xBF" . implode(';', ['Odběratel', 'IČ', 'Adresa odběratele', 'Provedeno', 'Byt / zákazník', 'Adresa servisu', 'Položka', 'Popis práce', 'Sazba DPH %', 'Základ Kč', 'DPH Kč', 'Celkem s DPH Kč', 'Kdo platí', 'ID servisu']) . "\r\n";
  $q = fn($v) => '"' . str_replace('"', '""', (string)$v) . '"'; $n = fn($v) => $v === null ? '' : number_format((float)$v, 2, ',', '');
  foreach ($sk as $g) foreach ($g['r'] as $r) $o .= implode(';', [$q($g['nazev']), $q($g['ic']), $q($g['adresa']), $q(so_datum($r['provedeno'])), $q($r['jmeno'] . ($r['byt'] ? ', byt ' . $r['byt'] : '')), $q(sj_adresa($r)),
    $q($r['_polozka']), $q($r['_text']), $n($r['_sazba']), $n($r['_z']), $n($r['_s'] === null ? null : $r['_s'] - $r['_z']), $n($r['_s']), $q($g['svj'] ? 'SVJ / družstvo' : (($r['plati'] ?? '') === 'najemnik' ? 'nájemník' : ($r['svj_id'] ? 'NEURČENO (byt v SVJ)' : 'zákazník'))), $q($r['id'])]) . "\r\n";
  return $o;
};

$a = (string)($_POST['akce'] ?? '');
if ($a === 'predano' || $a === 'vratit') {
  $ids = array_map('intval', (array)($_POST['ids'] ?? []));
  if ($ids) $pdo->prepare('UPDATE so_jed SET fakturace=? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')')->execute(array_merge([$a === 'predano' ? date('j. n. Y') . ' – ' . uziv_jmeno() : null], $ids));
  header('Location: fakturace.php?m=' . rawurlencode($mes) . ($iVyf ? '&i=1' : '') . '&ok=' . $a); exit;
}
if (isset($_GET['csv'])) {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="podklady-fakturace-servis-oken-' . ($mes === 'vse' ? date('Y-m-d') : $mes) . '.csv"');
  echo $csv($radky()['sk']); exit;
}
$zprava = '';
if ($a === 'poslat') {
  $sk = $radky()['sk']; $komu = trim((string)($_POST['email'] ?? ''));
  if (!$sk) $zprava = 'Není co poslat.';
  elseif (!filter_var($komu, FILTER_VALIDATE_EMAIL)) $zprava = 'Neplatný e-mail.';
  else {
    $pdo->prepare("INSERT INTO ok_nastaveni (klic, hodnota) VALUES ('fakt_email', ?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota")->execute([$komu]); $faktEmail = $komu;
    $dir = $DATA . '/okna/fakturace'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $soubor = 'podklady-fakturace-servis-oken-' . date('Y-m-d-His') . '.csv'; file_put_contents($dir . '/' . $soubor, $csv($sk));
    $telo = "Dobrý den,\n\nposílám podklady k fakturaci servisu oken" . ($mes !== 'vse' ? ' za ' . $mes : '') . ":\n\n";
    foreach ($sk as $g) { $sum = array_sum(array_map(fn($r) => (float)$r['_s'], $g['r']));
      $telo .= '• ' . $g['nazev'] . ($g['ic'] ? ', IČ ' . $g['ic'] : '') . ($g['adresa'] ? ', ' . $g['adresa'] : '') . "\n";
      foreach ($g['r'] as $r) $telo .= '   – ' . so_datum($r['provedeno']) . ' ' . $r['jmeno'] . ($r['byt'] ? ', byt ' . $r['byt'] : '') . ': ' . $r['_polozka'] . ' – ' . ($r['_s'] === null ? 'CENA NEVYPLNĚNA' : ok_kc($r['_s'], 2) . ' s DPH (' . (int)$r['_sazba'] . ' %)') . "\n";
      $telo .= '   Celkem: ' . ok_kc($sum, 2) . " s DPH\n\n"; }
    $telo .= "Podrobně v příloze (CSV pro Excel).\n\n" . uziv_jmeno();
    require_once OK_APP . '/lib/posta.php';
    $ok = posli_mail($cfg, $DATA, [$komu], [], $cfg['from'], 'Podklady k fakturaci – servis oken' . ($mes !== 'vse' ? ' ' . $mes : ''), $telo, [[$dir . '/' . $soubor, $soubor]]);
    $zprava = $ok ? '✅ Odesláno na ' . $komu . '. Pokud už jde k fakturaci, označte servisy jako předané.' : '❌ E-mail se nepodařilo odeslat – stáhněte CSV a pošlete ho ručně.';
  }
}

$vys = $radky(); $sk = $vys['sk']; $ceka = $vys['ceka'];
$mesice = $pdo->query("SELECT DISTINCT substr(provedeno,1,7) FROM so_jed WHERE stav='hotovo' AND provedeno IS NOT NULL ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);
ok_hlava('Fakturace'); ok_bar('fak'); ?>
<style>@media print{.okbar,.noprint{display:none!important}.box{box-shadow:none;border:1px solid #ccc;break-inside:avoid}}</style><main>
<h1>🧾 Podklady k fakturaci</h1>
<p class="mut noprint">Hotové servisy, které ještě nebyly předány k fakturaci. Když platí SVJ / družstvo, jsou byty pod SVJ (jedna faktura). Faktury dál vystavuje účetní v Pohodě.</p>
<?php if ($zprava): ?><div class="msg"><?= $e($zprava) ?></div><?php elseif (isset($_GET['ok'])): ?><div class="msg"><?= $_GET['ok'] === 'predano' ? 'Označeno jako předané k fakturaci.' : 'Vráceno mezi nepředané.' ?></div><?php endif; ?>
<form class="ln box noprint"><label>Provedeno v měsíci<select name="m"><option value="vse">všechny nepředané</option><?php foreach ($mesice as $m) if ($m): ?><option value="<?= $e($m) ?>"<?= $mes === $m ? ' selected' : '' ?>><?= $e((int)substr($m, 5) . '/' . substr($m, 0, 4)) ?></option><?php endif; ?></select></label>
<label style="flex-direction:row;align-items:center;gap:6px"><input type="checkbox" name="i" value="1"<?= $iVyf ? ' checked' : '' ?>> i už předané</label><button class="sec">Zobrazit</button></form>
<?php if ($ceka): ?><div class="box noprint" style="border-left:5px solid #d97706"><h2 style="margin-top:0">⏳ Čeká na dokončení opravy <span class="mut">(<?= count($ceka) ?>)</span></h2>
<p class="mut" style="margin-top:0">Tyto servisy skončily nálezem „nutná oprava“ a ještě nemají provedenou navazující opravu. Fakturují se až jako celek s opravou – proto tu zatím nejsou. Až opravu uděláte (u servisu tlačítko „Založit opravu“ a po provedení uložíte jako Hotovo), objeví se tu servis i oprava dohromady.</p>
<table><tr><th>Provedeno</th><th>Zákazník / byt</th><th>Nález</th></tr>
<?php foreach ($ceka as $r): ?><tr><td class="nowrap"><?= $e(so_datum($r['provedeno'])) ?></td><td><a href="index.php?id=<?= (int)$r['id'] ?>"><?= $e($r['jmeno']) ?></a><?= $r['byt'] ? ', byt ' . $e($r['byt']) : '' ?><div class="mut"><?= $e(sj_adresa($r)) ?></div></td>
  <td><span class="st st-nova"><?= $e(SJ_VYSLEDKY[$r['vysledek']] ?? '') ?></span><?php if ($r['oprava']): ?><div class="mut"><?= $e(mb_strimwidth($r['oprava'], 0, 160, '…')) ?></div><?php endif; ?></td></tr><?php endforeach; ?></table></div>
<?php endif; ?>
<?php if (!$sk): ?><div class="box"><p class="mut"><?= $ceka ? 'Nic připraveného k fakturaci – zatím se čeká na dokončení oprav (výše).' : 'Nic k fakturaci. Do přehledu se dostane servis ve stavu „Hotovo“.' ?></p></div>
<?php else: $celkem = 0; foreach ($sk as $g): $sum = 0; $chybi = 0; foreach ($g['r'] as $r) { $sum += (float)$r['_s']; if ($r['_s'] === null) $chybi++; } $celkem += $sum; ?>
<div class="box"><h2 style="margin-top:0"><?= $g['svj'] ? '🏢 ' : '🏠 ' ?><?= $e($g['nazev']) ?></h2>
<div class="mut"><?= $e(implode(' · ', array_filter([$g['ic'] ? 'IČ ' . $g['ic'] : '', $g['adresa'], $g['kontakt'], $g['email']]))) ?></div>
<div style="overflow-x:auto"><table><tr><th>Provedeno</th><th>Byt / zákazník</th><th>Položka a práce</th><th class="r">DPH</th><th class="r">Bez DPH</th><th class="r">S DPH</th></tr>
<?php foreach ($g['r'] as $r): ?><tr><td class="nowrap"><?= $e(so_datum($r['provedeno'])) ?></td>
  <td><a href="index.php?id=<?= (int)$r['id'] ?>"><?= $e($r['jmeno']) ?></a><?= $r['byt'] ? ', byt ' . $e($r['byt']) : '' ?><?= !empty($r['_navazuje']) ? ' <span class="st st-nova" title="Navazuje na dřívější servis – fakturuje se celek">↳ oprava, navazuje na ' . $e($r['_navazuje']) . '</span>' : '' ?><div class="mut"><?= $e(sj_adresa($r)) ?></div><?= $r['fakturace'] ? '<span class="st st-hotovo">předáno ' . $e($r['fakturace']) . '</span>' : '' ?><?= $r['svj_id'] && !in_array($r['plati'] ?? '', ['svj', 'najemnik'], true) ? '<span class="st st-zruseno">patří k SVJ – není určeno, kdo platí</span>' : '' ?></td>
  <td><?= $e($r['_polozka']) ?><?php if ($r['_text'] !== ''): ?><div class="mut"><?= $e(mb_strimwidth($r['_text'], 0, 200, '…')) ?></div><?php endif; ?></td>
  <td class="r nowrap"><?= (int)$r['_sazba'] ?> %<?= $r['_dph_odhad'] ? ' <span class="mut" title="Servis nemá vybranou položku ceníku – sazba je výchozí pro byty, ověřte">?</span>' : '' ?></td>
  <td class="r nowrap"><?= $e($r['_z'] === null ? '' : ok_kc($r['_z'], 2)) ?></td>
  <td class="r nowrap"><?= $r['_s'] === null ? '<span class="st st-zruseno">chybí cena</span>' : '<b>' . $e(ok_kc($r['_s'], 2)) . '</b>' ?></td></tr>
<?php endforeach; ?><tr><td colspan="5" class="r"><b>Celkem</b></td><td class="r nowrap"><b><?= $e(ok_kc($sum, 2)) ?></b></td></tr></table></div>
<?php if ($chybi): ?><p class="zk" style="margin:8px 0 0">⚠️ <?= $chybi ?>× chybí cena – doplňte ji v zápisu servisu (Cena a další servis), jinak se nedá fakturovat.</p><?php endif; ?>
<form method="post" class="noprint" style="margin-top:10px"><input type="hidden" name="m" value="<?= $e($mes) ?>"><?= $iVyf ? '<input type="hidden" name="i" value="1">' : '' ?>
<?php foreach ($g['r'] as $r): ?><input type="hidden" name="ids[]" value="<?= (int)$r['id'] ?>"><?php endforeach; ?>
<?php $vse = !array_filter($g['r'], fn($r) => !$r['fakturace']); ?><button name="akce" value="<?= $vse ? 'vratit' : 'predano' ?>" class="<?= $vse ? 'sec' : 'ok' ?>"><?= $vse ? '↩ Vrátit mezi nepředané' : '✔ Předáno k fakturaci' ?></button></form></div>
<?php endforeach; ?>
<div class="box"><b>Celkem za výběr: <?= $e(ok_kc($celkem, 2)) ?> s DPH</b>
<p class="noprint" style="margin-bottom:0"><a class="btn sec" href="?csv=1&amp;m=<?= $e(rawurlencode($mes)) ?><?= $iVyf ? '&amp;i=1' : '' ?>">⬇ CSV pro Excel</a> <button type="button" class="sec" onclick="print()">🖨 Tisk / PDF</button></p>
<form method="post" class="ln noprint" style="margin-top:10px"><input type="hidden" name="akce" value="poslat"><input type="hidden" name="m" value="<?= $e($mes) ?>"><?= $iVyf ? '<input type="hidden" name="i" value="1">' : '' ?>
<label class="w">Poslat účetní (přehled v e-mailu + CSV v příloze)<input name="email" value="<?= $e($faktEmail) ?>"></label><button onclick="return confirm('Poslat podklady na ' + this.form.email.value + '?')">📧 Poslat</button></form></div>
<?php endif; ?>
</main></body></html>
