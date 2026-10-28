<?php
// Veřejná stránka „Moje dveře“ pro zákazníka: po načtení QR kódu z předávacího listu ukáže záruku, servisní historii (jen datum a druh)
// a odkazy na registraci záruky a servis. Přístup jen se tajným kódem z QR; žádné osobní údaje ani ceny se nezobrazují.
require __DIR__ . '/lib/start.php'; require __DIR__ . '/lib/zaruka.php'; require_once __DIR__ . '/lib/pozadavky.php'; require_once __DIR__ . '/lib/bonus.php';
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$t = (string)($_GET['t'] ?? ''); $z = null;
if (preg_match('/^[a-f0-9]{24}$/', $t)) {
  $pdo = db();
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE typ='objednavka_interni' AND data_json LIKE ?");
  $st->execute(['%"zakaznik_token":"' . $t . '"%']); $z = $st->fetch(PDO::FETCH_ASSOC);
}
$dn = fn($v) => date('j. n. Y', strtotime($v));
$chyba = '';
if ($z && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['akce'] ?? '') === 'nahlasit') {
  $ip = klient_ip(); $ted = time();
  $st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='moje_servis' AND cas>?"); $st->execute([$ip, $ted - 3600]);
  if (!empty($_POST['_honey'])) { header('Location: ?t=' . $t . '&ok=1#servis'); exit; }
  if ((int)$st->fetchColumn() >= 6) $chyba = 'Příliš mnoho odeslání z jednoho zařízení. Zkuste to prosím za hodinu, nebo nám zavolejte.';
  else {
    $pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'moje_servis', ?)")->execute([$ip, $ted]);
    $soubory = [];
    foreach ((array)($_FILES['fotky']['tmp_name'] ?? []) as $i => $tmp) if ($tmp !== '' && ($_FILES['fotky']['error'][$i] ?? 1) === UPLOAD_ERR_OK) $soubory[] = [$tmp, (string)$_FILES['fotky']['name'][$i]];
    try {
      poz_vytvor($pdo, $cfg, $DATA, $z, ['druh' => $_POST['druh'] ?? 'zavada', 'popis' => $_POST['popis'] ?? '', 'preferovany' => $_POST['preferovany'] ?? '', 'telefon' => $_POST['telefon'] ?? '', 'email' => $_POST['email'] ?? ''], $soubory, 'web Moje dveře');
      header('Location: ?t=' . $t . '&ok=1#servis'); exit;
    } catch (InvalidArgumentException $x) { $chyba = 'Popište prosím závadu alespoň několika slovy.'; }
  }
}
if ($z && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['akce'] ?? '') === 'bonus') {
  $ip = klient_ip(); $ted = time();
  $st = $pdo->prepare("SELECT COUNT(*) FROM pokusy WHERE ip=? AND typ='moje_bonus' AND cas>?"); $st->execute([$ip, $ted - 3600]);
  if ((int)$st->fetchColumn() < 20) {
    $pdo->prepare("INSERT INTO pokusy (ip, typ, cas) VALUES (?, 'moje_bonus', ?)")->execute([$ip, $ted]);
    [$bok, $bzpr] = bonus_akce($pdo, $cfg, $DATA, $z, (string)($_POST['druh'] ?? ''));
    header('Location: ?t=' . $t . '&b=' . ($bok ? '1' : '0') . '&bz=' . rawurlencode($bzpr) . '#bonus'); exit;
  }
}
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Moje dveře – Bezpečné dveře</title><style>
body{font:17px/1.5 system-ui,Arial,sans-serif;margin:0;background:#f3f5f8;color:#1b2430}main{max-width:560px;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:14px;padding:16px;margin-bottom:14px;box-shadow:0 1px 3px #0002}h1{font-size:22px;margin:0 0 4px}h2{font-size:18px;margin:0 0 8px}.mut{color:#6b7787;font-size:14px}
.stav{border-radius:14px;padding:16px;margin-bottom:14px;font-size:21px;font-weight:600}.ok{background:#d9f2e0;color:#14653a}.po{background:#fde2e2;color:#9b1c1c}.nic{background:#fff0cc;color:#7a5200}
a.btn{display:block;text-align:center;background:#1f5fbf;color:#fff;text-decoration:none;border-radius:10px;padding:13px;margin-top:10px}a.btn.sec{background:#e6ebf3;color:#1b2430}
dl{margin:8px 0 0;display:grid;grid-template-columns:auto 1fr;gap:4px 12px}dt{color:#6b7787}dd{margin:0}.r{border-top:1px solid #e4e8ee;padding:8px 0}
.tl{list-style:none;margin:6px 0 0;padding:0}.tl li{position:relative;padding:0 0 12px 22px;border-left:2px solid #d5dbe5;margin-left:6px}.tl li:last-child{border-left-color:transparent;padding-bottom:0}
.tl li:before{content:"";position:absolute;left:-7px;top:5px;width:12px;height:12px;border-radius:50%;background:#1f5fbf}.tl li.bud:before{background:#fff;border:2px solid #14653a;left:-8px;top:4px}
.tl .dat{font-weight:600}.tl li.bud .dat{color:#14653a}.po2{border-radius:10px;background:#f3f5f8;padding:10px 12px;margin-top:8px;font-size:15px}.po2 b{display:block}
.pst{display:inline-block;font-size:12px;border-radius:999px;padding:2px 10px;background:#fff0cc;color:#7a5200;margin-left:6px}.pst.ok{background:#d9f2e0;color:#14653a}
form.sv label{display:block;margin-top:10px;font-size:14px;color:#556}form.sv input,form.sv textarea{width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:10px;margin-top:3px}form.sv textarea{min-height:96px}
form.sv button{width:100%;margin-top:14px;font:inherit;background:#1f5fbf;color:#fff;border:0;border-radius:10px;padding:13px;cursor:pointer}.okb{background:#d9f2e0;color:#14653a;border-radius:12px;padding:12px 14px;margin-bottom:14px}.erb{background:#fde2e2;color:#9b1c1c;border-radius:12px;padding:12px 14px;margin-bottom:14px}.hp{position:absolute;left:-9999px}
</style><link rel="stylesheet" href="https://bezpecne-dvere.cz/aplikace/moje-stranka.css?v=20261020a"></head><body class="bd"><header class="tp"><img src="https://bezpecne-dvere.cz/logo-white.svg" alt="Bezpečné dveře" width="150" height="32"><a href="tel:+420725559235">☎ Zavolat</a></header><main>
<?php if (!$z): ?><div class="box"><h1>Dveře nenalezeny</h1><p>Odkaz není platný. Zkuste prosím znovu načíst QR kód z předávacího listu, nebo nás kontaktujte: <a href="tel:+420725559235">+420 725 559 235</a>.</p></div>
<?php else:
  $d = json_decode((string)$z['data_json'], true) ?: [];
  $predano = !empty($z['predano_dne']); $dnes = date('Y-m-d'); $cislo = (string)($d['obj_cislo'] ?? $z['cislo']);
  $sv = $pdo->prepare('SELECT datum, druh, dalsi_kontrola FROM servis WHERE zadost_id=? ORDER BY datum DESC, id DESC'); $sv->execute([$z['id']]); $servisy = $sv->fetchAll(PDO::FETCH_ASSOC);
  $dalsi = ''; foreach ($servisy as $q) if (!empty($q['dalsi_kontrola']) && $q['dalsi_kontrola'] > $dalsi) $dalsi = $q['dalsi_kontrola'];
  $druhy = ['kontrola' => 'Roční kontrola', 'servis' => 'Servis', 'reklamace' => 'Reklamace'];
  $pozadavky = poz_pro_zakaznika($pdo, (int)$z['id']); $rodny = poz_rodny_list($pdo, $z); $telM = poz_maska_tel((string)($d['telefon'] ?? ''));
  $reg = null; $klic = ltrim(preg_replace('/\D+/', '', $cislo), '0');
  if ($klic !== '') foreach ($pdo->query("SELECT * FROM zadosti WHERE typ='registrace' ORDER BY id DESC") as $r) { $x = json_decode((string)$r['data_json'], true) ?: []; if (ltrim(preg_replace('/\D+/', '', (string)($x['cislo_objednavky'] ?? '')), '0') === $klic) { $reg = $r; break; } }
  $model = in_array($d['model_kod'] ?? '', ['premium60', 'extreme54', 'magnum56'], true); $zakladni = !preg_match('/Ano,\s*na/u', (string)($d['zaruka'] ?? ''));
  $lhuta = $predano ? date('Y-m-d', strtotime($z['predano_dne'] . ' +30 days')) : '';
  $regUrl = 'https://bezpecne-dvere.cz/registrace-zaruky.html?c=' . rawurlencode($cislo) . ($predano ? '&d=' . $z['predano_dne'] : ''); ?>
  <?php
    $kod = (string)($d['model_kod'] ?? ''); $mt = (string)($d['model'] ?? ''); $od = (string)($d['odstin'] ?? '');
    $byt = in_array($kod, ['premium60', 'extreme54', 'magnum56', 'securido65'], true);
    $kind = $byt ? 'byt' : (preg_match('/interi|vnit[rř]n/iu', $kod . ' ' . $mt) ? 'interier' : (preg_match('/erkado|dom|vchod|rodinn/iu', $kod . ' ' . $mt) ? 'domovni' : 'jine'));
    $popis = ['byt' => 'Bezpečnostní dveře do bytu', 'domovni' => 'Vchodové dveře do domu', 'interier' => 'Interiérové dveře', 'jine' => 'Dveře od Bezpečné dveře'][$kind];
    $spec = ['premium60' => 'RC3 · EI 60 · 42 dB', 'extreme54' => 'RC3 · EI 30 · 40 dB', 'magnum56' => 'RC3 · EI 30 · 44 dB', 'securido65' => 'RC3 · EI 30 · 39 dB'][$kod] ?? '';
    $odm = ['premium60' => ['černá perleť' => 'premium60-cerna-perlet', 'dub' => 'premium60-dub', 'ořech' => 'premium60-orech', 'světlé wenge' => 'premium60-svetle-wenge', 'bílá' => 'premium60-bila', 'antracit' => 'premium60-antracit'],
      'extreme54' => ['dub natura' => 'extreme54-dub-natura', 'ořech natura' => 'extreme54-orech-natura', 'antracit' => 'extreme54-antracit']];
    $obr = $odm[$kod][mb_strtolower(trim($od))] ?? ($byt ? $kod : ($kind === 'interier' ? 'interier/frezia1' : ($kind === 'domovni' ? 'domovni/arendal' : 'premium60')));
    $obrUrl = 'https://bezpecne-dvere.cz/aplikace/img/' . $obr . '.jpg'; ?>
  <div class="promo" id="promo" data-t="<?= $e($t) ?>" hidden><div class="promo-track" id="promoTrack"></div><div class="promo-dots" id="promoDots"></div></div>
  <div class="hero k-<?= $e($kind) ?>" id="hero" data-kod="<?= $e($kod) ?>" data-model="<?= $e($mt) ?>" data-odstin="<?= $e($od) ?>">
    <div class="hero-t"><span class="hero-k">Moje dveře · obj. <?= $e($cislo) ?></span><h1><?= $e($mt !== '' ? $mt : 'Vaše dveře') ?></h1><p class="hero-s"><?= $e($popis . ($spec !== '' ? ' · ' . $spec : '')) ?></p>
      <div class="chips"><?php if ($od !== ''): ?><span class="chip">🎨 <?= $e($od) ?></span><?php endif; ?><?php if ($predano): ?><span class="chip">📅 předáno <?= $e($dn($z['predano_dne'])) ?></span><?php endif; ?></div>
      <a class="hero-app" href="https://bezpecne-dvere.cz/aplikace/#moje?t=<?= $e($t) ?>">📱 Otevřít v aplikaci</a></div>
    <img class="hero-i" src="<?= $e($obrUrl) ?>" alt="<?= $e($mt) ?>" width="132"></div>
  <?php if (!empty($d['kovani']) || !empty($d['otevirani'])): ?><div class="box"><h2>Provedení dveří</h2><dl><?php foreach (['Kování' => 'kovani', 'Otevírání' => 'otevirani'] as $lab => $k) if (!empty($d[$k])): ?><dt><?= $lab ?></dt><dd><?= $e($d[$k]) ?></dd><?php endif; ?></dl></div><?php endif; ?>
  <?php if (!$predano): ?><div class="stav nic">Dveře zatím nebyly předány</div><p class="mut" style="margin:-6px 4px 14px">Záruka začíná dnem předání dveří.</p>
  <?php else:
    bonus_tabulky($pdo); $zdo = zaruka_efektivni_do($pdo, $z);
    if (empty($z['zaruka_do'])) { $cls = 'nic'; $txt = 'Záruka od ' . $dn($z['zaruka_od']); }
    elseif ($zdo >= $dnes) { $cls = 'ok'; $txt = 'V záruce do ' . $dn($zdo); }
    else { $cls = 'po'; $txt = 'Záruka skončila ' . $dn($zdo); } ?>
    <div class="stav <?= $cls ?>"><?= $e($txt) ?><div style="font-size:14px;font-weight:400">Dveře předány <?= $e($dn($z['predano_dne'])) ?><?= !empty($z['zaruka_prodlouzeno']) ? ' · včetně prodloužení o 12 měsíců' : '' ?></div></div>
    <?php bonus_tabulky($pdo); $z = bonus_zadost($pdo, (int)$z['id']) ?: $z; $bs = bonus_stav($z, $d, $pdo); ?>
    <?php if (isset($_GET['bz'])): ?><div class="<?= ($_GET['b'] ?? '') === '1' ? 'okb' : 'erb' ?>"><?= $e(mb_substr((string)$_GET['bz'], 0, 200)) ?></div><?php endif; ?>
    <?php if ($bs['mozne'] || $bs['reg'] !== '' || $bs['rec'] !== ''): ?><div class="box bnx" id="bonus">
      <div class="bnx-h"><span>🎁</span><div><h2>Bonus k záruce: až +12 měsíců</h2><p>6 měsíců za registraci dveří a dalších 6 za recenzi. Platí jen pro základní záruku a jen při pravidelné údržbě: zapisujte ji v aplikaci Moje dveře („Údržbu jsem provedl/a“) každých 6 měsíců podle návodu.</p></div></div>
      <?php if ($bs['mozne'] && $bs['udrzba_ok'] && $bs['udrzba_do'] !== ''): ?><div class="bnx-row wait"><i>🛠</i><div><b>Údržba</b><small>Další zápis o údržbě udělejte nejpozději do <?= $e($dn($bs['udrzba_do'])) ?>, aby bonus zůstal v platnosti.</small></div></div>
      <?php elseif ($bs['mozne'] && !$bs['udrzba_ok'] && $bs['mesicu_celkem'] > 0): ?><div class="bnx-row"><i>⚠</i><div><b>Bonus zanikl</b><small>Zápis o pravidelné údržbě nebyl proveden do <?= $e($dn($bs['udrzba_do'])) ?>, proto bonusové měsíce k záruce zanikly. Základní záruka zůstává.</small></div></div><?php endif; ?>
      <?php if ($bs['reg'] !== ''): ?><div class="bnx-row done"><i>✓</i><div><b>Registrace dveří</b><small>Hotovo – záruka prodloužena o 6 měsíců.</small></div></div>
      <?php elseif ($bs['lze_reg']): ?><div class="bnx-row"><i>1</i><div><b>Registrace dveří · +6 měsíců</b><small>Jedním klepnutím, do <?= $e($dn($bs['reg_lhuta'])) ?></small></div><form method="post"><input type="hidden" name="akce" value="bonus"><input type="hidden" name="druh" value="reg"><button class="btn">Zaregistrovat dveře</button></form></div>
      <?php elseif ($bs['reg_prosla']): ?><div class="bnx-row"><i>1</i><div><b>Registrace dveří</b><small>Lhůta 30 dnů od předání už uplynula.</small></div></div><?php endif; ?>
      <?php if ($bs['rec_schvaleno'] !== ''): ?><div class="bnx-row done"><i>✓</i><div><b>Recenze</b><small>Děkujeme – záruka prodloužena o dalších 6 měsíců.</small></div></div>
      <?php elseif ($bs['rec'] !== ''): ?><div class="bnx-row wait"><i>⏳</i><div><b>Recenze</b><small>Čeká na ověření, pak prodloužíme záruku o 6 měsíců.</small></div></div>
      <?php elseif ($bs['rec_prosla']): ?><div class="bnx-row"><i>2</i><div><b>Recenze na Google</b><small>Lhůta 30 dnů od předání už uplynula.</small></div></div>
      <?php elseif ($bs['lze_rec']): ?><div class="bnx-row"><i>2</i><div><b>Recenze na Google · +6 měsíců</b><small>Napište nám hodnocení do <?= $e($dn($bs['reg_lhuta'])) ?></small></div><div class="acts"><a class="btn sec" href="https://search.google.com/local/writereview?placeid=ChIJESw78t4hCkcRR1ygmiDY8ac" target="_blank" rel="noopener">Napsat recenzi na Google</a>
        <form method="post"><input type="hidden" name="akce" value="bonus"><input type="hidden" name="druh" value="recenze"><button class="btn">Recenzi jsem napsal/a</button></form></div></div><?php endif; ?></div><?php endif; ?>
    <?php $nab = zaruka_nabidka($d); $lhZ = zaruka_lhuta($z);
    if (!empty($z['zaruka_prodl_let'])): ?><div class="box"><h2>Prodloužená záruka</h2>Záruka byla prodloužena na <b><?= (int)$z['zaruka_prodl_let'] ?> let</b>. Děkujeme!</div>
    <?php elseif ($nab && $dnes <= $lhZ):
      $v = (int)($_GET['v'] ?? 0); if (!isset($nab[$v])) $v = array_key_first($nab); $cena = $nab[$v]; $vsP = zaruka_vs($cislo); $spd = zaruka_spd(zaruka_iban($cfg), $cislo, $cena); ?>
      <div class="box"><h2>Prodloužení záruky</h2>Záruku na dveře můžete dodatečně prodloužit do <b><?= $e($dn($lhZ)) ?></b>. Vyberte délku:
        <?php foreach ($nab as $let => $kcN): ?><a class="btn <?= $let === $v ? '' : 'sec' ?>" href="?t=<?= $e($t) ?>&amp;v=<?= $let ?>">Záruka na <?= $let ?> let · příplatek <?= number_format($kcN, 0, ',', ' ') ?> Kč</a><?php endforeach; ?>
        <p style="margin:14px 0 6px"><b>Platba <?= number_format($cena, 0, ',', ' ') ?> Kč</b> – naskenujte QR kód v bankovní aplikaci:</p>
        <div style="text-align:center"><canvas id="qr" width="240" height="240" style="max-width:100%"></canvas></div>
        <dl><dt>Účet</dt><dd><?= $e(zaruka_ucet($cfg)) ?></dd><dt>Částka</dt><dd><?= number_format($cena, 0, ',', ' ') ?> Kč</dd><dt>Var. symbol</dt><dd><?= $e($vsP) ?></dd></dl>
        <p class="mut" style="margin-bottom:0">Po připsání platby se záruka prodlouží automaticky a pošleme Vám potvrzení e-mailem. Variabilní symbol prosím neměňte.</p></div>
      <script src="qrcode.js"></script><script>(function(){try{var q=qrcode(0,'M');q.addData(<?= json_encode($spd) ?>);q.make();var n=q.getModuleCount(),c=document.getElementById('qr'),g=c.getContext('2d'),qt=4,cell=Math.floor(240/(n+2*qt)),s=cell*(n+2*qt);c.width=c.height=s;g.fillStyle='#fff';g.fillRect(0,0,s,s);g.fillStyle='#000';for(var r=0;r<n;r++)for(var k=0;k<n;k++)if(q.isDark(r,k))g.fillRect((k+qt)*cell,(r+qt)*cell,cell,cell);}catch(e){}})();</script>
    <?php endif; ?>
    <div class="box" id="servis"><h2>Servis a kontroly</h2>
      <?php if (!empty($_GET['ok'])): ?><div class="okb">Děkujeme, požadavek jsme přijali. Stav uvidíte níže – ozveme se a domluvíme termín návštěvy.</div><?php endif; ?>
      <?php if ($chyba !== ''): ?><div class="erb"><?= $e($chyba) ?></div><?php endif; ?>
      <?php if ($dalsi !== ''): ?><p>Doporučená další kontrola: <b><?= $e($dn($dalsi)) ?></b></p><?php endif; ?>
      <?php foreach ($pozadavky as $r): if ($r['stav'] === 'hotovo') continue; ?>
        <div class="po2"><b><?= $e($r['druh_text']) ?> <?= $e($r['cislo']) ?><span class="pst <?= $r['stav'] === 'naplanovano' ? 'ok' : '' ?>"><?= $e($r['stav_text']) ?></span></b>
          <?= $e($r['popis']) ?>
          <?php if ($r['stav'] === 'naplanovano'): ?><br>📅 Návštěva: <b style="display:inline"><?= $e($dn($r['termin_datum'])) ?></b><?= $r['termin_cas'] ? ' · ' . $e($r['termin_cas']) : '' ?><?= $r['zprava'] ? '<br>' . $e($r['zprava']) : '' ?><?php endif; ?></div>
      <?php endforeach; ?>
      <h2 style="margin-top:16px">Rodný list dveří</h2>
      <ul class="tl"><?php foreach ($rodny as $ev): ?><li class="<?= !empty($ev['budouci']) ? 'bud' : '' ?>"><span class="dat"><?= $e($dn($ev['datum'])) ?></span> · <?= $e($ev['text']) ?></li><?php endforeach; ?></ul>
      <?php if (count($rodny) <= 2): ?><p class="mut">Zatím tu není žádný servisní záznam.</p><?php endif; ?></div>
  <?php endif; ?>
  <div class="box"><h2>Nahlásit závadu nebo objednat prohlídku</h2>Údaje o objednávce už máme, stačí popsat, co se děje. Ozveme se a domluvíme termín návštěvy.
    <form class="sv" method="post" enctype="multipart/form-data" action="?t=<?= $e($t) ?>#servis">
      <input type="hidden" name="akce" value="nahlasit">
      <label>Co potřebujete? <select name="druh" style="width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:10px;margin-top:3px"><option value="zavada">Nahlásit závadu / reklamace</option><option value="prohlidka">Objednat roční prohlídku</option></select></label>
      <label>Popis (u závady povinný)<textarea name="popis" placeholder="např. dveře drhnou nahoře, klíč jde ztuha…"></textarea></label>
      <label>Fotky (až 3, nepovinné)<input type="file" name="fotky[]" accept="image/*" multiple></label>
      <label>Kdy se vám hodí návštěva<input name="preferovany" maxlength="200" placeholder="např. odpoledne po 15. hod."></label>
      <label>Telefon<input name="telefon" type="tel" maxlength="40" placeholder="<?= $telM !== '' ? 'Zavoláme na ' . $e($telM) . ' (z objednávky) – nebo zadejte jiné číslo' : '' ?>"></label>
      <label>E-mail (pro potvrzení termínu)<input name="email" type="email" maxlength="120"></label>
      <div class="hp" aria-hidden="true"><input name="_honey" tabindex="-1" autocomplete="off"></div>
      <button type="submit">Odeslat požadavek</button></form>
    <a class="btn sec" href="tel:+420725559235">Zavolat +420 725 559 235</a><a class="btn sec" href="https://wa.me/420725559235">WhatsApp</a>
    <p class="mut" style="margin-bottom:0">chci@bezpecne-dvere.cz · Obchodní 36, Most</p></div>
<?php endif; ?></main><script src="https://bezpecne-dvere.cz/aplikace/viz-data.js?v=20261020a"></script><script src="https://bezpecne-dvere.cz/aplikace/moje-stranka.js?v=20261020a" defer></script></body></html>
