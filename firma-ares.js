/* Ověření firmy podle IČ v ARES (přes server formulare.dvere.app/ares.php) a volba sazby DPH pro firmy.
   Použití: BDAres.attach(root, {onChange}) – root obsahuje input[name=ic], [name=dic], [name=fak_jmeno], [name=fak_adresa],
   checkbox [name=platce_dph], tlačítko [data-ares] a prvek [data-ares-out]. */
(function (w) {
  'use strict';
  var URL = 'https://formulare.dvere.app/ares.php';
  function icoOk(i) {
    i = String(i || '').replace(/\D/g, ''); if (i.length < 8) i = ('00000000' + i).slice(-8);
    if (!/^\d{8}$/.test(i)) return false;
    var s = 0; for (var k = 0; k < 7; k++) s += +i[k] * (8 - k);
    return +i[7] === (11 - (s % 11)) % 10;
  }
  function lookup(ico) {
    var ctl = w.AbortController ? new AbortController() : null; if (ctl) setTimeout(function () { ctl.abort(); }, 15000);
    return fetch(URL + '?ico=' + encodeURIComponent(String(ico).replace(/\D/g, '')), { headers: { Accept: 'application/json' }, signal: ctl ? ctl.signal : undefined })
      .then(function (r) { return r.json().catch(function () { return { ok: false, chyba: 'odpoved' }; }); });
  }
  var CHYBY = { ico: 'IČ nemá platný tvar (kontrolní číslice nesedí).', nenalezeno: 'Firma s tímto IČ v ARES není.', limit: 'Příliš mnoho dotazů, zkuste to za chvíli.',
    ares_nedostupny: 'ARES teď neodpovídá – vyplňte údaje ručně.', ares_chyba: 'ARES vrátil chybu – vyplňte údaje ručně.', origin: 'Ověření z této stránky není povolené.' };
  function attach(root, opt) {
    opt = opt || {};
    var q = function (s) { return root.querySelector(s); };
    var btn = q('[data-ares]'), out = q('[data-ares-out]'); if (!btn) return;
    function msg(t, cls) { if (out) { out.textContent = t; out.className = 'pq-help ares-out ' + (cls || ''); } }
    btn.addEventListener('click', function () {
      var ico = (q('[name=ic]').value || '').replace(/\D/g, '');
      if (!icoOk(ico)) { msg(CHYBY.ico, 'ares-bad'); return; }
      btn.disabled = true; msg('Ověřuji v ARES…');
      lookup(ico).then(function (r) {
        btn.disabled = false;
        if (!r || !r.ok) { msg(CHYBY[r && r.chyba] || 'Ověření se nepodařilo – vyplňte údaje ručně.', 'ares-bad'); return; }
        var set = function (n, v) { var el = q('[name=' + n + ']'); if (el && v) { el.value = v; el.classList.remove('bad'); } };
        set('ic', r.ico); set('dic', r.dic); set('fak_jmeno', r.nazev); set('fak_adresa', r.adresa);
        var cb = q('[name=platce_dph]'); if (cb && r.platce !== null) cb.checked = !!r.platce;
        var st = r.platce === true ? 'plátce DPH (registrace aktivní)' : r.platce === false ? 'NENÍ plátce DPH' : 'stav DPH ARES neuvádí – ověřte ručně';
        msg('ARES ' + r.overeno + ': ' + r.nazev + (r.dic ? ', DIČ ' + r.dic : ', bez DIČ') + ' – ' + st + '.', r.platce === null ? 'ares-warn' : 'ares-ok');
        root.setAttribute('data-ares-overeno', r.overeno + (r.platce === true ? ' plátce' : r.platce === false ? ' neplátce' : ' neuvedeno'));
        if (opt.onChange) opt.onChange(r);
      }).catch(function () { btn.disabled = false; msg(CHYBY.ares_nedostupny, 'ares-bad'); });
    });
  }
  /* sazby DPH pro firmy; ceník je s 12 % DPH (bydlení) */
  try {
    var css = document.createElement('style');
    css.textContent = '.ares-row{display:flex;gap:.5rem;align-items:stretch}.ares-row input{flex:1;min-width:0}.ares-btn{white-space:nowrap;padding:.45rem .8rem;font-size:.9rem}' +
      '.ares-out{grid-column:1/-1;margin:.2rem 0 0}.ares-ok{color:#14653a}.ares-warn{color:#92400e}.ares-bad{color:#a11}';
    document.head.appendChild(css);
  } catch (e) {}
  var REZIMY = { '12': '12 % – bydlení (byt, rodinný dům)', '21': '21 % – nebytové prostory (kancelář, provozovna…)', 'pdp': 'Přenesená daňová povinnost – bez DPH, daň přiznává odběratel' };
  w.BDAres = { attach: attach, lookup: lookup, icoOk: icoOk, REZIMY: REZIMY };
})(window);
