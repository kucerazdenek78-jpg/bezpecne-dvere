<?php
// Zámek záznamu po přijaté záloze + historie změn (kartotéka).
// Po přijetí zálohy jde měnit jen stav a interní poznámka. Odemknout lze jen heslem kartotéky (na 30 minut),
// každé odemknutí, zamknutí a změna zámčených údajů se zapíše do tabulky historie.
// Administrátor smí i u zamčeného záznamu BEZ odemčení: smazat fotku, uložit/vymazat zaměření a smazat celý záznam (2. 11. 2026).

const ZAMEK_MINUT = 30;

function zamek_init($pdo) {
  $pdo->exec('CREATE TABLE IF NOT EXISTS historie (id INTEGER PRIMARY KEY AUTOINCREMENT, zadost_id INTEGER NOT NULL, cas TEXT NOT NULL, akce TEXT NOT NULL, popis TEXT)');
  $pdo->exec('CREATE INDEX IF NOT EXISTS historie_zadost ON historie(zadost_id)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS odemceno (zadost_id INTEGER PRIMARY KEY, do_cas INTEGER NOT NULL)');
}

function hist_pis($pdo, $id, $akce, $popis = '') {
  $pdo->prepare('INSERT INTO historie (zadost_id, cas, akce, popis) VALUES (?,?,?,?)')->execute([(int)$id, date('Y-m-d H:i:s'), $akce, mb_substr((string)$popis . (function_exists('uziv_jmeno') && uziv_jmeno() !== '' ? ' [' . uziv_jmeno() . ']' : ''), 0, 300)]);
}

// záloha přijata (záznam je hlídaný)?
function zamek_hlidany($pdo, $id) {
  $st = $pdo->prepare('SELECT zaloha_prijata_dne FROM zadosti WHERE id=?'); $st->execute([(int)$id]);
  return trim((string)$st->fetchColumn()) !== '';
}

function zamek_odemceno_do($pdo, $id) {
  $st = $pdo->prepare('SELECT do_cas FROM odemceno WHERE zadost_id=?'); $st->execute([(int)$id]);
  $t = (int)$st->fetchColumn();
  return $t > time() ? $t : 0;
}

// true = záznam je zamčený (záloha přijata a není odemčeno)
function zamceno($pdo, $id) { return zamek_hlidany($pdo, $id) && !zamek_odemceno_do($pdo, $id); }

// Smazání celého záznamu (jen administrátor, i zamčeného). Před smazáním se uloží záloha databáze.
// Přílohy na disku (fotky, zaměření, PDF) zůstávají, maže se jen záznam.
function zamek_smazat($pdo) {
  $id = (int)($_POST['id'] ?? 0);
  $zpet = fn($z) => 'Location: kartoteka.php?id=' . $id . '&zprava=' . $z . '#smazat';
  if (!function_exists('uziv_admin') || !uziv_admin()) { header($zpet('smaz_admin')); exit; }
  $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$id]); $z = $st->fetch(PDO::FETCH_ASSOC);
  if (!$z) { header('Location: kartoteka.php'); exit; }
  // před smazáním se znovu potvrdí heslem přihlášeného administrátora
  if (!uziv_overit($GLOBALS['UZ']['login'] ?? '', (string)($_POST['heslo'] ?? ''))) {
    hist_pis($pdo, $id, 'smazání zamítnuto', 'neplatné heslo');
    sleep(2); header($zpet('smaz_heslo')); exit;
  }
  $zaloha = $GLOBALS['DATA'] . '/zaloha-pred-smazanim-' . date('Ymd-His') . '.sqlite';
  try { $pdo->exec('VACUUM INTO ' . $pdo->quote($zaloha)); } catch (Throwable $x) { @copy($GLOBALS['DATA'] . '/formulare.sqlite', $zaloha); }
  if (!is_file($zaloha) || filesize($zaloha) < 1000) { header($zpet('smaz_zaloha')); exit; }
  hist_pis($pdo, $id, 'záznam smazán', trim($z['typ'] . ' ' . $z['cislo'] . ', záloha ' . basename($zaloha)));
  foreach (['DELETE FROM servis WHERE zadost_id=?', 'UPDATE platby SET zadost_id=NULL WHERE zadost_id=?', 'UPDATE so_jed SET zadost_id=NULL WHERE zadost_id=?', 'DELETE FROM odemceno WHERE zadost_id=?'] as $sql) {
    try { $pdo->prepare($sql)->execute([$id]); } catch (Throwable $x) {}
  }
  $pdo->prepare('DELETE FROM zadosti WHERE id=?')->execute([$id]);
  header('Location: kartoteka.php?typ=' . urlencode((string)$z['typ']) . '&smazano=' . urlencode((string)$z['cislo'])); exit;
}

