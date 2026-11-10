<?php
// Společná horní lišta systému (kartoteka.dvere.app): tlačítko Úvod a menu. Všechny odkazy se otevírají ve stejném okně.
// Pomocník není v menu: je to plovoucí ikona 🙋 vpravo dole na každé stránce, která otevře chatovací okno (pomocnik.php?okno=1 v iframe).
// 2. 11. 2026: v nainstalované aplikaci (standalone) je lišta schovaná JEN na úvodní stránce aplikace (kartoteka.php, servis.php);
// na všech ostatních stránkách (Zákazníci, Rozpracované, Předání…) zůstává, aby se v aplikaci šlo vrátit (aplikace nemá tlačítko Zpět).
// 3. 11. 2026: rozbalovací (mobilní) menu platí do šířky 1100 px (rozevřený telefon / tablet), jinak se lišta s 11+ položkami rozpadala.
// 3. 11. 2026: na úvodní stránce pruh „Hlídání termínů“ (poptávky do 48 h, nabídky bez odpovědi), viz lib/terminy.php; stránka terminy.php s kalendářem k odběru.
// 4. 11. 2026: pruh „Hlídání termínů“ už nevykresluje lišta, ale úvodní stránka (index.php) – je pod zeleným pruhem s hledáním (terminy_banner()).
// 3. 11. 2026: Montáže (montaze.php) a Termíny (terminy.php) jsou v menu jedna položka „Termíny a montáže“; pod lištou je přepínač mezi oběma stránkami.
// 4. 11. 2026: přepínač 🚪 Dveře / 🪟 Okna u položky Servis (jen administrátor); okna/index.php má vlastní lištu se stejným přepínačem.
// 4. 11. 2026: menu poskládané do skupin: 📦 Sklad (Sklad, Dodavatel), 🛠 Požadavky (Požadavky, Poukazy), ⚙️ Správa (jen administrátor: Nástroje, Zaměstnanci, Číselná řada, E-shop,
//   Reklamní materiály, Využití aplikace). Ostatní zaměstnanci (ne administrátor) mají 🧰 Nástroje jako samostatnou položku; „Moje heslo“ je odkaz u jména vpravo.
// 4. 11. 2026: položka „📐 Zaměření“ (zamereni-nove.php) – rychlé zaměření v terénu z telefonu.
// 4. 11. 2026: „📲 Instalovat aplikaci“ přesunuto ze zeleného pruhu úvodní stránky do menu ⚙️ Správa (vidí administrátor); funguje na každé stránce (bd_instalace_tlacitko()).
// 5. 11. 2026: ⚙️ Správa → „🤖 Pomocník AI na webu“ (web-pomocnik.php): vypínač webového pomocníka v konfigurátoru dveří do domu a přehled využití.
// 9. 11. 2026: ⚙️ Správa → „⭐ Zpětná vazba od zákazníků“ (zpetna-vazba.php): hodnocení nabídek a montáží a reakce zákazníků na jednom místě (jen administrátor).
// 10. 11. 2026: ⚙️ Správa → „✍️ Můj podpis“ (podpis-muj.php): nakreslení podpisu pro akceptaci objednávek.
// 10. 11. 2026: ⚙️ Správa → „✉️ E-maily a příjemci“ (nastaveni-email.php): komu ve firmě chodí které e-maily, účetní, odesílatel a číslo účtu pro zálohy (jen administrátor).
// 9. 11. 2026: vlevo nahoře v liště přepínač 🚪 Dveře | 🪟 Okna (jen administrátor, jako v liště modulu Servis oken); původní přepínač pod položkou Servis zrušen.
// 9. 11. 2026: administrátor už nemá zelené tlačítko „🏠 Úvod“ (jeho úlohu plní „Dveře“ v přepínači); ostatní zaměstnanci ho mají dál.
function bd_bar(string $akt = ''): void {
  global $cfg;
  // stránka otevřená uvnitř plovoucího okna pomocníka: bez lišty a bez další plovoucí ikony
  if (isset($_GET['okno'])) { echo '<style>body{margin-top:0;padding-top:0}</style>'; return; }
  $skut = $akt; if ($akt === 'montaze') $akt = 'terminy';   // obě stránky svítí pod jednou položkou menu
  $web = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  $domov = in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), ['kartoteka.php', 'servis.php'], true);   // úvodní stránka aplikace
  $jeAdmin = function_exists('uziv_admin') && uziv_admin();
  $nPozM = 0; try { $nPozM = (int)db()->query("SELECT COUNT(*) FROM servis_pozadavky WHERE stav='nove'")->fetchColumn(); } catch (Throwable $x) {}
  $nKonc = 0; if (is_file(__DIR__ . '/koncepty.php')) { require_once __DIR__ . '/koncepty.php'; try { $nKonc = konc_pocet(db()); } catch (Throwable $x) {} }   // rozpracované nabídky
  $pol = [];
  if (!$jeAdmin) $pol[] = ['index.php', 'Úvod', 'uvod', '🏠'];   // administrátor má místo zeleného Úvodu přepínač 🚪 Dveře (vede na index.php)
  $pol[] = ['kartoteka.php', 'Kartotéka', 'kartoteka', '📋'];
  $pol[] = ['zamereni-nove.php', 'Zaměření', 'zamereni', '📐'];
  $pol[] = ['koncepty.php', 'Rozpracované', 'koncepty', '📝'];
  if (!$jeAdmin) $pol[] = ['nastroje.php', 'Nástroje', 'nastroje', '🧰'];   // administrátor má Nástroje ve Správě
  $pol[] = ['zakaznici.php', 'Zákazníci', 'zakaznici', '👥'];
  $pol[] = ['montaze.php', 'Termíny a montáže', 'terminy', '📅'];
  $pol[] = ['servis.php', 'Servis', 'servis', '🔧'];
  // rozbalovací skupiny vpravo od položek: [název, [položky], odznak]
  $menus = [
    ['📦 Sklad', [
      ['sklad.php', 'Sklad', 'sklad', '📦'],
      ['dodavatel.php', 'Dodavatel', 'dodavatel', '🚚'],
    ], ''],
    ['🛠 Požadavky', [
      ['servis-pozadavky.php', 'Požadavky', 'pozadavky', '🛠'],
      ['poukazy.php', 'Poukazy', 'poukazy', '🎁'],
    ], $nPozM > 0 ? ' <b class="bdn">' . $nPozM . '</b>' : ''],
  ];
  if ($jeAdmin) {
    $menus[] = ['⚙️ Správa', [
      ['nastroje.php', 'Nástroje', 'nastroje', '🧰'],
      ['uzivatele.php', 'Zaměstnanci (uživatelé)', 'uzivatele', '👤'],
      ['cisla.php', 'Číselná řada', 'cisla', '🔢'],
      ['reklama.php', 'Reklamní materiály', 'reklama', '📢'],
      ['statistika-aplikace.php', 'Využití aplikace', 'statistika', '📊'],
      ['zpetna-vazba.php', 'Zpětná vazba od zákazníků', 'zpetnavazba', '⭐'],
      ['podpis-muj.php', 'Můj podpis (akceptace objednávek)', 'podpis', '✍️'],
      ['nastaveni-email.php', 'E-maily a příjemci', 'nastaveni', '✉️'],
      ['web-pomocnik.php', 'Pomocník AI na webu', 'webpom', '🤖'],
      ['https://obchod.bezpecne-dvere.cz/admin', 'E-shop (administrace)', 'eshop', '🛒'],   // externí odkaz, otevře se v novém okně
      ['#instalovat', 'Instalovat aplikaci', 'instalace', '📲'],   // vyvolá instalaci systému jako aplikace (skript v bd_instalace_tlacitko)
    ], ''];
  }
  $sprava = []; foreach ($menus as [, $pp]) foreach ($pp as $p0) $sprava[] = $p0;   // všechny položky skupin (pro název aktuální stránky a zvýraznění)
  echo '<style>.bdbar{position:sticky;top:0;z-index:60;display:flex;gap:4px;align-items:center;overflow-x:auto;white-space:nowrap;background:#0f2747;padding:8px max(12px,env(safe-area-inset-left,0px));-webkit-overflow-scrolling:touch}'
     . '.bdbar a{color:#dbe6f7;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}'
     . '.bdbar a:hover{background:#1d3f6e}.bdbar a.on{background:#2563eb;color:#fff}.bdbar a.uvod{background:#16a34a;color:#fff;margin-right:6px}.bdbar b.bdn{background:#e11d48;color:#fff;border-radius:999px;padding:1px 7px;margin-left:5px;font-size:12px}.bd-app .bdbar.dom{display:none}.bd-app .bdsub.dom{display:none}'
     // přepínač Dveře | Okna vlevo nahoře v liště
     . '.bdsw{display:flex;gap:2px;flex:none;background:#0a1d38;border-radius:999px;padding:3px;margin-right:8px}.bdbar .bdsw a{padding:7px 14px;border-radius:999px}.bdbar .bdsw a.on,.bdbar .bdsw a.on:hover{background:#fff;color:#0f2747}'
     // přepínač pod lištou: Montáže | Termíny
     . '.bdsub{display:flex;gap:6px;flex-wrap:wrap;background:#e8eef8;padding:8px max(12px,env(safe-area-inset-left,0px))}.bdsub a{color:#0f2747;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:8px 14px;border-radius:999px;background:#fff;border:1px solid #c5d3ea}.bdsub a.on{background:#2563eb;border-color:#2563eb;color:#fff}'
     // rozbalovací skupiny (na počítači plovoucí pod tlačítkem, na mobilu rozbalené uvnitř menu)
     . '.bdsp summary{list-style:none;cursor:pointer;color:#dbe6f7;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}.bdsp summary::-webkit-details-marker{display:none}.bdsp summary:after{content:" ▾";font-size:11px}'
     . '.bdsp summary:hover{background:#1d3f6e}.bdsp summary.on{background:#2563eb;color:#fff}'
     . '.bdsp-m{display:none;position:fixed;z-index:70;background:#0f2747;border-radius:10px;padding:6px;min-width:210px;box-shadow:0 8px 24px rgba(0,0,0,.35);flex-direction:column;gap:2px}.bdsp[open] .bdsp-m{display:flex}'
     // mobil: rozbalovací menu přes celou šířku (tlačítko ☰ s názvem aktuální stránky)
     . '.bdbar-t{display:none;width:100%;align-items:center;justify-content:space-between;gap:10px;background:transparent;border:0;color:#fff;font:600 16px/1.2 system-ui,Arial,sans-serif;padding:10px 4px;cursor:pointer;text-align:left}.bdbar-s{transition:transform .15s}.bdbar.otevreno .bdbar-s{transform:rotate(180deg)}'
     . '@media (max-width:1100px){.bdbar{flex-wrap:wrap;overflow:visible;white-space:normal;padding:4px 12px;gap:2px}.bdbar-t{display:flex}.bdbar a{display:none;width:100%;box-sizing:border-box;padding:13px 12px;font-size:16px}.bdbar a.uvod{margin-right:0}.bdbar.otevreno a{display:block}.bdbar.otevreno{padding-bottom:10px}'
     . '.bdbar .bdsw{display:none;width:100%;box-sizing:border-box;margin:4px 0}.bdbar.otevreno .bdsw{display:flex}.bdbar .bdsw a{display:block;flex:1;width:auto;text-align:center;padding:10px 0;font-size:15px}'
     . '.bdsp{display:none;width:100%}.bdbar.otevreno .bdsp{display:block}.bdsp summary{padding:13px 12px;font-size:16px}.bdsp-m{position:static;box-shadow:none;background:transparent;padding:0 0 0 14px;min-width:0}}'
     // střední šířky (1101–1680 px): přepínač jen s ikonami a užší odsazení položek, ať se lišta s přepínačem vejde
     . '@media (min-width:1101px) and (max-width:1680px){.bdbar .bdsw .bdswt{display:none}.bdbar a{padding-left:8px;padding-right:8px}.bdbar .bdsw a{padding:7px 11px}.bdsp summary{padding-left:8px;padding-right:8px}.bdsw{margin-right:4px}}</style>'
     // nainstalovaná aplikace Servis (start_url servis.php?app=1) nebo Kartotéka (kartoteka.php?app=kartoteka), režim standalone: bez horní lišty jen na úvodní stránce aplikace (třída dom)
     . '<script>(function(){try{var s=sessionStorage;if(/[?&]app=(1|kartoteka)(&|$)/.test(location.search))s.setItem("bd-servis-app","1");if(s.getItem("bd-servis-app")==="1"&&(matchMedia("(display-mode: standalone)").matches||navigator.standalone===true))document.documentElement.classList.add("bd-app")}catch(e){}})();</script>'
     . '<nav class="bdbar' . ($domov ? ' dom' : '') . '">';
  // název aktuální stránky do tlačítka rozbalovacího menu (na mobilu)
  $akL = 'Menu'; foreach (array_merge($pol, $sprava) as [, $t0, $k0, $ik0]) if ($k0 === $akt) $akL = $ik0 . ' ' . $t0;
  if ($jeAdmin && $akt === 'uvod') $akL = '🚪 Dveře';
  echo '<button type="button" class="bdbar-t" aria-expanded="false" onclick="var n=this.parentNode;n.classList.toggle(\'otevreno\');this.setAttribute(\'aria-expanded\',n.classList.contains(\'otevreno\'))"><span>☰ ' . htmlspecialchars($akL) . ($nPozM > 0 ? ' <b class="bdn">' . $nPozM . '</b>' : '') . '</span><span class="bdbar-s">▾</span></button>';
  if ($jeAdmin) echo '<div class="bdsw" role="group" aria-label="Dveře / Okna"><a href="index.php" class="on" title="Dveře" aria-label="Dveře">🚪<span class="bdswt"> Dveře</span></a><a href="okna/index.php" title="Okna" aria-label="Okna">🪟<span class="bdswt"> Okna</span></a></div>';   // přepínač Dveře | Okna (modul Servis oken je zatím jen pro administrátora)
  foreach ($pol as [$u, $t, $k, $ik]) echo '<a href="' . htmlspecialchars($u) . '" class="' . ($k === 'uvod' ? 'uvod ' : ''). ($k === $akt ? 'on' : '') . '">' . $ik . ' ' . htmlspecialchars($t) . ($k === 'koncepty' && $nKonc > 0 ? ' <b class="bdn" style="background:#d97706">' . $nKonc . '</b>' : '') . '</a>';
  foreach ($menus as [$nazev, $polozky, $odznak]) {
    $spOn = false; foreach ($polozky as [, , $k3]) if ($k3 === $akt) $spOn = true;
    echo '<details class="bdsp"><summary class="' . ($spOn ? 'on' : '') . '">' . htmlspecialchars($nazev) . $odznak . '</summary><div class="bdsp-m">';
    foreach ($polozky as [$u, $t, $k, $ik]) echo '<a href="' . htmlspecialchars($u) . '" class="' . ($k === $akt ? 'on' : '') . '"' . (strpos($u, 'http') === 0 ? ' target="_blank" rel="noopener"' : '') . '>' . $ik . ' ' . htmlspecialchars($t) . ($k === 'pozadavky' && $nPozM > 0 ? ' <b class="bdn">' . $nPozM . '</b>' : '') . '</a>';
    echo '</div></details>';
  }
  // jméno přihlášeného vpravo; u něj odkaz „🔑 Moje heslo“ (uzivatele.php: administrátor spravuje zaměstnance, ostatní mění jen vlastní heslo)
  if (function_exists('uziv_jmeno') && uziv_jmeno() !== '') {
    echo '<span style="margin-left:auto;display:flex;align-items:center;gap:2px;color:#9fb6d8;font:13px system-ui,Arial,sans-serif;white-space:nowrap">'
       . '<span style="padding:0 6px">👤 ' . htmlspecialchars(uziv_jmeno()) . '</span>'
       . '<a href="uzivatele.php" class="' . ($akt === 'uzivatele' && !$jeAdmin ? 'on' : '') . '" style="font-size:13px;padding:7px 10px" title="Změnit si heslo">🔑 Moje heslo</a></span>';
  }
  echo '</nav>';
  // přepínač mezi Montážemi a Termíny (obě stránky jsou v menu pod jednou položkou)
  if ($skut === 'montaze' || $skut === 'terminy') echo '<div class="bdsub"><a href="montaze.php" class="' . ($skut === 'montaze' ? 'on' : '') . '">🔨 Montáže</a><a href="terminy.php" class="' . ($skut === 'terminy' ? 'on' : '') . '">⏰ Termíny (poptávky, nabídky, kalendář)</a></div>';
  if ($menus) echo '<script>(function(){var ds=document.querySelectorAll(".bdsp");if(!ds.length)return;[].forEach.call(ds,function(d){var m=d.querySelector(".bdsp-m"),s=d.querySelector("summary");d.addEventListener("toggle",function(){if(d.open){[].forEach.call(ds,function(o){if(o!==d)o.open=false});if(matchMedia("(min-width:1101px)").matches){var r=s.getBoundingClientRect();m.style.top=(r.bottom+4)+"px";m.style.left=Math.max(4,Math.min(r.left,innerWidth-230))+"px"}}})});document.addEventListener("click",function(e){[].forEach.call(ds,function(d){if(d.open&&!d.contains(e.target))d.open=false})})})();</script>';
  if ($jeAdmin) bd_instalace_tlacitko();   // položka „Instalovat aplikaci“ ve Správě (administrátor)
  if ($akt !== 'pomocnik') bd_pomocnik_okno();
  if ($akt === 'kartoteka' && isset($_GET['id'])) bd_navrh_tlacitko((int)$_GET['id']);
  if ($akt === 'kartoteka' && !isset($_GET['id']) && in_array($_GET['typ'] ?? 'objednavka_interni', ['nabidka', ''], true) && function_exists('konc_kartoteka_box')) konc_kartoteka_box();   // rozpracované nabídky nad seznamem
}

