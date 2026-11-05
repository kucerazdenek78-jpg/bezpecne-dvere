<?php
// Veřejná stránka pro zákazníka (bez přihlášení, přístup jen přes náhodný token):
//  ?t=… nabídka (odkaz z připomínky, viz terminy-pripominky.php): Co nám chcete vzkázat – rozmýšlím se / chci upravit / zavolejte mi / nemám zájem (+ důvod),
//        hodnocení 1–5 (nepovinné) a poznámka. ?a=upravit|zavolat|nezajem předvybere volbu (odkazy jedním klikem z e-mailu).
//        Upravit / zavolat → úkol na úvodu kartotéky; upravit / zavolat / nemám zájem → konec automatických připomínek; nemám zájem → stav Storno.
//  ?m=… dveře a montáž po předání: hodnocení 1–5 + poznámka; 4–5 → nabídne recenzi na Googlu, 1–3 → „ozveme se“.
// Zapíše se až po odeslání formuláře (POST), takže to neovlivní poštovní skenery, které jen otevřou odkaz. Výsledek vidí firma na úvodu kartotéky a dostane e-mail.
require __DIR__ . '/lib/start.php'; require __DIR__ . '/lib/terminy.php';
header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex'); header('Referrer-Policy: no-referrer');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$pdo = db(); terminy_tabulky($pdo);
$t = (string)($_GET['t'] ?? $_POST['t'] ?? ''); $m = (string)($_GET['m'] ?? $_POST['m'] ?? '');
$rezim = ''; $z = null; $zprava = ''; $hotovo = false;
if (preg_match('/^[a-f0-9]{32}$/', $t)) { $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='nabidka' AND nab_token=?"); $st->execute([$t]); $z = $st->fetch(PDO::FETCH_ASSOC) ?: null; $rezim = 'nabidka'; }
elseif (preg_match('/^[a-f0-9]{32}$/', $m)) { $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND mont_token=?"); $st->execute([$m]); $z = $st->fetch(PDO::FETCH_ASSOC) ?: null; $rezim = 'montaz'; }
$d = $z ? (json_decode((string)$z['data_json'], true) ?: []) : [];
$cislo = $z ? (string)($rezim === 'montaz' ? ($d['obj_cislo'] ?? $z['cislo']) : ($z['cislo'] ?: ($d['cislo'] ?? ''))) : '';
$VOLBY = ['rozmysl' => 'Nabídka je v pořádku, ještě se rozmýšlím', 'upravit' => 'Chci nabídku upravit (jiný model, barva, rozměr, kování…)', 'zavolat' => 'Zavolejte mi – chci konzultaci', 'nezajem' => 'Momentálně nemám zájem'];
$DUVODY = ['cena' => 'Cena je vyšší, než jsem čekal(a)', 'termin' => 'Termín nevyhovuje', 'jinde' => 'Vybral(a) jsem jinou firmu nebo jiné dveře', 'pozdeji' => 'Dveře zatím nepotřebuji / odkládám', 'jine' => 'Jiný důvod (napište do poznámky)'];
$volba = (string)($_POST['volba'] ?? $_GET['a'] ?? ''); if (!isset($VOLBY[$volba])) $volba = ($z && $rezim === 'nabidka' && (string)$z['nab_reakce'] !== '' && isset($VOLBY[$z['nab_reakce']])) ? (string)$z['nab_reakce'] : 'rozmysl';
$cisti = fn($s, $max) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$s)), 0, $max);

