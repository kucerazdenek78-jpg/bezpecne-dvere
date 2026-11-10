<?php
// Žádost zákazníkovi o doplnění poptávky (10. 11. 2026, přání Zdeňka): v detailu poptávky v kartotéce box „📨 Požádat zákazníka o doplnění“.
// Prodejce zaškrtne, co chybí (fotky zvenku / zevnitř / okolí, rozměry, adresa, něco jiného), e-mail odejde zákazníkovi s podpisem prodejce
// a s odkazem na doplnit.php?t=<id>-<podpis>, kde zákazník nahraje fotky z mobilu (rovnou z fotoaparátu) a napíše rozměry / poznámku.
// Odkaz jde poslat i přes WhatsApp / SMS. Fotky od zákazníka se uloží k poptávce do Fotek (stejně jako fotky prodejce, lib/foto.php),
// text do data_json (doplneni_odpovedi) a do historie; firma dostane e-mail „Zákazník doplnil poptávku“.
// Požadavky se ukládají do data_json (doplneni: [{cas, co[], text, komu, kdo}]).
// Volá: lib/foto.php (foto_obsluz → dp_poslat, foto_box → dp_box) a veřejná stránka doplnit.php.

const DP_CO = [
  'zvenku' => 'fotku stávajících dveří zvenku (celé dveře i se zárubní, z chodby / zvenku)',
  'zevnitr' => 'fotku dveří zevnitř (celé dveře i se zárubní)',
  'okoli' => 'fotku okolí dveří (zeď kolem dveří, podlaha a práh)',
  'rozmery' => 'rozměry: šířku a výšku zárubně (vnější rozměr) a případně tloušťku zdi',
  'adresa' => 'přesnou adresu montáže (ulice, číslo domu, patro)',
];
const DP_FOTO = ['zvenku', 'zevnitr', 'okoli'];

function dp_klic(): string {
  global $DATA; $f = $DATA . '/doplneni-klic.txt';
  $k = is_file($f) ? trim((string)file_get_contents($f)) : '';
  if (!preg_match('/^[0-9a-f]{64}$/', $k)) { $k = bin2hex(random_bytes(32)); @file_put_contents($f, $k, LOCK_EX); @chmod($f, 0600); }
  return $k;
}
function dp_token(int $id): string { return $id . '-' . substr(hash_hmac('sha256', 'doplneni|' . $id, dp_klic()), 0, 16); }
function dp_id_z_tokenu(string $t): int {
  if (!preg_match('/^(\d{1,9})-([0-9a-f]{16})$/', $t, $m)) return 0;
  return hash_equals(dp_token((int)$m[1]), $t) ? (int)$m[1] : 0;
}
function dp_url(array $cfg, int $id): string { return rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/') . '/doplnit.php?t=' . dp_token($id); }
function dp_data(PDO $pdo, int $id): array { $s = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: []; }
function dp_ulozit(PDO $pdo, int $id, array $d): void { $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $id]); }
function dp_cislo(array $r, array $d): string { return (string)($d['obj_cislo'] ?? $d['cislo'] ?? $r['cislo'] ?? ''); }
function dp_seznam(array $co, string $text): array {
  $o = []; foreach ($co as $k) if (isset(DP_CO[$k])) $o[] = DP_CO[$k];
  if (trim($text) !== '') $o[] = trim($text);
  return $o;
}

// Text e-mailu / zprávy pro zákazníka
function dp_text(array $r, array $d, array $co, string $text, string $url, bool $kratky = false): string {
  $cis = dp_cislo($r, $d); $body = dp_seznam($co, $text);
  if ($kratky) return 'Dobrý den, k Vaší poptávce' . ($cis !== '' ? ' č. ' . $cis : '') . ' u Bezpečných dveří ještě potřebujeme: ' . implode('; ', $body) . '. Pošlete prosím přes tento odkaz (i fotky z mobilu): ' . $url . ' Děkujeme.';
  return "Dobrý den,\n\nděkujeme za Vaši poptávku" . ($cis !== '' ? " č. $cis" : '') . ". Abychom Vám mohli připravit přesnou nabídku, potřebovali bychom ještě doplnit:\n\n"
    . implode("\n", array_map(fn($x) => '• ' . $x, $body)) . "\n\n"
    . "Fotky i údaje pošlete nejjednodušeji přes tento odkaz – funguje i z mobilu, fotky jdou nahrát rovnou z fotoaparátu:\n$url\n\n"
    . "Případně stačí odpovědět na tento e-mail (fotky do přílohy) nebo je poslat přes WhatsApp na +420 725 559 235.\n\nDěkujeme.";
}

