/* Bezpečné dveře → TRAL (ERKADO): načte konfiguraci ocelových dveří z kartotéky do konfigurátoru TRALu.
   Spouští se záložkou „Načíst z kartotéky“ na stránce zakázky v TRALu (tral.cz/nabidky/create nebo /change/…).
   Konfigurace přijde v adrese za #bdtral=… (tlačítko v kartotéce) nebo se vloží ručně. Nic neukládá:
   jen naplní konfigurátor TRALu (jeho vlastní režim úprav) – zboží a zakázku potvrzuje uživatel v TRALu sám. 24. 11. 2026 */
(function () {
  if (window.__bdTralBezi) return; window.__bdTralBezi = true;
  var KONEC = function () { window.__bdTralBezi = false; };
  function panel(html, barva) {
    var p = document.getElementById('bdTralPanel');
    if (!p) { p = document.createElement('div'); p.id = 'bdTralPanel'; document.body.appendChild(p);
      p.style.cssText = 'position:fixed;left:14px;bottom:14px;z-index:2147483000;max-width:380px;background:#fff;border-radius:10px;box-shadow:0 6px 28px rgba(0,0,0,.35);padding:12px 14px;font:14px/1.45 system-ui,Arial,sans-serif;color:#16233a'; }
    p.style.borderLeft = '7px solid ' + (barva || '#1d5fb8');
    p.innerHTML = '<div style="font-weight:700;margin-bottom:4px">Bezpečné dveře → TRAL <span id="bdTralX" style="float:right;cursor:pointer;color:#64708a">✕</span></div>' + html;
    document.getElementById('bdTralX').onclick = function () { p.remove(); };
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (m) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]; }); }
  function dek(s) { s = String(s).trim().replace(/^BDTRAL:/, '').replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '=';
    var b = atob(s), u = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) u[i] = b.charCodeAt(i); return JSON.parse(new TextDecoder().decode(u)); }
  function cekej(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  async function az(fn, pokusu, ms) { for (var i = 0; i < pokusu; i++) { try { if (fn()) return true; } catch (e) {} await cekej(ms || 300); } return false; }
  function zaneprazdnen() { var sl = document.getElementById('sidebarLoader'); return !!autoCascadeInProgress || !!isEditMode || (sl && sl.style.display === 'flex'); }
  async function klid() { var n = 0; for (var i = 0; i < 300; i++) { await cekej(300); if (!zaneprazdnen()) { if (++n > 3) return true; } else n = 0; } return false; }

  (async function () {
    var D = null, m = /[#&]bdtral=([A-Za-z0-9_\-]+)/.exec(location.hash || '');
    try {
      if (m) { D = dek(m[1]); try { sessionStorage.setItem('bdtral', m[1]); } catch (e) {} }
      else { var ss = null; try { ss = sessionStorage.getItem('bdtral'); } catch (e) {} if (ss) D = dek(ss); }
    } catch (e) { D = null; }
    if (!D) { var t = prompt('Vložte kód konfigurace z kartotéky (tlačítko „Zkopírovat kód“ u objednávky):'); if (!t) return KONEC(); try { D = dek(t); } catch (e) { panel('Kód se nepodařilo přečíst.', '#c81e1e'); return KONEC(); } }
    if (!D || !Array.isArray(D.o) || !D.o.length) { panel('V kódu není žádná konfigurace.', '#c81e1e'); return KONEC(); }
    if (!document.querySelector('.nabidky-form')) { panel('Tohle není stránka zakázky. V TRALu otevřete <b>Zakázky → Přidat zakázku</b> a klikněte na záložku znovu.', '#e8650a'); return KONEC(); }
    var nazev = esc((D.c ? 'objednávka ' + D.c + ' · ' : '') + (D.m || ''));
    panel('Načítám konfiguraci (' + nazev + ')…');
    try {
      var otevreno = function () { var s = document.querySelector('#modalContentOceloveDvere #erkado-scope'); return !!(s && s.offsetParent !== null); };
      if (!otevreno()) {
        var b = document.getElementById('add_nabidka_polozka_konstrukter');
        if (!b) { panel('Nenašel jsem tlačítko „na objednání (F4)“. Otevřete konfigurátor ocelových dveří ručně a klikněte na záložku znovu.', '#e8650a'); return KONEC(); }
        b.click();
        /* dlaždice „ocelové dveře“ bývá ve skryté záložce výběru – stačí, že je výběr otevřený */
        if (!(await az(function () { return document.querySelector('#modal2.in, #modal2.show') && document.querySelector('.typ_zbozi_cube_ocelove_dvere'); }, 30))) { panel('Nepodařilo se otevřít výběr zboží. Otevřete konfigurátor ocelových dveří ručně a klikněte na záložku znovu.', '#e8650a'); return KONEC(); }
        document.querySelector('.typ_zbozi_cube_ocelove_dvere').click();
      }
      if (!(await az(function () { return otevreno() && typeof recalculate === 'function' && !!lastStructure; }, 80))) { panel('Konfigurátor TRALu se nenačetl.', '#c81e1e'); return KONEC(); }
      await klid();
      /* stejný postup, jakým TRAL sám načítá uloženou položku (režim úprav) */
      userSelection = {}; userSelectionNames = {}; supplementedGroups = {}; pendingEditOptions = {}; lastStructure = null; cachedImages = [];
      isEditMode = true; autoCascadeInProgress = true;
      D.o.forEach(function (x) { pendingEditOptions[x[0] + '_' + x[1]] = { groupId: x[0], attrId: x[1], extId: String(x[2]), name: x[3] || '' }; supplementedGroups[x[0]] = true; });
      recalculate(null);
      if (!(await klid())) { panel('TRAL neodpověděl včas. Zkuste to znovu.', '#c81e1e'); return KONEC(); }
      var mam = {}; (typeof erkadoCollectOptions === 'function' ? erkadoCollectOptions() : []).forEach(function (o) { mam[o.group_id + '_' + o.attr_id] = String(o.ext_id); });
      var chybi = [], navic = 0, chci = {};
      D.o.forEach(function (x) { var k = x[0] + '_' + x[1]; chci[k] = 1; if (mam[k] !== String(x[2])) chybi.push((x[4] || k) + ': ' + (x[3] || x[2])); });
      Object.keys(mam).forEach(function (k) { if (!chci[k]) navic++; });
      var cena = (document.getElementById('priceNoVat') || {}).innerText || '';
      if (!chybi.length) panel('<b style="color:#14653a">Načteno všech ' + D.o.length + ' voleb</b> (' + nazev + ').' + (navic ? '<br>TRAL si sám doplnil ' + navic + ' dalších výchozích voleb – projděte je.' : '') +
        '<br>Cena v TRALu: <b>' + esc(cena) + '</b> bez DPH.<br><br>Zkontrolujte náhled a rozměry, pak <b>VYTVOŘIT ZBOŽÍ</b> → <b>Přidat k zakázce</b>. Zákazníka a uložení zakázky doplníte jako obvykle.', '#14653a');
      else panel('<b style="color:#c81e1e">Pozor: ' + chybi.length + ' z ' + D.o.length + ' voleb TRAL nepřevzal</b> (nabídka voleb se nejspíš změnila). Nastavte je ručně:<ul style="margin:6px 0 6px 18px;padding:0">' +
        chybi.slice(0, 12).map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('') + '</ul>Cena v TRALu teď: <b>' + esc(cena) + '</b> bez DPH.', '#c81e1e');
      try { sessionStorage.removeItem('bdtral'); } catch (e) {}
    } catch (e) { panel('Chyba při načítání: ' + esc(e && e.message || e), '#c81e1e'); }
    KONEC();
  })();
})();
