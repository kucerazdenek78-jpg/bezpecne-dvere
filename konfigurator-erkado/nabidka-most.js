/* Konfigurátor ERKADO jako výpočet ceny pro nabídku / objednávku prodejce (6. 11. 2026).
   Otevírá se v okně nad nabidka-nova.html / objednavka-vice.html (nabidka.js) jako
   konfigurator-erkado.html?nabidka=1&m=<řada>&d=<dekor>[&s=<uložený stav>].
   Cena se počítá stejnou funkcí jako konfigurátor pro zákazníky (KF.calc, ověřeno proti nabídkám ERKADO).
   V tomto režimu: bez okna „Uložíme vám návrh“, bez kontaktu, odeslání a dopravy (doprava je v nabídce),
   dole lišta s cenou a tlačítkem „Převzít do nabídky“ → postMessage do nadřazené stránky (stejný web). */
(function () {
  var q; try { q = new URLSearchParams(location.search); } catch (e) { return; }
  if (q.get('nabidka') !== '1') return;
  window.KF_NABIDKA = true;
  try { sessionStorage.setItem('bd-kf-gate', '1'); } catch (e) {}   // lead-gate.js se pak neukáže
  var css = document.createElement('style');
  css.textContent = '.kf-top,.kf-hero,#pomWebBtn,#pomWeb,.kf-vizcta,.kf-vizres,.kf-out,#kfMsg,#kfDone,#kfOdber,#kfAddrWrap,.kfg-bg,.cookie-bar,#cookieBar{display:none!important}'
    + '.kf-nb-skryt{display:none!important}body{padding-bottom:90px}'
    + '.kf-nb{position:fixed;left:0;right:0;bottom:0;z-index:9000;background:#123d27;color:#fff;padding:10px 16px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;box-shadow:0 -4px 16px rgba(0,0,0,.25);font:15px/1.35 system-ui,Arial,sans-serif}'
    + '.kf-nb b{font-size:18px}.kf-nb small{display:block;opacity:.85;font-size:12.5px}.kf-nb .kf-nb-a{display:flex;gap:8px;flex-wrap:wrap}'
    + '.kf-nb button{font:700 15px system-ui,Arial,sans-serif;border:0;border-radius:10px;padding:10px 16px;cursor:pointer;background:#fff;color:#123d27}'
    + '.kf-nb button.hl{background:#f59e0b;color:#1b1b1b}.kf-nb-err{color:#fde68a;font-size:13px}';
  document.head.appendChild(css);

  function kc(n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; }
  function b64dec(s) { try { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; return JSON.parse(decodeURIComponent(escape(atob(s)))); } catch (e) { return null; } }

  function start() {
    var KF = window.KF, E = window.ERKADO;
    if (!KF || !E) return;
    var st = KF.state;
    /* předvolba z nabídky: uložený stav (úprava výpočtu), jinak řada + dekor */
    var ulozeny = q.get('s') ? b64dec(q.get('s')) : null;
    if (ulozeny && typeof ulozeny === 'object') {
      Object.keys(ulozeny).forEach(function (k) { if (k !== 'step' && k in st) st[k] = ulozeny[k]; });
    } else {
      var m = String(q.get('m') || '').toLowerCase(), mod = null;
      E.modely.forEach(function (x) { if (x.id === m || x.nazev.toLowerCase() === m) mod = x; });
      if (mod) { st.model = mod.id; st.typ = mod.typy[0]; st.prov = mod.provedeni[0]; }
      var d = String(q.get('d') || '');
      E.dekory.forEach(function (x) { if (x[0] === d || x[1].toLowerCase() === d.toLowerCase()) st.dekor = x[0]; });
    }
    st.step = 1;
    /* kontakt a souhlas v tomto režimu nejsou (zákazník je v nabídce) */
    var kon = document.getElementById('kfJmeno'); if (kon) kon.closest('fieldset').classList.add('kf-nb-skryt');
    var gd = document.getElementById('kfGdpr'); if (gd) gd.closest('.pq-block').classList.add('kf-nb-skryt');
    var mp = document.getElementById('kfMode'); if (mp) mp.hidden = true;

    var bar = document.createElement('div'); bar.className = 'kf-nb';
    bar.innerHTML = '<div><b id="kfNbCena"></b><small id="kfNbSub"></small><span class="kf-nb-err" id="kfNbErr"></span></div>'
      + '<div class="kf-nb-a"><button type="button" id="kfNbMont"></button><button type="button" class="hl" id="kfNbOk">↩ Převzít výpočet</button></div>';
    document.body.appendChild(bar);

    function vypocet() {
      try { var r = KF.calc(); return r && isFinite(r.sDph) ? r : null; } catch (e) { return null; }
    }
    function obnov() {
      var r = vypocet();
      document.getElementById('kfNbMont').textContent = st.montaz ? '✓ S montáží na klíč' : 'Bez montáže';
      document.getElementById('kfNbMont').title = 'Přepnout montáž na klíč (+' + kc(E.montaz.cena) + ' bez DPH, DPH 12 %)';
      if (!r) { document.getElementById('kfNbCena').textContent = '–'; document.getElementById('kfNbSub').textContent = ''; document.getElementById('kfNbErr').textContent = 'Konfigurace není úplná.'; return; }
      document.getElementById('kfNbErr').textContent = '';
      document.getElementById('kfNbCena').textContent = kc(r.sDph) + ' vč. ' + r.rate + ' % DPH';
      document.getElementById('kfNbSub').textContent = 'bez DPH ' + kc(r.bez) + (st.montaz ? ' (vč. montáže ' + kc(E.montaz.cena) + ')' : ' · bez montáže') + ' · doprava se počítá v nabídce';
    }
    var t = null; function pozdeji() { clearTimeout(t); t = setTimeout(obnov, 30); }
    document.addEventListener('click', pozdeji, true); document.addEventListener('change', pozdeji, true); document.addEventListener('input', pozdeji, true);
    document.getElementById('kfNbMont').addEventListener('click', function () { st.montaz = !st.montaz; KF.render(); obnov(); });
    document.getElementById('kfNbOk').addEventListener('click', function () {
      var r = vypocet(); if (!r) { obnov(); return; }
      var d = KF.collect(), stav = {};
      Object.keys(st).forEach(function (k) { if (['step', 'tripMsg', 'trip', 'ref', 'km', 'odber'].indexOf(k) < 0) stav[k] = st[k]; });
      var msg = { typ: 'bd-erkado-nabidka', idx: q.get('i'), data: {
        model: d.model, model_kod: d.model_kod, odstin: d.odstin, provedeni: d.provedeni, konfigurace: d.konfigurace,
        polozky: r.lines.map(function (l) { return [l[0], l[1]]; }), cena_bez_dph: r.bez, dph_sazba: r.rate, cena_s_dph: r.sDph,
        montaz: !!st.montaz, info: r.info || [], obrazek: 'konfigurator-erkado/img/typy/' + String(d.model_kod || '').replace(/^erkado-/, '') + '.jpg', stav: stav, katalog: E.katalog
      } };
      if (window.parent && window.parent !== window) window.parent.postMessage(msg, location.origin);
      else if (window.opener) { window.opener.postMessage(msg, location.origin); window.close(); }
    });
    KF.render(); obnov();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