// POST z detailu poptávky (akce=doplneni_poslat) – volá foto_obsluz po přihlášení
function dp_poslat(PDO $pdo): void {
  global $cfg, $DATA;
  $id = (int)($_POST['id'] ?? 0); $r = dp_data($pdo, $id);
  if (!$r || $r['typ'] !== 'poptavka') { header('Location: kartoteka.php?id=' . $id); exit; }
  $d = json_decode((string)$r['data_json'], true) ?: [];
  $co = array_values(array_intersect(array_keys(DP_CO), (array)($_POST['co'] ?? [])));
  $text = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 1000);
  $komu = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
  if (!$co && $text === '') { header('Location: kartoteka.php?id=' . $id . '&doplneni=nic#doplneni'); exit; }
  if (!$komu) { header('Location: kartoteka.php?id=' . $id . '&doplneni=email#doplneni'); exit; }
  require_once __DIR__ . '/posta.php';
  $cis = dp_cislo($r, $d); $kdo = function_exists('uziv_jmeno') ? uziv_jmeno() : '';
  $ok = posli_mail($cfg, $DATA, [$komu], [], $cfg['from'], 'Doplnění poptávky' . ($cis !== '' ? ' č. ' . $cis : '') . ' – Bezpečné dveře', dp_text($r, $d, $co, $text, dp_url($cfg, $id)), [], '', $kdo);
  if ($ok) {
    $d['doplneni'] = array_slice(array_merge((array)($d['doplneni'] ?? []), [['cas' => date('Y-m-d H:i:s'), 'co' => $co, 'text' => $text, 'komu' => $komu, 'kdo' => $kdo]]), -20);
    dp_ulozit($pdo, $id, $d);
    if (function_exists('hist_pis')) hist_pis($pdo, $id, 'žádost o doplnění poslána zákazníkovi', $komu . ': ' . implode('; ', dp_seznam($co, $text)));
  }
  header('Location: kartoteka.php?id=' . $id . '&doplneni=' . ($ok ? 'ok' : 'chyba') . '#doplneni'); exit;
}