if ($z && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $ip = klient_ip();
  $st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='hodnoceni' AND cas>?"); $st->execute([$ip, time() - 3600]);
  $hv = (int)($_POST['h'] ?? 0); $pozn = $cisti($_POST['pozn'] ?? '', 1000);
  if ((int)$st->fetchColumn() >= 20) $zprava = 'Příliš mnoho pokusů, zkuste to prosím později.';
  elseif ($rezim === 'montaz' && ($hv < 1 || $hv > 5)) $zprava = 'Vyberte prosím hodnocení od 1 do 5.';
  elseif ($hv < 0 || $hv > 5) $zprava = 'Neplatné hodnocení.';
  else {
    $pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?,?,?)")->execute([$ip, 'hodnoceni', time()]);
    $ted = date('Y-m-d H:i:s'); $jm = terminy_jmeno($d); $odkaz = terminy_base($cfg) . '/kartoteka.php?id=' . (int)$z['id'];
    $komu = array_values(array_filter((array)($cfg['prijemci']['poptavka']['to'] ?? []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if ($rezim === 'montaz') {
      $pdo->prepare('UPDATE zadosti SET mont_hodnoceni=?, mont_hodnoceni_dne=?, mont_hodnoceni_pozn=? WHERE id=?')->execute([$hv, $ted, $pozn, $z['id']]);
      terminy_hist($pdo, (int)$z['id'], 'zákazník ohodnotil dveře a montáž', "$hv/5" . ($pozn !== '' ? ' – ' . $pozn : ''));
      $predmet = ($hv <= 3 ? '⚠ ' : '') . 'Hodnocení montáže ' . preg_replace('/[\r\n]+/', ' ', $cislo) . ": $hv/5";
      $telo = "Zákazník $jm ohodnotil dveře a montáž (objednávka č. $cislo): $hv z 5.\n" . ($pozn !== '' ? "Poznámka: $pozn\n" : '') . ($hv <= 3 ? "\nZákazník není úplně spokojený – ozvěte se mu prosím" . (!empty($d['telefon']) ? ' (☎ ' . $d['telefon'] . ')' : '') . ".\n" : '') . "\nOtevřít: $odkaz";
    } else {
      $duvod = $volba === 'nezajem' && isset($DUVODY[$_POST['duvod'] ?? '']) ? $DUVODY[$_POST['duvod']] : '';
      $tel = $cisti($_POST['tel'] ?? '', 40);
      $reakcePozn = trim(($duvod !== '' ? $duvod : '') . ($volba === 'zavolat' && $tel !== '' ? 'telefon: ' . $tel : ''));
      $pdo->prepare('UPDATE zadosti SET nab_hodnoceni=?, nab_hodnoceni_dne=?, nab_hodnoceni_srozum=?, nab_hodnoceni_pozn=?, nab_reakce=?, nab_reakce_dne=?, nab_reakce_pozn=?, nab_reakce_hotovo=? WHERE id=?')
        ->execute([$hv ?: null, $ted, $volba === 'upravit' ? 'upravit' : 'ano', $pozn, $volba, $ted, $reakcePozn, '', $z['id']]);
      // stejná nabídka zapsaná v kartotéce víckrát (stejný e-mail a číslo) – zastavit připomínky u všech záznamů
      $ids = [(int)$z['id']];
      $em = mb_strtolower(trim((string)($d['email'] ?? '')));
      if ($em !== '') foreach ($pdo->query("SELECT id, cislo, data_json FROM zadosti WHERE typ='nabidka' AND id<>" . (int)$z['id'])->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $d2 = json_decode((string)$r['data_json'], true) ?: [];
        if (mb_strtolower(trim((string)($d2['email'] ?? ''))) === $em && preg_replace('/\s+/', '', mb_strtolower((string)($d2['cislo'] ?? $r['cislo']))) === preg_replace('/\s+/', '', mb_strtolower($cislo))) $ids[] = (int)$r['id'];
      }
      if ($volba !== 'rozmysl') foreach ($ids as $i) $pdo->prepare('UPDATE zadosti SET nab_stop=? WHERE id=?')->execute(['zákazník: ' . $volba . ' ' . date('Y-m-d'), $i]);
      if ($volba === 'nezajem') foreach ($ids as $i) {
        $u = $pdo->prepare("UPDATE zadosti SET stav='storno' WHERE id=? AND stav IN ('nove','rozpracovano','odeslana')"); $u->execute([$i]);
        if ($u->rowCount()) terminy_hist($pdo, $i, 'zákazník nemá zájem – stav změněn na Storno', $duvod);
      }
      $txtVolba = ['rozmysl' => 'ještě se rozmýšlí', 'upravit' => 'CHCE NABÍDKU UPRAVIT', 'zavolat' => 'CHCE ZAVOLAT / KONZULTACI', 'nezajem' => 'nemá zájem'][$volba];
      terminy_hist($pdo, (int)$z['id'], 'reakce zákazníka na nabídku: ' . $txtVolba, trim(($hv ? "$hv/5 " : '') . $reakcePozn . ($pozn !== '' ? ' – ' . $pozn : '')));
      $predmet = ['rozmysl' => '', 'upravit' => '✏️ ', 'zavolat' => '📞 ', 'nezajem' => ''][$volba] . 'Nabídka č. ' . preg_replace('/[\r\n]+/', ' ', $cislo) . ': zákazník ' . $txtVolba . ($hv ? " ($hv/5)" : '');
      $telo = "Zákazník $jm reagoval na nabídku č. $cislo: $txtVolba.\n"
        . ($volba === 'zavolat' ? 'Telefon: ' . ($tel !== '' ? $tel : (string)($d['telefon'] ?? '(neuveden)')) . "\n" : (!empty($d['telefon']) ? 'Telefon: ' . $d['telefon'] . "\n" : ''))
        . ($duvod !== '' ? "Důvod: $duvod\n" : '') . ($hv ? "Hodnocení nabídky: $hv z 5\n" : '') . ($pozn !== '' ? "Poznámka: $pozn\n" : '')
        . ($volba === 'nezajem' ? "\nNabídka je přepnutá na Storno, připomínky se už neposílají.\n" : ($volba !== 'rozmysl' ? "\nAutomatické připomínky se zastavily. Úkol je na úvodní stránce kartotéky (po vyřízení klikněte ✓ Vyřízeno).\n" : ''))
        . "\nOtevřít: $odkaz";
    }
    $hotovo = true;
    try { require_once __DIR__ . '/lib/posta.php'; if ($komu) posli_mail($cfg, $DATA, $komu, [], $cfg['from'], $predmet, $telo); } catch (Throwable $e) { error_log('hodnoceni mail: ' . $e->getMessage()); }
  }
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title><?= $rezim === 'montaz' ? 'Hodnocení dveří a montáže' : 'Nabídka – Bezpečné dveře' ?></title>
<style>body{margin:0;background:#eef2f6;font:16px/1.5 system-ui,Arial,sans-serif;color:#1d2b3a}.b{max-width:540px;margin:0 auto;padding:16px}.k{background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(15,39,71,.12)}h1{font-size:21px;margin:0 0 6px;color:#0f2747}fieldset{border:0;padding:0;margin:16px 0}legend{font-weight:600;margin-bottom:6px}label.o{display:block;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;margin:6px 0;cursor:pointer}label.o:has(input:checked){border-color:#16a34a;background:#f0fdf4}.s{display:flex;gap:6px}.s label{flex:1;text-align:center;padding:12px 0;border:1px solid #cbd5e1;border-radius:10px;cursor:pointer;font-weight:600}.s label:has(input:checked){border-color:#16a34a;background:#f0fdf4}.s input,label.o input{margin-right:6px}textarea,select,input[type=tel]{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font:inherit;background:#fff}textarea{min-height:80px}button,.g{display:block;text-align:center;text-decoration:none;background:#16a34a;color:#fff;border:0;border-radius:10px;padding:13px 20px;font:600 16px system-ui,Arial,sans-serif;cursor:pointer;width:100%;box-sizing:border-box}.g{background:#c2410c;margin-top:14px}.c{color:#b91c1c}.m{color:#64748b;font-size:14px}.sub{margin:4px 0 0 28px}</style></head><body><div class="b"><div class="k">
<?php if (!$z): ?><h1>Odkaz neplatí</h1><p>Tento odkaz už neplatí nebo je neúplný. Napište nám prosím na chci@bezpecne-dvere.cz nebo zavolejte na +420 725 559 235.</p>
<?php elseif ($hotovo && $rezim === 'montaz'): ?><h1>Děkujeme!</h1>
  <?php if ($hv >= 4): ?><p>Moc nás těší, že jste spokojeni. Pomohli byste nám ještě krátkou recenzí na Googlu? Ostatním zákazníkům to hodně pomůže při výběru.</p><a class="g" href="<?= $h(TERM_GOOGLE_RECENZE) ?>">⭐ Napsat recenzi na Googlu</a>
  <?php else: ?><p>Mrzí nás, že nejste úplně spokojeni. Vaše hodnocení jsme předali a ozveme se Vám, abychom to dali do pořádku. Kdyby to spěchalo, volejte +420 725 559 235.</p><?php endif; ?>
<?php elseif ($hotovo): ?><h1>Děkujeme!</h1><p><?= ['rozmysl' => 'Vaši odpověď jsme přijali. Kdybyste cokoli potřebovali, jsme tu.', 'upravit' => 'Ozveme se Vám ohledně úpravy nabídky, obvykle do jednoho pracovního dne.', 'zavolat' => 'Zavoláme Vám, obvykle do jednoho pracovního dne.', 'nezajem' => 'Děkujeme za upřímnost, už Vás nebudeme připomínkami obtěžovat. Kdybyste si to rozmysleli, stačí se ozvat.'][$volba] ?></p>
<?php elseif ($rezim === 'montaz'): ?><h1>Jak jste spokojeni s dveřmi a montáží?</h1><p class="m">Objednávka č. <?= $h($cislo) ?> · zabere to půl minuty · Bezpečné dveře · KK SYSTEM</p>
<?php if ($zprava): ?><p class="c"><?= $h($zprava) ?></p><?php endif; ?>
<?php if ($z['mont_hodnoceni']): ?><p class="m">Už jste nás ohodnotili (<?= (int)$z['mont_hodnoceni'] ?>/5). Můžete hodnocení změnit.</p><?php endif; ?>
<form method="post"><input type="hidden" name="m" value="<?= $h($m) ?>">
<fieldset><legend>Celková spokojenost (1 = špatně, 5 = výborně)</legend><div class="s"><?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="h" value="<?= $i ?>" required><?= $i ?></label><?php endfor; ?></div></fieldset>
<fieldset><legend>Co se nám povedlo, nebo co můžeme zlepšit? (nepovinné)</legend><textarea name="pozn" maxlength="1000" placeholder="Montáž, čistota, dochvilnost, dveře samotné…"></textarea></fieldset>
<button type="submit">Odeslat hodnocení</button></form>
<?php else: ?><h1>Nabídka č. <?= $h($cislo) ?></h1><p class="m">Zabere to půl minuty. Bezpečné dveře · KK SYSTEM</p>
<?php if ($zprava): ?><p class="c"><?= $h($zprava) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="t" value="<?= $h($t) ?>">
<fieldset><legend>Co nám chcete vzkázat?</legend>
<?php foreach ($VOLBY as $k => $txt): ?><label class="o"><input type="radio" name="volba" value="<?= $k ?>"<?= $volba === $k ? ' checked' : '' ?>><?= $h($txt) ?></label>
<?php if ($k === 'zavolat'): ?><div class="sub" data-pro="zavolat"><input type="tel" name="tel" maxlength="40" placeholder="Telefon (pokud jiný než v nabídce)" autocomplete="tel"></div><?php endif; ?>
<?php if ($k === 'nezajem'): ?><div class="sub" data-pro="nezajem"><select name="duvod"><option value="">Co rozhodlo? (nepovinné)</option><?php foreach ($DUVODY as $dk => $dt): ?><option value="<?= $dk ?>"><?= $h($dt) ?></option><?php endforeach; ?></select></div><?php endif; ?>
<?php endforeach; ?></fieldset>
<fieldset><legend>Jak jste spokojeni s naší nabídkou? (nepovinné, 1 = špatně, 5 = výborně)</legend><div class="s"><?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="h" value="<?= $i ?>"<?= (int)$z['nab_hodnoceni'] === $i ? ' checked' : '' ?>><?= $i ?></label><?php endfor; ?></div></fieldset>
<fieldset><legend>Poznámka (nepovinné)</legend><textarea name="pozn" maxlength="1000" placeholder="Co upravit, co vysvětlit, kdy se Vám hodí zavolat…"></textarea></fieldset>
<button type="submit">Odeslat</button></form>
<script>(function(){var f=document.querySelector('form');function u(){var v=(f.querySelector('input[name=volba]:checked')||{}).value;[].forEach.call(f.querySelectorAll('[data-pro]'),function(e){e.style.display=e.getAttribute('data-pro')===v?'':'none'})}f.addEventListener('change',u);u()})();</script>
<?php endif; ?>
</div></div></body></html>
