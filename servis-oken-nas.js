/* Poptávka servisu oken → vlastní server na NAS (formulare.dvere.app) místo FormSubmit.
   Ukládá se do kartotéky jako poptávka (typ „poptavka“, číslo P xx/RRRR, model „Servis oken – …“), e-mail česky, fotka v příloze.
   Nahrazuje obecné odeslání z form-submit.js jen u tohoto formuláře (to by poslalo e-mail bez dat pro kartotéku).
   Načíst za formulare-klient.js a před form-submit.js. */
(function () {
  'use strict';
  var form = document.querySelector('#servis-oken-poptavka form');
  if (!form || !window.BDForm) return;

  var btn = form.querySelector('button[type="submit"]');
  var msg = document.createElement('div');
  msg.setAttribute('role', 'status');
  msg.style.cssText = 'display:none;margin-top:1rem;padding:1rem 1.1rem;border-radius:10px;font-size:.95rem;line-height:1.5';
  form.appendChild(msg);
  function show(ok, html) {
    msg.style.display = 'block'; msg.style.background = ok ? '#e8f5ee' : '#fdecea'; msg.style.color = ok ? '#123d27' : '#7a1c12'; msg.innerHTML = html;
  }
  function v(n) { var el = form.elements[n]; return el ? String(el.value || '').trim() : ''; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function zaloznCislo() {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'SO-' + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes());
  }

  /* capture: zachytí odeslání dřív než obecný form-submit.js */
  form.addEventListener('submit', function (e) {
    e.preventDefault(); e.stopImmediatePropagation();
    if (!form.reportValidity()) return;
    var label = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Odesílám…'; }
    msg.style.display = 'none';

    var cele = v('jmeno').replace(/\s+/g, ' '), i = cele.indexOf(' ');
    var jmeno = i > 0 ? cele.slice(0, i) : cele, prijmeni = i > 0 ? cele.slice(i + 1) : '';
    var pro = v('typ_zakaznika'), popis = v('popis'), adresa = v('adresa');
    var fileEl = form.elements.attachment, file = fileEl && fileEl.files && fileEl.files[0];
    var files = file && file.size > 0 ? [{ name: 'foto', file: file.name || 'foto.jpg', blob: file }] : [];

    BDForm.nextNumber('poptavka').then(function (c) { return c || zaloznCislo(); }).then(function (cislo) {
      var d = {
        verze: 1, cislo: cislo, odeslano: new Date().toISOString(), zdroj: 'web-servis-oken', druh: 'servis_oken',
        model: 'Servis oken' + (pro ? ' – ' + pro : ''), model_kod: 'servis-oken', pro_koho: pro,
        jmeno: jmeno, prijmeni: prijmeni, telefon: v('telefon'), email: v('email'), mesto: adresa,
        poznamka: popis, fotky: files.length ? ['foto'] : []
      };
      var telo = [
        'Poptávka servisu oken ' + cislo + ' z webu bezpecne-dvere.cz',
        '',
        'Jméno a příjmení: ' + cele,
        'Telefon: ' + d.telefon,
        'E-mail: ' + (d.email || '–'),
        'Adresa: ' + adresa,
        'Pro koho: ' + (pro || '–'),
        '',
        'Co potřebuje vyřešit:',
        popis,
        '',
        'Fotografie: ' + (files.length ? 'v příloze' : 'nepřiložena'),
        'Odesláno: ' + new Date().toLocaleString('cs-CZ')
      ].join('\n');
      return BDForm.send({
        typ: 'poptavka', predmet: 'Poptávka servisu oken ' + cislo + ' – ' + cele + (pro ? ' (' + pro + ')' : ''),
        telo: telo, reply_to: d.email, cislo: cislo, data_json: JSON.stringify(d), honey: v('_honey'), files: files
      }).then(function () { return cislo; });
    }).then(function (cislo) {
      form.reset();
      if (window.bdTrack) window.bdTrack('Lead', { content_name: 'Poptávka servisu oken' });
      show(true, '<strong>Děkujeme, poptávka byla odeslána.</strong> Číslo poptávky: <strong>' + esc(cislo) + '</strong>. Ozveme se Vám co nejdříve.');
    }).catch(function () {
      show(false, 'Odeslání se nepodařilo. Napište nám prosím na <a href="mailto:chci@bezpecne-dvere.cz">chci@bezpecne-dvere.cz</a> nebo zavolejte na <a href="tel:+420725559235">+420 725 559 235</a>.');
    }).then(function () {
      if (btn) { btn.disabled = false; btn.textContent = label; }
    });
  }, true);
})();
