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
  if (($_POST['potvrdit'] ?? '') !== 'ano') { header($zpet('smaz_potvrdit')); exit; }
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

// Okno „Smazat záznam“ (jen administrátor, i u zamčeného záznamu).
function zamek_smazat_box($pdo, $r) {
  if (!function_exists('uziv_admin') || !uziv_admin()) return;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $id = (int)$r['id']; $zpr = $_GET['zprava'] ?? '';
  $msg = ['smaz_potvrdit' => 'Zaškrtněte potvrzení, že záznam opravdu chcete smazat.', 'smaz_zaloha' => 'Záloha databáze se nepodařila uložit, nic se nesmazalo.', 'smaz_admin' => 'Záznam smí smazat jen administrátor.'];
  ?><div class="box" id="smazat"><h3>Smazat záznam</h3>
    <?php if (isset($msg[$zpr])): ?><div class="err"><?= $e($msg[$zpr]) ?></div><?php endif; ?>
    <details<?= isset($msg[$zpr]) ? ' open' : '' ?>><summary style="cursor:pointer">🗑 Smazat tento záznam…</summary>
      <form method="post" style="margin:.5rem 0"><input type="hidden" name="akce" value="smazat_zaznam"><input type="hidden" name="id" value="<?= $id ?>">
        <p style="margin:.2rem 0">Smaže se záznam <b><?= $e($r['cislo']) ?></b> (<?= $e($r['typ']) ?>), i když je zamčený. Před smazáním se uloží záloha databáze a zapíše se do historie. Fotky, zaměření a PDF na disku zůstanou.</p>
        <p style="margin:.4rem 0"><label><input type="checkbox" name="potvrdit" value="ano"> Ano, opravdu smazat</label></p>
        <button style="background:#b42318;color:#fff;border:0;border-radius:8px;padding:.5rem 1rem;cursor:pointer">🗑 Smazat záznam</button></form>
    </details></div><?php
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
