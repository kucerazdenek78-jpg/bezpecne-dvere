<?php
// Uživatelé kartotéky: každý zaměstnanec má vlastní přihlášení a roli.
//   admin = vše včetně správy uživatelů, odemykání záznamů a mazání; zam = běžná práce bez mazání a odemykání.
// Přihlášení (od 3. 11. 2026): přihlašovací formulář + cookie (platí 30 dní), místo okna prohlížeče (HTTP Basic). Basic zůstává pro skripty.
//   Zámek proti hádání hesla: 5 špatných pokusů (stejná IP + stejný login) = blokace na 2 minuty; celkem 15 špatných z jedné IP za 2 minuty = blokace IP.
//   Země (od 4. 11. 2026): zadat login (formulář i Basic) jde jen z České republiky – přes Cloudflare podle hlavičky CF-IPCountry (musí být CZ). Z interní sítě (bez Cloudflare) bez omezení; už přihlášená cookie platí dál.
//   Odhlášení: ke kterékoli stránce kartotéky připsat ?odhlasit=1.
// Při prvním použití se tabulka založí a admin „Kučera Zd.“ převezme dosavadní přihlašovací údaje z config.php
// (prehled_user / prehled_hash), takže se nic nemění. Zaměstnanci se přidávají na stránce uzivatele.php.

function uziv_init($pdo) {
  global $cfg;
  $pdo->exec('CREATE TABLE IF NOT EXISTS uzivatele (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, jmeno TEXT NOT NULL, hash TEXT, role TEXT NOT NULL DEFAULT \'zam\', aktivni INTEGER NOT NULL DEFAULT 1, vytvoreno TEXT)');
  if ((int)$pdo->query('SELECT COUNT(*) FROM uzivatele')->fetchColumn() === 0 && !empty($cfg['prehled_user']) && !empty($cfg['prehled_hash'])) {
    $ins = $pdo->prepare('INSERT INTO uzivatele (login, jmeno, hash, role, aktivni, vytvoreno) VALUES (?,?,?,?,1,?)');
    $ins->execute([(string)$cfg['prehled_user'], 'Kučera Zd.', (string)$cfg['prehled_hash'], 'admin', date('Y-m-d H:i')]);
    $ins->execute(['kucerova', 'Kučerová', null, 'zam', date('Y-m-d H:i')]); // heslo jí nastaví admin na stránce Uživatelé
  }
}

// ── zámek proti hádání hesla (tabulka pokusy z lib/start.php: ip, typ, cas) ──
if (!defined("UZ_POKUSU")) { define("UZ_POKUSU", 5); define("UZ_OKNO", 120); define("UZ_POKUSU_IP", 15); }
// Zadání loginu jen z ČR: přes Cloudflare (tunel) musí hlavička CF-IPCountry být CZ; bez Cloudflare (interní síť) se neomezuje.
function uziv_jen_cz() {
  if (!pres_tunel()) return;
  $zeme = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
  if ($zeme === '' || $zeme === 'CZ') return;   // hlavička chybí = zemi nelze zjistit, nezamykáme
  http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit('Přihlášení je možné jen z České republiky.');
}
function uziv_zamek_sekund($login) { // kolik sekund ještě trvá blokace (0 = neblokováno)
  try {
    $pdo = db(); $ip = klient_ip(); $od = time() - UZ_OKNO;
    $q = $pdo->prepare('SELECT COUNT(*), MAX(cas) FROM pokusy WHERE ip = ? AND typ = ? AND cas > ?');
    $q->execute([$ip, 'login:' . strtolower((string)$login), $od]); [$n, $max] = $q->fetch(PDO::FETCH_NUM);
    $ttl = ((int)$n >= UZ_POKUSU) ? (int)$max + UZ_OKNO - time() : 0;
    $q = $pdo->prepare("SELECT COUNT(*), MAX(cas) FROM pokusy WHERE ip = ? AND typ LIKE 'login:%' AND cas > ?");
    $q->execute([$ip, $od]); [$n2, $max2] = $q->fetch(PDO::FETCH_NUM);
    if ((int)$n2 >= UZ_POKUSU_IP) $ttl = max($ttl, (int)$max2 + UZ_OKNO - time());
    return max(0, $ttl);
  } catch (Throwable $e) { return 0; }
}
function uziv_zapsat_chybu($login) {
  try {
    $pdo = db(); $pdo->prepare('INSERT INTO pokusy (ip, typ, cas) VALUES (?,?,?)')->execute([klient_ip(), 'login:' . strtolower((string)$login), time()]);
    if (mt_rand(1, 50) === 1) $pdo->prepare("DELETE FROM pokusy WHERE typ LIKE 'login:%' AND cas < ?")->execute([time() - 86400]);
  } catch (Throwable $e) {}
  usleep(400000); // zpomalení hádání
}
function uziv_zapsat_uspech($login) {
  try { db()->prepare('DELETE FROM pokusy WHERE ip = ? AND typ = ?')->execute([klient_ip(), 'login:' . strtolower((string)$login)]); } catch (Throwable $e) {}
}

