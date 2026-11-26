<?php
// Zavaděč menu (12. 11. 2026, rozšířeno 15. 11. a 19. 11. 2026, 24. 11. a 26. 11. 2026): vezme původní menu.php ze zálohy ../zaloha-20261112-menu/ a doplní:
//   do skupiny ⚙️ Správa: ✉️ E-maily a příjemci (nastaveni-email.php), 🚪 Konfigurátor ERKADO – zkouška (erkado-test.php)
//   hned za Kartotéku: 📷 Předání dveří (servis.php?sken=1 – rovnou spustí skener QR z předávacího listu; 19. 11. 2026)
//   do stylu lišty: posouvání rozbaleného menu na telefonu (bd-menu-posun; 19. 11. 2026)
//   24. 11. 2026: objednávka z nabídky na byt přenáší patro, zeď, tloušťku zdi a fakturační údaje (bd_odkazy)
//   26. 11. 2026: objednávka z nabídky na byt – do kontroly přidat počet dveří v nabídce a jejich přehled (pol_count / pol_prehled)
// Když se cokoli nepovede, načte původní menu beze změny.
$__src = __DIR__ . '/../zaloha-20261112-menu/menu.php';
$__kod = (string)@file_get_contents($__src);
$__puv = $__kod;
$__kotva = "      ['uzivatele.php', ";
if ($__kod !== '' && strpos($__kod, 'nastaveni-email.php') === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, "      ['nastaveni-email.php', 'E-maily a příjemci', 'nastaveni', '✉️'],\n"
    . "      ['erkado-test.php', 'Konfigurátor ERKADO (zkouška)', 'erkadotest', '🚪'],\n" . $__kotva, $__kod);
}
// 26. 11. 2026: do ⚙️ Správa přidat 📊 Přehledy prodejů (jen admin; prehledy.php)
$__kotva = "      ['uzivatele.php', ";
if ($__kod !== '' && strpos($__kod, 'prehledy.php') === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, "      ['prehledy.php', 'Přehledy prodejů', 'prehledy', '📊'],\n" . $__kotva, $__kod);
}
// 26. 11. 2026: do ⚙️ Správa přidat 🏷️ Akce a slevy (jen admin; akce.php)
$__kotva = "      ['uzivatele.php', ";
if ($__kod !== '' && strpos($__kod, 'akce.php') === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, "      ['akce.php', 'Akce a slevy', 'akce', '🏷️'],\n" . $__kotva, $__kod);
}
$__kotva = "  \$pol[] = ['zamereni-nove.php', ";
if ($__kod !== '' && strpos($__kod, 'servis.php?sken=1') === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, "  \$pol[] = ['servis.php?sken=1', 'Předání dveří (QR)', 'predani', '📷'];\n" . $__kotva, $__kod);
}
// 19. 11. 2026: rozbalené menu na telefonu bylo vyšší než displej a spodní položky nešly zmáčknout (lišta je přilepená nahoře) → rozbalené menu se posouvá uvnitř; ikona pomocníka se přitom schová.
$__kotva = "  echo '<style>.bdbar{position:sticky;";
if ($__kod !== '' && strpos($__kod, 'bd-menu-posun') === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, "  echo '<style>/*bd-menu-posun*/@media (max-width:1100px){.bdbar.otevreno{position:fixed!important;top:0;left:0;right:0;bottom:0;box-sizing:border-box;max-height:none!important;overflow-x:hidden!important;overflow-y:auto!important;align-content:flex-start;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;touch-action:pan-y}.bdbar.otevreno>a{width:calc(50% - 1px);padding:11px 8px;font-size:15px}.bdbar.otevreno .bdsp summary{padding:11px 8px}.bdbar.otevreno .bdsp-m{flex-direction:row;flex-wrap:wrap;gap:0;padding-left:8px}.bdbar.otevreno .bdsp-m a{width:50%;padding:9px 6px;font-size:14px;line-height:1.2}.bdbar.otevreno~.bdpom-btn{display:none}}.bdbar{position:sticky;", $__kod);
}
// 24. 11. 2026: nabídka na byt → objednávka: přenést patro, výtah, zeď, tloušťku zdi a fakturační údaje (původní výběr přenášel jen konfiguraci a jméno)
$__kotva = '        foreach [\'jmeno\', \'prijmeni\', \'telefon\', \'email\', \'ulice\', \'mesto\', \'psc\', \'poznamka\'] as $k) { if (!empty($d[$k])) $o[$k] = $d[$k]; }';
$__kotva = str_replace('foreach [', 'foreach ([', $__kotva);
$__kotva = str_replace('] as $k) { if (!empty', '] as $k) { if (!empty', $__kotva);
if ($__kod !== '' && strpos($__kod, "'tl_zdi'") === false && substr_count($__kod, "foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'poznamka'] as \$k) { if (!empty(\$d[\$k])) \$o[\$k] = \$d[\$k]; }") === 1) {
  $__kod = str_replace("foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'poznamka'] as \$k) { if (!empty(\$d[\$k])) \$o[\$k] = \$d[\$k]; }",
    "foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'poznamka', 'vytah', 'zed', 'tl_zdi', 'vyska_cm', 'souradnice', 'doprava_km', 'doprava_cena', 'fakturace', 'fak_jmeno', 'fak_adresa', 'ic', 'platce_dph'] as \$k) { if (isset(\$d[\$k]) && \$d[\$k] !== '' && \$d[\$k] !== null && \$d[\$k] !== false) \$o[\$k] = \$d[\$k]; }\n"
    . "        if (isset(\$d['patro']) && \$d['patro'] !== '' && \$d['patro'] !== null) \$o['patro'] = \$d['patro'];", $__kod);
}
// 26. 11. 2026: objednávka z nabídky na byt – do kontroly přidat počet dveří v nabídce (objednávka bere jen první položku) a jejich krátký přehled (kontrola proti nabídce v poptavka-dvere-nova.js)
$__kotva = "        foreach (['model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'tl_zarubne'] as \$k) { \$v = \$d['polozky'][0]['cfg'][\$k] ?? ''; if (\$v !== '' && \$v !== null) \$o[\$k] = \$v; }";
if ($__kod !== '' && strpos($__kod, "'pol_count'") === false && substr_count($__kod, $__kotva) === 1) {
  $__kod = str_replace($__kotva, $__kotva . "\n"
    . "        \$o['pol_count'] = count(\$d['polozky']);\n"
    . "        if (\$o['pol_count'] > 1) { \$o['pol_prehled'] = array_values(array_map(function (\$p) { \$c = is_array(\$p['cfg'] ?? null) ? \$p['cfg'] : []; return trim(implode(' / ', array_filter([(string)(\$c['odstin'] ?? ''), (string)(\$c['kovani'] ?? ''), (\$c['sire'] ?? '') !== '' ? \$c['sire'] . ' cm' : '']))); }, \$d['polozky'])); }", $__kod);
}
if ($__kod !== '' && $__kod !== $__puv) {
  $__tmp = __DIR__ . '/menu-' . getmypid() . '.tmp';
  if (@file_put_contents($__tmp, $__kod) === strlen($__kod)) { require $__tmp; @unlink($__tmp); }
  else { @unlink($__tmp); eval('?>' . $__kod); }
} else {
  require $__src;
}
unset($__src, $__kod, $__puv, $__kotva, $__tmp);
// Položka „Předání dveří“ otevírá Servis s ?sken=1: po načtení stránky se sám spustí skener QR.
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'servis.php' && isset($_GET['sken'])) {
  echo '<script>window.addEventListener("load",function(){var b=document.getElementById("skenovat");if(b)b.click()});</script>';
}
