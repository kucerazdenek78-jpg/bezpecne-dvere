/* Cenová nabídka (prodejce): jedna kategorie dveří, libovolně položek, sleva na položku i celková.
   Odeslání: server formulare.dvere.app – typ 'nabidka' (interně, kartotéka) + 'objednavka_zakaznik' (e-mail zákazníkovi s PDF). */
(function () {
  'use strict';
  var KAT = { byt: 'Dveře do bytu', dum: 'Dveře do domu', interier: 'Interiérové dveře' };
  var PRODAVACI = ['Kučerová', 'Kučera Zd.'];
  var CC = 'chci@bezpecne-dvere.cz';
  var KATALOG = window.NB_KATALOG || [];          // dveře do bytu: modely a odstíny z formuláře poptávky
  var form = document.getElementById('nb');
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var kc = function (n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; };
  var int = function (v) { var x = parseInt(String(v == null ? '' : v).replace(/\s/g, ''), 10); return isNaN(x) ? 0 : x; };
  var st = { kat: '', rows: [] };   // rows: {model, odstin, nazev, popis, ks, cena, sleva}
  var fv = function (n) { return form.elements[n] ? String(form.elements[n].value).trim() : ''; };

  function katMod(k) { var r = null; KATALOG.forEach(function (m) { if (m.k === k) r = m; }); return r; }
  function nextNumber() { var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); }; return 'NAB-' + t.getFullYear() + '-' + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()); }

  /* ── kategorie ── */
  function renderKat() {
    $('#nbKat').innerHTML = Object.keys(KAT).map(function (k) {
      return '<label class="pq-opt pq-chip"><input type="radio" name="kat" value="' + k + '"' + (st.kat === k ? ' checked' : '') + (st.rows.length && st.kat !== k ? ' disabled' : '') + '>' + KAT[k] + '</label>';
    }).join('');
    $('#nbKatHint').textContent = st.rows.length ? 'Kategorii nelze změnit, dokud jsou v nabídce položky. Pro jinou kategorii vytvořte samostatnou nabídku.' :
      (st.kat === 'byt' ? 'Dveře do bytu: výběr jako v poptávce (obrázky, barvy, kování, příplatky); cena se spočítá sama.' : st.kat ? 'U této kategorie zadáváte položky ručně (název, popis, počet, cena).' : 'Nejdřív vyberte kategorii.');
    $('#nbAdd').disabled = !st.kat;
  }
  /* ── položky ── */
  function newRow() { return { model: '', odstin: '', cfg: null, nazev: '', popis: '', ks: 1, cena: 0, sleva: 0 }; }
  function modelPrice(r) {
    var m = katMod(r.model); if (!m) return 0; var pl = 0; m.shades.forEach(function (s) { if (s.n === r.odstin) pl = s.pl; }); return m.price + pl;
  }
  function renderRows() {
    $('#nbRows').innerHTML = st.rows.map(function (r, i) {
      var byt = st.kat === 'byt';
      var sel = byt ? '<p style="margin:.6rem 0"><button type="button" class="btn btn-primary" data-pick="' + i + '">' + (r.model ? 'Změnit výběr dveří' : '🚪 Vybrat dveře (jako v poptávce)') + '</button> ' +
        (r.model ? '<small>Výběr: <b>' + esc(r.nazev) + '</b>. Název, popis i cenu můžete níže upravit.</small>' : '<small>Otevře se výběr s obrázky, barvami, kováním a příplatky. Cena se spočítá sama.</small>') + '</p>' : '';
      var tot = r.ks * r.cena - r.sleva;
      return '<div class="pq-block-in" style="border:1.5px solid var(--gray-200);border-radius:12px;padding:1rem;margin-bottom:.8rem"><strong>Položka ' + (i + 1) + '</strong> ' +
        '<button type="button" class="btn btn-outline" data-del="' + i + '" style="float:right;padding:.3rem .8rem">Odebrat</button>' + sel +
        '<label class="pq-f">Název *<input data-i="' + i + '" data-f="nazev" value="' + esc(r.nazev) + '" placeholder="' + (byt ? 'doplní se z modelu a odstínu' : 'např. název dveří / modelu') + '"></label>' +
        '<label class="pq-f">Popis (barva, rozměr, provedení, kování …)<input data-i="' + i + '" data-f="popis" value="' + esc(r.popis) + '"></label>' +
        '<div class="pq-grid2"><label class="pq-f">Počet kusů<input data-i="' + i + '" data-f="ks" inputmode="numeric" value="' + r.ks + '"></label>' +
        '<label class="pq-f">Cena za kus vč. DPH (Kč)<input data-i="' + i + '" data-f="cena" inputmode="numeric" value="' + (r.cena || '') + '"></label>' +
        '<label class="pq-f">Sleva na položku (Kč)<input data-i="' + i + '" data-f="sleva" inputmode="numeric" value="' + (r.sleva || '') + '"></label></div>' +
        '<p style="margin:.4rem 0 0" data-tot="' + i + '">Za položku: <b>' + kc(tot) + '</b></p></div>';
    }).join('');
    sums();
  }
  function totals() {
    var sub = 0; st.rows.forEach(function (r) { sub += r.ks * r.cena - r.sleva; });
    var sl = int(fv('sleva_kc')); return { sub: sub, sleva: sl, total: Math.max(0, sub - sl) };
  }
  function sums() {
    var t = totals();
    $('#nbSum').innerHTML = st.rows.length ? 'Mezisoučet ' + kc(t.sub) + (t.sleva ? ' – celková sleva ' + kc(t.sleva) : '') + ' → <b>celkem ' + kc(t.total) + '</b> vč. DPH' : '';
    st.rows.forEach(function (r, i) { var e = document.querySelector('[data-tot="' + i + '"]'); if (e) e.innerHTML = 'Za položku: <b>' + kc(r.ks * r.cena - r.sleva) + '</b>'; });
  }
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'kat') { st.kat = t.value; renderKat(); return; }
  });
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t.dataset && t.dataset.f && t.dataset.f !== 'model' && t.dataset.f !== 'odstin') {
      var r = st.rows[+t.dataset.i], f = t.dataset.f; r[f] = (f === 'ks' || f === 'cena' || f === 'sleva') ? int(t.value) : t.value; sums();
    } else if (t.name === 'sleva_kc') sums();
  });
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t.id === 'nbAdd') { st.rows.push(newRow()); renderKat(); renderRows(); if (st.kat === 'byt') openPick(st.rows.length - 1); }
    if (t.dataset && t.dataset.pick != null && t.dataset.pick !== '') openPick(+t.dataset.pick);
    if (t.dataset && t.dataset.del != null && t.dataset.del !== '') { st.rows.splice(+t.dataset.del, 1); renderKat(); renderRows(); }
  });

  /* ── výběr dveří v okně (stejné obrázky a volby jako v poptávce; nabidka-vyber.html vrací výsledek přes postMessage) ── */
  function b64u(str) { var b = new TextEncoder().encode(str), s2 = ''; for (var i = 0; i < b.length; i++) s2 += String.fromCharCode(b[i]); return btoa(s2).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  var pickIdx = -1;
  function openPick(i) {
    pickIdx = i; var r = st.rows[i], cfg = r.cfg || (r.model ? { model_kod: r.model, odstin: r.odstin } : null);
    var ov = document.createElement('div'); ov.id = 'nbPick'; ov.style.cssText = 'position:fixed;inset:0;z-index:1000;background:rgba(10,30,20,.6);display:flex;flex-direction:column;padding:max(10px,env(safe-area-inset-top)) 10px 10px';
    ov.innerHTML = '<div style="background:#fff;border-radius:12px;flex:1;display:flex;flex-direction:column;overflow:hidden;max-width:1200px;width:100%;margin:0 auto"><div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border-bottom:1px solid #dce9e1"><strong>Výběr dveří – položka ' + (i + 1) + '</strong><button type="button" class="btn btn-outline" id="nbPickX" style="padding:.3rem .9rem">Zavřít</button></div>' +
      '<iframe title="Výběr dveří" style="flex:1;border:0;width:100%" src="nabidka-vyber.html' + (cfg ? '#d=' + b64u(JSON.stringify(cfg)) : '') + '"></iframe></div>';
    document.body.appendChild(ov); document.body.style.overflow = 'hidden';
    $('#nbPickX').onclick = closePick;
  }
  function closePick() { var o = $('#nbPick'); if (o) o.remove(); document.body.style.overflow = ''; pickIdx = -1; }
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || !e.data.bdPick || pickIdx < 0) return;
    var p = e.data.bdPick, r = st.rows[pickIdx]; if (!r) return;
    r.model = p.model_kod; r.odstin = p.odstin || ''; r.nazev = p.nazev; r.popis = p.popis; r.cena = p.cena; r.cfg = p.cfg;
    closePick(); renderKat(); renderRows();
  });

  /* ── data ── */
  function collect() {
    var t = totals(), d = {
      verze: 2, zdroj: 'web-nabidka', typ: 'nabidka', kategorie: st.kat, kategorie_nazev: KAT[st.kat],
      obj_cislo: fv('obj_cislo'), cislo: fv('obj_cislo'), obj_datum: fv('obj_datum'), nabidka_do: fv('nabidka_do'),
      prodavac: (document.querySelector('input[name="prodavac"]:checked') || {}).value || '',
      jmeno: fv('jmeno'), prijmeni: fv('prijmeni'), telefon: fv('telefon'), email: fv('email'), ulice: fv('ulice'), mesto: fv('mesto'), psc: fv('psc'),
      poznamka: fv('poznamka'), interni: fv('interni'), odeslano: new Date().toISOString(),
      sleva_celkem: t.sleva, sleva_text: fv('sleva_text'), mezisoucet: t.sub, cena_celkem: t.total,
      z_poptavky: st.fromRef || ''
    };
    d.pocet_kusu = st.rows.reduce(function (a, r) { return a + r.ks; }, 0);
    d.model = KAT[st.kat] + ': ' + st.rows.map(function (r) { return (r.ks > 1 ? r.ks + '× ' : '') + r.nazev; }).join(', ');
    d.polozky = st.rows.map(function (r) {
      return { co: r.nazev + (r.ks > 1 ? ' (' + r.ks + ' ks × ' + kc(r.cena) + ')' : ''), volba: [r.popis, r.sleva ? 'sleva ' + kc(r.sleva) : ''].filter(Boolean).join(' · '), priplatek: r.ks * r.cena - r.sleva,
        model_kod: r.model, odstin: r.odstin, cfg: r.cfg || null, popis: r.popis, nazev: r.nazev, ks: r.ks, cena_ks: r.cena, sleva: r.sleva };
    });
    if (t.sleva) d.polozky.push({ co: 'Celková sleva' + (d.sleva_text ? ': ' + d.sleva_text : ''), volba: '', priplatek: -t.sleva });
    return d;
  }
  function validate(d) {
    if (!st.kat) return 'Vyberte kategorii dveří.';
    if (!st.rows.length) return 'Přidejte aspoň jednu položku.';
    for (var i = 0; i < st.rows.length; i++) { var r = st.rows[i]; if (!r.nazev.trim()) return 'Položka ' + (i + 1) + ': doplňte název.'; if (r.ks < 1) return 'Položka ' + (i + 1) + ': počet kusů musí být aspoň 1.'; if (r.cena <= 0) return 'Položka ' + (i + 1) + ': doplňte cenu.'; }
    if (!d.jmeno || !d.prijmeni) return 'Doplňte jméno a příjmení zákazníka.';
    if (d.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) return 'E-mail zákazníka nevypadá správně.';
    if (!d.obj_cislo) return 'Doplňte číslo nabídky.';
    if (!d.prodavac) return 'Vyberte prodavače.';
    if (!$('#nbConsent').checked) return 'Potvrďte, že je nabídka zkontrolovaná.';
    return '';
  }

  /* ── PDF ── */
  function logo() {
    return new Promise(function (ok) {
      var img = new Image(); img.onload = function () {
        var s = Math.min(1, 600 / Math.max(img.naturalWidth, img.naturalHeight)), cv = document.createElement('canvas');
        cv.width = Math.round(img.naturalWidth * s); cv.height = Math.round(img.naturalHeight * s);
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(img, 0, 0, cv.width, cv.height);
        try { ok({ data: cv.toDataURL('image/jpeg', .85), w: cv.width, h: cv.height }); } catch (e) { ok(null); }
      }; img.onerror = function () { ok(null); }; img.src = 'logo.jpg';
    });
  }
  function buildPdf(d) {
    var P = window.PoptavkaPDF; if (!P || !P.prepare) return Promise.reject(new Error('pdf'));
    return Promise.all([P.prepare(), logo()]).then(function (res) {
      var fonts = res[0], lg = res[1];
      var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
      doc.addFileToVFS('Inter-Regular.ttf', fonts[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
      doc.addFileToVFS('Inter-Bold.ttf', fonts[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
      var W = 210, M = 14, GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], page = 1, y;
      var font = function (s, z, c) { doc.setFont('Inter', s); doc.setFontSize(z); doc.setTextColor.apply(doc, c || [15, 42, 28]); };
      var money = function (n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; };
      var dt = function (v) { return v ? new Date(v).toLocaleDateString('cs-CZ') : ''; };
      doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
      if (lg) { var lh = 16; doc.addImage(lg.data, 'JPEG', M, 7, lh * lg.w / lg.h, lh); }
      font('bold', 15, [255, 255, 255]); doc.text('Cenová nabídka – ' + KAT[d.kategorie].toLowerCase(), W - M, 13, { align: 'right' });
      font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + d.obj_cislo + '  ·  ' + dt(d.obj_datum || d.odeslano), W - M, 19.5, { align: 'right' });
      doc.text('Prodavač: ' + d.prodavac + (d.nabidka_do ? '  ·  platí do ' + dt(d.nabidka_do) : ''), W - M, 24.5, { align: 'right' });
      font('normal', 7.5, MUTED);
      var zl = doc.splitTextToSize('Zhotovitel: Zdeněk Kučera, Zd. Štěpánka 2764/1, Most. IČO: 64682676, DIČ: CZ7801242768. Vzorkovna: KK SYSTEM, Obchodní 36, Most, www.bezpecne-dvere.cz', W - 2 * M);
      doc.text(zl, M, 36); y = 36 + zl.length * 3.2 + 4;
      function footer() { font('normal', 7.5, MUTED); doc.text('Nabídka č. ' + d.obj_cislo + ' · strana ' + page, M, 289); doc.text('Dotazy: ' + CC + ' · +420 725 559 235', W - M, 289, { align: 'right' }); }
      function ensure(h) { if (y + h > 278) { footer(); doc.addPage(); page++; y = 18; } }
      font('bold', 9, ORANGE); doc.text('ZÁKAZNÍK', M, y); y += 5;
      font('bold', 10.5); doc.text((d.jmeno + ' ' + d.prijmeni).trim(), M, y); y += 4.8;
      font('normal', 9.5); var adr = [[d.ulice, d.psc, d.mesto].filter(Boolean).join(', '), [d.telefon, d.email].filter(Boolean).join(' · ')].filter(Boolean);
      adr.forEach(function (l) { doc.text(l, M, y); y += 4.4; }); y += 5;
      /* tabulka položek */
      var cx = { n: M, ks: 128, cena: 156, tot: W - M };
      function head() {
        doc.setFillColor(246, 250, 247); doc.rect(M, y - 4.5, W - 2 * M, 7, 'F'); font('bold', 8.5, GREEN);
        doc.text('POLOŽKA', cx.n + 1, y); doc.text('KS', cx.ks, y, { align: 'right' }); doc.text('CENA/KS', cx.cena, y, { align: 'right' }); doc.text('CELKEM', cx.tot - 1, y, { align: 'right' }); y += 6;
      }
      head();
      d.polozky.forEach(function (p, i) {
        if (p.ks == null) return;
        var nm = doc.splitTextToSize((i + 1) + '. ' + (p.co.replace(/ \(\d+ ks × .*\)$/, '')), 100);
        font('normal', 9); var ds = p.volba_popis ? [] : [];
        var popis = st.rows[i] ? st.rows[i].popis : '', pl = popis ? doc.splitTextToSize(popis, 100) : [];
        var h = nm.length * 4.4 + pl.length * 3.9 + (p.sleva ? 4.2 : 0) + 3;
        ensure(h + 8); if (y < 20) head();
        font('bold', 9.5); doc.text(nm, cx.n + 1, y);
        font('normal', 9.5); doc.text(String(p.ks), cx.ks, y, { align: 'right' }); doc.text(money(p.cena_ks), cx.cena, y, { align: 'right' });
        font('bold', 9.5); doc.text(money(p.ks * p.cena_ks - p.sleva), cx.tot - 1, y, { align: 'right' });
        var yy = y + nm.length * 4.4;
        if (pl.length) { font('normal', 8.5, MUTED); doc.text(pl, cx.n + 1, yy - 0.4); yy += pl.length * 3.9; }
        if (p.sleva) { font('normal', 8.5, ORANGE); doc.text('sleva na položku -' + money(p.sleva), cx.n + 1, yy - 0.4); yy += 4.2; }
        y = yy + 1.5; doc.setDrawColor(220, 233, 225); doc.setLineWidth(0.2); doc.line(M, y, W - M, y); y += 4;
      });
      ensure(30); y += 2;
      if (d.sleva_celkem) {
        font('normal', 9.5); doc.text('Mezisoučet', cx.cena - 20, y); doc.text(money(d.mezisoucet), cx.tot - 1, y, { align: 'right' }); y += 5;
        font('normal', 9.5, ORANGE); doc.text('Celková sleva' + (d.sleva_text ? ' (' + d.sleva_text + ')' : ''), cx.cena - 20, y); doc.text('-' + money(d.sleva_celkem), cx.tot - 1, y, { align: 'right' }); y += 6;
      }
      doc.setFillColor.apply(doc, GREEN); doc.rect(M, y - 5, W - 2 * M, 10, 'F');
      font('bold', 11, [255, 255, 255]); doc.text('CENA CELKEM vč. DPH', M + 3, y + 1.4); doc.text(money(d.cena_celkem), W - M - 3, y + 1.4, { align: 'right' }); y += 14;
      if (d.poznamka) { ensure(14); font('bold', 9, ORANGE); doc.text('POZNÁMKA', M, y); y += 5; font('normal', 9.5); var pn = doc.splitTextToSize(d.poznamka, W - 2 * M); ensure(pn.length * 4.4); doc.text(pn, M, y); y += pn.length * 4.4 + 3; }
      ensure(14); font('normal', 8.5, MUTED);
      var fin = doc.splitTextToSize('Nabídka platí do ' + (d.nabidka_do ? dt(d.nabidka_do) : 'odvolání') + '. Je-li pro Vás vyhovující, ozvěte se nám a převedeme ji na objednávku.', W - 2 * M);
      doc.text(fin, M, y);
      footer();
      return doc.output('blob');
    });
  }

  /* ── odeslání ── */
  function fieldsTelo(list) { return window.BDForm.bodyFromFields(list); }
  function dec(b) { return String(b); }
  function customerList(d) {
    return [['_subject', 'Cenová nabídka ' + d.obj_cislo + ' – ' + KAT[d.kategorie].toLowerCase() + ' – Bezpečné dveře'],
      ['Dobrý den', 'děkujeme za zájem. V příloze posíláme cenovou nabídku v PDF' + (d.nabidka_do ? ' (platí do ' + new Date(d.nabidka_do).toLocaleDateString('cs-CZ') + ')' : '') + '. Pokud Vám vyhovuje, ozvěte se nám a převedeme ji na objednávku. Dotazy: ' + CC + ' nebo +420 725 559 235.'],
      ['Nabídka', d.obj_cislo], ['Celkem vč. DPH', kc(d.cena_celkem)]].concat(d.polozky.map(function (p, i) { return ['Položka ' + (i + 1), p.co + (p.volba ? ' – ' + p.volba : '') + ': ' + kc(p.priplatek)]; }));
  }
  function internalList(d) {
    return [['_subject', 'Nabídka ' + d.obj_cislo + ' (interní) – ' + KAT[d.kategorie] + ' – ' + d.prijmeni + (d.mesto ? ', ' + d.mesto : '')],
      ['Číslo nabídky', d.obj_cislo], ['Kategorie', KAT[d.kategorie]], ['Prodavač', d.prodavac], ['Z poptávky', d.z_poptavky || '–'], ['Interní poznámka', d.interni || '–'],
      ['Cena celkem', kc(d.cena_celkem)], ['data_json', JSON.stringify(d)]].concat(d.polozky.map(function (p, i) { return ['Položka ' + (i + 1), p.co + (p.volba ? ' – ' + p.volba : '') + ': ' + kc(p.priplatek)]; }));
  }
  function pick(list, k) { var r = ''; list.forEach(function (x) { if (x[0] === k) r = x[1]; }); return r; }
  function send(typ, list, files, ref, extra) {
    var o = { typ: typ, predmet: pick(list, '_subject'), telo: fieldsTelo(list), reply_to: pick(list, '_replyto'), cislo: ref, data_json: pick(list, 'data_json'), honey: form._honey.value, files: files || [] };
    for (var k in (extra || {})) o[k] = extra[k];
    return window.BDForm.send(o);
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#nbMsg'); msg.hidden = true;
    var d = collect(), err = validate(d);
    if (err) { msg.textContent = err; msg.hidden = false; return; }
    if (form._honey.value) return;
    var btn = $('#nbSubmit'); btn.disabled = true; btn.textContent = 'Připravuji PDF…';
    var pdf = null, custOk = true, fn = 'Nabidka-' + d.obj_cislo + '.pdf';
    buildPdf(d).catch(function () { return null; }).then(function (b) {
      pdf = b; btn.textContent = 'Odesílám…';
      var files = pdf ? [{ name: 'objednavka_pdf', file: fn, blob: pdf }] : [];
      var ix = d.email ? { reply_to: d.email } : {};
      return send('nabidka', internalList(d), files, d.obj_cislo, ix).catch(function () { return send('nabidka', internalList(d), [], d.obj_cislo, ix); });
    }).then(function () {
      if (!d.email) return;
      var files = pdf ? [{ name: 'objednavka_pdf', file: fn, blob: pdf }] : [];
      return send('objednavka_zakaznik', customerList(d), files, d.obj_cislo, { email_zakaznik: d.email })
        .catch(function () { custOk = false; return send('objednavka_zakaznik', customerList(d), [], d.obj_cislo, { email_zakaznik: d.email }); });
    }).then(function () {
      $('#nbRef').textContent = d.obj_cislo; var w = $('#nbWarn');
      if (!d.email) { w.textContent = 'Zákazník nemá vyplněný e-mail – nabídka odešla jen vám. PDF si vytiskněte a předejte mu ho.'; w.hidden = false; }
      else if (!custOk) { w.textContent = 'E-mail zákazníkovi odešel bez přiloženého PDF. Stáhněte si PDF a pošlete mu ho ručně.'; w.hidden = false; }
      if (pdf) { var u = URL.createObjectURL(pdf), a = $('#nbPdf'); a.href = u; a.download = fn; a.hidden = false; var pr = $('#nbPrint'); pr.hidden = false; pr.onclick = function () { var wn = window.open(u, '_blank'); if (wn) wn.focus(); }; }
      form.hidden = true; $('.pq-hero').hidden = true; $('#nbDone').hidden = false; window.scrollTo(0, 0);
    }).catch(function () {
      msg.textContent = 'Odeslání se nepodařilo – zkontrolujte připojení a zkuste to znovu.'; msg.hidden = false; btn.disabled = false; btn.textContent = 'Odeslat nabídku zákazníkovi';
    });
  });

  /* ── start ── */
  $('#nbSeller').innerHTML = PRODAVACI.map(function (p) { return '<label class="pq-opt pq-chip"><input type="radio" name="prodavac" value="' + esc(p) + '">' + esc(p) + '</label>'; }).join('');
  form.obj_cislo.value = nextNumber();
  form.obj_datum.value = new Date().toISOString().slice(0, 10);
  var nd = new Date(); nd.setDate(nd.getDate() + 14); form.nabidka_do.value = nd.toISOString().slice(0, 10);   // výchozí platnost 14 dní – lze změnit
  /* z poptávky: odkaz #d=<base64url JSON> (stejný jako pro objednávku) */
  (function () {
    var h = (location.hash.match(/[#&]d=([A-Za-z0-9_-]+)/) || [])[1]; if (!h) return;
    try {
      var t = h.replace(/-/g, '+').replace(/_/g, '/'); while (t.length % 4) t += '=';
      var bin = atob(t), u = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
      var o = JSON.parse(new TextDecoder().decode(u));
      ['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'poznamka'].forEach(function (n) { if (o[n] && form.elements[n]) form.elements[n].value = o[n]; });
      st.fromRef = o.cislo || '';
      var m = katMod(o.model_kod);
      if (m) { st.kat = 'byt'; var r = newRow(); r.model = m.k; r.odstin = o.odstin || ''; r.nazev = m.name + (r.odstin ? ' – ' + r.odstin : ''); r.cena = modelPrice(r) || m.price; r.cfg = {}; ['model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka'].forEach(function (k) { if (o[k]) r.cfg[k] = o[k]; }); st.rows.push(r); }
      var info = $('#nbLoaded'); info.hidden = false; info.innerHTML = '✓ Načteno z poptávky <b>' + esc(o.cislo || '') + '</b>. U položky klikněte „Změnit výběr dveří“ – výběr se otevře předvyplněný z poptávky a spočítá cenu včetně příplatků.';
    } catch (e) {}
  })();
  renderKat(); renderRows();
})();