// Ověří login + heslo bez zápisu pokusů; vrátí řádek uživatele (id, login, jmeno, role, hash) nebo null.
function uziv_najit_a_overit($login, $heslo) {
  global $cfg;
  try {
    $pdo = db(); uziv_init($pdo);
    $st = $pdo->prepare('SELECT id, login, jmeno, hash, role FROM uzivatele WHERE login = ? AND aktivni = 1');
    $st->execute([(string)$login]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u && !empty($u['hash']) && password_verify((string)$heslo, $u['hash'])) return $u;
    return null;
  } catch (Throwable $e) { // nouzově, kdyby tabulka nešla číst: původní jediné přihlášení = admin
    if (!empty($cfg['prehled_hash']) && hash_equals((string)$cfg['prehled_user'], (string)$login) && password_verify((string)$heslo, $cfg['prehled_hash'])) {
      return ['id' => 0, 'login' => (string)$login, 'jmeno' => 'Kučera Zd.', 'role' => 'admin', 'hash' => (string)$cfg['prehled_hash']];
    }
    return null;
  }
}

// ── cookie s přihlášením: login.platnost.podpis; podpis zahrnuje otisk hesla, takže změna hesla starou cookie zruší ──
function uziv_cookie_tajemstvi() { global $cfg; return 'uz|' . (string)($cfg['prehled_hash'] ?? '') . '|' . (string)($cfg['prehled_user'] ?? ''); }
function uziv_cookie_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || pres_tunel(); }
function uziv_cookie_nastavit($u) {
  $exp = time() + 30 * 86400; $l = rtrim(strtr(base64_encode((string)$u['login']), '+/', '-_'), '=');
  $sig = hash_hmac('sha256', $l . '.' . $exp . '.' . substr(hash('sha256', (string)$u['hash']), 0, 16), uziv_cookie_tajemstvi());
  setcookie('bd_prihlaseni', $l . '.' . $exp . '.' . $sig, ['expires' => $exp, 'path' => '/', 'secure' => uziv_cookie_https(), 'httponly' => true, 'samesite' => 'Lax']);
}
function uziv_cookie_smazat() { setcookie('bd_prihlaseni', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => uziv_cookie_https(), 'httponly' => true, 'samesite' => 'Lax']); }
function uziv_z_cookie() {
  $c = (string)($_COOKIE['bd_prihlaseni'] ?? ''); $p = explode('.', $c);
  if (count($p) !== 3 || (int)$p[1] < time()) return null;
  $login = base64_decode(strtr($p[0], '-_', '+/'), true); if ($login === false || $login === '') return null;
  try {
    $pdo = db(); uziv_init($pdo);
    $st = $pdo->prepare('SELECT id, login, jmeno, hash, role FROM uzivatele WHERE login = ? AND aktivni = 1'); $st->execute([$login]);
    $u = $st->fetch(PDO::FETCH_ASSOC); if (!$u || empty($u['hash'])) return null;
    $ok = hash_equals(hash_hmac('sha256', $p[0] . '.' . $p[1] . '.' . substr(hash('sha256', (string)$u['hash']), 0, 16), uziv_cookie_tajemstvi()), $p[2]);
    return $ok ? $u : null;
  } catch (Throwable $e) { return null; }
}

