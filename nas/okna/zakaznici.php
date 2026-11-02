<?php
// Servis oken (samostatná aplikace /okna/) – ZÁKAZNÍCI: vlastní seznam zákazníků sestavený ze servisů jednotlivců (klíč telefon → e-mail → jméno).
// Se Zákazníky kartotéky dveří nemá nic společného. Detail zákazníka = všechny jeho servisy a tlačítko na nový servis.
require __DIR__ . '/lib.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, 0, ',', ' ') . ' Kč';
$vse = ok_zakaznici($pdo);
$k = (string)($_GET['k'] ?? ''); $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
ok_hlava('Zákazníci'); ok_bar('zak'); ?><main>
<?php if ($k !== '' && isset($vse[$k])): $c = $vse[$k]; $adr = implode(', ', array_filter([trim($c['ulice']), trim(trim($c['psc']) . ' ' . trim($c['mesto']))])); ?>
<p><a href="zakaznici.php">← Zákazníci</a></p>
<div class="box"><h1 style="margin-bottom:4px"><?= $e($c['jmeno']) ?></h1>
<div style="font-size:17px"><?= $e($adr) ?><?= ok_mapa($adr) ?></div>
<div><?= $c['telefon'] ? '📞 <a href="tel:' . $e(preg_replace('/[^\d+]/', '', $c['telefon'])) . '">' . $e($c['telefon']) . '</a>' : '' ?><?= $c['email'] ? ' · ✉ <a href="mailto:' . $e($c['email']) . '">' . $e($c['email']) . '</a>' : '' ?></div>
<p style="margin-bottom:0"><a class="btn ok" href="index.php?nove=1&amp;k=<?= $e(rawurlencode($k)) ?>">+ Nový servis u zákazníka</a></p></div>
<div class="box"><h2 style="margin-top:0">Servisy (<?= count($c['servisy']) ?>)</h2>
<table><tr><th>Provedeno / termín</th><th>Stav</th><th>Závady / návrh opravy</th><th class="r">Cena</th></tr>
<?php foreach (array_reverse($c['servisy']) as $r): $dl = sj_dalsi($r); ?><tr><td class="nowrap"><a href="index.php?id=<?= (int)$r['id'] ?>"><?= $e(so_datum($r['provedeno'] ?: $r['termin']) ?: 'bez termínu') ?></a><?= $dl ? '<div class="mut">další ' . $e(so_datum($dl)) . '</div>' : '' ?></td>
<td><span class="st st-<?= $e($r['stav']) ?>"><?= $e(SJ_STAVY[$r['stav']] ?? '') ?></span></td><td class="mut"><?= $e(mb_strimwidth(trim($r['zavada'] . ' ' . $r['oprava']), 0, 160, '…')) ?></td><td class="r nowrap"><?= $e($kc($r['cena'])) ?></td></tr><?php endforeach; ?></table></div>
<?php else:
  $rows = array_reverse($vse);
  if ($q !== '') { $qt = preg_replace('/\D+/', '', $q); $rows = array_filter($rows, fn($c) => mb_strpos(mb_strtolower($c['jmeno'] . ' ' . $c['ulice'] . ' ' . $c['mesto'] . ' ' . $c['email']), $q) !== false || (strlen($qt) >= 4 && strpos(preg_replace('/\D+/', '', $c['telefon']), $qt) !== false)); } ?>
<h1>👥 Zákazníci <span class="mut">(<?= count($rows) ?>)</span></h1>
<form class="ln box"><label class="w">Hledat (jméno, telefon, obec)<input name="q" value="<?= $e($_GET['q'] ?? '') ?>"></label><button class="sec">Hledat</button> <a class="btn ok" href="index.php?nove=1">+ Nový servis</a></form>
<div class="box"><?php if (!$rows): ?><p class="mut">Zatím žádní zákazníci. Přibudou se servisy u jednotlivců.</p><?php else: ?>
<table><tr><th>Zákazník</th><th>Servisů</th><th>Poslední</th><th>Další servis</th></tr>
<?php foreach ($rows as $c): $posl = end($c['servisy']); $dl = ''; foreach ($c['servisy'] as $r) if ($x = sj_dalsi($r)) $dl = $x; ?>
<tr><td><a href="?k=<?= $e(rawurlencode($c['klic'])) ?>"><b><?= $e($c['jmeno']) ?></b></a><div class="mut"><?= $e(implode(', ', array_filter([$c['ulice'], $c['mesto']]))) ?><?= $c['telefon'] ? ' · ' . $e($c['telefon']) : '' ?></div></td>
<td><?= count($c['servisy']) ?></td><td class="nowrap"><?= $e(so_datum($posl['provedeno'] ?: $posl['termin'])) ?> <span class="st st-<?= $e($posl['stav']) ?>"><?= $e(SJ_STAVY[$posl['stav']] ?? '') ?></span></td><td class="nowrap"><?= $e(so_datum($dl)) ?></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>
<?php endif; ?>
</main></body></html>
