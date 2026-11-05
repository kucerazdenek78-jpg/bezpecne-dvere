<?php
// Správa → Pomocník AI na webu: vypínač webového pomocníka (konfigurátor dveří do domu, pomocnik-web.php) a přehled využití. Jen administrátor.
// Vypínač zapisuje web_zapnuto do /data/pomocnik.json (stejný soubor jako nastavení interního pomocníka); klíč ke Claude se nastavuje v pomocnik-nastaveni.php.
// Patří na NAS do /docker/formulare/app/web-pomocnik.php.
require __DIR__ . '/lib/start.php'; require_once __DIR__ . '/lib/pomocnik.php';
pom_overit($cfg);
if (function_exists('uziv_jen_admin')) uziv_jen_admin('Tuto stránku smí otevřít jen administrátor.');
$pdo = db(); pom_tabulky($pdo);
$pdo->exec("CREATE TABLE IF NOT EXISTS pomocnik_web (token TEXT PRIMARY KEY, cas INTEGER, ip TEXT, zprav INTEGER DEFAULT 0, posledni INTEGER DEFAULT 0)");
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$zprava = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]); $orig = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
  if ($orig !== '' && strtolower((string)parse_url($orig, PHP_URL_HOST)) !== $host) { http_response_code(403); exit('Nepovolený požadavek.'); }
  $ulozene = is_file(pom_soubor($DATA)) ? (json_decode((string)file_get_contents(pom_soubor($DATA)), true) ?: []) : [];
  $nove = array_merge(POM_VYCHOZI, $ulozene);
  $nove['web_zapnuto'] = (($_POST['akce'] ?? '') === 'zapnout');
  $zprava = pom_ulozit($DATA, $nove) ? ($nove['web_zapnuto'] ? 'Pomocník na webu je zapnutý.' : 'Pomocník na webu je vypnutý. Zákazníci uvidí ikonu, ale pomocník odpoví, že je dočasně nedostupný.') : 'Uložení se nepodařilo (zápis do /data).';
  header('Location: web-pomocnik.php?z=' . rawurlencode($zprava)); exit;
}
if (isset($_GET['z'])) $zprava = mb_substr((string)$_GET['z'], 0, 300);
$n = pom_nastaveni($DATA);
$zap = !array_key_exists('web_zapnuto', $n) || !empty($n['web_zapnuto']);
$maKlic = trim((string)$n['api_klic']) !== '';
$dnes = date('Y-m-d');
$st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(usd),0) usd FROM pomocnik_log WHERE druh='web' AND den=? AND (chyba IS NULL OR chyba='')"); $st->execute([$dnes]); $dn = $st->fetch(PDO::FETCH_ASSOC);
$st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(usd),0) usd FROM pomocnik_log WHERE druh='web' AND den>=? AND (chyba IS NULL OR chyba='')"); $st->execute([date('Y-m-d', strtotime('-29 days'))]); $d30 = $st->fetch(PDO::FETCH_ASSOC);
$st = $pdo->prepare('SELECT COUNT(*) FROM pomocnik_web WHERE cas>?'); $st->execute([strtotime('today')]); $rozhDnes = (int)$st->fetchColumn();
$chyby = $pdo->query("SELECT cas, chyba FROM pomocnik_log WHERE druh='web' AND chyba IS NOT NULL AND chyba<>'' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
if (is_file(__DIR__ . '/lib/menu.php')) { require_once __DIR__ . '/lib/menu.php'; if (function_exists('bd_bar')) bd_bar('webpom'); }
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="icon" href="kartoteka-192.png"><title>Pomocník AI na webu – Bezpečné dveře</title><style>
body{font:16px/1.5 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:760px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{margin:0 0 10px}h2{margin:0 0 8px;font-size:18px}.mut{color:#6b7787;font-size:14px}
button{border:0;border-radius:10px;padding:12px 20px;font:inherit;font-weight:600;color:#fff;cursor:pointer}.zap{background:#16a34a}.vyp{background:#dc2626}
.ok{background:#e6f6ea;border:1px solid #9bd2a8;border-radius:10px;padding:10px 14px;margin-bottom:14px}.stav{font-size:20px;font-weight:700}
table{border-collapse:collapse;width:100%}td,th{padding:6px 8px;border-bottom:1px solid #e3e8ef;text-align:left}td.r,th.r{text-align:right}a{color:#1f5fbf}
</style></head><body><main>
<h1>🤖 Pomocník AI na webu</h1>
<?php if ($zprava): ?><div class="ok"><?= $e($zprava) ?></div><?php endif; ?>
<div class="box">
  <div class="stav"><?= $zap ? '🟢 Zapnutý' : '🔴 Vypnutý' ?></div>
  <p class="mut">Plovoucí ikona dveří „AI“ v konfigurátoru dveří do domu na bezpecne-dvere.cz. Zákazník po zadání telefonu, e-mailu a souhlasu mluví s pomocníkem, který mu dveře nastaví v konfigurátoru. Po vypnutí ikona zůstane, ale pomocník odpoví, že je dočasně nedostupný; konfigurátor funguje dál.</p>
  <?php if (!$maKlic): ?><p><b>⚠️ Chybí klíč ke Claude</b> – pomocník zatím neodpovídá. Vložte ho v <a href="pomocnik-nastaveni.php">Nastavení pomocníka</a>.</p><?php endif; ?>
  <form method="post"><?php if ($zap): ?><button class="vyp" name="akce" value="vypnout">Vypnout pomocníka na webu</button><?php else: ?><button class="zap" name="akce" value="zapnout">Zapnout pomocníka na webu</button><?php endif; ?></form>
</div>
<div class="box"><h2>Využití</h2>
  <table><tr><th>Období</th><th class="r">Odpovědí</th><th class="r">Odhad ceny</th></tr>
  <tr><td>Dnes (zahájených rozhovorů: <?= $rozhDnes ?>)</td><td class="r"><?= (int)$dn['n'] ?></td><td class="r">$<?= number_format((float)$dn['usd'], 2, ',', ' ') ?></td></tr>
  <tr><td>Posledních 30 dní</td><td class="r"><?= (int)$d30['n'] ?></td><td class="r">$<?= number_format((float)$d30['usd'], 2, ',', ' ') ?></td></tr></table>
  <p class="mut">Limity: 6 rozhovorů za hodinu z jedné adresy, 150 rozhovorů za den, 40 zpráv v rozhovoru, 600 odpovědí denně celkem. Cena je odhad podle ceníku Anthropic, skutečnou útratu vidíte na platform.claude.com.</p>
  <?php if ($chyby): ?><p><b>Poslední chyby:</b></p><ul class="mut"><?php foreach ($chyby as $c): ?><li><?= $e($c['cas']) ?>: <?= $e($c['chyba']) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <p><a href="pomocnik-nastaveni.php">Nastavení klíče a modelu →</a></p>
</div>
</main></body></html>
