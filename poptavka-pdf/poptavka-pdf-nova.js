/* PDF shrnutí poptávky (generuje se v prohlížeči, jsPDF 2.5.2 + Inter s češtinou).
   PoptavkaPDF.build(data, extra) → Promise<Blob>
   extra = { modelImg, shadeImg ('url' nebo 'color:#hex'), photos: {fotka_dveri: Blob, fotka_zarubne: Blob}, rows: [[label, value, group]] } */
(function () {
  'use strict';
  var BASE = (function () { var s = document.currentScript && document.currentScript.src; return s ? s.replace(/[^/]*$/, '') : 'poptavka-pdf/'; })();
  var GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], LINE = [220, 233, 225], PALE = [246, 250, 247];
  var fontsReady = null;
  /* Právní ujednání objednávky – doslovně z papírových vzorů prodejce/objednavka-*.pdf (Revize 10/2026, shodné pro všechny modely) */
  var LEGAL = [
      "Zhotovitel se zavazuje provést na svůj náklad a nebezpečí pro objednatele dílo — dodávku a montáž bezpečnostních bytových dveří dle výše uvedených parametrů — s odbornou péčí, v rozsahu a kvalitě podle této objednávky a v uvedené době plnění. Objednatel se zavazuje dokončené dílo převzít a zaplatit sjednanou cenu; cena je splatná v hotovosti při převzetí díla, není-li sjednáno jinak. Objednatel nabývá vlastnické právo k dílu jeho převzetím a úplným zaplacením ceny; převzetím na něj přechází nebezpečí škody na věci. Zjevné vady je objednatel povinen vytknout při převzetí díla.",
      "Práva z vadného plnění se řídí zákonem č. 89/2012 Sb., občanský zákoník (u spotřebitele zejména § 2161 až 2174b, jinak § 2099 až 2117), a Reklamačním řádem zhotovitele. Objednatel-spotřebitel může vytknout vadu, která se projeví do 24 měsíců od převzetí díla (základní záruka 24 měsíců); podmínky bonusu a prodloužení záruky na 5 nebo 10 let jsou na www.bezpecne-dvere.cz/podminky-prodlouzeni-zaruky.html. Reklamaci lze uplatnit na provozovně Obchodní 36, Most, e-mailem na chci@bezpecne-dvere.cz, telefonicky nebo formulářem na www.bezpecne-dvere.cz; písemně na adresu Zd. Štěpánka 2764/1, 434 01 Most. Odpovědnost za vady se nevztahuje zejména na mechanické poškození, běžné opotřebení a poškození vlivem stavby či neodborného zacházení — podrobně Reklamační řád.",
      "Nedílnou součástí této objednávky jsou Obchodní podmínky a Reklamační řád zhotovitele, dostupné na www.bezpecne-dvere.cz a na provozovně. Objednatel podpisem potvrzuje, že se s nimi před uzavřením smlouvy seznámil.",
      "Byla-li tato objednávka uzavřena distančním způsobem nebo mimo obchodní prostory zhotovitele, je objednatel-spotřebitel oprávněn od smlouvy odstoupit bez udání důvodu ve lhůtě 14 dnů (§ 1829 občanského zákoníku). Právo odstoupit nenáleží u zboží vyrobeného podle požadavků spotřebitele nebo přizpůsobeného jeho osobním potřebám (dveře v provedení dle výběru objednatele — rozměr, dekor, kování a výbava) a v rozsahu služeb (montáže) zcela splněných s předchozím výslovným souhlasem spotřebitele před uplynutím lhůty pro odstoupení. Objednatel podpisem této objednávky výslovně žádá, aby montáž byla provedena před uplynutím lhůty pro odstoupení, a bere na vědomí, že jejím úplným splněním právo odstoupit od smlouvy v rozsahu montáže zaniká.",
      "K mimosoudnímu řešení spotřebitelských sporů z této smlouvy je příslušná Česká obchodní inspekce (www.coi.cz, adr.coi.cz). Objednatel bere na vědomí, že čistý průchod u dveří šíře 80 cm je 80 cm. Pro správné fungování požárního uzávěru je nutná pravidelná roční kontrola provozuschopnosti dle vyhlášky č. 246/2001 Sb.; kontrola není zahrnuta v ceně a zhotovitel ji nabízí jako placenou službu. Osobní údaje objednatele zpracovává zhotovitel pro uzavření a plnění smlouvy a plnění zákonných povinností dle Zásad zpracování osobních údajů na www.bezpecne-dvere.cz; číslo občanského průkazu se uvádí výhradně pro účely dokladu o likvidaci odpadu (demontovaných dveří). Zámečnickou pohotovost při zabouchnutí dveří či ztrátě klíčů naleznete na www.bezpecne-dvere.cz."
  ];
  var ZHOTOVITEL = 'Zhotovitel: Zdeněk Kučera, Zd. Štěpánka 2764/1, Most. IČO: 64682676, DIČ: CZ7801242768, bankovní spojení: RB Teplice, číslo účtu: 1821576001/5500. Vzorkovna: KK SYSTEM, Obchodní 36, Most, www.kksystem.cz';
  var NORMA = 'Dveře splňují normu požárního uzávěru dle § 6 a 7 vyhl. MV č. 246/2001 Sb., o požární prevenci';

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
    var O = !!x.order;
    var shade = x.shadeImg && x.shadeImg.indexOf('color:') !== 0 ? x.shadeImg : null;
    var photos = x.photos || {};
    return prepare().then(function (fonts) {
      return Promise.all([toJpeg('logo.jpg', 600), toJpeg(x.modelImg, 700), toJpeg(shade, 300),
        toJpeg(photos.fotka_dveri, x.order ? 700 : 1400), toJpeg(photos.fotka_zarubne, x.order ? 700 : 1400)]).then(function (im) {
        var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        doc.addFileToVFS('Inter-Regular.ttf', fonts[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
        doc.addFileToVFS('Inter-Bold.ttf', fonts[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
        var W = 210, M = 14, y, page = 1;
        var font = function (st, sz, col) { doc.setFont('Inter', st); doc.setFontSize(sz); doc.setTextColor.apply(doc, col || [15, 42, 28]); };

        /* hlavička */
        doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
        if (im[0]) { var lh = 16, lw = lh * im[0].w / im[0].h; doc.addImage(im[0].data, 'JPEG', M, 7, lw, lh); }
        font('bold', 15, [255, 255, 255]); doc.text(O ? 'Objednávka bezpečnostních dveří' : 'Poptávka bezpečnostních dveří', W - M, 13, { align: 'right' });
        if (O) {
          font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + d.obj_cislo + '  ·  ' + (d.obj_datum ? new Date(d.obj_datum).toLocaleDateString('cs-CZ') : new Date(d.odeslano).toLocaleDateString('cs-CZ')), W - M, 19.5, { align: 'right' });
          doc.text('Prodavač: ' + (d.prodavac || '–') + (d.z_poptavky ? '  ·  z poptávky ' + d.z_poptavky : ''), W - M, 24.5, { align: 'right' });
        } else {
          font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + d.cislo + '  ·  ' + new Date(d.odeslano).toLocaleString('cs-CZ'), W - M, 19.5, { align: 'right' });
          doc.text('Odesláno z webu bezpecne-dvere', W - M, 24.5, { align: 'right' });
        }
        y = 40;
        if (O) {
          font('normal', 7.5, MUTED); var zl = doc.splitTextToSize(ZHOTOVITEL, W - 2 * M); doc.text(zl, M, 36); y = 36 + zl.length * 3.2 + 1.5;
          font('bold', 8, GREEN); doc.text(NORMA, M, y); y += 7;
        }
        function footer() {
          font('normal', 7.5, MUTED);
          if (O) { doc.text('Revize 10/2026 · Objednávka č. ' + d.obj_cislo + ' · strana ' + page, M, 289); doc.text('Dotazy: chci@bezpecne-dvere.cz · +420 725 559 235', W - M, 289, { align: 'right' }); }
          else doc.text('Bezpečné dveře · Zdeněk Kučera, IČ 64682676 · vzorkovna Obchodní 36, Most · +420 725 559 235 · chci@bezpecne-dvere.cz', W / 2, 289, { align: 'center' });
        }
        function ensure(h) { if (y + h > 280) { footer(); doc.addPage(); page++; y = 18; } }

        function section(t) {
          ensure(16);
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
        var yl = pairs([['Jméno', (d.jmeno + ' ' + d.prijmeni).trim()], ['Telefon', d.telefon], ['E-mail', d.email]].concat(O ? [['Číslo OP', d.op ? d.op + ' (pro likvidaci odpadu)' : ''], ['Zvonek', d.zvonek]] : []), M, 22, 66);
        var bill = d.fakturace;
        if (d.fakturace && d.fakturace.indexOf('Stejná') !== 0) bill = d.fak_jmeno + '\n' + d.fak_adresa + (d.ic ? '\nIČ ' + d.ic + (d.platce_dph ? ', plátce DPH' : '') : '');
        var yr = pairs([['Montáž', adr], ['Patro', d.patro == null ? '' : (d.patro === 0 ? 'přízemí' : d.patro + '. patro, ' + (d.vytah === 'Ano' ? 's výtahem' : 'bez výtahu'))], ['Fakturace', bill]], 108, 22, 66);
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
        if (O) spec.push(['Tl. zárubně', d.tl_zarubne], ['Zeď', [d.zed, d.tl_zdi ? 'tloušťka ' + d.tl_zdi + ' cm' : ''].filter(Boolean).join(', ')]);
        var yd = pairs(spec, M + imgW + 8, 22, W - M - (M + imgW + 8) - 22);
        y = Math.max(yd, sy + 18) + 3;

        /* cena */
        section(O ? 'Cena' : 'Orientační cena');
        var cx = M, cw = W - 2 * M;
        var priceRow = function (l, v, bold) { font(bold ? 'bold' : 'normal', bold ? 10 : 9.5, bold ? [15, 42, 28] : MUTED); doc.text(l, cx, y); doc.text(v, cx + cw, y, { align: 'right' }); y += 5.4; };
        priceRow('Dveře ' + d.model + ' s montáží na klíč' + (O && d.cena_zakladni_bezna > d.cena_zaklad ? ' (běžná cena ' + fmt(d.cena_zakladni_bezna) + ', akční cena)' : ''), fmt(d.cena_zaklad));
        (d.polozky || []).forEach(function (p) { if (p.priplatek || p.info) { ensure(6); priceRow(p.co + ': ' + p.volba, p.priplatek > 0 ? '+ ' + fmt(p.priplatek) : p.priplatek < 0 ? '– ' + fmt(-p.priplatek) : p.info); } });
        ensure(14);
        y += 1;
        doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(cx, y - 4.5, cw, 12, 2, 2, 'FD');
        font('bold', 11, GREEN); doc.text('Celkem vč. 12 % DPH', cx + 4, y + 3);
        font('bold', 15, ORANGE); doc.text(fmt(d.cena_celkem), cx + cw - 4, y + 3.4, { align: 'right' });
        y += 12;
        font('normal', 8, MUTED);
        var noteL = O ? doc.splitTextToSize('DPH bude vyčísleno na konečné faktuře. Cena zahrnuje dodávku, montáž na klíč, demontáž a odvoz starých dveří a hrubý úklid.' + (d.doprava_km != null ? ' Vzdálenost od vzorkovny Obchodní 36, Most: ' + String(d.doprava_km).replace('.', ',') + ' km.' : ''), cw) : doc.splitTextToSize('Cena zahrnuje montáž na klíč, odvoz starých dveří a hrubý úklid. Doprava do 40 km zdarma, 41–80 km 1 200 Kč, dál dle dohody' + (d.doprava_km != null ? ' (spočítáno ' + String(d.doprava_km).replace('.', ',') + ' km po silnici od vzorkovny Obchodní 36, Most)' : '') + '. ' + (x.priceNote || '') + ' Nezávazná poptávka, konečnou cenu potvrdí prodejce.', cw); doc.text(noteL, cx, y);
        y += noteL.length * 3.4 + 5;

        /* bonus: aplikace Moje dveře v ceně (od 1. 11. 2026; náhled: localStorage bd-bonus = 1) */
        var bonusOn = Date.now() >= new Date('2026-11-01T00:00:00+01:00').getTime();
        try { if (localStorage.getItem('bd-bonus') === '1') bonusOn = true; } catch (e) {}
        if (bonusOn) {
          var regB = (d.model_kod === 'premium60' || d.model_kod === 'extreme54' || d.model_kod === 'magnum56' || d.model_kod === 'securido65') && !/Ano,\s*na/.test(String(d.zaruka || '')), bh0 = regB ? 15 : 11;
          ensure(bh0 + 4);
          doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(M, y - 3.5, W - 2 * M, bh0, 2, 2, 'FD');
          var ix = M + 4, iy = y - 1.2;
          doc.setDrawColor.apply(doc, GREEN); doc.setLineWidth(0.4); doc.roundedRect(ix, iy, 6, 9.5, 1, 1, 'S'); doc.roundedRect(ix + 1.6, iy + 1.6, 2.8, 5.2, 0.3, 0.3, 'S');
          doc.setFillColor.apply(doc, GREEN); doc.circle(ix + 3.9, iy + 4.3, 0.3, 'F'); doc.setLineWidth(0.2);
          font('bold', 9.5, GREEN); doc.text('V ceně: aplikace Moje dveře', M + 14, y + 0.6);
          font('normal', 8, MUTED); doc.text('Přehled dveří a záruky, návod na údržbu a vlastní servis, hlášení závady, roční kontrola.', M + 14, y + 4.9);
          if (regB) doc.text('Registrací dveří v aplikaci +6 měsíců a recenzí dalších +6 měsíců záruky.', M + 14, y + 8.8);
          y += bh0 + 2;
        }

        if (O) {
          /* platba a termín */
          section('Platba a termín');
          var pay = (d.zaloha > 0 && window.BDPlatba) ? window.BDPlatba.info(d.obj_cislo || d.cislo, d.zaloha) : null, y0 = y;
          y = pairs([[d.zaloha_label || 'Záloha', fmt(d.zaloha)],
            pay ? ['Platba ' + String(d.zaloha_label || 'zálohy').toLowerCase().replace(/^záloha$/, 'zálohy').replace(/^rezervace$/, 'rezervace'), 'účet ' + pay.ucet + (pay.vs ? ', VS ' + pay.vs : '') + ' (nebo QR vpravo)'] : null,
            ['Doplatek', fmt(d.doplatek) + ' po montáži HOTOVĚ'],
            ['Termín montáže', (d.termin ? 'odhad ' + d.termin + ', ' : '') + 'přesný termín dohodneme telefonicky s týdenním předstihem']].filter(Boolean), M, 34, cw - 34 - (pay ? 30 : 0)) + 1;
          if (pay) { try { doc.addImage(window.BDPlatba.png(pay.spd, 300), 'PNG', W - M - 27, y0 - 2, 27, 27); font('normal', 6.5, MUTED); doc.text('QR platba', W - M - 13.5, y0 + 27, { align: 'center' }); } catch (e) {} y = Math.max(y, y0 + 29); }
          if (d.poznamka) { section('Poznámka'); font('normal', 9.5); var pl = doc.splitTextToSize(d.poznamka, cw); ensure(pl.length * 4.4); doc.text(pl, M, y); y += pl.length * 4.4 + 3; }
          /* fotky z poptávky (malé) */
          var fit = x.fit || { photoH: 52, legal: 7.4 };
          var phs = [[im[3], 'Stávající dveře zvenku'], [im[4], 'Stávající zárubeň']].filter(function (p) { return p[0]; });
          if (phs.length && fit.photoH) {
            section('Fotky z poptávky (původní stav)');
            ensure(fit.photoH + 6);
            var bw = (cw - 6) / 2, bh = fit.photoH;
            phs.forEach(function (p, i) {
              var bx = M + i * (bw + 6);
              var r3 = Math.min(bw / p[0].w, bh / p[0].h), pw3 = p[0].w * r3, ph3 = p[0].h * r3;
              doc.addImage(p[0].data, 'JPEG', bx, y, pw3, ph3);
              font('normal', 7.5, MUTED); doc.text(p[1], bx, y + bh + 3.6);
            });
            y += bh + 9;
          }
          /* právní ujednání */
          section('Smluvní ujednání');
          font('normal', fit.legal, [40, 60, 50]);
          LEGAL.forEach(function (par) {
            var ll = doc.splitTextToSize(par, cw);
            var lh2 = fit.legal * 0.425;
            ll.forEach(function (line) { ensure(lh2 + 0.2); doc.text(line, M, y); y += lh2; });
            y += 1.6;
          });
          /* podpisy */
          ensure(38); y += 6;
          font('normal', 9, MUTED); doc.text('Dne ' + (d.obj_datum ? new Date(d.obj_datum).toLocaleDateString('cs-CZ') : ''), M, y); y += 16;
          doc.setDrawColor(120, 140, 130); doc.setLineWidth(0.3);
          doc.line(M, y, M + 75, y); doc.line(W - M - 75, y, W - M, y); y += 4.5;
          font('bold', 9); doc.text('Závazně objednávám – objednatel', M, y); doc.text('Zhotovitel', W - M - 75, y); y += 4.2;
          font('normal', 8.5, MUTED); doc.text((d.jmeno + ' ' + d.prijmeni).trim(), M, y);
          doc.text(['Zdeněk Kučera, KK SYSTEM', 'Zd. Štěpánka 2764/1, 434 01 Most', '+420 725 559 235, +420 471 008 994'], W - M - 75, y);
          footer();
          return { blob: doc.output('blob'), pages: doc.getNumberOfPages() };
        }

        /* doplňující */
        var extra = [['Zeď', d.zed], ['Výška k nadpraží', d.vyska_cm ? d.vyska_cm + ' cm' : ''], ['Poznámka', d.poznamka], ['Fotky', (im[3] || im[4]) ? 'v příloze na další straně' : '']];
        if (extra.some(function (e) { return e[1]; })) { section('Doplňující informace'); y = pairs(extra, M, 34, cw - 34) + 2; }

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

  /* objednávka: vždy max. 2 strany – když se nevejde, zmenší fotky a písmo smluvních ujednání, nakonec fotky vynechá */
  function buildOrder(d, x) {
    x = x || {}; x.order = true;
    var tries = [{ photoH: 52, legal: 7.4 }, { photoH: 38, legal: 7.0 }, { photoH: 28, legal: 6.6 }, { photoH: 0, legal: 6.6 }];
    function attempt(i) {
      x.fit = tries[i];
      return build(d, x).then(function (res) { return res.pages <= 2 || i === tries.length - 1 ? res.blob : attempt(i + 1); });
    }
    return attempt(0);
  }
  /* ── předávací list: vyplní prodejce/predavaci-list.pdf údaji z objednávky (pdf-lib, vektorově, jen pro prodejce) ── */
  var plReady = null;
  function preparePL() {
    if (plReady) return plReady;
    plReady = (window.PDFLib ? Promise.resolve() : loadScript(BASE + 'pdf-lib.min.js'))
      .then(function () { return window.fontkit ? null : loadScript(BASE + 'fontkit.umd.min.js'); })
      .then(function () {
        return Promise.all([fetch(new URL('../prodejce/predavaci-list.pdf', BASE).href), fetch(BASE + 'Inter-Bold.ttf'), fetch(BASE + 'Inter-Regular.ttf')])
          .then(function (rs) { rs.forEach(function (r) { if (!r.ok) throw new Error('pl'); }); return Promise.all(rs.map(function (r) { return r.arrayBuffer(); })); });
      });
    plReady.catch(function () { plReady = null; });
    return plReady;
  }
  function keysText(d) {
    var k = d.model_kod, extra = 0, base = '';
    (d.polozky || []).forEach(function (p) { if (p.co === 'Klíče navíc') extra = parseInt(p.volba, 10) || 0; });
    var tail = extra ? ' (vč. ' + extra + ' navíc)' : '';
    if (k === 'premium60') return extra ? (5 + extra) + ' + 2 instalační' + tail : '5 + 2 instalační';
    if (k === 'extreme54') {
      var n = /vrtulk/i.test(JSON.stringify(d.polozky || [])) ? 5 : 3;
      return (n + extra) + tail;
    }
    if (k === 'magnum56') {
      var m = /dole i nahoře/.test(d.vlozky || '') ? '7 (sjednocené)' : /vrtulkou nahoře/.test(d.vlozky || '') ? '5 (sjednocené)' : '2 × 5';
      return extra ? m + ' + ' + extra + ' navíc' : m;
    }
    if (k === 'securido65') return extra ? (6 + extra) + ' + 2 montážní' + tail : '6 + 2 montážní';
    return '';
  }
  /* o (volitelné, online předání s podpisem): { datum, zarukaDo (YYYY-MM-DD), montaznik, vyhrady, podpisZak, podpisZhot (PNG data URL) } */
  function buildHandover(d, o) {
    o = o || null;
    return preparePL().then(function (b) {
      var L = window.PDFLib;
      return L.PDFDocument.load(b[0]).then(function (pdf) {
        pdf.registerFontkit(window.fontkit);
        return Promise.all([pdf.embedFont(b[1], { subset: true }), pdf.embedFont(b[2], { subset: true })]).then(function (f) {
          var page = pdf.getPages()[0], H = page.getHeight(), col = L.rgb(0.07, 0.24, 0.15);
          // text do pole: x, baseline (y od horního okraje v pt), max. šířka – písmo se zmenší, aby se vešlo
          function put(text, x, yTop, maxW, size, regular) {
            if (!text) return;
            var fnt = regular ? f[1] : f[0], sz = size || 9.5;
            while (sz > 6 && fnt.widthOfTextAtSize(text, sz) > maxW) sz -= 0.25;
            while (fnt.widthOfTextAtSize(text, sz) > maxW && text.length > 3) text = text.slice(0, -2) + '…';
            page.drawText(text, { x: x, y: H - yTop, size: sz, font: fnt, color: col });
          }
          // nadpis „PŘEDÁVACÍ LIST“ → „PŘEDÁVACÍ A ZÁRUČNÍ LIST“ (přemaže původní nápis vpravo nahoře) + věta o záručním listu nad poli pro podpisy
          page.drawRectangle({ x: 440, y: H - 47, width: 140, height: 14, color: L.rgb(1, 1, 1) });
          (function () { var t = 'PŘEDÁVACÍ A ZÁRUČNÍ LIST', sz = 7.5, w = f[0].widthOfTextAtSize(t, sz); page.drawText(t, { x: 559 - w, y: H - 43.5, size: sz, font: f[0], color: L.rgb(0.07, 0.24, 0.15) }); })();
          put('Tento list slouží zároveň jako záruční list.', 51, 719, 400, 8, false);
          var prov = [d.model, d.odstin].filter(Boolean).join(' · ');   // jen model a barva
          put(prov, 136, 216.5, 148);                    // Model a provedení
          put(d.obj_cislo, 389, 216.5, 154);             // Číslo objednávky
          put(keysText(d), 103, 267.5, 181);             // Počet klíčů
          var adr = [d.ulice, [d.psc, d.mesto].filter(Boolean).join(' ')].filter(Boolean).join(', ');
          put(adr, 51, 754, 150, 8, true);               // Místo (datum doplní montážník)
          var sigsDone = Promise.resolve();
          if (o) {
            var cz = function (v) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || ''); return m ? (+m[3]) + '. ' + (+m[2]) + '. ' + m[1] : ''; };
            put(cz(o.datum), 136, 242, 148);                               // Datum předání
            put(cz(o.zarukaDo), 398, 242, 148);                            // Základní záruka do
            put(o.montaznik, 398, 267.5, 148);                             // Předal (montážník)
            put(o.vyhrady, 150, 731, 395, 8.5, true);                      // Výhrady
            if (o.datum) { page.drawRectangle({ x: 49, y: H - 757.5, width: 160, height: 12, color: L.rgb(1, 1, 1) }); put([d.mesto, cz(o.datum)].filter(Boolean).join(', '), 51, 754, 150, 8, true); }
            var sigs = [];
            function sig(url, x, w) { if (!url) return; sigs.push(pdf.embedPng(url).then(function (im) {
              var sc = Math.min(w / im.width, 28 / im.height); page.drawImage(im, { x: x + (w - im.width * sc) / 2, y: H - 757.5, width: im.width * sc, height: im.height * sc }); })); }
            sig(o.podpisZhot, 222, 150); sig(o.podpisZak, 393, 150);
            sigsDone = Promise.all(sigs);
          }
          put((d.jmeno + ' ' + d.prijmeni).trim(), 392, 782, 150, 8, true);   // pod „Objednatel (podpis)“
          put('Objednávka ' + d.obj_cislo + (d.prodavac ? ' · prodavač ' + d.prodavac : '') + (d.telefon ? ' · tel. ' + d.telefon : ''), 49.5, 290, 330, 7, true);
          // bonus: aplikace Moje dveře v ceně (od 1. 11. 2026; náhled: localStorage bd-bonus = 1)
          (function () {
            var on = Date.now() >= new Date('2026-11-01T00:00:00+01:00').getTime();
            try { if (localStorage.getItem('bd-bonus') === '1') on = true; } catch (e) {}
            if (!on) return;
            var t = 'V ceně dveří: aplikace Moje dveře', sz = 7.5, w = f[0].widthOfTextAtSize(t, sz);
            page.drawText(t, { x: 539 - w, y: H - 290, size: sz, font: f[0], color: L.rgb(0.07, 0.24, 0.15) });
          })();
          // QR „Moje dveře“ (záruka, servis, registrace) pro zákazníka: nahradí v kartě „Zaregistrujte své dveře“ původní QR registrace
          var pm = (d.zakaznik_token && window.BDPlatba && window.BDPlatba.mojeUrl)
            ? pdf.embedPng(window.BDPlatba.png(window.BDPlatba.mojeUrl(d.zakaznik_token), 600)).then(function (qimg) {
                page.drawRectangle({ x: 49, y: H - 602, width: 74, height: 74, color: L.rgb(1, 1, 1) });
                page.drawImage(qimg, { x: 49, y: H - 602, width: 74, height: 74 });
              })
            : Promise.resolve();
          return pm.then(function () {
          // QR „předání díla“ pro montážníka: malý, v pravém dolním rohu 1. strany (po načtení v přihlášené kartotéce zapíše předání, začátek záruky a odepíše dveře ze skladu). Zákazník se přes něj bez přihlášení nedostane. List se tiskne jen na 1 stranu.
          if (d.predani_token && window.BDPlatba && window.BDPlatba.predaniUrl) {
            return pdf.embedPng(window.BDPlatba.png(window.BDPlatba.predaniUrl(d.predani_token), 600)).then(function (img) {
              var Q = 56, qx = 516, qTop = 770;
              page.drawRectangle({ x: qx - 5, y: H - (qTop + Q + 6), width: Q + 10, height: Q + 18, color: L.rgb(1, 1, 1) });
              page.drawText('PŘEDÁVACÍ QR', { x: qx + (Q - f[0].widthOfTextAtSize('PŘEDÁVACÍ QR', 5.5)) / 2, y: H - (qTop - 3), size: 5.5, font: f[0], color: L.rgb(0.45, 0.45, 0.45) });
              page.drawImage(img, { x: qx, y: H - (qTop + Q), width: Q, height: Q });
              return sigsDone.then(function () { return pdf.save(); });
            });
          }
          return sigsDone.then(function () { return pdf.save(); });
          });
        });
      });
    }).then(function (bytes) { return new Blob([bytes], { type: 'application/pdf' }); });
  }

  /* ── voucher Doporuč a získej: jméno zákazníka do „Doporučuje“ na všech 3 voucherech (prodejce/voucher-doporuc-a-ziskej.pdf) ── */
  var VOUCHER_PLATI_DO = '2026-12-31';   // akce platí do 31. 12. 2026 nebo do odvolání – po tomto dni se voucher nepřikládá
  var VOUCHER_RADKY = [79.8, 360.3, 641.5];   // yMin popisku „Doporučuje“ na 1.–3. voucheru (pt od horního okraje)
  function voucherActive() { return new Date().toISOString().slice(0, 10) <= VOUCHER_PLATI_DO; }
  function buildVoucher(d) {
    if (!voucherActive()) return Promise.resolve(null);
    return preparePL().then(function (b) {
      return fetch(new URL('../prodejce/voucher-doporuc-a-ziskej.pdf', BASE).href).then(function (r) { if (!r.ok) throw new Error('voucher'); return r.arrayBuffer(); })
        .then(function (vb) {
          var L = window.PDFLib;
          return L.PDFDocument.load(vb).then(function (pdf) {
            pdf.registerFontkit(window.fontkit);
            return pdf.embedFont(b[1], { subset: true }).then(function (fnt) {
              var page = pdf.getPages()[0], H = page.getHeight(), name = (d.jmeno + ' ' + d.prijmeni).trim();
              if (!name) return pdf.save();
              var sz = 10.5; while (sz > 7 && fnt.widthOfTextAtSize(name, sz) > 280) sz -= 0.25;
              VOUCHER_RADKY.forEach(function (yTop) { page.drawText(name, { x: 268, y: H - (yTop + 8.6), size: sz, font: fnt, color: L.rgb(0.07, 0.24, 0.15) }); });
              return pdf.save();
            });
          });
        });
    }).then(function (bytes) { return bytes ? new Blob([bytes], { type: 'application/pdf' }) : null; });
  }

  window.PoptavkaPDF = { LEGAL: LEGAL, prepare: prepare, build: build, buildOrder: buildOrder, buildHandover: buildHandover, buildVoucher: buildVoucher, voucherActive: voucherActive, preparePL: preparePL };
})();