// Položka „📲 Instalovat aplikaci“ v menu Správa (4. 11. 2026; dřív tlačítko v zeleném pruhu úvodní stránky): instalace celého systému (kartotéka, zákazníci, sklad…) jako aplikace.
// Používá manifest kartoteka-manifest.php (start_url index.php, scope celý systém). V Chrome/Edge (počítač, Android) vyvolá instalační okno, jinde ukáže postup.
// Skript se připojí ke všem odkazům a[href="#instalovat"]; v už nainstalované aplikaci se položka schová.
function bd_instalace_tlacitko(): void {
  echo <<<'BDINST'
<script>
(function () {
  var ev = null;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; });
  document.addEventListener('DOMContentLoaded', function () {
    var st = false; try { st = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true; } catch (e) {}
    var as = document.querySelectorAll('a[href="#instalovat"]');
    window.addEventListener('appinstalled', function () { [].forEach.call(as, function (a) { a.style.display = 'none'; }); });
    [].forEach.call(as, function (a) {
      if (st) { a.style.display = 'none'; return; }
      a.addEventListener('click', function (x) {
        x.preventDefault();
        var d = a.closest('details'); if (d) d.open = false;
        if (ev) { ev.prompt(); ev.userChoice.then(function () { ev = null; }, function () {}); return; }
        var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        alert(ios ? 'iPhone / iPad: v Safari klepněte na Sdílet → Přidat na plochu.'
          : 'Prohlížeč teď okno s instalací nenabídl. V Edge: nabídka ⋯ → Aplikace → Nainstalovat tento web jako aplikaci. V Chrome: ikona instalace v adresním řádku, nebo nabídka ⋮ → Instalovat.');
      });
    });
  });
})();
</script>
BDINST;
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
    'model', 'tl_zarubne', 'cena_celkem', 'polozky', 'typ_dveri', 'provedeni', 'pocet_ks', 'zdroj', 'kategorie', 'bourani',
    'konfigurace', 'cena_bez_dph', 'cena_s_dph', 'dph_sazba', 'montaz', 'kf_stav'];   // poptávka z konfigurátoru ERKADO: celá konfigurace a cena → nabídka ji převezme (nabidka.js)
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

