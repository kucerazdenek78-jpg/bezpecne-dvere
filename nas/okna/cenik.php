<?php
// Servis oken – CENÍK (3. 11. 2026): položky ceníku (ceny bez DPH, DPH podle položky), hromadné navýšení v %, slevy (počet bytů,
// servisní smlouva 3× / 5×), příplatky (večer, víkend, expres), DPH, texty do nabídky, převody velikostí bytů a historie změn
// (před každou změnou se uloží celý ceník, jde vrátit). Měnit smí jen administrátor, ostatní ceník vidí.
require __DIR__ . '/lib.php';
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$P = fn($k, $max = 500) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)($_POST[$k] ?? ''))), 0, $max);
$num = function ($v) { $v = str_replace([' ', "\u{a0}", ',', 'Kč', '%'], ['', '', '.', '', ''], trim((string)$v)); return $v === '' || !is_numeric($v) ? null : (float)$v; };
$ted = date('Y-m-d H:i:s');
$zaloha = function (string $popis) use ($pdo, $ted) {
  $pdo->prepare('INSERT INTO ok_cenik_historie (kdy, kdo, popis, data) VALUES (?,?,?,?)')->execute([$ted, uziv_jmeno(), $popis, json_encode(['cenik' => ok_cenik($pdo), 'nastaveni' => ok_nast($pdo)], JSON_UNESCAPED_UNICODE)]);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  uziv_jen_admin('Ceník smí měnit jen administrátor.');
  $a = $_POST['akce'] ?? '';
  if ($a === 'polozky') {
    $zaloha('Před úpravou položek');
    $pdo->beginTransaction();
    foreach ((array)($_POST['p'] ?? []) as $id => $p) {
      $id = (int)$id; $nazev = mb_substr(trim((string)($p['nazev'] ?? '')), 0, 200); $kod = mb_substr(trim((string)($p['kod'] ?? '')), 0, 20);
      if ($id > 0 && !empty($p['smazat'])) { $pdo->prepare('DELETE FROM ok_cenik WHERE id=?')->execute([$id]); continue; }
      if ($nazev === '' || $kod === '') continue;
      $sk = isset(OK_SKUPINY[$p['skupina'] ?? '']) ? $p['skupina'] : 'jine';
      $f = [$kod, $nazev, $sk, mb_substr(trim((string)($p['jednotka'] ?? 'ks')), 0, 20), (float)($num($p['cena'] ?? '') ?? 0), (float)($num($p['dph'] ?? '') ?? 21), mb_substr(trim((string)($p['velikost'] ?? '')), 0, 20), (int)($p['poradi'] ?? 0), empty($p['aktivni']) ? 0 : 1, mb_substr(trim((string)($p['poznamka'] ?? '')), 0, 300), $ted];
      try {
        if ($id > 0) $pdo->prepare('UPDATE ok_cenik SET kod=?, nazev=?, skupina=?, jednotka=?, cena=?, dph=?, velikost=?, poradi=?, aktivni=?, poznamka=?, upraveno=? WHERE id=?')->execute(array_merge($f, [$id]));
        else $pdo->prepare('INSERT INTO ok_cenik (kod, nazev, skupina, jednotka, cena, dph, velikost, poradi, aktivni, poznamka, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($f);
      } catch (Throwable $x) { $pdo->rollBack(); header('Location: cenik.php?chyba=kod'); exit; }
    }
    $pdo->commit(); header('Location: cenik.php?ok=1'); exit;
  }
  if ($a === 'navysit') {
    $pct = $num($_POST['pct'] ?? ''); $sk = array_values(array_intersect(array_keys(OK_SKUPINY), (array)($_POST['skupiny'] ?? []))); $krok = (float)($_POST['krok'] ?? 1);
    if ($pct === null || $pct == 0 || !$sk) { header('Location: cenik.php?chyba=navyseni#navyseni'); exit; }
    $zaloha('Před navýšením o ' . str_replace('.', ',', (string)$pct) . ' % (' . implode(', ', array_map(fn($k) => OK_SKUPINY[$k], $sk)) . ')');
    $st = $pdo->prepare('UPDATE ok_cenik SET cena=?, upraveno=? WHERE id=?');
    foreach (ok_cenik($pdo) as $r) {
      if (!in_array($r['skupina'], $sk, true)) continue;
      $s = (float)$r['cena'] * (1 + (float)$r['dph'] / 100) * (1 + $pct / 100);       // nová cena s DPH
      $s = $krok > 0 ? ok_zaokr($s, $krok) : $s;                                       // zaokrouhlí se cena s DPH (to vidí zákazník)
      $st->execute([round($s / (1 + (float)$r['dph'] / 100), 4), $ted, $r['id']]);
    }
    header('Location: cenik.php?ok=navyseni'); exit;
  }
  if ($a === 'nastaveni') {
    $zaloha('Před změnou slev a nastavení');
    $st = $pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota');
    foreach (OK_NAST_VYCHOZI as $k => $v) if (isset($_POST[$k])) $st->execute([$k, is_numeric($v) ? (string)($num($_POST[$k]) ?? $v) : $P($k, 3000)]);
    if (!empty($_POST['dph_prepocet'])) {   // nové DPH promítnout do položek
      $pdo->prepare("UPDATE ok_cenik SET dph=? WHERE skupina IN ('byt','prace') OR (skupina='okno' AND kod NOT LIKE 'F%')")->execute([(float)($num($_POST['dph_byt']) ?? 12)]);
      $pdo->prepare("UPDATE ok_cenik SET dph=? WHERE skupina='doprava' OR (skupina='okno' AND kod LIKE 'F%')")->execute([(float)($num($_POST['dph_firma']) ?? 21)]);
    }
    header('Location: cenik.php?ok=1#nastaveni'); exit;
  }
  if ($a === 'velikost') {
    $t = mb_strtolower(trim(preg_replace('/\s+/u', '', $P('text', 30)))); $kod = $P('kod', 20);
    if ($t !== '') {
      if ($kod === '') $pdo->prepare('DELETE FROM ok_nastaveni WHERE klic=?')->execute(['velikost:' . $t]);
      else $pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota')->execute(['velikost:' . $t, $kod]);
    }
    $zpet = (string)($_POST['zpet'] ?? ''); header('Location: ' . (preg_match('/^nabidky\.php\?[\w=&%.-]*$/', $zpet) ? $zpet : 'cenik.php#velikosti')); exit;
  }
  if ($a === 'obnovit') {
    $st = $pdo->prepare('SELECT * FROM ok_cenik_historie WHERE id=?'); $st->execute([(int)$_POST['id']]); $h = $st->fetch(PDO::FETCH_ASSOC);
    $d = $h ? json_decode((string)$h['data'], true) : null; $c = $d['cenik'] ?? (is_array($d) && isset($d[0]) ? $d : null);
    if ($c) {
      $zaloha('Před obnovením verze z ' . date('j. n. Y H:i', strtotime($h['kdy'])));
      $pdo->beginTransaction(); $pdo->exec('DELETE FROM ok_cenik');
      $ins = $pdo->prepare('INSERT INTO ok_cenik (id, kod, nazev, skupina, jednotka, cena, dph, velikost, poradi, aktivni, poznamka, upraveno) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
      foreach ($c as $r) $ins->execute([$r['id'], $r['kod'], $r['nazev'], $r['skupina'], $r['jednotka'], $r['cena'], $r['dph'], $r['velikost'], $r['poradi'], $r['aktivni'], $r['poznamka'] ?? '', $ted]);
      if (!empty($d['nastaveni'])) { $st = $pdo->prepare('INSERT INTO ok_nastaveni (klic, hodnota) VALUES (?,?) ON CONFLICT(klic) DO UPDATE SET hodnota=excluded.hodnota'); foreach ($d['nastaveni'] as $k => $v) if (array_key_exists($k, OK_NAST_VYCHOZI)) $st->execute([$k, (string)$v]); }
      $pdo->commit();
    }
    header('Location: cenik.php?ok=obnoveno'); exit;
  }
  http_response_code(400); exit('Neznámá akce');
}

$n = ok_nast($pdo); $cen = ok_cenik($pdo); $adm = uziv_admin();
$hist = $pdo->query('SELECT id, kdy, kdo, popis FROM ok_cenik_historie ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
$vel = $pdo->query("SELECT klic, hodnota FROM ok_nastaveni WHERE klic LIKE 'velikost:%' ORDER BY klic")->fetchAll(PDO::FETCH_KEY_PAIR);
$OK = ['1' => 'Uloženo.', 'navyseni' => 'Ceny navýšeny. Předchozí ceník je v historii.', 'obnoveno' => 'Ceník obnoven.'];
ok_hlava('Ceník'); ok_bar('cen'); ?><main>
<?php if (isset($OK[$_GET['ok'] ?? ''])): ?><div class="msg"><?= $e($OK[$_GET['ok']]) ?></div><?php endif; ?>
<?php if (($_GET['chyba'] ?? '') === 'kod'): ?><div class="msg err">Kód položky musí být jedinečný – nic se neuložilo.</div><?php elseif (($_GET['chyba'] ?? '') === 'navyseni'): ?><div class="msg err">Zadejte procento a vyberte aspoň jednu skupinu.</div><?php endif; ?>
<h1>💰 Ceník servisu oken</h1>
<p class="mut">Ceny se zadávají <b>bez DPH</b>, cena s DPH se dopočítá. Výchozí ceny jsou z ceníku „Servis 48 – koncový zákazník“ platného od 1. 1. 2022. <?= $adm ? '' : 'Měnit může jen administrátor.' ?></p>

<form method="post" class="box"><input type="hidden" name="akce" value="polozky">
<div style="overflow-x:auto"><table><tr><th>Kód</th><th>Název</th><th>Skupina</th><th>Jednotka</th><th class="r">Cena bez DPH</th><th class="r">DPH %</th><th class="r">S DPH</th><th>Velikost</th><th>Pořadí</th><th>Aktivní</th><?= $adm ? '<th>Smazat</th>' : '' ?></tr>
<?php foreach (array_merge($cen, $adm ? [['id' => 0, 'kod' => '', 'nazev' => '', 'skupina' => 'jine', 'jednotka' => 'ks', 'cena' => '', 'dph' => $n['dph_firma'], 'velikost' => '', 'poradi' => 99, 'aktivni' => 1]] : []) as $r): $i = $r['id'] ?: 'nova'; ?>
<tr<?= !$r['id'] ? ' style="background:#f6fbf7"' : '' ?>><td><input name="p[<?= $i ?>][kod]" value="<?= $e($r['kod']) ?>" size="5" placeholder="<?= $r['id'] ? '' : 'nová' ?>"<?= $adm ? '' : ' disabled' ?>></td>
<td><input name="p[<?= $i ?>][nazev]" value="<?= $e($r['nazev']) ?>" style="width:100%;min-width:220px"<?= $adm ? '' : ' disabled' ?>></td>
<td><select name="p[<?= $i ?>][skupina]"<?= $adm ? '' : ' disabled' ?>><?php foreach (OK_SKUPINY as $k => $t): ?><option value="<?= $k ?>"<?= $r['skupina'] === $k ? ' selected' : '' ?>><?= $e(explode(' (', $t)[0]) ?></option><?php endforeach; ?></select></td>
<td><input name="p[<?= $i ?>][jednotka]" value="<?= $e($r['jednotka']) ?>" size="5"<?= $adm ? '' : ' disabled' ?>></td>
<td class="r"><input name="p[<?= $i ?>][cena]" value="<?= $r['cena'] === '' ? '' : $e(str_replace('.', ',', (string)round((float)$r['cena'], 2))) ?>" size="8" inputmode="decimal" style="text-align:right"<?= $adm ? '' : ' disabled' ?>></td>
<td class="r"><input name="p[<?= $i ?>][dph]" value="<?= $e((float)$r['dph']) ?>" size="3" inputmode="decimal" style="text-align:right"<?= $adm ? '' : ' disabled' ?>></td>
<td class="r nowrap"><b><?= $r['cena'] === '' ? '' : $e(ok_kc((float)$r['cena'] * (1 + (float)$r['dph'] / 100))) ?></b></td>
<td><input name="p[<?= $i ?>][velikost]" value="<?= $e($r['velikost']) ?>" size="4"<?= $adm ? '' : ' disabled' ?>></td>
<td><input name="p[<?= $i ?>][poradi]" value="<?= (int)$r['poradi'] ?>" size="2"<?= $adm ? '' : ' disabled' ?>></td>
<td><input type="checkbox" name="p[<?= $i ?>][aktivni]" value="1"<?= $r['aktivni'] ? ' checked' : '' ?><?= $adm ? '' : ' disabled' ?>></td>
<?php if ($adm): ?><td><?php if ($r['id']): ?><input type="checkbox" name="p[<?= $i ?>][smazat]" value="1" title="Smazat položku"><?php endif; ?></td><?php endif; ?></tr>
<?php endforeach; ?></table></div>
<?php if ($adm): ?><p><button>Uložit ceník</button> <span class="mut">Poslední zelený řádek = nová položka. Skupina „Doprava“ se nezlevňuje a nedostává příplatky.</span></p><?php endif; ?></form>

<?php if ($adm): ?><form method="post" class="box" id="navyseni"><input type="hidden" name="akce" value="navysit"><h2 style="margin-top:0">Hromadné navýšení cen</h2>
<div class="ln"><label>O kolik % (sleva = minus)<input name="pct" size="6" placeholder="např. 8" inputmode="decimal"></label>
<label>Zaokrouhlit cenu s DPH na<select name="krok"><option value="1">celé koruny</option><option value="5">5 Kč</option><option value="10" selected>10 Kč</option><option value="0">nezaokrouhlovat</option></select></label></div>
<div class="chk"><?php foreach (OK_SKUPINY as $k => $t): ?><label><input type="checkbox" name="skupiny[]" value="<?= $k ?>"<?= $k !== 'doprava' ? ' checked' : '' ?>> <?= $e($t) ?></label><?php endforeach; ?></div>
<button onclick="return confirm('Navýšit ceny vybraných skupin? Předchozí ceník se uloží do historie.')">Navýšit</button></form><?php endif; ?>

<form method="post" class="box" id="nastaveni"><input type="hidden" name="akce" value="nastaveni"><h2 style="margin-top:0">Slevy, příplatky a DPH</h2>
<p class="mut" style="margin-top:0">Slevy se nesčítají – v nabídce se použije vždy ta vyšší (stejně jako v ceníku 2022). Na dopravu se slevy ani příplatky nevztahují.</p>
<div class="ln"><label>Množstevní sleva od bytů<input name="sleva_mn1_od" value="<?= $e($n['sleva_mn1_od']) ?>" size="3"></label><label>sleva %<input name="sleva_mn1" value="<?= $e($n['sleva_mn1']) ?>" size="3"></label>
<label>Vyšší sleva od bytů<input name="sleva_mn2_od" value="<?= $e($n['sleva_mn2_od']) ?>" size="3"></label><label>sleva %<input name="sleva_mn2" value="<?= $e($n['sleva_mn2']) ?>" size="3"></label></div>
<div class="ln"><label>Smlouva 3× opakovaný servis – sleva %<input name="sleva_sml3" value="<?= $e($n['sleva_sml3']) ?>" size="3"></label><label>Smlouva 5× – sleva %<input name="sleva_sml5" value="<?= $e($n['sleva_sml5']) ?>" size="3"></label></div>
<div class="ln"><label>Příplatek po 18. hodině %<input name="priplatek_vecer" value="<?= $e($n['priplatek_vecer']) ?>" size="3"></label><label>Soboty, neděle, svátky %<input name="priplatek_vikend" value="<?= $e($n['priplatek_vikend']) ?>" size="3"></label><label>Expres (nástup do 4 h) %<input name="priplatek_expres" value="<?= $e($n['priplatek_expres']) ?>" size="3"></label></div>
<div class="ln"><label>DPH byty a RD %<input name="dph_byt" value="<?= $e($n['dph_byt']) ?>" size="3"></label><label>DPH firmy a doprava %<input name="dph_firma" value="<?= $e($n['dph_firma']) ?>" size="3"></label>
<label style="flex-direction:row;align-items:center;gap:6px"><input type="checkbox" name="dph_prepocet" value="1"> promítnout DPH do položek (byty, práce a okna v bytech / firmy F… a doprava)</label><label>Platnost nabídky (dní)<input name="platnost_dni" value="<?= $e($n['platnost_dni']) ?>" size="3"></label></div>
<label class="mut">Rozsah servisu (tiskne se do nabídky)<textarea name="text_rozsah" rows="4"><?= $e($n['text_rozsah']) ?></textarea></label>
<p class="mut" style="margin-bottom:0">Kroky se píšou na řádky ve tvaru <b>Nadpis | popis</b> (na začátek nadpisu může být ikona). V nabídce se vykreslí jako očíslované bloky.</p>
<label class="mut">Jak u nás probíhá servis (SVJ, domy)<textarea name="text_prubeh" rows="7"><?= $e($n['text_prubeh']) ?></textarea></label>
<label class="mut">Když okno nejde seřídit – jak probíhá oprava<textarea name="text_oprava" rows="7"><?= $e($n['text_oprava']) ?></textarea></label>
<label class="mut">Podmínky oprav (každá na řádek)<textarea name="text_podminky_oprava" rows="5"><?= $e($n['text_podminky_oprava']) ?></textarea></label>
<label class="mut">Podmínky (tiskne se do nabídky)<textarea name="text_podminky" rows="3"><?= $e($n['text_podminky']) ?></textarea></label>
<?php if ($adm): ?><button>Uložit nastavení</button><?php endif; ?></form>

<div class="box" id="velikosti"><h2 style="margin-top:0">Převody velikostí bytů</h2>
<p class="mut" style="margin-top:0">Velikosti „2+1“, „3+kk“ apod. se poznají samy. Jiné zápisy ze seznamů SVJ (např. „1:3“) se tu přiřadí k položce ceníku – nabídka na ně pak počítá sama.</p>
<?php if ($vel): ?><table><tr><th>Zápis v seznamu</th><th>Položka ceníku</th></tr><?php foreach ($vel as $k => $v): ?><tr><td><?= $e(substr($k, 9)) ?></td><td><?= $e($v) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php if ($adm): ?><form method="post" class="ln"><input type="hidden" name="akce" value="velikost"><label>Zápis<input name="text" size="8" placeholder="1:3"></label><label>Položka<select name="kod"><option value="">– zrušit převod –</option><?php foreach ($cen as $r) if ($r['skupina'] === 'byt'): ?><option value="<?= $e($r['kod']) ?>"><?= $e($r['kod'] . ' – ' . $r['nazev']) ?></option><?php endif; ?></select></label><button class="sec">Uložit převod</button></form><?php endif; ?></div>

<div class="box"><h2 style="margin-top:0">Historie ceníku</h2>
<?php if (!$hist): ?><p class="mut">Zatím bez změn.</p><?php else: ?><table><tr><th>Kdy</th><th>Kdo</th><th>Co</th><th></th></tr>
<?php foreach ($hist as $h): ?><tr><td class="nowrap"><?= $e(date('j. n. Y H:i', strtotime($h['kdy']))) ?></td><td><?= $e($h['kdo']) ?></td><td><?= $e($h['popis']) ?></td>
<td><?php if ($adm): ?><form method="post" onsubmit="return confirm('Vrátit ceník do tohoto stavu? Současný stav se uloží do historie.')"><input type="hidden" name="akce" value="obnovit"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button class="sec" style="padding:4px 10px;font-size:13px">Vrátit</button></form><?php endif; ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</main></body></html>
