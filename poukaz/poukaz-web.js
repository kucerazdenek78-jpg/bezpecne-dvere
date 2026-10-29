/* Stránka darkovy-poukaz.html: výběr hodnoty a motivu, živý náhled poukazu, objednávka na NAS (poukaz-api.php),
   vytvoření PDF v prohlížeči a jeho nahrání, QR platba. Potřebuje poukaz.js, platba/qrcode.js, platba/platba.js, formulare-klient.js, firma-ares.js. */
(function () {
  'use strict';
  var form = document.getElementById('pvForm'); if (!form || !window.Poukaz) return;
  var API = (window.BDForm && BDForm.ENDPOINT ? BDForm.ENDPOINT : 'https://formulare.dvere.app/odeslat.php').replace('odeslat.php', 'poukaz-api.php');
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var cv = $('#pvCanvas'), own = $('#pvOwn'), ownBox = $('.pv-ownbox'), err = $('#pvErr'), btn = $('#pvSubmit');
  var q = new URLSearchParams(location.search), foto = null, fotoBox = $('.pv-fotobox'), fotoIn = $('#pvFoto');

  // výchozí motiv: do Vánoc vánoční, jinak dárkový; ?motiv= / ?hodnota= z reklamy nebo odkazu
  var mot = q.get('motiv'); if (!Poukaz.MOTIVY[mot]) mot = Poukaz.vychoziMotiv();
  form.querySelector('input[name=motiv][value=' + mot + ']').checked = true;
  var hq = parseInt(q.get('hodnota'), 10);
  if (hq) { var r0 = form.querySelector('input[name=hodnota_v][value="' + hq + '"]'); if (r0) r0.checked = true; else { form.querySelector('input[name=hodnota_v][value=vlastni]').checked = true; own.value = hq; } }

  function hodnota() {
    var v = (form.querySelector('input[name=hodnota_v]:checked') || {}).value;
    if (v === 'vlastni') { var n = Math.round((parseFloat(own.value) || 0) / 100) * 100; return n; }
    return parseInt(v, 10) || 0;
  }
  function data() {
    return { motiv: (form.querySelector('input[name=motiv]:checked') || {}).value || 'darkovy', hodnota: hodnota() || 0, cislo: 'DP —/' + new Date().getFullYear(), kod: '••••-••••',
      platnost_do: '', obdarovany: form.obdarovany.value, venovani: form.venovani.value, od_koho: form.od_koho.value, foto: foto };
  }
  var t = null;
  function prekresli() {
    var d = data(), vlastni = (form.querySelector('input[name=hodnota_v]:checked') || {}).value === 'vlastni';
    ownBox.hidden = !vlastni; fotoBox.hidden = d.motiv !== 'vlastni';
    $('#pvVenN').textContent = form.venovani.value.length;
    var h = d.hodnota, f = h / Poukaz.VZOR_CENA;
    $('#pvMeterFill').style.width = Math.min(100, Math.max(h ? 3 : 0, f * 100)) + '%';
    $('#pvMeterText').innerHTML = h >= 500 ? (f >= 1 ? 'S touto částkou jsou dveře <b>PREMIUM 60</b> (akční cena ' + Poukaz.kc(Poukaz.VZOR_CENA) + ' s montáží) <b>zaplacené celé</b>.'
      : 'Na dveře <b>PREMIUM 60</b> (akční cena ' + Poukaz.kc(Poukaz.VZOR_CENA) + ' s montáží) je to <b>' + Math.round(f * 100) + ' %</b> – zbytek obdarovaný doplatí.') : 'Zadejte částku od 500 Kč.';
    $('#pvSubmitKc').textContent = h >= 500 ? Poukaz.kc(h) : '…';
    var lab = { vanoce: 'Vánoční dárkový poukaz', narozeniny: 'Dárkový poukaz k narozeninám', darkovy: 'Dárkový poukaz', vlastni: 'Dárkový poukaz s vlastní fotkou' }[d.motiv];
    $('#pv-label').textContent = lab;
    clearTimeout(t); t = setTimeout(function () { Poukaz.ready().then(function () { Poukaz.kresli(cv, d, 3); }); }, 120);
  }
  form.addEventListener('input', prekresli); form.addEventListener('change', prekresli);
  fotoIn.addEventListener('change', function () {
    var f = fotoIn.files && fotoIn.files[0]; foto = null;
    if (!f) return prekresli();
    if (f.size > 15 * 1048576) { chyba('Fotka je příliš velká (max. 15 MB).'); fotoIn.value = ''; return prekresli(); }
    Poukaz.nactiFoto(f).then(function (img) { foto = img; chyba(''); prekresli(); }).catch(function () { chyba('Fotku se nepodařilo načíst – zkuste jinou (JPG nebo PNG).'); fotoIn.value = ''; prekresli(); });
  });
  prekresli();

  // firma + ARES
  var fch = $('#pvFirmaCh'), fbox = $('#pvFirma');
  fch.addEventListener('change', function () { fbox.hidden = !fch.checked; });
  if (window.BDAres) BDAres.attach(fbox, { onChange: function (r) {
    var m = /^(.*?),\s*(\d{3}\s?\d{2})\s+(.+)$/.exec(r.adresa || '');
    if (m) { if (!form.kupujici_ulice.value) form.kupujici_ulice.value = m[1]; if (!form.kupujici_psc.value) form.kupujici_psc.value = m[2]; if (!form.kupujici_mesto.value) form.kupujici_mesto.value = m[3]; }
  } });

  function chyba(s, el) { if (!err) return; err.textContent = s; err.hidden = !s; if (el) { el.classList.add('bad'); el.focus(); } }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault(); chyba('');
    [].forEach.call(form.querySelectorAll('.bad'), function (x) { x.classList.remove('bad'); });
    var h = hodnota();
    if (h < 500 || h > 50000) return chyba('Hodnota poukazu musí být od 500 do 50 000 Kč.', own);
    var povinne = [['kupujici_jmeno', 'Vyplňte jméno a příjmení.'], ['email', 'Vyplňte e-mail – pošleme na něj poukaz.'], ['kupujici_ulice', 'Vyplňte ulici.'], ['kupujici_mesto', 'Vyplňte obec.'], ['kupujici_psc', 'Vyplňte PSČ.']];
    for (var i = 0; i < povinne.length; i++) { var el = form[povinne[i][0]]; if (!el.value.trim()) return chyba(povinne[i][1], el); }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email.value.trim())) return chyba('E-mail nemá správný tvar.', form.email);
    if (!form.souhlas.checked) return chyba('Potvrďte prosím souhlas s podmínkami.', form.souhlas);
    if (data().motiv === 'vlastni' && !foto) return chyba('Nahrajte prosím svou fotku, nebo vyberte jiný motiv.', fotoIn);
    var d = data(), fd = new FormData();
    fd.append('akce', 'objednat'); fd.append('hodnota', h); fd.append('motiv', d.motiv);
    ['obdarovany', 'venovani', 'od_koho', 'kupujici_jmeno', 'email', 'telefon', 'kupujici_ulice', 'kupujici_mesto', 'kupujici_psc', '_honey'].forEach(function (k) { fd.append(k, form[k].value.trim()); });
    if (fch.checked) { fd.append('kupujici_ic', form.ic.value.trim()); fd.append('kupujici_dic', form.dic.value.trim()); fd.append('kupujici_firma', form.fak_jmeno.value.trim()); }
    if (form.vyzvednout.checked) fd.append('vyzvednout', '1');
    fd.append('souhlas', '1');
    btn.disabled = true; btn.textContent = 'Odesílám…';
    fetch(API, { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (j) {
        if (!j || !j.ok) throw new Error((j && j.chyba) || 'Objednávku se nepodařilo odeslat. Zkuste to prosím znovu nebo zavolejte +420 725 559 235.');
        hotovo(j);
      })
      .catch(function (e) { btn.disabled = false; btn.innerHTML = 'Objednat poukaz za <span id="pvSubmitKc">' + Poukaz.kc(h) + '</span>'; chyba(e.message && !/fetch|network/i.test(e.message) ? e.message : 'Spojení se nepodařilo. Zkuste to prosím znovu nebo zavolejte +420 725 559 235.'); });
  });

  function hotovo(j) {
    var p = j.poukaz, done = $('#pvDone');
    try { if (window.bdTrack) bdTrack('Lead', { content_name: 'Dárkový poukaz', value: p.hodnota, currency: 'CZK' }); } catch (e) {}
    form.hidden = true; done.hidden = false;
    var zprava = 'Darkovy poukaz ' + p.cislo;
    var set = function (k, v) { [].forEach.call(done.querySelectorAll('[data-f=' + k + ']'), function (x) { x.textContent = v; }); };
    set('cislo', p.cislo); set('email', p.email); set('ucet', p.ucet); set('castka', Poukaz.kc(p.hodnota)); set('vs', p.vs); set('zprava', zprava);
    $('#pvStavOdkaz').href = 'poukaz-stav.html?t=' + j.token;
    if (window.BDPlatba) { var c = BDPlatba.canvas(BDPlatba.spd(p.hodnota, p.vs, zprava), 240), qc = $('#pvQr'); qc.getContext('2d').drawImage(c, 0, 0, 240, 240); }
    done.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // PDF poukazu se vytvoří tady v prohlížeči a uloží na server (pošle se po zaplacení)
    var st = $('#pvPdfStav'), d0 = data();
    p.foto = d0.motiv === 'vlastni' ? foto : null;
    (p.foto ? Poukaz.fotoBlob(p.foto).then(function (fb) {
      var fd = new FormData(); fd.append('akce', 'foto'); fd.append('t', j.token); fd.append('foto', fb, 'foto.jpg');
      return fetch(API, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }).catch(function () { return null; }) : Promise.resolve()).then(function () { return Poukaz.pdf(p); }).then(function (b) {
      var fd = new FormData(); fd.append('akce', 'pdf'); fd.append('t', j.token); fd.append('pdf', b, 'poukaz.pdf');
      return fetch(API, { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }).then(function (x) { if (!x || !x.ok) throw 0; }).catch(function () { st.textContent = 'Poznámka: náhled poukazu se nepodařilo uložit, připravíme ho ručně – nic dalšího dělat nemusíte.'; });
  }
})();