// Malá ikona mapy 🗺️ u adresy (30. 10. 2026): otevře Google Mapy (v telefonu aplikaci Mapy) s vyhledanou adresou –
// odtud se dá podívat na dům (satelit, Street View) a jedním klepnutím spustit Navigovat. Bez adresy nevrací nic.
function bd_mapa(string $adresa, string $souradnice = ''): string {
  $q = trim(preg_replace('/\s+/u', ' ', $adresa), " ,");
  if ($q === '' && preg_match('/^\s*(-?\d{1,2}\.\d+)\s*[,; ]\s*(-?\d{1,3}\.\d+)\s*$/', $souradnice, $m)) $q = $m[1] . ',' . $m[2];
  if ($q === '') return '';
  $u = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
  return ' <a class="bdmapa" href="' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" title="Ukázat na mapě: ' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '" aria-label="Ukázat na mapě" style="text-decoration:none;white-space:nowrap">🗺️</a>';
}
// totéž z údajů záznamu / zákazníka (klíče ulice, psc, mesto, případně souradnice)
function bd_mapa_d(array $d): string {
  $s = fn($k) => is_scalar($d[$k] ?? null) ? trim((string)$d[$k]) : '';
  return bd_mapa(implode(', ', array_filter([$s('ulice'), trim($s('psc') . ' ' . $s('mesto'))])), $s('souradnice'));
}