function uziv_prihlasovaci_stranka($chyba = '', $login = '') {
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  http_response_code($chyba !== '' ? 401 : 200); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
  echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Přihlášení – Bezpečné dveře</title>'
    . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font:16px/1.5 system-ui,Arial,sans-serif;background:#f3f6fb;color:#12284a}'
    . 'form{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.12);padding:24px;width:min(360px,calc(100vw - 32px))}h1{margin:0 0 4px;font-size:22px}p.s{margin:0 0 16px;color:#5a6b85;font-size:14px}'
    . 'label{display:block;font-size:14px;margin:0 0 12px}input{display:block;width:100%;box-sizing:border-box;margin-top:4px;padding:11px;border:1px solid #c5cfdd;border-radius:8px;font:inherit}'
    . 'button{width:100%;padding:12px;border:0;border-radius:10px;background:#16a34a;color:#fff;font:600 16px system-ui,sans-serif;cursor:pointer}.err{background:#fde8e8;color:#9b1c1c;border-radius:8px;padding:9px 11px;margin:0 0 12px;font-size:14px}</style></head><body>'
    . '<form method="post" autocomplete="on"><h1>Bezpečné dveře</h1><p class="s">Kartotéka – přihlášení</p>'
    . ($chyba !== '' ? '<div class="err" role="alert">' . $e($chyba) . '</div>' : '')
    . '<label>Přihlašovací jméno<input name="bd_login_user" value="' . $e($login) . '" autocomplete="username" autocapitalize="none" autocorrect="off" required' . ($login === '' ? ' autofocus' : '') . '></label>'
    . '<label>Heslo<input name="bd_login_pass" type="password" autocomplete="current-password" required' . ($login !== '' ? ' autofocus' : '') . '></label>'
    . '<button type="submit">Přihlásit</button></form></body></html>';
  exit;
}

// Hlavní vstup: vrátí řádek uživatele (id, login, jmeno, role) nebo ukončí požadavek přihlašovací stránkou / chybou 401.
// Parametry z HTTP Basic se berou jen pro skripty; prohlížeč se přihlašuje formulářem a cookie.
function uziv_overit($login = '', $heslo = '') {
  if (isset($_GET['odhlasit'])) { uziv_cookie_smazat(); unset($_COOKIE['bd_prihlaseni']); $login = ''; $heslo = ''; }
  // 1) skriptové přihlášení Basic
  if ((string)$login !== '') {
    uziv_jen_cz();
    if (($t = uziv_zamek_sekund($login)) > 0) { http_response_code(429); header('Retry-After: ' . $t); exit('Příliš mnoho pokusů, zkuste to za ' . ceil($t / 60) . ' min.'); }
    $u = uziv_najit_a_overit($login, $heslo);
    if ($u) { uziv_zapsat_uspech($login); unset($u['hash']); return $u; }
    uziv_zapsat_chybu($login); http_response_code(401); exit('Neplatné přihlášení');
  }
  // 2) odeslaný přihlašovací formulář
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['bd_login_user'], $_POST['bd_login_pass'])) {
    uziv_jen_cz();
    $lg = trim((string)$_POST['bd_login_user']); $pw = (string)$_POST['bd_login_pass'];
    if (($t = uziv_zamek_sekund($lg)) > 0) uziv_prihlasovaci_stranka('Příliš mnoho nesprávných pokusů. Zkuste to znovu za ' . max(1, (int)ceil($t / 60)) . ' min.', $lg);
    $u = uziv_najit_a_overit($lg, $pw);
    if (!$u) {
      uziv_zapsat_chybu($lg);
      $t = uziv_zamek_sekund($lg);
      uziv_prihlasovaci_stranka($t > 0 ? 'Příliš mnoho nesprávných pokusů. Přihlášení je na 2 minuty zablokované.' : 'Nesprávné jméno nebo heslo.', $lg);
    }
    uziv_zapsat_uspech($lg); uziv_cookie_nastavit($u);
    $to = (string)($_SERVER['REQUEST_URI'] ?? '/'); $to = preg_replace('/([?&])odhlasit(=[^&]*)?/', '$1', $to); if ($to === '' || $to[0] !== '/' || strpos($to, '//') === 0) $to = '/';
    header('Location: ' . rtrim($to, '?&'), true, 303); exit;
  }
  // 3) platná cookie
  if (($u = uziv_z_cookie())) { unset($u['hash']); return $u; }
  // 4) nepřihlášen: stránka v prohlížeči dostane formulář, ostatní (fetch apod.) čisté 401 bez okna prohlížeče
  if (stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') !== false && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === '') { uziv_jen_cz(); uziv_prihlasovaci_stranka(); }
  http_response_code(401); header('Content-Type: text/plain; charset=utf-8'); exit('Přihlášení vypršelo – obnovte stránku a přihlaste se znovu.');
}

function uziv_jmeno() { return (string)($GLOBALS['UZ']['jmeno'] ?? ''); }
function uziv_admin() { return ($GLOBALS['UZ']['role'] ?? '') === 'admin'; }
// Odmítne akci, kterou smí jen admin (mazání, odemykání…).
function uziv_jen_admin($zprava = 'Tuto akci smí provést jen administrátor.') {
  if (uziv_admin()) return;
  http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit($zprava);
}
