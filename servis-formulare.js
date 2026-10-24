/* Servisní formuláře na webu → vlastní server na NAS (formulare.dvere.app).
   • Servis oken (servis-oken.html, #servis-oken-poptavka): zatím jen poptávka – uloží se do kartotéky jako poptávka P xx/RRRR
     (model „Servis oken – …“), e-mail česky, v příloze PDF poptávky a fotka.
   • Servis dveří (servis-dveri.html, #servis-formular): podle čísla objednávky + telefonu se najdou dveře v kartotéce a založí se
     servisní požadavek S-RRRR-xxx (aplikace-api.php, akce servis_web → kartotéka „Servisní požadavky“, zákazník ho uvidí i v aplikaci).
     Když objednávku nenajdeme (starší objednávka, překlep v čísle/telefonu) nebo server neodpoví, odešle se jako poptávka
     „Servis dveří“ s PDF, ať se nic neztratí.
   Načíst za formulare-klient.js a před form-submit.js (zachytí odeslání dřív než obecný form-submit.js). */
(function () {
  'use strict';
  if (!window.BDForm) return;
  var API = BDForm.ENDPOINT.replace('odeslat.php', 'aplikace-api.php');
  var PDFBASE = 'poptavka-pdf/';
  var GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], LINE = [220, 233, 225], INK = [15, 42, 28];

  /* ── pomocné ── */
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function p2(n) { return String(n).padStart(2, '0'); }
  function zaloznCislo(pre) { var t = new Date(); return pre + t.getFullYear() + p2(t.getMonth() + 1) + p2(t.getDate()) + '-' + p2(t.getHours()) + p2(t.getMinutes()); }
  function souborNazev(s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function loadScript(src) { return new Promise(function (ok, ko) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); }); }
  function b64(buf) { var s = '', u = new Uint8Array(buf), i; for (i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); }
  var fontsP = null;
  function fonts() {
    if (fontsP) return fontsP;
    fontsP = (window.jspdf ? Promise.resolve() : loadScript(PDFBASE + 'jspdf.umd.min.js')).then(function () {
      return Promise.all(['Inter-Regular.ttf', 'Inter-Bold.ttf'].map(function (f) { return fetch(PDFBASE + f).then(function (r) { if (!r.ok) throw new Error(f); return r.arrayBuffer(); }).then(b64); }));
    });
    fontsP.catch(function () { fontsP = null; });
    return fontsP;
  }
  function toJpeg(src, maxPx) {
    return new Promise(function (ok) {
      if (!src) return ok(null);
      var url = typeof src === 'string' ? src : URL.createObjectURL(src), img = new Image();
      img.onload = function () {
        var s = Math.min(1, maxPx / Math.max(img.naturalWidth, img.naturalHeight)), cv = document.createElement('canvas');
        cv.width = Math.max(1, Math.round(img.naturalWidth * s)); cv.height = Math.max(1, Math.round(img.naturalHeight * s));
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(img, 0, 0, cv.width, cv.height);
        if (typeof src !== 'string') URL.revokeObjectURL(url);
        try { ok({ data: cv.toDataURL('image/jpeg', 0.85), w: cv.width, h: cv.height }); } catch (e) { ok(null); }
      };
      img.onerror = function () { ok(null); };
      img.src = url;
    });
  }

  /* PDF poptávky (A4, stejný vzhled jako poptávka dveří): o = { titulek, cislo, rows: [[popisek, hodnota]], popis, foto (File) } → Promise<Blob> */
  function buildPdf(o) {
    return fonts().then(function (f) {
      return Promise.all([toJpeg('logo.jpg', 600), toJpeg(o.foto, 1400)]).then(function (im) {
        var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        doc.addFileToVFS('Inter-Regular.ttf', f[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
        doc.addFileToVFS('Inter-Bold.ttf', f[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
        var W = 210, M = 14, y, cw = W - 2 * M;
        var font = function (st, sz, col) { doc.setFont('Inter', st); doc.setFontSize(sz); doc.setTextColor.apply(doc, col || INK); };
        function footer() { font('normal', 7.5, MUTED); doc.text('Bezpečné dveře · Zdeněk Kučera, IČ 64682676 · vzorkovna Obchodní 36, Most · +420 725 559 235 · chci@bezpecne-dvere.cz', W / 2, 289, { align: 'center' }); }
        function ensure(h) { if (y + h > 280) { footer(); doc.addPage(); y = 18; } }
        function section(t) { ensure(16); font('bold', 9, ORANGE); doc.text(t.toUpperCase(), M, y); y += 2; doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.line(M, y, W - M, y); y += 5.5; }

        doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
        if (im[0]) { var lh = 16; doc.addImage(im[0].data, 'JPEG', M, 7, lh * im[0].w / im[0].h, lh); }
        font('bold', 15, [255, 255, 255]); doc.text(o.titulek, W - M, 13, { align: 'right' });
        font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + o.cislo + '  ·  ' + new Date().toLocaleString('cs-CZ'), W - M, 19.5, { align: 'right' });
        doc.text('Odesláno z webu bezpecne-dvere.cz', W - M, 24.5, { align: 'right' });
        y = 40;

        section('Zákazník a místo');
        o.rows.forEach(function (r) {
          if (!r[1]) return;
          var lines = doc.splitTextToSize(String(r[1]), cw - 46); ensure(lines.length * 4.6 + 1.5);
          font('normal', 9.5, MUTED); doc.text(r[0], M, y);
          font('bold', 9.5); doc.text(lines, M + 46, y); y += lines.length * 4.6 + 1.5;
        });
        y += 3;
        section('Co je potřeba vyřešit');
        font('normal', 10);
        doc.splitTextToSize(o.popis || '–', cw).forEach(function (l) { ensure(5.2); doc.text(l, M, y); y += 5.2; });
        if (im[1]) {
          y += 4; section('Fotografie od zákazníka');
          var maxW = cw, maxH = 280 - y; if (maxH < 70) { footer(); doc.addPage(); y = 18; maxH = 262; }
          var s = Math.min(maxW / im[1].w, maxH / im[1].h), w = im[1].w * s, h = im[1].h * s;
          doc.addImage(im[1].data, 'JPEG', M, y, w, h); y += h + 4;
        }
        footer();
        return doc.output('blob');
      });
    });
  }

  /* odeslání jako poptávka (kartotéka → Poptávky) + PDF a fotka v příloze */
  function poslatPoptavku(x) {
    return BDForm.nextNumber('poptavka').then(function (c) { return c || zaloznCislo(x.prefix); }).then(function (cislo) {
      x.data.cislo = cislo;
      var telo = [x.titulek + ' ' + cislo + ' z webu bezpecne-dvere.cz', ''].concat(x.rows.map(function (r) { return r[0] + ': ' + (r[1] || '–'); }))
        .concat(['', 'Co je potřeba vyřešit:', x.popis, '', 'V příloze: PDF poptávky' + (x.foto ? ' a fotografie od zákazníka' : ''), 'Odesláno: ' + new Date().toLocaleString('cs-CZ')]);
      if (x.poznamka) telo.splice(2, 0, x.poznamka, '');
      return buildPdf({ titulek: x.titulek, cislo: cislo, rows: x.poznamka ? x.rows.concat([['Upozornění', x.poznamka]]) : x.rows, popis: x.popis, foto: x.foto }).catch(function () { return null; }).then(function (pdf) {
        var files = [];
        if (pdf) files.push({ name: 'pdf', file: souborNazev(x.titulek + ' ' + cislo) + '.pdf', blob: pdf });
        else telo.push('(PDF se nepodařilo vytvořit.)');
        if (x.foto) files.push({ name: 'foto', file: x.foto.name || 'foto.jpg', blob: x.foto });
        x.data.fotky = x.foto ? ['foto'] : [];
        return BDForm.send({ typ: 'poptavka', predmet: x.titulek + ' ' + cislo + ' – ' + x.kdo, telo: telo.join('\n'), reply_to: x.data.email,
          cislo: cislo, data_json: JSON.stringify(x.data), honey: x.honey, files: files }).then(function () { return cislo; });
      });
    });
  }

  /* společná obsluha formuláře */
  function napojit(form, zpracuj) {
    var btn = form.querySelector('button[type="submit"]');
    var msg = document.createElement('div');
    msg.setAttribute('role', 'status');
    msg.style.cssText = 'display:none;margin-top:1rem;padding:1rem 1.1rem;border-radius:10px;font-size:.95rem;line-height:1.5';
    form.appendChild(msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault(); e.stopImmediatePropagation();
      if (!form.reportValidity()) return;
      var label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Odesílám…'; }
      msg.style.display = 'none';
      var v = function (n) { var el = form.elements[n]; return el ? String(el.value || '').trim() : ''; };
      var fe = form.elements.attachment, foto = fe && fe.files && fe.files[0] && fe.files[0].size > 0 ? fe.files[0] : null;
      Promise.resolve().then(function () { return zpracuj(v, foto); }).then(function (html) {
        form.reset();
        msg.style.display = 'block'; msg.style.background = '#e8f5ee'; msg.style.color = '#123d27'; msg.innerHTML = html;
      }).catch(function (err) {
        msg.style.display = 'block'; msg.style.background = '#fdecea'; msg.style.color = '#7a1c12';
        if (err && err.ukaz) { msg.textContent = err.message; return; }
        msg.innerHTML = 'Odeslání se nepodařilo. Napište nám prosím na <a href="mailto:chci@bezpecne-dvere.cz">chci@bezpecne-dvere.cz</a> nebo zavolejte na <a href="tel:+420725559235">+420 725 559 235</a>.';
      }).then(function () { if (btn) { btn.disabled = false; btn.textContent = label; } });
    }, true);
  }

  /* ── servis oken (jen poptávka) ── */
  var fo = document.querySelector('#servis-oken-poptavka form');
  if (fo) napojit(fo, function (v, foto) {
    var cele = v('jmeno').replace(/\s+/g, ' '), i = cele.indexOf(' '), pro = v('typ_zakaznika');
    var rows = [['Jméno a příjmení', cele], ['Telefon', v('telefon')], ['E-mail', v('email')], ['Adresa', v('adresa')], ['Pro koho', pro]];
    return poslatPoptavku({
      titulek: 'Poptávka servisu oken', prefix: 'SO-', kdo: cele + (pro ? ' (' + pro + ')' : ''), rows: rows, popis: v('popis'), foto: foto, honey: v('_honey'),
      data: { verze: 1, odeslano: new Date().toISOString(), zdroj: 'web-servis-oken', druh: 'servis_oken', model: 'Servis oken' + (pro ? ' – ' + pro : ''), model_kod: 'servis-oken',
        pro_koho: pro, jmeno: i > 0 ? cele.slice(0, i) : cele, prijmeni: i > 0 ? cele.slice(i + 1) : '', telefon: v('telefon'), email: v('email'), mesto: v('adresa'), poznamka: v('popis') }
    }).then(function (cislo) {
      if (window.bdTrack) window.bdTrack('Lead', { content_name: 'Poptávka servisu oken' });
      return '<strong>Děkujeme, poptávka byla odeslána.</strong> Číslo poptávky: <strong>' + esc(cislo) + '</strong>. Ozveme se Vám co nejdříve.';
    });
  });

  /* ── servis dveří (servisní požadavek k objednávce, jinak poptávka) ── */
  var fd = document.querySelector('#servis-formular form');
  if (fd) napojit(fd, function (v, foto) {
    var body = new FormData();
    body.append('akce', 'servis_web');
    ['jmeno', 'prijmeni', 'ulice', 'mesto', 'email', 'telefon', 'popis', '_honey'].forEach(function (k) { body.append(k, v(k)); });
    body.append('cislo', v('cislo_objednavky'));
    if (foto) body.append('foto', foto, foto.name || 'foto.jpg');
    var ctl = typeof AbortController === 'function' ? new AbortController() : null, t = ctl ? setTimeout(function () { ctl.abort(); }, 60000) : 0;
    var ok = function (cislo, kde) {
      if (window.bdTrack) window.bdTrack('Lead', { content_name: 'Servis dveří' });
      return '<strong>Děkujeme, požadavek byl odeslán.</strong> Číslo: <strong>' + esc(cislo) + '</strong>. ' + (kde ? kde + ' ' : '') + 'Ozveme se Vám do 48 hodin (neplatí o svátky a víkendy).';
    };
    return fetch(API, { method: 'POST', headers: { 'Accept': 'application/json' }, body: body, signal: ctl ? ctl.signal : undefined })
      .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }, function () { return { s: r.status, j: null }; }); }, function () { return { s: 0, j: null }; })
      .then(function (x) {
        if (t) clearTimeout(t);
        if (x.s === 200 && x.j && x.j.ok) return ok(x.j.cislo, 'Požadavek jsme přiřadili k Vaší objednávce ' + esc(x.j.objednavka || v('cislo_objednavky')) + '.');
        if (x.s === 400 && x.j && x.j.zprava) { var e = new Error(x.j.zprava); e.ukaz = true; throw e; }
        // objednávku jsme nenašli / limit / server nedostupný → poptávka, ať se požadavek neztratí
        var kdo = (v('jmeno') + ' ' + v('prijmeni')).trim(), co = v('cislo_objednavky');
        var rows = [['Jméno a příjmení', kdo], ['Telefon', v('telefon')], ['E-mail', v('email')], ['Adresa', v('ulice') + ', ' + v('mesto')], ['Číslo objednávky', co]];
        return poslatPoptavku({
          titulek: 'Servis dveří', prefix: 'SD-', kdo: kdo + ' – obj. ' + co, rows: rows, popis: v('popis'), foto: foto, honey: v('_honey'),
          poznamka: x.s === 404 ? 'POZOR: objednávku ' + co + ' s tímto telefonem jsme v kartotéce nenašli – ověřte číslo objednávky a záruku ručně.'
            : 'Servisní požadavek se nepodařilo založit automaticky (odpověď serveru ' + (x.s || 'žádná') + ') – zadejte ho ručně.',
          data: { verze: 1, odeslano: new Date().toISOString(), zdroj: 'web-servis-dveri', druh: 'servis_dveri', model: 'Servis dveří – obj. ' + co, model_kod: 'servis-dveri',
            obj_cislo_zakaznik: co, jmeno: v('jmeno'), prijmeni: v('prijmeni'), telefon: v('telefon'), email: v('email'), ulice: v('ulice'), mesto: v('mesto'), poznamka: v('popis') }
        }).then(function (cislo) { return ok(cislo, ''); });
      });
  });
})();
