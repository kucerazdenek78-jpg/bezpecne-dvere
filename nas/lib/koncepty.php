<?php
// Rozpracované nabídky / objednávky (uložené bez odeslání) – tlačítko „💾 Uložit rozpracovanou“ v nabidka-nova.html a objednavka-vice.html.
// Zápis a načtení přes veřejný koncept.php (jen s tajným klíčem konceptu), seznam v kartotéce koncepty.php (Rozpracované).
// Patří na NAS do /docker/formulare/app/lib/koncepty.php.

function konc_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS koncepty (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL, vytvoreno TEXT, upraveno TEXT, stav TEXT NOT NULL DEFAULT 'rozpracovano',
    druh TEXT, cislo TEXT, zakaznik TEXT, kategorie TEXT, cena REAL, prodavac TEXT, data_json TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS koncepty_stav ON koncepty(stav)');
}
function konc_pocet(PDO $pdo): int {
  try { konc_tabulka($pdo); return (int)$pdo->query("SELECT COUNT(*) FROM koncepty WHERE stav='rozpracovano'")->fetchColumn(); } catch (Throwable $e) { return 0; }
}
// Odkaz na web, který koncept otevře k dokončení (#k=id.token).
function konc_url(array $cfg, array $k): string {
  $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  return $wu . '/' . (($k['druh'] ?? '') === 'objednavka-vice' ? 'objednavka-vice.html' : 'nabidka-nova.html') . '?sys=1#k=' . (int)$k['id'] . '.' . $k['token'];
}