// Box v detailu poptávky
function dp_box(array $r): void {
  global $cfg;
  if (($r['typ'] ?? '') !== 'poptavka') return;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $id = (int)$r['id']; $d = json_decode((string)$r['data_json'], true) ?: [];
  $email = (string)($d['email'] ?? $r['email'] ?? ''); $tel = preg_replace('/\D+/', '', (string)($d['telefon'] ?? ''));
  if (strlen($tel) === 9) $tel = '420' . $tel;
  $url = dp_url($cfg, $id); $pozad = (array)($d['doplneni'] ?? []); $odp = (array)($d['doplneni_odpovedi'] ?? []);
  $zpr = ['ok' => ['ok', '✅ Žádost o doplnění odeslána zákazníkovi.'], 'chyba' => ['err', 'E-mail se nepodařilo odeslat.'], 'nic' => ['err', 'Zaškrtněte, co má zákazník doplnit, nebo napište vlastní text.'], 'email' => ['err', 'Doplňte platný e-mail zákazníka.']][$_GET['doplneni'] ?? ''] ?? null;
  $vychozi = $pozad ? [] : ['zvenku'];   // nejčastěji chybí fotka dveří zvenku
  $kratky = dp_text($r, $d, $vychozi ?: ['zvenku'], '', $url, true);
  ?><div class="box" id="doplneni"><h3>📨 Požádat zákazníka o doplnění</h3>
  <?php if ($zpr): ?><div class="<?= $zpr[0] ?>"><?= $e($zpr[1]) ?></div><?php endif; ?>
  <?php foreach ($odp as $o): ?><div style="background:#e8f5ec;border-left:4px solid #16a34a;border-radius:6px;padding:8px 12px;margin:0 0 8px">✅ <b>Zákazník doplnil <?= $e(date('j. n. Y H:i', strtotime((string)$o['cas']))) ?></b><?= !empty($o['fotek']) ? ' · fotek: ' . (int)$o['fotek'] . ' (jsou ve Fotkách níže)' : '' ?><?= trim((string)($o['text'] ?? '')) !== '' ? '<div style="white-space:pre-wrap;margin-top:4px">' . $e($o['text']) . '</div>' : '' ?></div><?php endforeach; ?>
  <?php foreach ($pozad as $p): ?><p class="mut" style="margin:0 0 4px">Odesláno <?= $e(date('j. n. Y H:i', strtotime((string)$p['cas']))) ?> na <?= $e($p['komu'] ?? '') ?><?= !empty($p['kdo']) ? ' (' . $e($p['kdo']) . ')' : '' ?>: <?= $e(implode('; ', dp_seznam((array)($p['co'] ?? []), (string)($p['text'] ?? '')))) ?></p><?php endforeach; ?>
  <form method="post" style="margin-top:6px"><input type="hidden" name="akce" value="doplneni_poslat"><input type="hidden" name="id" value="<?= $id ?>">
    <p style="margin:.2rem 0">Co chybí:</p>
    <?php foreach (DP_CO as $k => $t): ?><label style="display:block;padding:3px 0"><input type="checkbox" name="co[]" value="<?= $e($k) ?>"<?= in_array($k, $vychozi, true) ? ' checked' : '' ?>> <?= $e(ucfirst($t)) ?></label><?php endforeach; ?>
    <p><textarea name="text" rows="2" placeholder="Něco jiného – vlastní text (např. Pošlete prosím i fotku zámku)"></textarea></p>
    <p><label>E-mail zákazníka <input type="email" name="email" value="<?= $e($email) ?>" size="30"></label></p>
    <button>📨 Poslat žádost e-mailem</button>
    <?php if (strlen($tel) >= 11): ?> <a class="btn sec" target="_blank" rel="noopener" id="dpWa" href="https://wa.me/<?= $e($tel) ?>?text=<?= rawurlencode($kratky) ?>">WhatsApp</a> <a class="btn sec" id="dpSms" href="sms:+<?= $e($tel) ?>?body=<?= rawurlencode($kratky) ?>">SMS</a><?php endif; ?>
    <button type="button" class="sec" id="dpKop">Kopírovat odkaz</button> <span class="mut" id="dpMsg"></span>
    <p class="mut" style="margin:.5rem 0 0">Zákazník přes odkaz nahraje fotky z mobilu a napíše rozměry / poznámku. Fotky se objeví ve Fotkách u této poptávky a firma dostane e-mail. WhatsApp a SMS posílají text podle zaškrtnutých položek.</p>
  </form>
  <script>
  (function () {
    var url = <?= json_encode($url) ?>, CO = <?= json_encode(DP_CO, JSON_UNESCAPED_UNICODE) ?>, cis = <?= json_encode(dp_cislo($r, $d), JSON_UNESCAPED_UNICODE) ?>, box = document.getElementById('doplneni');
    function text() {
      var s = [].map.call(box.querySelectorAll('input[name="co[]"]:checked'), function (c) { return CO[c.value]; }), v = box.querySelector('textarea[name="text"]').value.trim(); if (v) s.push(v);
      return 'Dobrý den, k Vaší poptávce' + (cis ? ' č. ' + cis : '') + ' u Bezpečných dveří ještě potřebujeme: ' + (s.join('; ') || 'doplnit pár údajů') + '. Pošlete prosím přes tento odkaz (i fotky z mobilu): ' + url + ' Děkujeme.';
    }
    function obnov() { var wa = document.getElementById('dpWa'), sm = document.getElementById('dpSms'); if (wa) wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent(text()); if (sm) sm.href = sm.href.split('?')[0] + '?body=' + encodeURIComponent(text()); }
    box.addEventListener('change', obnov); box.addEventListener('input', obnov);
    obnov();
    document.getElementById('dpKop').addEventListener('click', function () { var m = document.getElementById('dpMsg'); (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { m.textContent = 'Odkaz zkopírován.'; }, function () { m.textContent = url; }); });
  })();
  </script></div><?php
}
