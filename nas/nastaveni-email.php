<?php
// Nastavení e-mailů kartotéky (Správa → ✉️ E-maily a příjemci). Jen administrátor.
// Komu ve firmě chodí jednotlivé typy e-mailů, účetní e-mail, odesílatel a číslo účtu pro zálohy.
// Ukládá do /data/nastaveni.json (lib/nastaveni.php); start.php to přepíše přes $cfg. 10. 11. 2026.
require __DIR__ . '/lib/start.php';
if (pres_tunel()) {
  $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
  $povolenyHost = strtolower((string)($cfg['kartoteka_host'] ?? 'kartoteka.dvere.app'));
  if ($host !== $povolenyHost || empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) { http_response_code(404); exit('Not found'); }
}
require_once __DIR__ . '/lib/uzivatele.php'; $u = $_SERVER['PHP_AUTH_USER'] ?? ''; $pw = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!($UZ = uziv_overit($u, $pw))) { header('WWW-Authenticate: Basic realm="Kartotéka"'); http_response_code(401); echo 'Přihlášení vyžadováno'; exit; }
if (!uziv_admin()) { http_response_code(403); exit('Jen pro administrátora.'); }
require_once __DIR__ . '/lib/nastaveni.php';
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// hodnoty z configu (už přepsané nastavením) – předvyplnění polí = aktuálně platný stav
$ef = fn($typ, $k) => nast_emaily($cfg['prijemci'][$typ][$k] ?? []);
$zprava = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $n = nast_nacti();
  $pr = [];
  foreach (NAST_TYPY as $typ => $_x) {
    $maTo = $typ !== 'objednavka_zakaznik';
    $row = [];
    if ($maTo) { $to = nast_emaily($_POST['to'][$typ] ?? ''); $row['to'] = $to ?: $ef($typ, 'to'); }   // „komu" nenecháme prázdné – zůstane dosavadní
    $row['cc'] = nast_emaily($_POST['cc'][$typ] ?? '');
    $pr[$typ] = $row;
  }
  $n['prijemci'] = $pr;
  $n['ucetni'] = nast_emaily($_POST['ucetni'] ?? '') ?: nast_ucetni($cfg);
  $from = trim((string)($_POST['from'] ?? '')); $n['from'] = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : (string)($cfg['from'] ?? '');
  $ucet = nast_ucet_ocisti((string)($_POST['ucet'] ?? ''));
  if ($ucet !== '') $n['ucet'] = $ucet; elseif (trim((string)($_POST['ucet'] ?? '')) === '') unset($n['ucet']);
  $n['zaloha'] = [                                           // hlídání nezaplacené zálohy (lhůta + opakování)
    'lhuta' => max(1, min(60, (int)($_POST['zal_lhuta'] ?? 4))),
    'opak' => !empty($_POST['zal_opak']),
    'opak_dnu' => max(1, min(60, (int)($_POST['zal_opak_dnu'] ?? 2))),
    'opak_max' => max(0, min(10, (int)($_POST['zal_opak_max'] ?? 2))),
  ];
  $ok = nast_uloz($n);
  $badUcet = trim((string)($_POST['ucet'] ?? '')) !== '' && $ucet === '';
  header('Location: nastaveni-email.php?ulozeno=' . ($ok ? ($badUcet ? 'ucet' : '1') : '0')); exit;
}
// znovu načteme s právě uloženými hodnotami (po redirectu)
nast_merge($cfg);
$ucetni = implode(', ', nast_ucetni($cfg));
$ucet = nast_ucet($cfg);
$zal = nast_zaloha($cfg);
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<link rel="icon" href="kartoteka-192.png"><title>Nastavení e-mailů – Kartotéka</title><style>
:root{--bg:#f3f5f8;--card:#fff;--ink:#16233a;--mut:#64708a;--line:#e1e6ee;--acc:#1d5fb8}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--ink);font-size:15px}
main{max-width:820px;margin:0 auto;padding:16px}a{color:var(--acc)}
.box{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px 18px;margin-bottom:14px}
h1{font-size:21px;margin:.2rem 0 .2rem}h2{font-size:16px;margin:0 0 4px}.mut{color:var(--mut);font-size:13.5px}
label{display:block;font-weight:600;margin:10px 0 3px}textarea,input[type=text],input[type=email]{width:100%;box-sizing:border-box;font:inherit;padding:9px 10px;border:1px solid #c9d1de;border-radius:8px;background:#fff;color:var(--ink)}
textarea{min-height:46px;resize:vertical}
.lbl{font-weight:400;color:var(--mut);font-size:13px;font-weight:400}
button{background:var(--acc);color:#fff;border:0;border-radius:9px;padding:11px 20px;font:600 15px system-ui,Arial,sans-serif;cursor:pointer}
.ok{background:#d9f2e0;color:#14653a;padding:10px 14px;border-radius:9px;margin-bottom:12px}.err{background:#fde2e2;color:#9b1c1c;padding:10px 14px;border-radius:9px;margin-bottom:12px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}@media(max-width:620px){.two{grid-template-columns:1fr}}
</style></head><body><?php require_once __DIR__ . '/lib/menu.php'; bd_bar('nastaveni'); ?><main>
  <h1>✉️ E-maily a příjemci</h1>
  <p class="mut">Nastavení, komu ve firmě chodí které e-maily a odkud se posílají. Ukládá se zvlášť od config.php – co tu necháte prázdné, zůstane podle původního nastavení. Adresy oddělujte čárkou nebo každou na nový řádek.</p>
  <?php if (($_GET['ulozeno'] ?? '') === '1'): ?><div class="ok">Nastavení uloženo.</div><?php endif; ?>
  <?php if (($_GET['ulozeno'] ?? '') === 'ucet'): ?><div class="ok">Nastavení uloženo. <b>Pozor:</b> číslo účtu nemělo platný tvar (např. 1821576001/5500), proto zůstalo původní.</div><?php endif; ?>
  <?php if (($_GET['ulozeno'] ?? '') === '0'): ?><div class="err">Nastavení se nepodařilo uložit (zápis do /data/nastaveni.json).</div><?php endif; ?>
  <form method="post">
  <div class="box"><h2>Kdo co dostává</h2><p class="mut" style="margin:.2rem 0 .6rem">U každého typu: <b>Komu</b> = hlavní příjemci ve firmě, <b>Skrytá kopie</b> = další adresy do kopie (zákazník je nevidí).</p>
    <?php foreach (NAST_TYPY as $typ => [$nazev, $popis]): $maTo = $typ !== 'objednavka_zakaznik'; ?>
    <div style="border-top:1px solid var(--line);padding-top:10px;margin-top:10px">
      <b><?= $e($nazev) ?></b> <span class="mut"><?= $e($popis) ?></span>
      <div class="two">
        <?php if ($maTo): ?><div><label>Komu <span class="lbl">(hlavní příjemci)</span></label><textarea name="to[<?= $e($typ) ?>]" placeholder="napr@firma.cz, dalsi@firma.cz"><?= $e(implode(", ", $ef($typ, 'to'))) ?></textarea></div><?php endif; ?>
        <div<?= $maTo ? '' : ' style="grid-column:1/-1"' ?>><label><?= $maTo ? 'Skrytá kopie' : 'Skrytá kopie zákaznických e-mailů' ?> <span class="lbl">(volitelné)</span></label><textarea name="cc[<?= $e($typ) ?>]" placeholder="kopie@firma.cz"><?= $e(implode(", ", $ef($typ, 'cc'))) ?></textarea></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="box"><h2>Účetní</h2><p class="mut" style="margin:.2rem 0 .5rem">Komu chodí týdenní podklady pro účetnictví a upozornění „záloha nedorazila, zavolejte zákazníkovi".</p>
    <label>E-mail účetní</label><textarea name="ucetni" placeholder="ucetni@firma.cz"><?= $e($ucetni) ?></textarea></div>
  <div class="box"><h2>Odesílatel a účet</h2>
    <label>Odesílatel e-mailů <span class="lbl">(adresa „Od")</span></label><input type="email" name="from" value="<?= $e((string)($cfg['from'] ?? '')) ?>" placeholder="chci@bezpecne-dvere.cz">
    <label>Číslo účtu pro zálohy</label><input type="text" name="ucet" value="<?= $e($ucet) ?>" placeholder="1821576001/5500">
    <p class="mut" style="margin:.4rem 0 0">Tento účet se použije v upozornění na nezaplacenou zálohu. <b>Pozor:</b> QR kód a účet v samotné objednávce na webu se berou z webové stránky (platba.js) a tímto se nemění – při změně banky je potřeba upravit i tam (napište mi).</p></div>
  <div class="box"><h2>Hlídání nezaplacené zálohy</h2><p class="mut" style="margin:.2rem 0 .6rem">U objednávek se zálohou placenou převodem: když záloha do nastavené lhůty nedorazí na účet, systém pošle interní upozornění (účetní + příjemci „Objednávky (firmě)") a vlídnou připomínku zákazníkovi s údaji k platbě. Běží ráno přes Plánovač, spolu s připomínkami nabídek.</p>
    <div class="two">
      <div><label>Lhůta do první připomínky <span class="lbl">(dnů od objednání)</span></label><input type="text" inputmode="numeric" name="zal_lhuta" value="<?= $e($zal['lhuta']) ?>" placeholder="4"></div>
      <div><label>Opakovat připomínku zákazníkovi</label><label style="font-weight:400;margin:6px 0 0"><input type="checkbox" name="zal_opak" value="1"<?= $zal['opak'] ? ' checked' : '' ?>> ano, posílat opakovaně (dokud záloha nedorazí)</label></div>
      <div><label>Opakovat po <span class="lbl">(dnů)</span></label><input type="text" inputmode="numeric" name="zal_opak_dnu" value="<?= $e($zal['opak_dnu']) ?>" placeholder="2"></div>
      <div><label>Max. počet opakování <span class="lbl">(navíc k prvnímu)</span></label><input type="text" inputmode="numeric" name="zal_opak_max" value="<?= $e($zal['opak_max']) ?>" placeholder="2"></div>
    </div>
    <p class="mut" style="margin:.5rem 0 0">Interní upozornění firmě/účetní jde jen jednou (poprvé). Opakuje se pouze vlídná připomínka zákazníkovi – nejvýš nastavený početkrát a jen dokud záloha nedorazí nebo se objednávka nestornuje.</p></div>
  <p><button>Uložit nastavení</button></p>
  </form>
</main></body></html>
