<?php
// Veřejná stránka (bez přihlášení, přístup jen přes náhodný token): schválení návrhu oprav oken pro SVJ / nájemníka.
// Odkaz je v e-mailu, který posílá svj.php (akce navrh_poslat). Zákazník zvolí „Schvaluji" nebo „Zavolejte mi";
// zapíše se do ok_navrh v okna.sqlite, do historie domu (so_historie) a firmě přijde upozornění e-mailem.
// Zapisuje se až po odeslání formuláře (POST), aby to neovlivnily poštovní skenery, které jen otevřou odkaz.
require __DIR__ . '/lib/start.php';
require_once (getenv('FORMULARE_DATA') ?: '/data') . '/okna-app/navrh.php';
header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex'); header('Referrer-Policy: no-referrer');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$pdo = new PDO('sqlite:' . $DATA . '/okna.sqlite'); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $pdo->exec('PRAGMA busy_timeout=5000');
ok_navrh_tabulka($pdo);
$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$nv = ok_navrh($pdo, $t);
$pol = $nv ? (json_decode((string)$nv['polozky'], true) ?: []) : [];
$jeZj = $nv && ($nv['druh'] ?? 'oprava') === 'zjisteni';   // zjištění závady vs. odsouhlasení opravy
$volba = ($_POST['volba'] ?? $_GET['a'] ?? '') === 'zavolat' ? 'zavolat' : 'ano';
$hotovo = false;

