<?php
// Číslování (jen admin): ruční nastavení počátku řady – číslo poslední použité objednávky / nabídky / poptávky pro daný rok.
// Další číslo pak bude o 1 vyšší (např. poslední 85 → další 86/2026). Číslo se nikdy nevrací pod nejvyšší už existující.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php';
$u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) {
  header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit;
}
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
require_once __DIR__ . '/lib/cisla.php';
$pdo = db(); uziv_init($pdo); cisla_init($pdo);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$admin = uziv_admin(); $msg = ''; $err = '';
$T = cisla_typy();
$rok = (int)($_POST['rok'] ?? $_GET['rok'] ?? date('Y'));
if ($rok < 2020 || $rok > 2100) $rok = (int)date('Y');

if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'ulozit') {
  $zmeny = []; $chyby = [];
  foreach ($T as $k => $def) {
    $raw = trim((string)($_POST['posledni'][$k] ?? ''));
    if ($raw === '') {   // prázdné = zpět na automatiku (nejvyšší existující + 1)
      $pdo->prepare('DELETE FROM cisla_start WHERE typ=? AND rok=?')->execute([$k, $rok]); continue;
    }
    if (!preg_match('/^\d{1,6}$/', $raw)) { $chyby[] = $def[0] . ': zadejte jen číslo (např. 85).'; continue; }
    $pdo->prepare('INSERT INTO cisla_start (typ, rok, posledni, zmeneno, kdo) VALUES (?,?,?,?,?) ON CONFLICT(typ, rok) DO UPDATE SET posledni=excluded.posledni, zmeneno=excluded.zmeneno, kdo=excluded.kdo')
        ->execute([$k, $rok, (int)$raw, date('Y-m-d H:i'), $UZ['jmeno']]);
    $zmeny[] = $def[0];
  }
  if ($chyby) $err = implode(' ', $chyby); else $msg = 'Uloženo.';
}
// aktuální stav řad
$stav = [];
foreach ($T as $k => $def) {
  $v = cisla_spocitej($pdo, $cfg, $k, $rok);
  $q = $pdo->prepare('SELECT posledni, zmeneno, kdo FROM cisla_start WHERE typ=? AND rok=?'); $q->execute([$k, $rok]); $r = $q->fetch(PDO::FETCH_ASSOC);
  $stav[$k] = $v + ['rucne' => $r ?: null];
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="icon" href="kartoteka-192.png"><title>Číslování – Bezpečné dveře</title><style>
body{font:15px/1.45 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}
main{max-width:760px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:10px;padding:14px 16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}
button{background:#1f5fbf;color:#fff;border:0;border-radius:8px;padding:9px 15px;font:inherit;cursor:pointer}
.mut{color:#6b7787;font-size:13px}
input{font:inherit;padding:8px 10px;border:1px solid #c9d1dc;border-radius:8px;box-sizing:border-box;max-width:100%}
.ok{background:#d9f2e0;border-radius:8px;padding:8px 12px;margin-bottom:10px}.err{background:#fde0e0;border-radius:8px;padding:8px 12px;margin-bottom:10px}
h1{font-size:22px;margin:4px 0 12px}h3{margin:0 0 4px}
.rad{display:grid;grid-template-columns:1fr 150px;gap:10px;align-items:center;padding:12px 0;border-top:1px solid #e4e8ee}.rad:first-of-type{border-top:0}
.dal{font-size:20px;font-weight:700;color:#14653a}
@media (max-width:560px){.rad{grid-template-columns:1fr}}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('cisla'); ?><main>
<h1>Číslování</h1>
<?php if ($msg): ?><div class="ok"><?= $e($msg) ?></div><?php endif; ?><?php if ($err): ?><div class="err"><?= $e($err) ?></div><?php endif; ?>
<?php if (!$admin): ?>
<div class="box">Číslování může nastavit jen administrátor.</div>
<?php else: ?>
<div class="box">
  <p style="margin:0 0 10px">Zadejte <b>číslo poslední použité</b> objednávky, nabídky nebo poptávky v roce <b><?= $rok ?></b>. Další číslo bude o 1 vyšší (poslední 85 → další <b>86/<?= $rok ?></b>). Prázdné pole = řada se čísluje automaticky podle nejvyššího čísla v kartotéce.</p>
  <form method="post"><input type="hidden" name="akce" value="ulozit"><input type="hidden" name="rok" value="<?= $rok ?>">
  <?php foreach ($T as $k => $def): $s = $stav[$k]; ?>
    <div class="rad">
      <div><h3><?= $e($def[0]) ?> <span class="mut">(tvar <?= $e($def[4]) ?>)</span></h3>
        <div>Další číslo bude: <span class="dal"><?= $e($s['pre'] . $s['dalsi'] . '/' . $rok) ?></span></div>
        <div class="mut">Nejvyšší už v kartotéce: <?= $s['max'] > 0 ? $e($s['pre'] . $s['max'] . '/' . $rok) : 'žádné' ?> · počátek: <?= $e($s['zdroj']) ?><?= $s['rucne'] ? ' (' . $e($s['rucne']['kdo']) . ', ' . $e($s['rucne']['zmeneno']) . ')' : '' ?></div>
        <?php if ($s['rucne'] && $s['max'] > (int)$s['rucne']['posledni']): ?><div class="mut" style="color:#8a1c1c">V kartotéce už existuje vyšší číslo, proto se čísluje od něj (číslo se nevrací zpět).</div><?php endif; ?></div>
      <label class="mut">Poslední použité<br><input name="posledni[<?= $e($k) ?>]" inputmode="numeric" pattern="\d{1,6}" maxlength="6" placeholder="automaticky" value="<?= $s['rucne'] ? (int)$s['rucne']['posledni'] : '' ?>"></label>
    </div>
  <?php endforeach; ?>
  <p style="margin:12px 0 0"><button>Uložit</button></p></form>
</div>
<div class="box mut">Nastavení platí pro formuláře na webu i v kartotéce (nabídka, objednávka, poptávka). Prodavač může číslo ve formuláři dál přepsat ručně. Od 1. 1. se řady samy začnou od 1/<?= (int)date('Y') + 1 ?>. Variabilní symbol se tvoří z číslic čísla objednávky (86/<?= $rok ?> → 86<?= $rok ?>).</div>
<?php endif; ?>
</main></body></html>
