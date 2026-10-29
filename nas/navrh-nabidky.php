<?php
// Tlačítko „🤖 Připravit nabídku“ v detailu poptávky (kartoteka.php) → tato stránka.
//   navrh-nabidky.php?id=N        – ukáže „Připravuji…“, zavolá pomocníka (go=1) a přesměruje na předvyplněný formulář nabídky
//   navrh-nabidky.php?id=N&go=1   – JSON {ok, url} nebo {ok:false, chyba}
//   navrh-nabidky.php?prehled=1   – posledních 50 návrhů a jestli z nich vznikla odeslaná nabídka (kontrola „učení“)
// Stejná ochrana jako kartoteka.php (Cloudflare Access přes tunel + jméno a heslo uživatele kartotéky).
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
require_once __DIR__ . '/lib/pomocnik.php'; require_once __DIR__ . '/lib/navrh-nabidky.php'; require_once __DIR__ . '/lib/menu.php';
$pdo = db(); $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); $id = (int)($_GET['id'] ?? 0);

if (isset($_GET['go'])) {
  header('Content-Type: application/json; charset=utf-8'); @set_time_limit(180);
  try { $r = nav_vytvor($pdo, $cfg, $DATA, $id, uziv_jmeno()); }
  catch (Throwable $ex) { $r = ['ok' => false, 'chyba' => 'Chyba: ' . $ex->getMessage()]; }
  if (!empty($r['ok'])) $r = ['ok' => true, 'url' => bd_prod_url($r['url'])];
  else $r = ['ok' => false, 'chyba' => (string)($r['chyba'] ?? 'Neznámá chyba')];
  echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Návrh nabídky – pomocník</title>
<style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#f3f5f8;color:#16233a;font-size:16px}main{max-width:900px;margin:0 auto;padding:20px}
.box{background:#fff;border:1px solid #e1e6ee;border-radius:12px;padding:18px 20px;margin-bottom:14px}.spin{width:44px;height:44px;border:5px solid #dbe6f7;border-top-color:#2563eb;border-radius:50%;animation:t 1s linear infinite;margin:10px auto}
@keyframes t{to{transform:rotate(360deg)}}.err{background:#fde2e2;color:#9b1c1c;padding:10px 14px;border-radius:8px}a{color:#1d5fb8}.btn{display:inline-block;background:#1d5fb8;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none}
table{width:100%;border-collapse:collapse;font-size:14px}td,th{padding:7px 8px;border-bottom:1px solid #e1e6ee;text-align:left;vertical-align:top}th{color:#64708a;font-size:12px;text-transform:uppercase}</style></head>
<body><?php bd_bar('kartoteka'); ?><main>
<?php if (isset($_GET['prehled'])):
  nav_tabulka($pdo);
  $rows = $pdo->query('SELECT n.*, (SELECT cislo FROM zadosti z WHERE z.typ=\'nabidka\' AND z.data_json LIKE \'%"ai_navrh":"\' || n.id || \'"%\' ORDER BY z.id DESC LIMIT 1) nabidka FROM pomocnik_navrhy n ORDER BY n.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="box"><h2 style="margin-top:0">🤖 Návrhy nabídek od pomocníka</h2>
  <p>Když z návrhu vznikne odeslaná nabídka, pomocník ji příště dostane jako příklad (co navrhl × co jste nakonec poslali) a podle rozdílů se zlepšuje. Obecná pravidla mu napište do <a href="pomocnik-nastaveni.php">Nastavení pomocníka</a> → Doplňující pokyny.</p>
  <table><tr><th>Kdy</th><th>Poptávka</th><th>Návrh</th><th>Odeslaná nabídka</th></tr>
  <?php foreach ($rows as $r): $v = json_decode((string)$r['navrh_json'], true) ?: []; ?>
    <tr><td><?= $e(substr((string)$r['cas'], 0, 16)) ?><br><span style="color:#64708a"><?= $e($r['uzivatel']) ?></span></td>
    <td><a href="kartoteka.php?id=<?= (int)$r['zadost_id'] ?>"><?= $e($r['poptavka_cislo'] ?: 'záznam ' . $r['zadost_id']) ?></a></td>
    <td><?= $e($v['shrnuti'] ?? '') ?><br><span style="color:#64708a"><?= $e(($v['kategorie'] ?? '') . ': ' . implode(', ', array_map(fn($p) => trim(($p['ks'] ?? 1) . '× ' . (($p['model'] ?? '') ?: ($p['nazev'] ?? '')) . ' ' . ($p['odstin'] ?? '')), (array)($v['polozky'] ?? [])) ?: [trim(($v['byt']['model_kod'] ?? '') . ' ' . ($v['byt']['odstin'] ?? ''))])) ?></span></td>
    <td><?= $r['nabidka'] ? '✅ ' . $e($r['nabidka']) : '<span style="color:#64708a">zatím ne</span>' ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="4" style="color:#64708a">Zatím žádný návrh.</td></tr><?php endif; ?></table></div>
<?php else: ?>
  <div class="box" style="text-align:center" id="stav"><h2 style="margin-top:0">🤖 Pomocník připravuje návrh nabídky…</h2><div class="spin"></div>
  <p>Čte poptávku a vybírá modely z našeho katalogu. Obvykle to trvá 10–40 sekund. Nic se neodesílá – otevře se formulář nabídky k vaší kontrole.</p></div>
  <script>
  (function () {
    var box = document.getElementById('stav');
    fetch('navrh-nabidky.php?id=<?= $id ?>&go=1', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.ok && j.url) { location.href = j.url; return; }
      box.innerHTML = '<div class="err">' + String(j.chyba || 'Návrh se nepovedl.').replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }) + '</div><p><a class="btn" href="navrh-nabidky.php?id=<?= $id ?>">Zkusit znovu</a> <a href="kartoteka.php?id=<?= $id ?>">← zpět na poptávku</a></p>';
    }).catch(function () { box.innerHTML = '<div class="err">Spojení se nepovedlo.</div><p><a class="btn" href="navrh-nabidky.php?id=<?= $id ?>">Zkusit znovu</a> <a href="kartoteka.php?id=<?= $id ?>">← zpět na poptávku</a></p>'; });
  })();
  </script>
<?php endif; ?></main></body></html>
