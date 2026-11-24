<?php
// erkado-do-tralu.php (24. 11. 2026): přenos konfigurace dveří ERKADO z objednávky do TRALu (objednávkový systém ERKADA, tral.cz).
// Ze serveru se do TRALu nic neposílá: stránka připraví odkaz s kódem konfigurace a v TRALu ji načte jejich vlastní konfigurátor
// (záložka „Načíst z kartotéky“ = skript https://bezpecne-dvere.cz/tral/nacti.js). Zboží a zakázku potvrzuje člověk v TRALu.
// ?id=<záznam kartotéky>  nebo  ?h=<otisk konfigurace> (zkouška). Přístup jako kartotéka.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php';
if (!($UZ = uziv_overit($_SERVER['PHP_AUTH_USER'] ?? '', $_SERVER['PHP_AUTH_PW'] ?? ''))) { header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit; }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store'); header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/tral-prenos.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$TRAL = 'https://www.tral.cz/nabidky/create?sklad_id=1';
$ZALOZKA = "javascript:(function(){var s=document.createElement('script');s.src='https://bezpecne-dvere.cz/tral/nacti.js?t='+Date.now();document.head.appendChild(s);})();";
$pdo = db(); $r = null; $cislo = ''; $jmeno = ''; $konf = []; $hashe = [];
if (isset($_GET['id'])) {
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE id=?"); $st->execute([(int)$_GET['id']]); $r = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($r) { $d = json_decode((string)$r['data_json'], true) ?: []; $cislo = (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo']); $jmeno = trim(($d['jmeno'] ?? '') . ' ' . ($d['prijmeni'] ?? ''));
    $hashe = tral_hashe((string)$r['data_json']); $konf = tral_konfigy((string)$r['data_json']); }
} elseif (isset($_GET['h'])) { $k = tral_konfig((string)$_GET['h']); $hashe = [(string)$_GET['h']]; if ($k) $konf[(string)$_GET['h']] = $k; $cislo = 'zkouška'; }
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Přenos do TRALu – Kartotéka</title>
<style>
:root{--ink:#16233a;--mut:#64708a;--line:#e1e6ee;--acc:#1d5fb8}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f3f5f8;color:var(--ink);font-size:15px}
main{max-width:980px;margin:0 auto;padding:16px}a{color:var(--acc)}
.box{background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px 16px;margin-bottom:14px}
h1{font-size:21px;margin:4px 0 10px}h2{font-size:17px;margin:0 0 8px}.mut{color:var(--mut);font-size:13px}
.btn{display:inline-block;background:var(--acc);color:#fff;border:1px solid var(--acc);border-radius:8px;padding:11px 18px;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}
.btn.sec{background:#fff;color:var(--acc);font-weight:600;padding:8px 14px}
.zal{display:inline-block;background:#fff4c2;border:2px dashed #e0a800;border-radius:8px;padding:8px 14px;font-weight:700;color:#7a5200;text-decoration:none;cursor:grab}
table{border-collapse:collapse;width:100%}td{padding:4px 10px 4px 0;border-bottom:1px solid var(--line);vertical-align:top}td:first-child{color:var(--mut);width:42%}
ol{margin:6px 0 0 20px;padding:0}li{margin:5px 0}.var{background:#fde2e2;color:#9b1c1c;padding:9px 12px;border-radius:8px}
details{margin-top:10px}summary{cursor:pointer;color:var(--acc);font-weight:600}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('kartoteka'); ?><main>
<p><a href="kartoteka.php<?= $r ? '?id=' . (int)$r['id'] : '?typ=objednavka_interni' ?>">← zpět<?= $r ? ' na objednávku' : '' ?></a></p>
<h1>🚚 Přenos do TRALu<?= $cislo !== '' ? ' · objednávka ' . $e($cislo) : '' ?><?= $jmeno !== '' ? ' · ' . $e($jmeno) : '' ?></h1>
<?php if (isset($_GET['id']) && !$r): ?><div class="box var">Záznam nenalezen.</div>
<?php elseif (!$konf): ?><div class="box var"><b>K této objednávce není uložená konfigurace pro TRAL.</b><br>
  Přenést jdou jen dveře nakonfigurované v průvodci ERKADO od 24. 11. 2026 (od té doby se ukládá přesný výběr s kódy TRALu). <?= $hashe ? 'Tato objednávka vznikla dřív' : 'Tato objednávka nevznikla v průvodci ERKADO' ?> – v TRALu ji prosím zadejte ručně.</div>
<?php else: ?>
<div class="box"><h2>Jak na to</h2>
  <ol><li>Klikněte na <b>Otevřít TRAL s konfigurací</b> u dveří níže (otevře se nová zakázka v TRALu; musíte tam být přihlášeni).</li>
  <li>V TRALu klikněte v liště záložek na <b>Načíst z kartotéky</b>. Otevře se konfigurátor ocelových dveří a během pár vteřin se sám vyplní.</li>
  <li>Zkontrolujte náhled, rozměry a cenu, pak <b>VYTVOŘIT ZBOŽÍ</b> → <b>Přidat k zakázce</b>. Zákazníka doplníte a zakázku uložíte v TRALu jako obvykle.</li></ol>
  <p class="mut" style="margin:10px 0 0">Do TRALu se odsud nic samo neodesílá a neukládá – vše potvrzujete až tam.</p>
  <details><summary>Poprvé: přidat záložku „Načíst z kartotéky“ (jednou na každém počítači)</summary>
    <p>Zobrazte si v prohlížeči lištu záložek (Ctrl+Shift+B) a <b>přetáhněte do ní myší</b> toto žluté tlačítko: <a class="zal" href="<?= $e($ZALOZKA) ?>" onclick="alert('Tlačítko neklikejte – přetáhněte ho myší do lišty záložek. Kliká se na něj až v TRALu.');return false;">Načíst z kartotéky</a></p>
    <p class="mut">Záložka funguje jen na stránce zakázky v TRALu. Když ji stisknete jinde, jen vám řekne, kam jít.</p></details></div>
<?php foreach ($konf as $h => $k): $kod = tral_kod($k, $cislo); ?>
<div class="box"><h2><?= $e($k['model'] ?: 'Dveře ERKADO') ?><?= !empty($k['rozmer']['w']) ? ' <span class="mut">· zárubeň ' . (int)$k['rozmer']['w'] . ' × ' . (int)$k['rozmer']['h'] . ' mm</span>' : '' ?></h2>
  <p><a class="btn" target="_blank" rel="noopener" href="<?= $e($TRAL . '#bdtral=' . $kod) ?>">Otevřít TRAL s konfigurací</a>
     <button type="button" class="btn sec" data-kod="BDTRAL:<?= $e($kod) ?>" onclick="var b=this;navigator.clipboard.writeText(b.dataset.kod).then(function(){b.textContent='Zkopírováno ✓';},function(){prompt('Zkopírujte kód:',b.dataset.kod);});">Zkopírovat kód</button>
     <span class="mut">(kód je záloha: když záložka v TRALu konfiguraci nenajde, zeptá se na něj)</span></p>
  <details><summary>Co se přenese (<?= count($k['opts']) ?> voleb, uloženo <?= $e(date('j. n. Y H:i', strtotime((string)($k['kdy'] ?? 'now')))) ?>)</summary>
  <table><?php $sk = null; foreach ($k['opts'] as $x): if (preg_match('/^(ne|nie)$/iu', (string)$x['name'])) continue; ?><tr><td><?= $e(($x['group'] !== $sk && (int)$x['groupId'] !== 1 ? $x['group'] . ' – ' : '') . $x['attr']) ?></td><td><b><?= $e($x['name']) ?></b></td></tr><?php endforeach; ?></table></details></div>
<?php endforeach; endif; ?>
</main></body></html>
