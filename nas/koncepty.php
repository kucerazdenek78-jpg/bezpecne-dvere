<?php
// Kartotéka → 📝 Rozpracované: nabídky a objednávky (interiér / dům) uložené na webu bez odeslání.
// „Pokračovat“ otevře formulář na webu s uloženými údaji; po odeslání se koncept sám označí jako odeslaný.
// Ochrana stejná jako kartoteka.php (Cloudflare Access přes tunel + jméno a heslo uživatele kartotéky).
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php';
if (!($UZ = uziv_overit($_SERVER['PHP_AUTH_USER'] ?? '', $_SERVER['PHP_AUTH_PW'] ?? ''))) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
require_once __DIR__ . '/lib/koncepty.php'; require_once __DIR__ . '/lib/menu.php';
$pdo = db(); konc_tabulka($pdo); $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'smazat') {
  $pdo->prepare("UPDATE koncepty SET stav='smazano', upraveno=? WHERE id=?")->execute([date('Y-m-d H:i:s'), (int)($_POST['id'] ?? 0)]);
  header('Location: koncepty.php?smazano=1'); exit;
}
$vse = isset($_GET['vse']);
$rows = $pdo->query("SELECT * FROM koncepty WHERE stav " . ($vse ? "<>'smazano'" : "='rozpracovano'") . " ORDER BY upraveno DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
$kc = fn($n) => ($n === null || $n === '' || !is_numeric($n) || (float)$n == 0) ? '–' : number_format((float)$n, 0, ',', ' ') . ' Kč';
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rozpracované – Bezpečné dveře</title>
<style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f3f5f8;color:#16233a;font-size:15px}main{max-width:1100px;margin:0 auto;padding:16px}
.box{background:#fff;border:1px solid #e1e6ee;border-radius:10px;padding:14px 16px;margin-bottom:14px}a{color:#1d5fb8}.btn{display:inline-block;background:#1d5fb8;color:#fff;border:1px solid #1d5fb8;padding:7px 14px;border-radius:7px;text-decoration:none;font:inherit;cursor:pointer}
.btn.sec{background:#fff;color:#a11;border-color:#e3b7b7}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e1e6ee}th,td{padding:9px 10px;border-bottom:1px solid #e1e6ee;text-align:left;vertical-align:top}
th{background:#eef2f8;font-size:12px;text-transform:uppercase;color:#64708a}.mut{color:#64708a;font-size:13px}.ok{background:#d9f2e0;color:#14653a;padding:8px 12px;border-radius:8px;margin-bottom:12px}
.chip{display:inline-block;padding:2px 9px;border-radius:99px;font-size:12px;font-weight:700;background:#fff3c4;color:#7a5b00}.chip.od{background:#d9f2e0;color:#14653a}
@media(max-width:700px){.hide-m{display:none}}</style></head><body><?php bd_bar('koncepty'); ?><main>
<?php if (isset($_GET['smazano'])): ?><div class="ok">Rozpracovaná nabídka byla odstraněna ze seznamu.</div><?php endif; ?>
<div class="box"><h2 style="margin:0 0 6px">📝 Rozpracované nabídky a objednávky</h2>
<p class="mut" style="margin:0">Uložené tlačítkem „💾 Uložit rozpracovanou“ ve formuláři nabídky (nebo objednávky interiér / dům). Zákazníkovi nic neodešlo. „Pokračovat“ otevře formulář s uloženými údaji; po odeslání nabídka z tohoto seznamu sama zmizí. Ukládá se i PDF rozpočtu z jiného programu a vlastní obrázky.
<?= $vse ? '<a href="koncepty.php">Jen rozpracované</a>' : '<a href="koncepty.php?vse=1">Zobrazit i odeslané</a>' ?></p></div>
<table><tr><th>Uloženo</th><th>Druh</th><th>Číslo</th><th>Zákazník</th><th class="hide-m">Dveře</th><th class="hide-m">Cena</th><th class="hide-m">Prodavač</th><th></th></tr>
<?php foreach ($rows as $k): ?><tr>
  <td class="mut"><?= $e(date('j. n. H:i', strtotime((string)$k['upraveno']))) ?></td>
  <td><?= $k['druh'] === 'objednavka-vice' ? 'Objednávka' : 'Nabídka' ?><?= $k['stav'] === 'odeslano' ? ' <span class="chip od">odesláno</span>' : ' <span class="chip">rozpracováno</span>' ?></td>
  <td><?= $e($k['cislo'] ?: '–') ?></td><td><?= $e($k['zakaznik'] ?: '(bez jména)') ?></td>
  <td class="hide-m"><?= $e($k['kategorie']) ?></td><td class="hide-m"><?= $e($kc($k['cena'])) ?></td><td class="hide-m"><?= $e($k['prodavac']) ?></td>
  <td style="white-space:nowrap"><?php if ($k['stav'] === 'rozpracovano'): ?><a class="btn" href="<?= $e(bd_prod_url(konc_url($cfg, $k))) ?>">Pokračovat</a>
    <form method="post" style="display:inline" onsubmit="return confirm('Opravdu odstranit tuto rozpracovanou nabídku?')"><input type="hidden" name="akce" value="smazat"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn sec">Smazat</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="mut">Žádné rozpracované nabídky.</td></tr><?php endif; ?></table>
</main></body></html>
