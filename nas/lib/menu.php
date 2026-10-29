<?php
// Společná horní lišta systému (kartoteka.dvere.app): tlačítko Úvod a menu. Všechny odkazy se otevírají ve stejném okně.
// Pomocník není v menu: je to plovoucí ikona 🙋 vpravo dole na každé stránce, která otevře chatovací okno (pomocnik.php?okno=1 v iframe).
function bd_bar(string $akt = ''): void {
  global $cfg;
  // stránka otevřená uvnitř plovoucího okna pomocníka: bez lišty a bez další plovoucí ikony
  if (isset($_GET['okno'])) { echo '<style>body{margin-top:0;padding-top:0}</style>'; return; }
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  $pol = [
    ['index.php', 'Úvod', 'uvod', '🏠'],
    ['kartoteka.php', 'Kartotéka', 'kartoteka', '📋'],
    ['koncepty.php', 'Rozpracované', 'koncepty', '📝'],
    ['zakaznici.php', 'Zákazníci', 'zakaznici', '👥'],
    ['poukazy.php', 'Poukazy', 'poukazy', '🎁'],
    ['sklad.php', 'Sklad', 'sklad', '📦'],
    ['dodavatel.php', 'Dodavatel', 'dodavatel', '🚚'],
    ['montaze.php', 'Montáže', 'montaze', '📅'],
    ['servis.php', 'Servis', 'servis', '🔧'],
    ['servis-pozadavky.php', 'Požadavky', 'pozadavky', '🛠'],
  ];
  echo '<style>.bdbar{position:sticky;top:0;z-index:60;display:flex;gap:4px;align-items:center;overflow-x:auto;white-space:nowrap;background:#0f2747;padding:8px max(12px,env(safe-area-inset-left,0px));-webkit-overflow-scrolling:touch}'
     . '.bdbar a{color:#dbe6f7;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}'
     . '.bdbar a:hover{background:#1d3f6e}.bdbar a.on{background:#2563eb;color:#fff}.bdbar a.uvod{background:#16a34a;color:#fff;margin-right:6px}.bdbar b.bdn{background:#e11d48;color:#fff;border-radius:999px;padding:1px 7px;margin-left:5px;font-size:12px}.bd-app .bdbar{display:none}'
     // mobil: rozbalovací menu přes celou šířku (tlačítko ☰ s názvem aktuální stránky)
     . '.bdbar-t{display:none;width:100%;align-items:center;justify-content:space-between;gap:10px;background:transparent;border:0;color:#fff;font:600 16px/1.2 system-ui,Arial,sans-serif;padding:10px 4px;cursor:pointer;text-align:left}.bdbar-s{transition:transform .15s}.bdbar.otevreno .bdbar-s{transform:rotate(180deg)}'
     . '@media (max-width:700px){.bdbar{flex-wrap:wrap;overflow:visible;white-space:normal;padding:4px 12px;gap:2px}.bdbar-t{display:flex}.bdbar a{display:none;width:100%;box-sizing:border-box;padding:13px 12px;font-size:16px}.bdbar a.uvod{margin-right:0}.bdbar.otevreno a{display:block}.bdbar.otevreno{padding-bottom:10px}}</style>'
     // nainstalovaná aplikace Servis (start_url servis.php?app=1) nebo Kartotéka (kartoteka.php?app=kartoteka), režim standalone: bez horní lišty na všech stránkách v jejím okně
     . '<script>(function(){try{var s=sessionStorage;if(/[?&]app=(1|kartoteka)(&|$)/.test(location.search))s.setItem("bd-servis-app","1");if(s.getItem("bd-servis-app")==="1"&&(matchMedia("(display-mode: standalone)").matches||navigator.standalone===true))document.documentElement.classList.add("bd-app")}catch(e){}})();</script>'
     . '<nav class="bdbar">';
  $nPozM = 0; try { $nPozM = (int)db()->query("SELECT COUNT(*) FROM servis_pozadavky WHERE stav='nove'")->fetchColumn(); } catch (Throwable $x) {}
  $nKonc = 0; if (is_file(__DIR__ . '/koncepty.php')) { require_once __DIR__ . '/koncepty.php'; try { $nKonc = konc_pocet(db()); } catch (Throwable $x) {} }   // rozpracované nabídky
  // název aktuální stránky do tlačítka rozbalovacího menu (na mobilu)
  if (function_exists('uziv_admin')) $pol[] = ['uzivatele.php', uziv_admin() ? 'Uživatelé' : 'Moje heslo', 'uzivatele', '👤'];
  $akL = 'Menu'; foreach ($pol as [, $t0, $k0, $ik0]) if ($k0 === $akt) $akL = $ik0 . ' ' . $t0;
  echo '<button type="button" class="bdbar-t" aria-expanded="false" onclick="var n=this.parentNode;n.classList.toggle(\'otevreno\');this.setAttribute(\'aria-expanded\',n.classList.contains(\'otevreno\'))"><span>☰ ' . htmlspecialchars($akL) . ($nPozM > 0 ? ' <b class="bdn">' . $nPozM . '</b>' : '') . '</span><span class="bdbar-s">▾</span></button>';
  foreach ($pol as [$u, $t, $k, $ik]) echo '<a href="' . htmlspecialchars($u) . '" class="' . ($k === 'uvod' ? 'uvod ' : '') . ($k === $akt ? 'on' : '') . '">' . $ik . ' ' . htmlspecialchars($t) . ($k === 'pozadavky' && $nPozM > 0 ? ' <b class="bdn">' . $nPozM . '</b>' : '') . ($k === 'koncepty' && $nKonc > 0 ? ' <b class="bdn" style="background:#d97706">' . $nKonc . '</b>' : '') . '</a>';
  if (function_exists('uziv_jmeno') && uziv_jmeno() !== '') echo '<span style="margin-left:auto;color:#9fb6d8;font:13px system-ui,Arial,sans-serif;padding:0 8px;white-space:nowrap">👤 ' . htmlspecialchars(uziv_jmeno()) . '</span>';
  echo '</nav>';
  if ($akt !== 'pomocnik') bd_pomocnik_okno();
  if ($akt === 'kartoteka' && isset($_GET['id'])) bd_navrh_tlacitko((int)$_GET['id']);
}