// Odemknutí / zamknutí (POST akce=odemknout|zamknout) a smazání záznamu (akce=smazat_zaznam)
function zamek_obsluz($pdo, $cfg) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  $akce = $_POST['akce'] ?? '';
  if ($akce === 'smazat_zaznam') zamek_smazat($pdo);
  if ($akce !== 'odemknout' && $akce !== 'zamknout') return;
  $id = (int)($_POST['id'] ?? 0);
  if (!zamek_hlidany($pdo, $id)) { header('Location: kartoteka.php?id=' . $id); exit; }
  if ($akce === 'zamknout') {
    $pdo->prepare('DELETE FROM odemceno WHERE zadost_id=?')->execute([$id]);
    hist_pis($pdo, $id, 'zamčeno', 'ručně');
    header('Location: kartoteka.php?id=' . $id . '&zprava=zamknuto'); exit;
  }
  if (!uziv_admin()) { hist_pis($pdo, $id, 'odemčení zamítnuto', 'jen administrátor'); header('Location: kartoteka.php?id=' . $id . '&zprava=admin'); exit; }
  $h = (string)($_POST['heslo'] ?? '');
  if (uziv_overit($GLOBALS['UZ']['login'] ?? '', $h)) {
    $pdo->prepare('INSERT OR REPLACE INTO odemceno (zadost_id, do_cas) VALUES (?,?)')->execute([$id, time() + ZAMEK_MINUT * 60]);
    hist_pis($pdo, $id, 'odemčeno', 'heslem na ' . ZAMEK_MINUT . ' min');
    header('Location: kartoteka.php?id=' . $id . '&zprava=odemknuto'); exit;
  }
  hist_pis($pdo, $id, 'neplatné heslo', 'pokus o odemčení');
  sleep(2);
  header('Location: kartoteka.php?id=' . $id . '&zprava=heslo'); exit;
}

// Hlídá změny zámčených údajů (fotky, zaměření) – volat PŘED foto_obsluz() a zam_obsluz().
// Administrátor smí smazat fotku a uložit/vymazat zaměření i u zamčeného záznamu (zapíše se do historie).
function zamek_hlidej($pdo) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  $akce = $_POST['akce'] ?? '';
  $popisy = ['foto_nahraj' => 'fotka nahrána', 'foto_smaz' => 'fotka smazána', 'zamereni_uloz' => 'zaměření uloženo'];
  if (!isset($popisy[$akce])) return;
  $id = (int)($_POST['id'] ?? 0);
  if (!zamek_hlidany($pdo, $id)) return;
  $zam = zamceno($pdo, $id);
  $adminSmi = $akce !== 'foto_nahraj' && function_exists('uziv_admin') && uziv_admin();
  if ($zam && !$adminSmi) {
    if ($akce === 'foto_nahraj') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'zprava' => 'Záznam je zamčený (záloha přijata). Odemkněte ho heslem.']); exit; }
    header('Location: kartoteka.php?id=' . $id . '&zprava=zamceno'); exit;
  }
  hist_pis($pdo, $id, $popisy[$akce], $zam ? 'u zamčeného záznamu (administrátor)' : 'při odemčeném záznamu');
}

// Okno „Smazat záznam“ (jen administrátor, i u zamčeného záznamu). Malé červené tlačítko se přesune úplně dolů na stránku;
// po kliknutí se objeví pole pro heslo a smazání se potvrdí heslem administrátora.
function zamek_smazat_box($pdo, $r) {
  if (!function_exists('uziv_admin') || !uziv_admin()) return;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $id = (int)$r['id']; $zpr = $_GET['zprava'] ?? '';
  $msg = ['smaz_heslo' => 'Heslo nesouhlasí, nic se nesmazalo.', 'smaz_zaloha' => 'Záloha databáze se nepodařila uložit, nic se nesmazalo.', 'smaz_admin' => 'Záznam smí smazat jen administrátor.'];
  $otevri = isset($msg[$zpr]);
  ?><div id="smazat" style="max-width:1100px;margin:2.5rem auto 1.5rem;padding:0 12px;text-align:right">
    <?php if ($otevri): ?><div class="err" style="text-align:left"><?= $e($msg[$zpr]) ?></div><?php endif; ?>
    <button type="button" id="smazatOtevri" style="background:#b42318;color:#fff;border:0;border-radius:6px;padding:.25rem .7rem;font-size:.8em;cursor:pointer;<?= $otevri ? 'display:none' : '' ?>">🗑 Smazat záznam</button>
    <form method="post" id="smazatForm" style="<?= $otevri ? '' : 'display:none;' ?>margin:.4rem 0 0" onsubmit="return confirm('Opravdu smazat záznam <?= $id ?>? Uloží se záloha databáze.')">
      <input type="hidden" name="akce" value="smazat_zaznam"><input type="hidden" name="id" value="<?= $id ?>">
      <span style="font-size:.85em;color:#555">Smaže se záznam <b><?= $e($r['cislo']) ?></b>, i když je zamčený. Potvrď heslem:</span>
      <input type="password" name="heslo" autocomplete="current-password" required size="14" style="margin:0 .4rem">
      <button style="background:#b42318;color:#fff;border:0;border-radius:6px;padding:.25rem .7rem;font-size:.8em;cursor:pointer">Smazat navždy</button>
    </form>
  </div>
  <script>(function(){var d=document.getElementById('smazat'),b=document.getElementById('smazatOtevri'),f=document.getElementById('smazatForm');
  b.addEventListener('click',function(){b.style.display='none';f.style.display='';f.heslo.focus()});
  document.addEventListener('DOMContentLoaded',function(){document.body.appendChild(d)});if(document.readyState!=='loading')document.body.appendChild(d);})();</script><?php
}