// Kartotéka → záložka Nabídky (a Vše): rozpracované neodeslané nabídky nad tabulkou, se zaškrtnutím a hromadným odesláním.
// Vkládá menu.php (bd_bar) – kartoteka.php se nemění; blok se skriptem přesune před tabulku.
// „Odeslat“ otevře nabídku na webu v režimu &auto=1 (nabidka.js ji po 5 s odpočtu odešle a otevře další z fronty &q=…).
function konc_kartoteka_box(): void {
  global $cfg;
  try { $pdo = db(); konc_tabulka($pdo); $rows = $pdo->query("SELECT * FROM koncepty WHERE stav='rozpracovano' AND druh='nabidka' ORDER BY upraveno DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) { return; }
  $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
  if ($q !== '') $rows = array_values(array_filter($rows, fn($k) => mb_strpos(mb_strtolower($k['zakaznik'] . ' ' . $k['cislo'] . ' ' . $k['prodavac']), $q) !== false));
  if (!$rows) return;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $kc = fn($n) => ($n === null || $n === '' || !is_numeric($n) || (float)$n == 0) ? '–' : number_format((float)$n, 0, ',', ' ') . ' Kč';
  $pu = fn($k) => function_exists('bd_prod_url') ? bd_prod_url(konc_url($cfg, $k)) : konc_url($cfg, $k);
  echo '<div class="box" id="bdKonc" style="border:2px solid #e0b100;background:#fffbea"><h3 style="color:#7a5b00">📝 Rozpracované nabídky – neodeslané (' . count($rows) . ')</h3>'
     . '<p class="mut" style="margin:0 0 8px">Uložené tlačítkem „💾 Uložit rozpracovanou“, zákazníkovi zatím nic neodešlo. Zaškrtněte a dejte „Odeslat vybrané zákazníkům“ – nabídky se otevřou na webu a po krátkém odpočtu se samy odešlou jedna po druhé (odpočet jde zastavit). „Otevřít“ = upravit před odesláním, „PDF“ = náhled toho, co dostane zákazník (nic se neodešle).</p>'
     . '<table><tr><th style="width:30px"><input type="checkbox" id="bdKoncVse" title="vybrat vše"></th><th>Uloženo</th><th>Číslo</th><th>Zákazník</th><th class="hide-m">E-mail</th><th class="hide-m">Druh</th><th class="num">Cena</th><th class="hide-m">Prodavač</th><th>Stav</th><th>Akce</th></tr>';
  foreach ($rows as $k) {
    $dj = json_decode((string)$k['data_json'], true) ?: []; $em = (string)($dj['f']['email'] ?? '');
    $url = $pu($k); $kt = (int)$k['id'] . '.' . $k['token'];
    echo '<tr><td><input type="checkbox" class="bdKoncCh" data-k="' . $e($kt) . '" data-url="' . $e($url) . '" data-em="' . $e($em) . '"></td>'
       . '<td class="mut">' . $e(date('j. n. H:i', strtotime((string)$k['upraveno']))) . '</td><td>' . $e($k['cislo'] ?: '–') . '</td><td>' . $e($k['zakaznik'] ?: '(bez jména)') . '</td>'
       . '<td class="hide-m">' . ($em !== '' ? $e($em) : '<span style="color:#a11">chybí – odejde jen vám</span>') . '</td><td class="hide-m">' . $e($k['kategorie']) . '</td>'
       . '<td class="num">' . $e($kc($k['cena'])) . '</td><td class="hide-m">' . $e($k['prodavac']) . '</td><td><span class="chip s-rozpracovano">Rozpracováno – neodesláno</span></td>'
       . '<td style="white-space:nowrap"><a class="btn sec" style="padding:4px 8px;font-size:13px" href="' . $e($url) . '">Otevřít</a> <a class="btn sec" style="padding:4px 8px;font-size:13px" target="_blank" rel="noopener" title="Náhled PDF přesně tak, jak by odešlo zákazníkovi (nic se neodešle)" href="' . $e($url . '&pdf=1') . '">PDF</a> <a class="btn" style="padding:4px 8px;font-size:13px" href="' . $e($url . '&auto=1') . '" onclick="return confirm(\'Nabídka je zkontrolovaná a má se odeslat zákazníkovi? E-mail odejde po 5s odpočtu (jde zastavit).\')">Odeslat</a></td></tr>';
  }
  echo '</table><p style="margin:10px 0 0"><button type="button" class="btn" id="bdKoncOdeslat" disabled>📤 Odeslat vybrané zákazníkům</button> <span class="mut" id="bdKoncInfo"></span> · <a href="koncepty.php">všechny rozpracované</a></p></div>';
  echo <<<'JS'
<script>document.addEventListener('DOMContentLoaded', function () {
  var box = document.getElementById('bdKonc'), main = document.querySelector('main'); if (!box || !main || box.parentNode === main) return;
  var cil = main.querySelector('table'); var mut = cil && cil.previousElementSibling && cil.previousElementSibling.matches('p.mut') ? cil.previousElementSibling : cil;
  if (mut) main.insertBefore(box, mut); else main.insertBefore(box, main.firstChild);
  var chs = [].slice.call(box.querySelectorAll('.bdKoncCh')), btn = document.getElementById('bdKoncOdeslat'), inf = document.getElementById('bdKoncInfo');
  function upd() { var v = chs.filter(function (c) { return c.checked; }); btn.disabled = !v.length; inf.textContent = v.length ? 'vybráno ' + v.length : ''; }
  chs.forEach(function (c) { c.addEventListener('change', upd); });
  document.getElementById('bdKoncVse').addEventListener('change', function () { var on = this.checked; chs.forEach(function (c) { c.checked = on; }); upd(); });
  btn.addEventListener('click', function () {
    var v = chs.filter(function (c) { return c.checked; }); if (!v.length) return;
    var bez = v.filter(function (c) { return !c.dataset.em; }).length;
    if (!confirm('Vybrané nabídky jsou zkontrolované? Odeslat ' + v.length + (v.length === 1 ? ' nabídku' : (v.length < 5 ? ' nabídky' : ' nabídek')) + ' zákazníkům?' + (bez ? '\n' + bez + '× bez e-mailu – ty odejdou jen vám.' : '') + '\nNabídky se otevřou na webu a odešlou se jedna po druhé (každá po 5s odpočtu, jde zastavit).')) return;
    var q = v.slice(1).map(function (c) { return c.dataset.k; }).join(',');
    location.href = v[0].dataset.url + '&auto=1' + (q ? '&q=' + q : '');
  });
});</script>
JS;
}