// Detail poptávky: tlačítko „🤖 Připravit nabídku“ vedle „Vytvořit nabídku z této poptávky“ (pomocník připraví návrh, viz navrh-nabidky.php).
function bd_navrh_tlacitko(int $id): void {
  if ($id <= 0 || !is_file(__DIR__ . '/navrh-nabidky.php')) return;
  echo '<script>document.addEventListener("DOMContentLoaded",function(){var a=[].slice.call(document.querySelectorAll("a.btn")).filter(function(x){return /Vytvořit nabídku z této poptávky/.test(x.textContent)})[0];if(!a)return;'
     . 'var b=document.createElement("a");b.className="btn";b.href="navrh-nabidky.php?id=' . $id . '";b.textContent="🤖 Připravit nabídku (pomocník)";b.title="Pomocník přečte poptávku a předvyplní nabídku. Nic se neodesílá – zkontrolujete a doplníte.";b.style.cssText="background:#7c3aed;border-color:#7c3aed";'
     . 'a.parentNode.insertBefore(document.createTextNode(" "),a.nextSibling);a.parentNode.insertBefore(b,a.nextSibling.nextSibling)});</script>';
}

// Plovoucí ikona pomocníka + chatovací okno. Konverzaci si pomocník drží v sessionStorage (pom-historie),
// takže po přechodu na jinou stránku zůstane. Otevřené okno se po přechodu otevře znovu (jen na počítači).
function bd_pomocnik_okno(): void {
  echo <<<'BDPOM'
<style>
.bdpom-btn{position:fixed;right:max(16px,env(safe-area-inset-right,0px));bottom:max(16px,env(safe-area-inset-bottom,0px));z-index:9998;width:60px;height:60px;border-radius:50%;border:0;background:#2563eb;color:#fff;font-size:30px;line-height:1;box-shadow:0 6px 20px rgba(15,39,71,.35);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
.bdpom-btn:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}
@media (hover:hover){.bdpom-btn:hover{background:#1d4ed8}}
.bdpom-okno{position:fixed;right:16px;bottom:88px;z-index:9999;width:400px;max-width:calc(100vw - 32px);height:min(640px,calc(100vh - 110px));background:#fff;border-radius:14px;box-shadow:0 12px 40px rgba(15,39,71,.35);display:none;flex-direction:column;overflow:hidden}
.bdpom-okno.on{display:flex}
.bdpom-hl{display:flex;align-items:center;gap:4px;background:#0f2747;color:#fff;padding:8px 8px 8px 14px;font:600 16px/1.2 system-ui,Arial,sans-serif}
.bdpom-hl span{flex:1}
.bdpom-hl a,.bdpom-hl button{color:#fff;background:transparent;border:0;font:600 20px/1 system-ui,Arial,sans-serif;cursor:pointer;text-decoration:none;padding:8px 10px;border-radius:8px}
@media (hover:hover){.bdpom-hl a:hover,.bdpom-hl button:hover{background:#1d3f6e}}
.bdpom-okno iframe{flex:1;border:0;width:100%;background:#fff}
@media (max-width:600px){.bdpom-okno{right:0;bottom:0;width:100%;max-width:100%;height:100%;height:100dvh;border-radius:0}}
@media print{.bdpom-btn,.bdpom-okno{display:none!important}}
</style>
<button type="button" class="bdpom-btn" id="bdpom-btn" aria-label="Pomocník – zeptat se" title="Pomocník – zeptat se">🙋</button>
<div class="bdpom-okno" id="bdpom-okno" role="dialog" aria-label="Pomocník">
  <div class="bdpom-hl"><span>🙋 Pomocník</span><a href="pomocnik.php" title="Otevřít na celou stránku" aria-label="Otevřít na celou stránku">⤢</a><button type="button" id="bdpom-zavrit" title="Zavřít" aria-label="Zavřít">✕</button></div>
</div>
<script>
(function () {
  var b = document.getElementById('bdpom-btn'), o = document.getElementById('bdpom-okno'), f = null;
  var maly = function () { return window.innerWidth <= 600; };
  function otevri() {
    if (!f) { f = document.createElement('iframe'); f.src = 'pomocnik.php?okno=1'; f.title = 'Pomocník'; f.setAttribute('allow', 'microphone; camera'); o.appendChild(f); }
    o.classList.add('on'); if (maly()) b.style.display = 'none';
    try { sessionStorage.setItem('bdpom-otevreno', '1'); } catch (e) {}
  }
  function zavri() {
    o.classList.remove('on'); b.style.display = '';
    try { sessionStorage.removeItem('bdpom-otevreno'); } catch (e) {}
  }
  b.addEventListener('click', function () { o.classList.contains('on') ? zavri() : otevri(); });
  document.getElementById('bdpom-zavrit').addEventListener('click', zavri);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && o.classList.contains('on')) zavri(); });
  try { if (sessionStorage.getItem('bdpom-otevreno') === '1' && !maly()) otevri(); } catch (e) {}
})();
</script>
BDPOM;
}

// Odkazy „Vytvořit objednávku / nabídku“ pro záznam v kartotéce (z poptávky nebo nabídky). Údaje zákazníka se přenesou do formuláře na webu.
function bd_prod_p(): string { $j = function_exists('uziv_jmeno') ? uziv_jmeno() : ''; return $j === '' ? '' : '&p=' . rawurlencode($j); }
function bd_prod_url(string $url): string {
  $j = function_exists('uziv_jmeno') ? uziv_jmeno() : ''; if ($j === '') return $url;
  $h = strpos($url, '#'); $base = $h === false ? $url : substr($url, 0, $h); $hash = $h === false ? '' : substr($url, $h);
  return $base . (strpos($base, '?') === false ? '?' : '&') . 'p=' . rawurlencode($j) . $hash;
}
function bd_odkazy(array $r, array $d): array {
  global $cfg;
  $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  // celá data poptávky (typ dveří, barva, zárubeň, kování… i údaje zákazníka) – stejné klíče jako na webu (LINK_KEYS v poptavka-dvere-nova.js)
  $klice = ['cislo', 'model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'jmeno', 'prijmeni', 'telefon', 'email',
    'ulice', 'mesto', 'psc', 'patro', 'vytah', 'doprava_km', 'doprava_cena', 'souradnice', 'fakturace', 'fak_jmeno', 'fak_adresa', 'ic', 'platce_dph', 'zed', 'vyska_cm', 'poznamka',
    'model', 'tl_zarubne', 'cena_celkem', 'polozky', 'typ_dveri', 'provedeni', 'pocet_ks', 'zdroj', 'kategorie', 'bourani'];
  $o = [];
  foreach ($klice as $k) { $v = $d[$k] ?? null; if ($v !== null && $v !== '' && $v !== false) $o[$k] = $v; }
  if (empty($o['cislo']) && !empty($r['cislo'])) $o['cislo'] = (string)$r['cislo'];
  // nabídka na dveře do bytu: přenést celou konfiguraci z první položky (cfg) a zákazníka – jako orderLink() v nabidka.js
    if (($d['typ'] ?? '') === 'nabidka' && ($d['kategorie'] ?? '') === 'byt' && is_array($d['polozky'][0]['cfg'] ?? null)) {
        $o = [];
        foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'poznamka'] as $k) { if (!empty($d[$k])) $o[$k] = $d[$k]; }
        $o['cislo'] = (string)($d['cislo'] ?? $d['obj_cislo'] ?? $r['cislo'] ?? '');
        foreach (['model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'tl_zarubne'] as $k) { $v = $d['polozky'][0]['cfg'][$k] ?? ''; if ($v !== '' && $v !== null) $o[$k] = $v; }
    }
    $pf = rtrim(strtr(base64_encode(json_encode($o, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
  // náhledy fotek (&f=…) z odkazu v těle e-mailu poptávky, pokud tam jsou
  $foto = (preg_match('#Vytvořit objednávku:\s*https?://\S*?(&f=[A-Za-z0-9_.~-]+)#u', (string)($r['telo'] ?? ''), $m)) ? $m[1] : '';
  if (($d['typ'] ?? '') === 'nabidka' && in_array($d['kategorie'] ?? '', ['interier', 'dum'], true)) { // nabídka na interiér / dům (víc položek) → objednávka s více položkami
    $plna = $d; unset($plna['objednavka_odkaz']);
    $pv = rtrim(strtr(base64_encode(json_encode($plna, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    return [$wu . '/objednavka-vice.html#d=' . $pv, $wu . '/nabidka-nova.html#d=' . $pf . $foto];
  }
  return [$wu . '/objednavka-nova.html#d=' . $pf . $foto, $wu . '/nabidka-nova.html#d=' . $pf . $foto];
}