// Okno „Původní zpráva“ (e-mail z formuláře) obsahuje obří odkazy pro vytvoření nabídky/objednávky. Dlouhé odkazy se nahradí
// krátkým textem (tlačítka jsou nahoře) a celá zpráva se sbalí; rozbalí se klepnutím nebo tlačítkem „Původní zpráva“.
function zamek_puvodni_skript() {
  ?><script>document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('puvodni');if(!b)return;var p=b.querySelector('pre'),h=b.querySelector('h3');if(!p||!h)return;
  p.textContent=p.textContent.replace(/https?:\/\/\S{60,}/g,'[dlouhý odkaz – použijte tlačítka nahoře]');
  var d=document.createElement('details'),s=document.createElement('summary');s.textContent=h.textContent;s.style.cssText='cursor:pointer;font-weight:700;font-size:1.1em';
  h.remove();b.appendChild(d);d.appendChild(s);d.appendChild(p);
  function otevri(){if(location.hash==='#puvodni')d.open=true}window.addEventListener('hashchange',otevri);otevri();});</script><?php
}

// Okno „Zámek a historie“ do detailu záznamu.
function zamek_box($pdo, $r) {
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $id = (int)$r['id'];
  $zpr = $_GET['zprava'] ?? '';
  $msg = ['zamceno' => ['err', 'Záznam je zamčený (záloha přijata). Pro změnu ho odemkněte heslem.'], 'heslo' => ['err', 'Heslo nesouhlasí.'], 'admin' => ['err', 'Zamčený záznam může odemknout jen administrátor.'],
          'odemknuto' => ['ok', 'Záznam je odemčený na ' . ZAMEK_MINUT . ' minut, změny se zapisují do historie.'], 'zamknuto' => ['ok', 'Záznam je zamčený.']];
  $st = $pdo->prepare('SELECT cas, akce, popis FROM historie WHERE zadost_id=? ORDER BY id DESC LIMIT 30'); $st->execute([$id]);
  $h = $st->fetchAll(PDO::FETCH_ASSOC);
  $hlidany = zamek_hlidany($pdo, $id); $do = $hlidany ? zamek_odemceno_do($pdo, $id) : 0;
  $adm = function_exists('uziv_admin') && uziv_admin();
  zamek_puvodni_skript();
  zamek_smazat_box($pdo, $r);
  if (!$hlidany && !$h) return;
  ?><div class="box" id="zamek"><h3>Zámek a historie</h3>
    <?php if (isset($msg[$zpr])): ?><div class="<?= $msg[$zpr][0] ?>"><?= $e($msg[$zpr][1]) ?></div><?php endif; ?>
    <?php if ($hlidany && !$do): ?>
      <p>🔒 <b>Zamčeno</b> – záloha je přijatá, měnit lze jen stav a interní poznámku.<?= $adm ? ' Jako administrátor můžete bez odemčení mazat fotky, ukládat zaměření a smazat celý záznam.' : '' ?></p>
      <style><?= $adm ? '#fotoTlacitko{display:none!important}' : '#fotoTlacitko,#fotky form,#zamereni details{display:none!important}' ?></style>
      <form method="post" style="margin:.3rem 0"><input type="hidden" name="akce" value="odemknout"><input type="hidden" name="id" value="<?= $id ?>">
        <label>Heslo kartotéky <input type="password" name="heslo" autocomplete="current-password" required size="14"></label> <button class="sec">🔓 Odemknout na <?= ZAMEK_MINUT ?> min</button></form>
    <?php elseif ($hlidany): ?>
      <p>🔓 <b>Odemčeno</b> do <?= $e(date('H:i', $do)) ?> – změny se zapisují do historie.</p>
      <form method="post" style="margin:.3rem 0"><input type="hidden" name="akce" value="zamknout"><input type="hidden" name="id" value="<?= $id ?>"><button class="sec">🔒 Zamknout hned</button></form>
    <?php endif; ?>
    <?php if ($h): ?><details style="margin-top:.4rem"><summary style="cursor:pointer">Historie změn (<?= count($h) ?>)</summary>
      <table style="margin-top:.4rem"><?php foreach ($h as $x): ?><tr><td style="white-space:nowrap"><?= $e(date('j. n. Y H:i', strtotime($x['cas']))) ?></td><td><b><?= $e($x['akce']) ?></b></td><td><?= $e($x['popis']) ?></td></tr><?php endforeach; ?></table></details><?php endif; ?>
  </div><?php
}
