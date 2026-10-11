/* PDF shrnutí poptávky (generuje se v prohlížeči, jsPDF 2.5.2 + Inter s češtinou).
   PoptavkaPDF.build(data, extra) → Promise<Blob>
   extra = { modelImg, shadeImg ('url' nebo 'color:#hex'), photos: {fotka_dveri: Blob, fotka_zarubne: Blob}, rows: [[label, value, group]] } */
(function () {
  'use strict';
  var BASE = (function () { var s = document.currentScript && document.currentScript.src; return s ? s.replace(/[^/]*$/, '') : 'poptavka-pdf/'; })();
  var GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], LINE = [220, 233, 225], PALE = [246, 250, 247];
  var fontsReady = null;

  function loadScript(src) {
    return new Promise(function (ok, ko) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); });
  }
  function b64(buf) { var s = '', u = new Uint8Array(buf), i; for (i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); }
  function prepare() {
    if (fontsReady) return fontsReady;
    fontsReady = (window.jspdf ? Promise.resolve() : loadScript(BASE + 'jspdf.umd.min.js')).then(function () {
      return Promise.all(['Inter-Regular.ttf', 'Inter-Bold.ttf'].map(function (f) { return fetch(BASE + f).then(function (r) { if (!r.ok) throw new Error(f); return r.arrayBuffer(); }).then(b64); }));
    });
    fontsReady.catch(function () { fontsReady = null; });
    return fontsReady;
  }

  /* obrázek → JPEG dataURL přes canvas (zmenší a sjednotí formát) */
  function toJpeg(src, maxPx) {
    return new Promise(function (ok) {
      if (!src) return ok(null);
      var url = typeof src === 'string' ? src : URL.createObjectURL(src);
      var img = new Image();
      img.onload = function () {
        var s = Math.min(1, maxPx / Math.max(img.naturalWidth, img.naturalHeight));
        var cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(img.naturalWidth * s)); cv.height = Math.max(1, Math.round(img.naturalHeight * s));
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(img, 0, 0, cv.width, cv.height);
        if (typeof src !== 'string') URL.revokeObjectURL(url);
        try { ok({ data: cv.toDataURL('image/jpeg', 0.85), w: cv.width, h: cv.height }); } catch (e) { ok(null); }
      };
      img.onerror = function () { ok(null); };
      img.src = url;
    });
  }
  function hex(c) { c = c.replace('#', ''); return [parseInt(c.substr(0, 2), 16), parseInt(c.substr(2, 2), 16), parseInt(c.substr(4, 2), 16)]; }

  function build(d, x) {
    x = x || {};
    var shade = x.shadeImg && x.shadeImg.indexOf('color:') !== 0 ? x.shadeImg : null;
    var photos = x.photos || {};
    return prepare().then(function (fonts) {
      return Promise.all([toJpeg('logo.jpg', 600), toJpeg(x.modelImg, 700), toJpeg(shade, 300),
        toJpeg(photos.fotka_dveri, 1400), toJpeg(photos.fotka_zarubne, 1400)]).then(function (im) {
        var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        doc.addFileToVFS('Inter-Regular.ttf', fonts[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
        doc.addFileToVFS('Inter-Bold.ttf', fonts[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
        var W = 210, M = 14, y;
        var font = function (st, sz, col) { doc.setFont('Inter', st); doc.setFontSize(sz); doc.setTextColor.apply(doc, col || [15, 42, 28]); };

        /* hlavička */
        doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
        if (im[0]) { var lh = 16, lw = lh * im[0].w / im[0].h; doc.addImage(im[0].data, 'JPEG', M, 7, lw, lh); }
        font('bold', 15, [255, 255, 255]); doc.text('Poptávka bezpečnostních dveří', W - M, 13, { align: 'right' });
        font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + d.cislo + '  ·  ' + new Date(d.odeslano).toLocaleString('cs-CZ'), W - M, 19.5, { align: 'right' });
        doc.text('Odesláno z webu bezpecne-dvere', W - M, 24.5, { align: 'right' });
        y = 40;

        function section(t) {
          font('bold', 9, ORANGE); doc.text(t.toUpperCase(), M, y); y += 2;
          doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.line(M, y, W - M, y); y += 5.5;
        }
        function pairs(list, x0, wLab, wVal) {
          var yy = y;
          list.forEach(function (p) {
            if (!p[1]) return;
            font('normal', 9, MUTED); doc.text(p[0], x0, yy);
            font('bold', 10); var lines = doc.splitTextToSize(String(p[1]), wVal);
            doc.text(lines, x0 + wLab, yy); yy += Math.max(1, lines.length) * 4.6 + 1.6;
          });
          return yy;
        }

        /* zákazník */
        section('Zákazník');
        var adr = [d.ulice, [d.psc, d.mesto].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        var yl = pairs([['Jméno', (d.jmeno + ' ' + d.prijmeni).trim()], ['Telefon', d.telefon], ['E-mail', d.email]], M, 22, 66);
        var bill = d.fakturace;
        if (d.fakturace && d.fakturace.indexOf('Stejná') !== 0) bill = d.fak_jmeno + '\n' + d.fak_adresa + (d.ic ? '\nIČ ' + d.ic + (d.platce_dph ? ', plátce DPH' : '') : '');
        var yr = pairs([['Montáž', adr], ['Fakturace', bill]], 108, 22, 66);
        y = Math.max(yl, yr) + 3;

        /* dveře */
        section('Vybrané dveře');
        var top = y - 3, imgW = 52, imgH = 52;
        doc.setFillColor(255, 255, 255); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(M, top, imgW, imgH, 2, 2, 'FD');
        if (im[1]) { var r = Math.min((imgW - 4) / im[1].w, (imgH - 4) / im[1].h), iw = im[1].w * r, ih = im[1].h * r; doc.addImage(im[1].data, 'JPEG', M + (imgW - iw) / 2, top + (imgH - ih) / 2, iw, ih); }
        // vzorek barvy pod fotkou
        var sy = top + imgH + 3;
        if (im[2]) doc.addImage(im[2].data, 'JPEG', M, sy, 14, 14);
        else if (x.shadeImg && x.shadeImg.indexOf('color:') === 0) { doc.setFillColor.apply(doc, hex(x.shadeImg.slice(6))); doc.roundedRect(M, sy, 14, 14, 1.5, 1.5, 'F'); }
        font('normal', 8, MUTED); doc.text('Barva', M + 17, sy + 5); font('bold', 9.5); doc.text(doc.splitTextToSize(d.odstin || '', imgW - 17), M + 17, sy + 10);

        font('bold', 16, GREEN); doc.text(d.model, M + imgW + 8, top + 5);
        y = top + 13;
        var spec = [['Barva', d.odstin], ['Zárubeň', d.zarubne], ['Kování', d.kovani], ['Kukátko', d.kukatko], ['Vložky', d.vlozky],
          ['Otevírání', d.otevirani], ['Šířka', d.sire && d.sire !== 'Nevím' ? d.sire + ' cm' : d.sire], ['Záruka', d.zaruka]];
        var yd = pairs(spec, M + imgW + 8, 22, W - M - (M + imgW + 8) - 22);
        y = Math.max(yd, sy + 18) + 3;

        /* cena */
        section('Orientační cena');
        var cx = M, cw = W - 2 * M;
        var priceRow = function (l, v, bold) { font(bold ? 'bold' : 'normal', bold ? 10 : 9.5, bold ? [15, 42, 28] : MUTED); doc.text(l, cx, y); doc.text(v, cx + cw, y, { align: 'right' }); y += 5.4; };
        priceRow('Základní cena ' + d.model + ' (montáž na klíč)', fmt(d.cena_zaklad));
        (d.polozky || []).forEach(function (p) { if (p.priplatek) priceRow(p.co + ': ' + p.volba, '+ ' + fmt(p.priplatek)); });
        y += 1;
        doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(cx, y - 4.5, cw, 12, 2, 2, 'FD');
        font('bold', 11, GREEN); doc.text('Celkem vč. 12 % DPH', cx + 4, y + 3);
        font('bold', 15, ORANGE); doc.text(fmt(d.cena_celkem), cx + cw - 4, y + 3.4, { align: 'right' });
        y += 12;
        font('normal', 8, MUTED);
        doc.text(doc.splitTextToSize('Cena zahrnuje montáž na klíč, odvoz starých dveří, hrubý úklid a dopravu do 40 km. ' + (x.priceNote || '') + ' Nezávazná poptávka, konečnou cenu potvrdí prodejce.', cw), cx, y);
        y += 10;

        /* doplňující */
        var extra = [['Zeď', d.zed], ['Výška k nadpraží', d.vyska_cm ? d.vyska_cm + ' cm' : ''], ['Poznámka', d.poznamka], ['Fotky', (im[3] || im[4]) ? 'v příloze na další straně' : '']];
        if (extra.some(function (e) { return e[1]; })) { section('Doplňující informace'); y = pairs(extra, M, 34, cw - 34) + 2; }

        /* patička */
        function footer() {
          font('normal', 7.5, MUTED);
          doc.text('Bezpečné dveře · Zdeněk Kučera, IČ 64682676 · vzorkovna Obchodní 36, Most · +420 725 559 235 · chci@bezpecne-dvere.cz', W / 2, 289, { align: 'center' });
        }
        footer();

        /* fotky */
        var ph = [[im[3], 'Stávající dveře zvenku'], [im[4], 'Stávající zárubeň']].filter(function (p) { return p[0]; });
        if (ph.length) {
          doc.addPage(); y = 18;
          font('bold', 13, GREEN); doc.text('Fotky od zákazníka · ' + d.cislo, M, y); y += 6;
          var boxH = ph.length === 1 ? 250 : 124;
          ph.forEach(function (p) {
            font('bold', 9, ORANGE); doc.text(p[1].toUpperCase(), M, y + 4); y += 6;
            var r2 = Math.min((W - 2 * M) / p[0].w, (boxH - 8) / p[0].h), pw = p[0].w * r2, phh = p[0].h * r2;
            doc.addImage(p[0].data, 'JPEG', M + (W - 2 * M - pw) / 2, y, pw, phh); y += boxH - 4;
          });
          footer();
        }
        return doc.output('blob');
      });
    });
  }
  function fmt(n) { return Number(n || 0).toLocaleString('cs-CZ').replace(/ /g, ' ') + ' Kč'; }

  window.PoptavkaPDF = { prepare: prepare, build: build };
})();
