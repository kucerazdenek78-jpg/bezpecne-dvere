/* Poptávka dveří do domu a interiérových dveří – jeden společný formulář (stejná pole a stejná data do kartotéky jako průvodce u dveří do bytu).
   Použití: <div data-poptavka-kat="dum|interier"></div> + formulare-klient.js; PoptavkaKat.mount(el, kat) a PoptavkaKat.pick(model) předvybere model. */
(function () {
  'use strict';
  var CC = 'chci@bezpecne-dvere.cz';
  var KAT = { dum: { nazev: 'Dveře do domu', h: 'Poptávka dveří do domu', sub: 'Vyberte model a pošlete poptávku. Ozveme se s nabídkou včetně montáže do 24 hodin.', adr: true, multi: false },
              interier: { nazev: 'Interiérové dveře', h: 'Poptávka interiérových dveří', sub: 'Vyberte modely a počty kusů. Ozveme se s nabídkou do 24 hodin.', adr: false, multi: true } };
  var MODELY = {"dum": [["Hliníkové dveře", ["OPTIMAL 60 – hliníkové (akce od 27 500 Kč)", "OPTIMAL 75 – hliníkové (akce od 34 500 Kč)"]], ["Ocelové dveře ERKADO — Doporučené", ["ERKADO ARENDAL (od 52 269 Kč)", "ERKADO LAHTI (od 44 821 Kč)", "ERKADO TREBUR (od 38 304 Kč)", "ERKADO LANGEN (od 37 041 Kč)", "ERKADO ALTA (od 34 846 Kč)", "ERKADO UTRECHT (od 41 629 Kč)", "ERKADO ROTTERDAM (od 45 819 Kč)", "ERKADO SOLID (od 23 940 Kč)"]], ["Ocelové dveře ERKADO — Ostatní modely", ["ERKADO HORN", "ERKADO WELS", "ERKADO BAZYLEA", "ERKADO HELMOND", "ERKADO FRYBURG", "ERKADO LEIMEN", "ERKADO AMBERG", "ERKADO LINDEN", "ERKADO MURTER", "ERKADO EBERN", "ERKADO WIENER", "ERKADO SION", "ERKADO SINGEN", "ERKADO SELTERS", "ERKADO SAMEDAN", "ERKADO RODING", "ERKADO MELK", "ERKADO LUCERNA", "ERKADO KREBEN", "ERKADO LEOBEN", "ERKADO LUTTER", "ERKADO BIENE", "ERKADO KELLA"]], ["", ["Nevím — chci poradit"]]], "interier": [["Skladem", ["PATRAS", "KAVALA", "LARISA", "FRÉZIA 1", "FRÉZIA 9", "NEMÉZIA 14", "LAURENCIA 1", "ALTAMURA 1", "ALTAMURA 1 – falcové", "LINEA FORTE 5", "NORMA DECOR 1", "AVERSA 1", "VERTE HOME G.4", "CPL 1.1", "DECOR P"]], ["Na objednávku", ["ALTAMURA", "NEMÉZIA", "MISKANT", "LAURENCIA", "MAGNÓLIA", "FRÉZIA", "DAGLÉZIA", "ALTAMURA LUX", "LIBRA · NORMA DECOR", "DECORATO · BELGATTO", "LINEA FORTE", "NOGARO · TAMPARO", "CAPRIO · NIDA · ROMA", "VECTOR", "VERTE HOME G / H", "VERTE PREMIUM A", "VERTE PREMIUM E", "HARMONY", "CPL"]], ["", ["Nevím — chci poradit"]]]};
  var CSS = '.pqk{max-width:760px;margin:0 auto;font-family:inherit}.pqk h2{margin:0 0 .3rem}.pqk .pqk-sub{color:var(--gray-600,#555);margin:0 0 1.2rem}' +
    '.pqk fieldset{border:1px solid var(--gray-200,#dde3df);border-radius:var(--radius-lg,12px);padding:1rem 1.2rem 1.2rem;margin:0 0 1rem;background:#fff;min-width:0}' +
    '.pqk legend{font-weight:700;padding:0 .4rem;color:var(--navy,#123d27)}.pqk .pqk-g2{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}' +
    '.pqk label.pqk-f{display:block;font-size:.88rem;color:var(--gray-600,#555)}.pqk .pqk-f input,.pqk .pqk-f select,.pqk .pqk-f textarea{display:block;width:100%;box-sizing:border-box;margin-top:.25rem;font:inherit;font-size:1rem;padding:.65rem .75rem;border:1px solid var(--gray-300,#c5cdc8);border-radius:8px;background:#fff;color:#111}' +
    '.pqk textarea{min-height:96px}.pqk .bad{border-color:#c0392b!important;background:#fdf1ef}.pqk .pqk-row{display:grid;grid-template-columns:1fr 90px 36px;gap:.6rem;align-items:end;margin-bottom:.6rem}' +
    '.pqk .pqk-x{height:42px;border:1px solid var(--gray-300,#c5cdc8);background:#fff;border-radius:8px;cursor:pointer;font-size:1.1rem}.pqk .pqk-add{margin-top:.2rem}' +
    '.pqk .pqk-gdpr{font-size:.82rem;margin:0 0 .6rem}.pqk .pqk-chk{display:flex;gap:.5rem;align-items:flex-start;font-size:.92rem;margin-bottom:1rem}.pqk .pqk-msg{margin:0 0 1rem;padding:.8rem 1rem;border-radius:10px;background:#fdecea;color:#7a1c12}' +
    '.pqk .pqk-done{padding:1.4rem;border-radius:14px;background:#e8f5ee;color:#123d27}.pqk .pqk-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}.pqk .pqk-help{font-size:.82rem;color:var(--gray-600,#555);margin:.4rem 0 0}' +
    '.pqk .pqk-ph{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}.pqk .pqk-ph img{height:64px;border-radius:6px}.pqk .pqk-btn{display:inline-block;border:0;border-radius:10px;padding:.9rem 1.4rem;font:inherit;font-weight:700;cursor:pointer}' +
    '.pqk .pqk-send{background:var(--orange,#e8761a);color:#fff;width:100%;font-size:1.05rem}.pqk .pqk-send[disabled]{opacity:.6}.pqk .pqk-ghost{background:#fff;border:1px solid var(--gray-300,#c5cdc8);padding:.55rem 1rem}' +
    '@media(max-width:560px){.pqk .pqk-g2{grid-template-columns:1fr}.pqk .pqk-row{grid-template-columns:1fr 76px 36px}}';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  function makeRef() { var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'W' + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase(); }
  function options(kat) {
    return '<option value="">– vyberte model –</option>' + MODELY[kat].map(function (g) {
      var o = g[1].map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('');
      return g[0] ? '<optgroup label="' + esc(g[0]) + '">' + o + '</optgroup>' : o; }).join('');
  }
  function rowHtml(kat, withKs) {
    return '<div class="pqk-row"><label class="pqk-f">Model<select data-m>' + options(kat) + '</select></label>' +
      (withKs ? '<label class="pqk-f">Kusů<input data-ks type="number" min="1" max="99" value="1" inputmode="numeric"></label><button type="button" class="pqk-x" data-rm title="Odebrat" aria-label="Odebrat">×</button>' : '') + '</div>';
  }
  function shrink(file) {
    return new Promise(function (res) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () { var k = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); c.toBlob(function (b) { res(b || file); }, 'image/jpeg', 0.82); };
      img.onerror = function () { URL.revokeObjectURL(url); res(null); }; img.src = url;
    });
  }
  function mount(el, kat) {
    if (!KAT[kat]) return; var K = KAT[kat];
    if (!document.getElementById('pqk-css')) { var st = document.createElement('style'); st.id = 'pqk-css'; st.textContent = CSS; document.head.appendChild(st); }
    el.setAttribute('data-poptavka-kat', kat);
    el.innerHTML = '<div class="pqk"><form novalidate><h2>' + K.h + '</h2><p class="pqk-sub">' + K.sub + '</p>' +
      '<fieldset><legend>Které dveře vás zajímají?</legend><div data-rows>' + rowHtml(kat, K.multi) + '</div>' + (K.multi ? '<button type="button" class="pqk-btn pqk-ghost pqk-add" data-add>+ Přidat další model</button>' : '') + '</fieldset>' +
      '<fieldset><legend>Na koho se máme obrátit?</legend><div class="pqk-g2">' +
      '<label class="pqk-f">Jméno *<input name="jmeno" autocomplete="given-name" required></label><label class="pqk-f">Příjmení *<input name="prijmeni" autocomplete="family-name" required></label>' +
      '<label class="pqk-f">Telefon *<input name="telefon" type="tel" inputmode="tel" autocomplete="tel" placeholder="777 123 456" required></label><label class="pqk-f">E-mail' + (K.adr ? '' : ' *') + '<input name="email" type="email" autocomplete="email" placeholder="vas@email.cz"' + (K.adr ? '' : ' required') + '></label></div></fieldset>' +
      '<fieldset><legend>' + (K.adr ? 'Kde budeme dveře montovat? *' : 'Kde budeme dveře montovat? (nepovinné)') + '</legend><div class="pqk-g2">' +
      '<label class="pqk-f">Ulice a číslo popisné' + (K.adr ? ' *' : '') + '<input name="ulice" autocomplete="street-address"' + (K.adr ? ' required' : '') + '></label><label class="pqk-f">Město' + (K.adr ? ' *' : '') + '<input name="mesto" autocomplete="address-level2"' + (K.adr ? ' required' : '') + '></label>' +
      '<label class="pqk-f">PSČ<input name="psc" autocomplete="postal-code" inputmode="numeric"></label></div></fieldset>' +
      '<fieldset><legend>Doplnit</legend><label class="pqk-f">Poznámka<textarea name="poznamka" placeholder="' + (K.adr ? 'Např. rozměr 90 × 210 cm, stávající zárubeň, otevírání dovnitř / ven, barva…' : 'Např. bílá, šířka 70/80/90, zárubeň 110–135 mm, s montáží…') + '"></textarea></label>' +
      '<label class="pqk-f" style="margin-top:.8rem">Fotky (až 3, nepovinné)<input type="file" name="fotky" accept="image/*" multiple></label><p class="pqk-help">Fotka otvoru nebo stávajících dveří nám pomůže připravit přesnější nabídku.</p></fieldset>' +
      '<div class="pqk-hp" aria-hidden="true"><input name="_honey" tabindex="-1" autocomplete="off"></div>' +
      '<p class="pqk-gdpr">Osobní údaje zpracováváme podle GDPR jen k vyřízení vaší poptávky, neprodáváme je. Podrobnosti v <a href="zasady-osobnich-udaju.html" target="_blank" rel="noopener">Zásadách zpracování osobních údajů</a>.</p>' +
      '<label class="pqk-chk"><input type="checkbox" data-consent> Souhlasím se zpracováním osobních údajů dle uvedených podmínek. *</label>' +
      '<div class="pqk-msg" data-msg hidden></div><button type="submit" class="pqk-btn pqk-send">Odeslat poptávku</button></form></div>';
    var form = $('form', el), rows = $('[data-rows]', el), msg = $('[data-msg]', el);
    function err(t) { msg.textContent = t; msg.hidden = false; msg.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-add]')) { rows.insertAdjacentHTML('beforeend', rowHtml(kat, true)); }
      var rm = e.target.closest('[data-rm]'); if (rm && rows.children.length > 1) rm.closest('.pqk-row').remove();
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); msg.hidden = true;
      var bad = null, items = [];
      [].forEach.call(rows.querySelectorAll('.pqk-row'), function (r) { var s = $('[data-m]', r), k = $('[data-ks]', r); s.classList.toggle('bad', !s.value); if (!s.value) { bad = bad || s; return; }
        items.push({ model: s.value, ks: k ? Math.max(1, parseInt(k.value, 10) || 1) : 1 }); });
      ['jmeno', 'prijmeni', 'telefon'].concat(K.adr ? ['ulice', 'mesto'] : [], K.adr ? [] : ['email']).forEach(function (n) { var i = form.elements[n]; var b = !i.value.trim(); i.classList.toggle('bad', b); if (b) bad = bad || i; });
      var tel = form.elements.telefon.value.replace(/\D/g, ''); if (tel.length < 9) { form.elements.telefon.classList.add('bad'); bad = bad || form.elements.telefon; }
      var em = form.elements.email; if (em.value.trim() && !em.checkValidity()) { em.classList.add('bad'); bad = bad || em; }
      if (bad) { bad.scrollIntoView({ behavior: 'smooth', block: 'center' }); err('Vyplňte prosím označená pole.'); return; }
      if (!$('[data-consent]', el).checked) { err('Pro odeslání prosím potvrďte souhlas se zpracováním osobních údajů.'); return; }
      var btn = $('.pqk-send', el); btn.disabled = true; btn.textContent = 'Odesílám…';
      var f = form.elements, files = [], list = [].slice.call(f.fotky.files || [], 0, 3);
      Promise.all(list.map(shrink)).then(function (bl) {
        bl.forEach(function (b, i) { if (b) files.push({ name: 'foto' + (i + 1), file: 'foto' + (i + 1) + '-' + ref + '.jpg', blob: b }); });
        return window.BDForm.send({ typ: 'poptavka', predmet: predmet, telo: telo, reply_to: d.email, cislo: ref, data_json: JSON.stringify(d), honey: f._honey.value, files: files });
      }).then(function () {
        if (window.bdTrack) window.bdTrack('Lead', { content_name: 'Poptávka ' + K.nazev + ': ' + d.model });
        $('.pqk', el).innerHTML = '<div class="pqk-done"><h2 style="margin-top:0">Děkujeme, poptávka byla odeslána.</h2><p>Číslo vaší poptávky: <b>' + esc(ref) + '</b>. Ozveme se vám co nejdříve, nejpozději do 24 hodin v pracovní dny.</p><p>Máte-li dotaz, volejte <a href="tel:+420725559235">+420 725 559 235</a> nebo pište na <a href="mailto:' + CC + '">' + CC + '</a>.</p></div>';
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }).catch(function () {
        btn.disabled = false; btn.textContent = 'Odeslat poptávku';
        err('Odeslání se nepodařilo. Zkuste to prosím znovu, nebo nám zavolejte na +420 725 559 235 či napište na ' + CC + '.');
      });
      var ref = makeRef(), modelTxt = items.map(function (x) { return (x.ks > 1 ? x.ks + '× ' : '') + x.model; }).join(', ');
      var d = { verze: 1, cislo: ref, odeslano: new Date().toISOString(), zdroj: 'web-poptavka-' + kat, kategorie: kat, kategorie_nazev: K.nazev,
        model: modelTxt, model_kod: '', polozky: items.map(function (x) { return { co: x.model, volba: x.ks + ' ks', priplatek: 0, info: '' }; }),
        jmeno: f.jmeno.value.trim(), prijmeni: f.prijmeni.value.trim(), telefon: f.telefon.value.trim(), email: f.email.value.trim(),
        ulice: f.ulice.value.trim(), mesto: f.mesto.value.trim(), psc: f.psc.value.trim(), poznamka: f.poznamka.value.trim(), fotky: list.map(function (_, i) { return 'foto' + (i + 1); }) };
      var predmet = 'Poptávka ' + K.nazev.toLowerCase() + ' ' + ref + ' – ' + modelTxt + ' – ' + d.prijmeni + (d.mesto ? ', ' + d.mesto : '');
      var telo = window.BDForm.bodyFromFields([['Číslo poptávky', ref], ['Kategorie', K.nazev], ['Dveře', modelTxt], ['Jméno', d.jmeno + ' ' + d.prijmeni], ['Telefon', d.telefon], ['E-mail', d.email],
        ['Adresa montáže', [d.ulice, d.psc + ' ' + d.mesto].join(', ').replace(/^,\s*|\s+$/g, '')], ['Poznámka', d.poznamka], ['Fotky', list.length ? list.length + ' ks v příloze' : ''], ['Odesláno', new Date().toLocaleString('cs-CZ')]].filter(function (x) { return x[1]; }));
    });
    return el;
  }
  function pick(model) { var s = document.querySelector('[data-poptavka-kat] [data-m]'); if (!s) return false;
    [].some.call(s.options, function (o) { if (o.value === model || o.text.indexOf(model) === 0) { s.value = o.value; return true; } return false; }); s.scrollIntoView({ behavior: 'smooth', block: 'center' }); return true; }
  window.PoptavkaKat = { mount: mount, pick: pick };
  document.addEventListener('DOMContentLoaded', function () { [].forEach.call(document.querySelectorAll('[data-poptavka-kat]'), function (el) { if (!el.firstChild) mount(el, el.getAttribute('data-poptavka-kat')); }); });
})();
