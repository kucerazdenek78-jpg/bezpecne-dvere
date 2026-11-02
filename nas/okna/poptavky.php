<?php
// Servis oken (samostatná aplikace /okna/) – POPTÁVKY Z WEBU. Formulář „Servis oken“ na webu ukládá poptávku do kartotéky dveří
// (tabulka zadosti); tady se jen čtou a tlačítkem „Převzít“ se z nich založí servis u zákazníka v této aplikaci.
require __DIR__ . '/lib.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$vz = []; foreach ($pdo->query('SELECT id, zadost_id FROM so_jed WHERE zadost_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $r) $vz[(int)$r['zadost_id']] = (int)$r['id'];
$vse = ok_poptavky(); $vsechny = isset($_GET['vse']);
$rows = $vsechny ? $vse : array_values(array_filter($vse, fn($r) => !isset($vz[(int)$r['id']]) && !in_array($r['stav'], ['vyrizeno', 'storno'], true)));
ok_hlava('Poptávky z webu'); ok_bar('popt'); ?><main>
<h1>📨 Poptávky z webu <span class="mut">(<?= count($rows) ?>)</span></h1>
<p class="mut">Poptávky z formuláře „Servis oken“ na bezpecne-dvere.cz. <?= $vsechny ? '<a href="poptavky.php">Jen nepřevzaté</a>' : '<a href="?vse=1">Zobrazit i převzaté a vyřízené</a>' ?></p>
<div class="box"><?php if (!$rows): ?><p class="mut">Žádné nové poptávky.</p><?php else: ?>
<table><tr><th>Přišla</th><th>Zákazník</th><th>Požadavek</th><th></th></tr>
<?php foreach ($rows as $r): $d = $r['d']; ?>
<tr><td class="nowrap"><?= $e(date('j. n. Y', strtotime($r['vytvoreno']))) ?><div class="mut"><?= $e($d['cislo'] ?? $r['cislo']) ?></div></td>
<td><b><?= $e(trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''))) ?></b><div class="mut"><?= $e(implode(' · ', array_filter([(string)($d['telefon'] ?? ''), (string)($d['mesto'] ?? ''), (string)($d['pro_koho'] ?? '')]))) ?></div></td>
<td class="mut"><?= $e(mb_strimwidth(trim((string)($d['poznamka'] ?? '')), 0, 200, '…')) ?></td>
<td class="nowrap"><?php if (isset($vz[(int)$r['id']])): ?><a class="btn sec" href="index.php?id=<?= $vz[(int)$r['id']] ?>">Otevřít servis</a><?php else: ?><a class="btn" href="index.php?nove=1&amp;z=<?= (int)$r['id'] ?>">Převzít</a><?php endif; ?></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>
</main></body></html>