if ($nv && $nv['stav'] === 'odeslano' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $tel = mb_substr(trim((string)($_POST['tel'] ?? '')), 0, 40);
  $pozn = mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)($_POST['pozn'] ?? ''))), 0, 1000);
  $ted = date('Y-m-d H:i:s');
  $stav = $volba === 'zavolat' ? 'zavolat' : 'schvaleno';
  $pdo->prepare('UPDATE ok_navrh SET stav=?, reakce_dne=?, reakce_tel=?, reakce_pozn=? WHERE id=? AND stav=\'odeslano\'')->execute([$stav, $ted, $tel, $pozn, $nv['id']]);
  if ($nv['svj_id']) $pdo->prepare('INSERT INTO so_historie (svj_id, datum, druh, popis, dokument, vytvoreno, kdo) VALUES (?,?,?,?,?,?,?)')
    ->execute([(int)$nv['svj_id'], date('Y-m-d'), 'nabidka',
      ($stav === 'schvaleno' ? ($jeZj ? 'Zákazník SOUHLASÍ se zjištěním závady' : 'Zákazník SCHVÁLIL návrh oprav') : ('Zákazník žádá o telefonický kontakt k ' . ($jeZj ? 'zjištění závady' : 'návrhu oprav'))) . ' (' . $nv['komu'] . ')'
        . ($tel !== '' ? ', tel ' . $tel : '') . ($pozn !== '' ? ' – ' . $pozn : ''), '', $ted, 'web']);
  try {
    require_once __DIR__ . '/lib/posta.php';
    $komu = array_values(array_filter((array)($cfg['prijemci']['poptavka']['to'] ?? []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if (!$komu && filter_var($cfg['from'] ?? '', FILTER_VALIDATE_EMAIL)) $komu = [$cfg['from']];
    if ($komu) {
      $predmet = ($stav === 'schvaleno' ? ($jeZj ? '✔ Souhlas se zjištěním závady – okna' : '✔ Schváleno – opravy oken') : '☎ Žádá o telefon – okna') . ' – ' . $nv['nazev'];
      $telo = ($stav === 'schvaleno' ? ($jeZj ? 'Zákazník SOUHLASÍ se zjištěním / zmapováním závady.' : 'Zákazník SCHVÁLIL návrh oprav oken.') : ('Zákazník žádá o telefonický kontakt k ' . ($jeZj ? 'zjištění závady.' : 'návrhu oprav oken.')))
        . "\n\nKomu bylo posláno: " . $nv['komu'] . ($nv['komu_typ'] === 'najemnik' ? ' (nájemník ' . $nv['nazev'] . ')' : ' (SVJ ' . $nv['nazev'] . ')')
        . "\nPočet bytů: " . count($pol) . ", cena " . ok_navrh_kc($nv['cena'])
        . ($tel !== '' ? "\nTelefon: " . $tel : '') . ($pozn !== '' ? "\nPoznámka: " . $pozn : '')
        . "\n\nOtevřít SVJ v kartotéce: " . rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/') . '/okna/svj.php?svj=' . (int)$nv['svj_id'] . '#navrhy';
      posli_mail($cfg, $DATA, $komu, [], $cfg['from'], $predmet, $telo);
    }
  } catch (Throwable $ex) { error_log('okna-schvaleni mail: ' . $ex->getMessage()); }
  $nv['stav'] = $stav; $hotovo = true;
}
$jizPotvrzeno = $nv && $nv['stav'] !== 'odeslano' && !$hotovo;
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Schválení oprav – Bezpečné dveře</title>
<style>
*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#0f2747;background:#eef2f7;margin:0;padding:16px;line-height:1.55}
main{max-width:680px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 2px 14px rgba(15,39,71,.08);padding:22px}
.hd{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;border-bottom:2px solid #0f2747;padding-bottom:8px;font-size:13px}
h1{font-size:22px;margin:16px 0 6px}table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #cbd5e1;padding:7px 9px;font-size:14px;text-align:left;vertical-align:top}
th{background:#eef2f7}.r{text-align:right}.mut{color:#64748b;font-size:13px}
.btn{display:inline-block;border:none;border-radius:9px;padding:13px 22px;font-size:16px;font-weight:bold;cursor:pointer;text-decoration:none;margin:6px 8px 6px 0}
.ano{background:#15803d;color:#fff}.tel{background:#f1f5f9;color:#0f2747;border:1px solid #cbd5e1}
.box{background:#f8fafc;border:1px solid #e3e8ef;border-radius:10px;padding:14px;margin-top:14px}
input,textarea{font:inherit;padding:9px;border:1px solid #cbd5e1;border-radius:8px;width:100%;max-width:320px}
.ok{background:#dcfce7;border:1px solid #86efac;border-radius:10px;padding:16px;font-size:16px}
.err{background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:16px}
</style></head><body><main>
<div class="hd"><div><b>KK SYSTEM · BEZPEČNÉ DVEŘE CZ</b><br>IČ 64682676 · info@kksystem.cz</div><div class="r">+420 725 559 235<br>+420 471 008 994</div></div>
<?php if (!$nv): ?>
<h1>Odkaz není platný</h1><p>Tento odkaz na schválení oprav už neplatí nebo je chybný. Zkontrolujte prosím adresu v e-mailu, nebo nám zavolejte na +420&nbsp;725&nbsp;559&nbsp;235.</p>
<?php elseif ($hotovo): ?>
<div class="ok"><b><?= $nv['stav'] === 'schvaleno' ? ($jeZj ? '✔ Děkujeme za souhlas se zjištěním závady.' : '✔ Děkujeme, opravy jsou schválené.') : '☎ Děkujeme, ozveme se Vám.' ?></b><br>
<?= $nv['stav'] === 'schvaleno' ? ($jeZj ? 'Domluvíme se na termínu a závadu zmapujeme. Poté Vám pošleme samostatnou cenu případné opravy ke schválení.' : 'Opravu provedeme a předem se domluvíme na termínu. Ceny nepřesáhnou uvedenou maximální hranici.') : 'Co nejdříve se Vám ozveme telefonicky.' ?></div>
<?php elseif ($jizPotvrzeno): ?>
<div class="ok"><b><?= $nv['stav'] === 'schvaleno' ? ($jeZj ? '✔ Souhlas se zjištěním závady už evidujeme.' : '✔ Tento návrh už je schválený.') : '☎ U tohoto návrhu už evidujeme Váš požadavek o telefonát.' ?></b><br>Děkujeme. Pokud chcete něco upřesnit, zavolejte nám na +420&nbsp;725&nbsp;559&nbsp;235.</div>
<?php else: ?>
<h1><?= $jeZj ? 'Souhlas se zjištěním závady' : 'Návrh oprav ke schválení' ?></h1>
<?php if ($jeZj): ?><p class="mut">Pro <b><?= $e($nv['nazev']) ?></b>. Při servisu jsme zjistili závadu, kterou nelze odstranit běžným seřízením. Abychom zjistili přesnou příčinu, je potřeba okno rozebrat a závadu zmapovat – prosíme o souhlas s tímto úkonem.</p>
<?php else: ?><p class="mut">Pro <b><?= $e($nv['nazev']) ?></b>. Prosíme o schválení níže uvedených oprav.</p><?php endif; ?>
<?= ok_navrh_html($pol, $nv['druh'] ?? 'oprava') ?>
<p class="mut"><?= $jeZj ? 'Uvedené ceny jsou maximální a budou vyčísleny podle skutečných nákladů, hranici nepřesáhnou. Po zjištění Vám pošleme samostatnou cenu případné opravy – tu provedeme až po Vašem dalším souhlasu.' : 'Uvedené ceny jsou maximální a budou vyčísleny podle skutečných nákladů, hranici nepřesáhnou. Opravu provedeme až po Vašem schválení a domluvě termínu.' ?></p>
<form method="post" class="box">
<input type="hidden" name="t" value="<?= $e($t) ?>">
<p style="margin-top:0"><b><?= $jeZj ? 'Souhlasíte se zjištěním závady?' : 'Chcete tyto opravy provést?' ?></b></p>
<label style="display:block;margin:6px 0"><input type="radio" name="volba" value="ano" <?= $volba !== 'zavolat' ? 'checked' : '' ?> style="width:auto"> <?= $jeZj ? 'Ano, <b>souhlasím</b> se zjištěním závady' : 'Ano, <b>schvaluji</b> provedení oprav' ?></label>
<label style="display:block;margin:6px 0"><input type="radio" name="volba" value="zavolat" <?= $volba === 'zavolat' ? 'checked' : '' ?> style="width:auto"> Mám dotaz – <b>zavolejte mi</b></label>
<p style="margin-bottom:4px">Telefon (nepovinné):<br><input name="tel" inputmode="tel" value=""></p>
<p style="margin-bottom:4px">Poznámka (nepovinné):<br><textarea name="pozn" rows="2"></textarea></p>
<p style="margin-bottom:0"><button class="btn ano" type="submit">Odeslat odpověď</button></p>
</form>
<?php endif; ?>
<p class="mut" style="margin-top:18px">KK SYSTEM, Zd. Štěpánka 2764/1, 434 01 Most · info@kksystem.cz · www.bezpecne-dvere.cz</p>
</main></body></html>
