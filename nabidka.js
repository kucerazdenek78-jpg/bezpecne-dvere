/* Cenová nabídka (prodejce): jedna kategorie dveří, libovolně položek, sleva na položku i celková.
   Odeslání: server formulare.dvere.app – typ 'nabidka' (interně, kartotéka) + 'objednavka_zakaznik' (e-mail zákazníkovi s PDF). */
(function () {
  'use strict';
  var KAT = { byt: 'Dveře do bytu', dum: 'Dveře do domu', interier: 'Interiérové dveře' };
  var PRODAVACI = ['Kučerová', 'Kučera Zd.'];
  var CC = 'chci@bezpecne-dvere.cz';
  var KATALOG = window.NB_KATALOG || [];          // dveře do bytu: modely a odstíny z formuláře poptávky
  var form = document.getElementById('nb');
  var OBJ = form.getAttribute('data-mode') === 'objednavka';   // objednávka (interiér / dům) × cenová nabídka
  var KATS = OBJ ? ['dum', 'interier'] : ['byt', 'dum', 'interier'];
  var ZALOHA_PROC = 70;   // záloha u dveří na míru a interiérových dveří: 70 % z ceny zakázky
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var kc = function (n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; };
  var int = function (v) { var x = parseInt(String(v == null ? '' : v).replace(/\s/g, ''), 10); return isNaN(x) ? 0 : x; };
  var st = { kat: '', rows: [], rozp: false, rozpBuf: null, rozpName: '', rozpPages: 0 };   // rows: {model, odstin, nazev, popis, ks, cena, sleva}; rozp = rozpočet z jiného programu (PDF) místo položek
  var fv = function (n) { return form.elements[n] ? String(form.elements[n].value).trim() : ''; };

  /* dveře do bytu i do domu: jedny dveře na adresu (víc kusů jen u interiérových) */
  function limited() { return st.kat === 'byt' || st.kat === 'dum'; }
  function katMod(k) { var r = null; KATALOG.forEach(function (m) { if (m.k === k) r = m; }); return r; }
  function nextNumber() { var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); }; return 'NAB-' + t.getFullYear() + '-' + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()); }

  var VIZ = window.VIZ_DATA || { domovni: [], interier: [] };
  var DEF_ZAHRNUJE = 'Montáž na klíč, odvoz starých dveří a hrubý úklid. Doprava do 40 km zdarma, 41–80 km 1 200 Kč, dál dle dohody.';
  /* interiérové dveře: akční ceník 10/2026 (akciovy-katalog.pdf, str. 4, 7–10), komplet dveře + zárubeň, Kč BEZ DPH; rozsahy = tloušťka zdi (zárubně). Cena platí pro šířky 60–90 cm. */
  var INT_MONTAZ = 1800, INT_DPH_MONT = 12, INT_DPH_BEZ = 21;
  var R1 = ['80–180 mm', '180–300 mm'], R2 = ['75–179 mm', '180–299 mm'];
  var LF = { r: R2, v: { 'Dub evropský': [5650, 6250], 'Bílá': [5650, 6250], 'Dub popelavý': [5650, 6250], 'Kašmír matný': [6400, 7150], 'Světle šedá matná': [4900, 5550] } };
  var INT_CENIK = {
    'PATRAS': { r: R1, c: [5450, 6020] }, 'KAVALA': { r: R1, c: [5450, 6020] }, 'LARISA': { r: R1, c: [4300, 4850] },
    'FRÉZIA 1': { r: R1, c: [7000, 7550] }, 'FRÉZIA 9': { r: R1, c: [8800, 9200] }, 'NEMÉZIA 14': { r: R1, c: [7050, 7600] },
    'LAURENCIA 1': { r: R1, c: [6850, 7400] }, 'ALTAMURA 1': { r: ['80–195 mm', '195–315 mm'], c: [8050, 8400] }, 'ALTAMURA 1 – falcové': { r: R1, c: [4900, 5450] },
    'LINEA FORTE 5': LF, 'AVERSA 1': LF,
    'NORMA DECOR 1': { r: R2, v: { 'Dub evropský': [3850, 4450], 'Dub popelavý': [3850, 4450], 'Bílá': [4650, 5300], 'Světle šedá matná': [3900, 4450] } },
    'CPL 1.1': { r: ['75–180 mm', '180–300 mm'], c: [5650, 6000] }, 'DECOR P': { r: ['75–180 mm', '180–300 mm'], c: [4750, 5200] }, 'VERTE HOME G.4': { r: ['75–180 mm', '180–300 mm'], c: [6300, 6800] }
  };
  var INT_ZAHRNUJE = 'Montáž zárubně, osazení dveřního křídla a montáž kování do připraveného otvoru: ' + '1 800 Kč bez DPH za dveře, platí jen když dveře, zárubně i kování kupujete u nás. Vše ostatní se určí až po prohlídce a zaměření technikem. Doprava do 40 km zdarma, 41–80 km 1 200 Kč, dál dle dohody.';
  var DEF_PLATBA = 'Záloha se hradí po potvrzení objednávky, doplatek po montáži hotově.';
  var lastDef = { zahrnuje: '', platba: '' };

  /* ── kategorie ── */
  function setDefaults() {
    var z = form.elements.zahrnuje, p = form.elements.platba;
    var nz = st.kat === 'byt' ? DEF_ZAHRNUJE : st.kat === 'interier' ? INT_ZAHRNUJE : '', np = st.kat === 'byt' ? DEF_PLATBA : '';
    if (z && (z.value === '' || z.value === lastDef.zahrnuje)) z.value = nz;
    if (p && (p.value === '' || p.value === lastDef.platba)) p.value = np;
    lastDef.zahrnuje = nz; lastDef.platba = np;
  }
  function renderKat() {
    $('#nbKat').innerHTML = KATS.map(function (k) {
      return '<label class="pq-opt pq-chip"><input type="radio" name="kat" value="' + k + '"' + (st.kat === k ? ' checked' : '') + (st.rows.length && st.kat !== k ? ' disabled' : '') + '>' + KAT[k] + '</label>';
    }).join('');
    $('#nbKatHint').textContent = (limited() ? 'Na jednu adresu lze ' + (OBJ ? 'objednat' : 'nabídnout') + ' jedny dveře (' + (st.kat === 'byt' ? 'do bytu' : 'do domu') + '). Další dveře patří do samostatné ' + (OBJ ? 'objednávky' : 'nabídky') + '. ' : '') + (st.rows.length ? 'Kategorii nelze změnit, dokud jsou v nabídce položky. Pro jinou kategorii vytvořte samostatnou ' + (OBJ ? 'objednávku' : 'nabídku') + '.' :
      (st.kat === 'byt' ? 'Dveře do bytu: výběr jako v poptávce (obrázky, barvy, kování, příplatky); cena se spočítá sama. Do PDF jde karta dveří s obrázkem, barvou a všemi parametry.' :
        st.kat ? 'Vyberte model z katalogu (obrázek a odstíny se doplní sami) nebo zadejte vlastní dveře. Rozměr, zárubeň a kování doplňte u položky; cenu vč. DPH zadáváte ručně.' : 'Nejdřív vyberte kategorii.'));
    $('#nbAdd').disabled = !st.kat || (limited() && st.rows.length >= 1);
    renderMode();
  }

  /* ── rozpočet z jiného programu (PDF): jen dveře do domu a interiérové; místo položek se nahraje PDF ── */
  function rozpOk() { return st.kat === 'dum' || st.kat === 'interier'; }
  function defUvod(k) {
    return 'Dobrý den,\nděkujeme za Váš zájem. Na základě Vašeho požadavku jsme pro Vás připravili cenovou nabídku na ' + (k === 'interier' ? 'interiérové dveře' : 'vchodové dveře do domu') +
      '. Podrobný rozpis položek, rozměrů, provedení a cen najdete v přiloženém rozpočtu za touto stranou.\n' +
      'Jsme rodinná firma z Mostu a působíme od roku 1996. Dveře si můžete prohlédnout naživo v naší vzorkovně KK SYSTEM, Obchodní 36, Most. Nabídku Vám rádi vysvětlíme nebo upravíme podle Vašich přání.';
  }
  var lastUvod = '';
  function ensureRozpUI() {
    if ($('#nbRozpVolba')) return;
    var fs = $('#nbRows').parentNode, box = document.createElement('div');
    box.innerHTML = '<div id="nbRozpVolba" class="pq-chips" style="margin-bottom:1rem" hidden>' +
      '<label class="pq-opt pq-chip"><input type="radio" name="zadani" value="polozky" checked>Položky ručně</label>' +
      '<label class="pq-opt pq-chip"><input type="radio" name="zadani" value="rozpocet">Rozpočet z jiného programu (PDF)</label></div>' +
      '<div id="nbRozp" hidden>' +
      '<p class="pq-help" style="margin:0 0 .8rem">Nahrajte rozpočet z programu jako PDF. Zákazník dostane jedno PDF: naše úvodní strana (zákazník, předmět, cena, ' + (OBJ ? 'platba, smluvní ujednání a podpisy' : 'podmínky nabídky') + ') a za ní celý rozpočet.</p>' +
      '<label class="pq-f">Rozpočet (PDF) *<input type="file" id="nbRozpFile" accept="application/pdf,.pdf"></label>' +
      '<p class="pq-help" id="nbRozpInfo" style="margin:.3rem 0 .8rem"></p>' +
      '<div class="pq-grid2">' +
      '<label class="pq-f">Předmět ' + (OBJ ? 'objednávky' : 'nabídky') + ' (krátce, jde do PDF a kartotéky) *<input name="rozp_popis" placeholder="např. Interiérové dveře 5 ks vč. zárubní a kování"></label>' +
      '<label class="pq-f">Číslo rozpočtu v programu (volitelné)<input name="rozp_cislo"></label>' +
      '<label class="pq-f">Cena celkem vč. DPH (Kč) *<input name="rozp_cena" inputmode="numeric" placeholder="0"></label></div>' +
      (OBJ ? '' : '<label class="pq-f" style="margin-top:.8rem">Úvodní text nabídky (jde do PDF, lze upravit)<textarea name="uvod" rows="6"></textarea></label>') +
      '</div>';
    while (box.firstChild) fs.insertBefore(box.firstChild, fs.children[1] || null);
  }
  function renderMode() {
    ensureRozpUI();
    if (!rozpOk()) st.rozp = false;
    $('#nbRozpVolba').hidden = !rozpOk() || st.rows.length > 0;
    var r = document.querySelector('input[name="zadani"][value="' + (st.rozp ? 'rozpocet' : 'polozky') + '"]'); if (r) r.checked = true;
    $('#nbRozp').hidden = !st.rozp;
    $('#nbRows').hidden = st.rozp;
    $('#nbAdd').parentNode.hidden = st.rozp;
    var sl = form.elements.sleva_kc; if (sl) sl.closest('.pq-grid2').hidden = st.rozp;
    var u = form.elements.uvod;
    if (u && st.rozp && (u.value === '' || u.value === lastUvod)) { u.value = defUvod(st.kat); lastUvod = u.value; }
  }
  function rozpFile(f) {
    var info = $('#nbRozpInfo');
    st.rozpBuf = null; st.rozpName = ''; st.rozpPages = 0; info.textContent = '';
    if (!f) return;
    var fr = new FileReader();
    fr.onload = function () {
      var buf = fr.result, head = new Uint8Array(buf.slice(0, 5));
      if (String.fromCharCode.apply(null, head) !== '%PDF-') { info.textContent = '⚠ Soubor není PDF. Uložte rozpočet z programu jako PDF a nahrajte ho znovu.'; return; }
      st.rozpBuf = buf; st.rozpName = f.name;
      var mb = (f.size / 1048576).toFixed(1).replace('.', ','), warn = f.size > 8 * 1048576 ? ' ⚠ Soubor je velký – e-mail s přílohou nemusí odejít; zkuste rozpočet uložit menší (bez fotek / v nižší kvalitě).' : '';
      info.textContent = '✓ ' + f.name + ' · ' + mb + ' MB' + warn;
      var P = window.PoptavkaPDF;
      if (P && P.preparePL) P.preparePL().then(function () { return window.PDFLib.PDFDocument.load(buf, { ignoreEncryption: true }); })
        .then(function (doc) { st.rozpPages = doc.getPageCount(); info.textContent = '✓ ' + f.name + ' · ' + st.rozpPages + ' str. · ' + mb + ' MB' + warn; })
        .catch(function () { info.textContent = '✓ ' + f.name + ' · ' + mb + ' MB (počet stran se nepodařilo zjistit – PDF se přiloží jako samostatný soubor)' + warn; });
    };
    fr.readAsArrayBuffer(f);
  }

  /* ── položky ── */
  function newRow() { return { model: '', odstin: '', cfg: null, nazev: '', popis: '', ks: 1, cena: 0, sleva: 0, img: '', shadeImg: '', spec: [], cat: '', prov: '', hint: '', sirka: '', otev: '', zarub: '', tl: '', montaz: true, cenaRuc: false, calc: '', kov: '', imgData: '' }; }
  function modelPrice(r) {
    var m = katMod(r.model); if (!m) return 0; var pl = 0; m.shades.forEach(function (s) { if (s.n === r.odstin) pl = s.pl; }); return m.price + pl;
  }
  function catList() { return st.kat === 'interier' ? VIZ.interier : st.kat === 'dum' ? VIZ.domovni : []; }
  function catFind(name) { var r = null; catList().forEach(function (c) { if (c[0] === name) r = c; }); return r; }
  /* cena z akčního ceníku: komplet bez DPH + montáž 1 800 Kč bez DPH (volitelně) → cena za kus vč. DPH (s montáží 12 %, bez montáže 21 %) */
  function intZ(r) { return st.kat === 'interier' ? INT_CENIK[r.cat] : null; }
  function autoPrice(r) {
    r.calc = ''; var e = intZ(r); if (!e) return;
    var arr = e.v ? e.v[r.odstin] : e.c, ix = e.r.indexOf(r.tl);
    if (!arr || ix < 0) { r.calc = e.v && !r.odstin ? 'Vyberte odstín a tloušťku zárubně, cena se doplní z ceníku.' : 'Vyberte tloušťku zárubně, cena se doplní z ceníku.'; return; }
    var base = arr[ix], bez = base + (r.montaz ? INT_MONTAZ : 0), sz = r.montaz ? INT_DPH_MONT : INT_DPH_BEZ, vc = Math.round(bez * (1 + sz / 100));
    r.calc = 'Ceník: dveře se zárubní ' + kc(base) + ' bez DPH' + (r.montaz ? ' + montáž ' + kc(INT_MONTAZ) : '') + ' = ' + kc(bez) + ' bez DPH → ' + kc(vc) + ' vč. ' + sz + ' % DPH za kus.';
    if (!r.cenaRuc) r.cena = vc;
  }
  function sirkaHtml(r, i) {
    if (st.kat !== 'interier') return '<label class="pq-f">Šířka křídla (cm)<input data-i="' + i + '" data-f="sirka" value="' + esc(r.sirka) + '" placeholder="např. 80"></label>';
    var o = ['60', '70', '80', '90']; if (r.sirka && o.indexOf(r.sirka) < 0) o.push(r.sirka);
    return '<label class="pq-f">Šířka křídla (cm)<select data-i="' + i + '" data-f="sirka"><option value="">– vybrat –</option>' + o.map(function (x) { return '<option' + (x === r.sirka ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</select></label>';
  }
  function tlHtml(r, i) {
    if (st.kat !== 'interier') return '';
    var e = intZ(r);
    if (e) return '<label class="pq-f">Tloušťka zárubně (zdi)<select data-i="' + i + '" data-f="tl"><option value="">– vybrat –</option>' + e.r.map(function (x) { return '<option' + (x === r.tl ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</select></label>';
    return '<label class="pq-f">Tloušťka zárubně (mm)<input data-i="' + i + '" data-f="tl" value="' + esc(r.tl) + '" placeholder="např. 100–125"></label>';
  }
  function montHtml(r, i) {
    if (st.kat !== 'interier') return '';
    return '<p style="margin:.7rem 0 0"><label style="display:flex;gap:.5rem;align-items:flex-start"><input type="checkbox" data-i="' + i + '" data-f="montaz"' + (r.montaz ? ' checked' : '') + '> <span>Montáž zárubně, osazení křídla a montáž kování (+' + kc(INT_MONTAZ) + ' bez DPH za kus, do připraveného otvoru; jen když vše kupuje u nás)</span></label></p>' +
      (r.calc ? '<p class="pq-help" style="margin:.4rem 0 0">' + esc(r.calc) + (r.cenaRuc ? ' <button type="button" class="btn btn-outline" data-reprice="' + i + '" style="padding:.15rem .6rem">Použít cenu z ceníku</button>' : '') + '</p>' : '');
  }
  function applyCat(r, name) {
    r.cat = name; r.odstin = ''; r.shadeImg = ''; r.prov = ''; r.hint = ''; r.img = ''; r.tl = ''; r.cenaRuc = false; r.calc = '';
    if (!name || name === '__jiny') { r.prov = ''; return; }
    var c = catFind(name); if (!c) return;
    r.img = 'aplikace/' + c[1];
    if (st.kat === 'interier') { r.nazev = 'Interiérové dveře ' + c[0]; r.prov = c[2]; r.hint = 'Orientační cena z webu: ' + c[3] + ' (komplet se zárubní, bez kování, bez DPH).'; autoPrice(r); }
    else { r.nazev = c[2] || ('Vchodové dveře ' + c[0]); r.hint = 'Orientační cena z webu: ' + c[3] + '. Konečnou cenu vč. DPH doplňte níže.'; }
  }
  function rowSpec(r) {
    if (st.kat === 'byt') return r.spec || [];
    return [['Provedení', r.prov], ['Odstín / dekor', r.odstin], ['Šířka křídla', r.sirka ? r.sirka + (/cm/i.test(r.sirka) ? '' : ' cm') : ''], ['Otevírání', r.otev], ['Zárubeň', r.zarub], ['Tloušťka zárubně', st.kat === 'interier' && r.tl ? r.tl : ''], ['Kování', r.kov], ['Montáž', st.kat === 'interier' && r.montaz ? 'zárubně, osazení křídla a kování do připraveného otvoru – ' + kc(INT_MONTAZ) + ' bez DPH za kus' : '']]
      .filter(function (x) { return x[1]; }).map(function (x) { return [x[0], x[1], 0, '']; });
  }
  function fixSpec(sp) { return (sp || []).map(function (x) { var v = String(x[1] || ''); if (x[0] === 'Záruka') v = v.replace(/^Ne,\s*stačí\s*/i, 'základní ').replace(/^Ano,\s*/i, 'prodloužená: '); return [x[0], v, x[2] || 0, x[3] || '']; }); }
  function specTxt(sp) { return sp.map(function (x) { return x[0] + ': ' + x[1] + (x[2] > 0 ? ' (+' + kc(x[2]) + ')' : (x[3] ? ' (' + x[3] + ')' : '')); }).join('; '); }
  function renderRows() {
    $('#nbRows').innerHTML = st.rows.map(function (r, i) {
      var byt = st.kat === 'byt', tot = r.ks * r.cena - r.sleva, im = r.imgData || r.img;
      var thumb = im ? '<img src="' + esc(im) + '" alt="" style="float:right;max-height:110px;max-width:70px;margin:0 0 .5rem .8rem;border:1px solid var(--gray-200);border-radius:8px;background:#fff;object-fit:contain">' : '';
      var sel = '';
      if (byt) {
        sel = '<p style="margin:.6rem 0"><button type="button" class="btn btn-primary" data-pick="' + i + '">' + (r.model ? 'Změnit výběr dveří' : '🚪 Vybrat dveře (jako v poptávce)') + '</button> ' +
          (r.model ? '<small>Výběr: <b>' + esc(r.nazev) + '</b>. Název, popis i cenu můžete níže upravit.</small>' : '<small>Otevře se výběr s obrázky, barvami, kováním a příplatky. Cena se spočítá sama.</small>') + '</p>' +
          (r.spec && r.spec.length ? '<p style="margin:.3rem 0 .8rem;font-size:.88rem;color:var(--text-muted)">' + esc(specTxt(r.spec)) + '</p>' : '');
      } else {
        var groups = catList().map(function (c) { return '<option value="' + esc(c[0]) + '"' + (r.cat === c[0] ? ' selected' : '') + '>' + esc(st.kat === 'interier' ? c[0] + ' – ' + c[2] + ' · ' + c[3] : c[0] + ' · ' + c[3]) + '</option>'; }).join('');
        var cobj = catFind(r.cat), dek = cobj && cobj[5] && cobj[5].length ? '<label class="pq-f">Odstín / dekor<select data-i="' + i + '" data-f="dekor"><option value="">– vybrat –</option>' + cobj[5].map(function (d) { return '<option value="' + esc(d[0]) + '"' + (r.odstin === d[0] ? ' selected' : '') + '>' + esc(d[0]) + '</option>'; }).join('') + '</select></label>' : '<label class="pq-f">Odstín / dekor<input data-i="' + i + '" data-f="odstin" value="' + esc(r.odstin) + '" placeholder="např. Dub Halifax"></label>';
        sel = '<label class="pq-f">Model z katalogu<select data-i="' + i + '" data-f="cat"><option value="">– vybrat model –</option>' + groups + '<option value="__jiny"' + (r.cat === '__jiny' ? ' selected' : '') + '>Jiné dveře (vyplním ručně)</option></select></label>' +
          (r.hint ? '<p class="pq-help" style="margin:.2rem 0 .6rem">' + esc(r.hint) + '</p>' : '') +
          '<div class="pq-grid2">' + dek +
          sirkaHtml(r, i) +
          '<label class="pq-f">Otevírání<input data-i="' + i + '" data-f="otev" value="' + esc(r.otev) + '" placeholder="např. levé"></label>' +
          '<label class="pq-f">Zárubeň<input data-i="' + i + '" data-f="zarub" value="' + esc(r.zarub) + '" placeholder="např. obložková, dekor shodný s dveřmi"></label>' + tlHtml(r, i) +
          '<label class="pq-f">Kování<input data-i="' + i + '" data-f="kov" value="' + esc(r.kov) + '" placeholder="např. klika Hera nerez"></label>' +
          '<label class="pq-f">Vlastní obrázek (volitelné)<input type="file" accept="image/*" data-i="' + i + '" data-f="imgfile"></label></div>' + montHtml(r, i);
      }
      return '<div class="pq-block-in" style="border:1.5px solid var(--gray-200);border-radius:12px;padding:1rem;margin-bottom:.8rem;overflow:hidden"><strong>Položka ' + (i + 1) + '</strong> ' + thumb +
        '<button type="button" class="btn btn-outline" data-del="' + i + '" style="float:right;padding:.3rem .8rem">Odebrat</button>' +
        (r.ai && r.ai.length ? '<p style="clear:none;margin:.5rem 0;padding:.45rem .7rem;background:#fff7d6;border:1px solid #e0b100;border-radius:8px;font-size:.9rem">🤖 Ověřte: ' + r.ai.map(esc).join(' · ') + '</p>' : '') + sel +
        '<label class="pq-f">Název *<input data-i="' + i + '" data-f="nazev" value="' + esc(r.nazev) + '" placeholder="' + (byt ? 'doplní se z modelu a odstínu' : 'např. název dveří / modelu') + '"></label>' +
        '<label class="pq-f">Doplňující popis (volitelné, jde do PDF)<input data-i="' + i + '" data-f="popis" value="' + esc(r.popis) + '"></label>' +
        '<div class="pq-grid2"><label class="pq-f">Počet kusů<input data-i="' + i + '" data-f="ks" inputmode="numeric" value="' + r.ks + '"' + (limited() ? ' readonly' : '') + '></label>' +
        '<label class="pq-f">Cena za kus vč. DPH (Kč)<input data-i="' + i + '" data-f="cena" inputmode="numeric" value="' + (r.cena || '') + '"></label>' +
        '<label class="pq-f">Sleva na položku (Kč)<input data-i="' + i + '" data-f="sleva" inputmode="numeric" value="' + (r.sleva || '') + '"></label></div>' +
        '<p style="margin:.4rem 0 0" data-tot="' + i + '">Za položku: <b>' + kc(tot) + '</b></p></div>';
    }).join('');
    sums();
  }
  function totals() {
    if (st.rozp) { var c = Math.max(0, int(fv('rozp_cena'))); return { sub: c, sleva: 0, total: c }; }
    var sub = 0; st.rows.forEach(function (r) { sub += r.ks * r.cena - r.sleva; });
    var sl = int(fv('sleva_kc')); return { sub: sub, sleva: sl, total: Math.max(0, sub - sl) };
  }
  /* fakturace a DPH (jen objednávka dům/interiér – blok #nbFak) */
  function hasFak() { return !!(form.elements.dph_rezim); }
  function radioVal(n) { var el = form.querySelector('input[name="' + n + '"]:checked'); return el ? el.value : ''; }
  function firma() { return hasFak() && radioVal('fakturace') === 'Firma nebo OSVČ'; }
  function dphRezim() { if (!hasFak()) return ''; var v = radioVal('dph_rezim'); return v === '21' || (v === 'pdp' && firma()) ? v : '12'; }
  function dphTxt() { var r = dphRezim(); return r === 'pdp' ? 'bez DPH (přenesená daňová povinnost)' : r ? 'vč. ' + r + ' % DPH' : 'vč. DPH'; }
  function fakToggle() {
    if (!hasFak()) return;
    var f = firma(); $('#nbFirma').hidden = !f; $('#nbPdp').hidden = !f;
    $('#nbFirma').style.display = f ? '' : 'none'; $('#nbPdp').style.display = f ? '' : 'none';   // pq-grid2 / pq-opt mají vlastní display, samotné hidden nestačí
    if (!f && radioVal('dph_rezim') === 'pdp') form.querySelector('input[name="dph_rezim"][value="12"]').checked = true;
  }
  function sums() {
    var t = totals();
    if (st.rozp) { $('#nbSum').innerHTML = t.total ? '<b>Celkem ' + kc(t.total) + '</b> ' + dphTxt() + ' (dle rozpočtu)' : ''; return; }
    $('#nbSum').innerHTML = st.rows.length ? 'Mezisoučet ' + kc(t.sub) + (t.sleva ? ' – celková sleva ' + kc(t.sleva) : '') + ' → <b>celkem ' + kc(t.total) + '</b> ' + dphTxt() : '';
    st.rows.forEach(function (r, i) { var e = document.querySelector('[data-tot="' + i + '"]'); if (e) e.innerHTML = 'Za položku: <b>' + kc(r.ks * r.cena - r.sleva) + '</b>'; });
  }
  function toJpeg(src, maxPx) {
    return new Promise(function (ok) {
      if (!src) return ok(null);
      var img = new Image(); img.onload = function () {
        var s = Math.min(1, maxPx / Math.max(img.naturalWidth, img.naturalHeight)), cv = document.createElement('canvas');
        cv.width = Math.max(1, Math.round(img.naturalWidth * s)); cv.height = Math.max(1, Math.round(img.naturalHeight * s));
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.drawImage(img, 0, 0, cv.width, cv.height);
        try { ok({ data: cv.toDataURL('image/jpeg', .85), w: cv.width, h: cv.height }); } catch (e) { ok(null); }
      }; img.onerror = function () { ok(null); }; img.src = src;
    });
  }
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'kat') { st.kat = t.value; setDefaults(); renderKat(); return; }
    if (t.name === 'zadani') { st.rozp = t.value === 'rozpocet'; renderKat(); sums(); return; }
    if (t.id === 'nbRozpFile') { rozpFile(t.files && t.files[0]); return; }
    if (t.dataset && t.dataset.f === 'cat') { var r = st.rows[+t.dataset.i]; applyCat(r, t.value); renderRows(); return; }
    if (t.dataset && t.dataset.f === 'dekor') {
      var r2 = st.rows[+t.dataset.i], c = catFind(r2.cat); r2.odstin = t.value; r2.shadeImg = '';
      if (c && c[5]) c[5].forEach(function (d) { if (d[0] === t.value) { r2.shadeImg = 'aplikace/' + d[1]; r2.img = r2.shadeImg; } });
      autoPrice(r2); renderRows(); return;
    }
    if (t.dataset && (t.dataset.f === 'tl' || t.dataset.f === 'montaz') && st.kat === 'interier') {
      var r4 = st.rows[+t.dataset.i]; if (t.dataset.f === 'montaz') r4.montaz = t.checked; else r4.tl = t.value;
      autoPrice(r4); renderRows(); return;
    }
    if (t.dataset && t.dataset.f === 'imgfile' && t.files && t.files[0]) {
      var r3 = st.rows[+t.dataset.i], fr = new FileReader();
      fr.onload = function () { toJpeg(fr.result, 900).then(function (j) { r3.imgData = j ? j.data : ''; renderRows(); }); };
      fr.readAsDataURL(t.files[0]);
    }
  });
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t.dataset && t.dataset.f && ['model', 'cat', 'dekor', 'imgfile', 'montaz'].indexOf(t.dataset.f) < 0 && !(t.dataset.f === 'tl' && st.kat === 'interier')) {
      var r = st.rows[+t.dataset.i], f = t.dataset.f; r[f] = (f === 'ks' || f === 'cena' || f === 'sleva') ? int(t.value) : t.value; if (f === 'cena' && intZ(r)) { r.cenaRuc = true; autoPrice(r); } sums();
    } else if (t.name === 'sleva_kc' || t.name === 'rozp_cena') sums();
  });
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t.id === 'nbAdd') { if (limited() && st.rows.length >= 1) return; st.rows.push(newRow()); renderKat(); renderRows(); if (st.kat === 'byt') openPick(st.rows.length - 1); }
    if (t.dataset && t.dataset.reprice != null && t.dataset.reprice !== '') { var rr = st.rows[+t.dataset.reprice]; rr.cenaRuc = false; autoPrice(rr); renderRows(); return; }
    if (t.dataset && t.dataset.pick != null && t.dataset.pick !== '') openPick(+t.dataset.pick);
    if (t.dataset && t.dataset.del != null && t.dataset.del !== '') { st.rows.splice(+t.dataset.del, 1); renderKat(); renderRows(); }
  });

  /* ── výběr dveří v okně (stejné obrázky a volby jako v poptávce; nabidka-vyber.html vrací výsledek přes postMessage) ── */
  function b64u(str) { var b = new TextEncoder().encode(str), s2 = ''; for (var i = 0; i < b.length; i++) s2 += String.fromCharCode(b[i]); return btoa(s2).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  var pickIdx = -1;
  function openPick(i) {
    pickIdx = i; var r = st.rows[i], cfg = r.cfg || (r.model ? { model_kod: r.model, odstin: r.odstin } : null);
    var ov = document.createElement('div'); ov.id = 'nbPick'; ov.style.cssText = 'position:fixed;inset:0;z-index:1000;background:rgba(10,30,20,.6);display:flex;flex-direction:column;padding:max(10px,env(safe-area-inset-top,0px)) max(10px,env(safe-area-inset-right,0px)) max(10px,env(safe-area-inset-bottom,0px)) max(10px,env(safe-area-inset-left,0px))';
    ov.innerHTML = '<div style="background:#fff;border-radius:12px;flex:1;display:flex;flex-direction:column;overflow:hidden;max-width:1200px;width:100%;margin:0 auto"><div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 1rem;border-bottom:1px solid #e3e8e5"><strong>Výběr dveří</strong><button type="button" id="nbPickX" class="btn btn-outline" style="padding:.3rem .9rem">Zavřít</button></div>' +
      '<iframe title="Výběr dveří" style="flex:1;border:0;width:100%" src="nabidka-vyber.html' + (cfg ? '#d=' + b64u(JSON.stringify(cfg)) : '') + '"></iframe></div>';
    document.body.appendChild(ov); document.body.style.overflow = 'hidden';
    $('#nbPickX').onclick = closePick;
  }
  function closePick() { var o = $('#nbPick'); if (o) o.remove(); document.body.style.overflow = ''; pickIdx = -1; }
  window.addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || !e.data.bdPick || pickIdx < 0) return;
    var p = e.data.bdPick, r = st.rows[pickIdx]; if (!r) return;
    r.model = p.model_kod; r.odstin = p.odstin || ''; r.nazev = p.nazev; r.popis = ''; r.cena = p.cena; r.cfg = p.cfg; r.img = p.img || ''; r.shadeImg = p.shadeImg || ''; r.spec = fixSpec(p.spec);
    closePick(); renderKat(); renderRows();
  });

  /* ── data ── */
  function collect() {
    var t = totals(), d = {
      verze: 3, zdroj: OBJ ? 'web-objednavka-vice' : 'web-nabidka', typ: OBJ ? 'objednavka' : 'nabidka', kategorie: st.kat, kategorie_nazev: KAT[st.kat],
      obj_cislo: fv('obj_cislo'), cislo: fv('obj_cislo'), obj_datum: fv('obj_datum'), nabidka_do: fv('nabidka_do'),
      prodavac: (document.querySelector('input[name="prodavac"]:checked') || {}).value || '',
      jmeno: fv('jmeno'), prijmeni: fv('prijmeni'), telefon: fv('telefon'), email: fv('email'), ulice: fv('ulice'), mesto: fv('mesto'), psc: fv('psc'),
      poznamka: fv('poznamka'), interni: fv('interni'), odeslano: new Date().toISOString(),
      zahrnuje: fv('zahrnuje'), platba: fv('platba'), termin_text: fv('termin_text'),
      sleva_celkem: t.sleva, sleva_text: fv('sleva_text'), mezisoucet: t.sub, cena_celkem: t.total,
      z_poptavky: st.fromRef || '', ai_navrh: st.aiNavrh || ''
    };
    if (hasFak()) {
      var rz = dphRezim(), sz = rz === 'pdp' ? 0 : +rz, bez = rz === 'pdp' ? t.total : Math.round(t.total / (1 + sz / 100));
      d.dph_rezim = rz; d.dph_sazba = sz; d.cena_bez_dph = bez; d.dph_kc = t.total - bez;
      d.fakturace = firma() ? 'Jiná adresa – firma nebo OSVČ' : 'Stejná jako instalační';
      if (firma()) {
        d.fak_jmeno = fv('fak_jmeno'); d.fak_adresa = fv('fak_adresa'); d.ic = fv('ic').replace(/\s+/g, ''); d.dic = fv('dic').replace(/\s+/g, '').toUpperCase();
        d.platce_dph = !!(form.platce_dph && form.platce_dph.checked); d.ares_overeno = $('#nbFirma').getAttribute('data-ares-overeno') || '';
      }
    }
    if (st.rozp) {
      var rp = fv('rozp_popis'), rc = fv('rozp_cislo');
      d.rozpocet = { popis: rp, cislo: rc, soubor: st.rozpName, strany: st.rozpPages || null };
      d.model = rp.toLowerCase().indexOf(KAT[st.kat].split(' ')[0].toLowerCase()) === 0 ? rp : KAT[st.kat] + ': ' + rp;
      d.polozky = [{ co: rp, volba: 'dle přiloženého rozpočtu' + (rc ? ' č. ' + rc : ''), priplatek: t.total, nazev: rp }];
      if (!OBJ) d.uvod = fv('uvod');
    } else {
    d.pocet_kusu = st.rows.reduce(function (a, r) { return a + r.ks; }, 0);
    d.model = KAT[st.kat] + ': ' + st.rows.map(function (r) { return (r.ks > 1 ? r.ks + '× ' : '') + r.nazev; }).join(', ');
    d.polozky = st.rows.map(function (r) {
      var sp = rowSpec(r);
      return { co: r.nazev + (r.ks > 1 ? ' (' + r.ks + ' ks × ' + kc(r.cena) + ')' : ''), volba: [specTxt(sp), r.popis, r.sleva ? 'sleva ' + kc(r.sleva) : ''].filter(Boolean).join(' · '), priplatek: r.ks * r.cena - r.sleva,
        model_kod: r.model, odstin: r.odstin, cfg: r.cfg || null, popis: r.popis, nazev: r.nazev, ks: r.ks, cena_ks: r.cena, sleva: r.sleva, spec: sp, obrazek: r.img || '' };
    });
    }
    if (OBJ) {
      var pr = fv('zaloha_proc') === '' ? ZALOHA_PROC : int(fv('zaloha_proc'));
      d.zaloha_proc = pr; d.zaloha_label = 'Záloha'; d.zaloha = Math.round(t.total * pr / 100); d.doplatek = Math.max(0, t.total - d.zaloha);
      d.termin = fv('termin_text'); d.z_nabidky = st.fromRef || '';
      var hex = function () { var b = new Uint8Array(12); (window.crypto || window.msCrypto).getRandomValues(b); return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join(''); };
      d.predani_token = hex(); d.zakaznik_token = hex();
    }
    if (t.sleva) d.polozky.push({ co: 'Celková sleva' + (d.sleva_text ? ': ' + d.sleva_text : ''), volba: '', priplatek: -t.sleva });
    if (!OBJ) d.objednavka_odkaz = orderLink(d);
    return d;
  }
  /* odkaz „Vytvořit objednávku“: byt → objednavka-nova.html (jedny dveře, plná konfigurace), dům a interiér → objednavka-vice.html (víc položek) */
  function orderLink(d) {
    var o;
    if (d.kategorie === 'byt' && d.polozky[0] && d.polozky[0].cfg) {
      var c = d.polozky[0].cfg; o = { cislo: d.obj_cislo, jmeno: d.jmeno, prijmeni: d.prijmeni, telefon: d.telefon, email: d.email, ulice: d.ulice, mesto: d.mesto, psc: d.psc, poznamka: d.poznamka };
      ['model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'tl_zarubne'].forEach(function (k) { if (c[k]) o[k] = c[k]; });
      return new URL('objednavka-nova.html', location.href).href + '#d=' + b64u(JSON.stringify(o));
    }
    o = JSON.parse(JSON.stringify(d)); delete o.objednavka_odkaz;
    return new URL('objednavka-vice.html', location.href).href + '#d=' + b64u(JSON.stringify(o));
  }
  function validate(d) {
    if (!st.kat) return 'Vyberte kategorii dveří.';
    if (st.rozp) {
      if (!st.rozpBuf) return 'Nahrajte rozpočet z programu (PDF).';
      if (!fv('rozp_popis')) return 'Doplňte krátce předmět ' + (OBJ ? 'objednávky' : 'nabídky') + '.';
      if (!(int(fv('rozp_cena')) > 0)) return 'Doplňte cenu celkem vč. DPH podle rozpočtu.';
    } else {
    if (!st.rows.length) return 'Přidejte aspoň jednu položku.';
    for (var i = 0; i < st.rows.length; i++) { var r = st.rows[i]; if (!r.nazev.trim()) return 'Položka ' + (i + 1) + ': doplňte název.'; if (r.ks < 1) return 'Položka ' + (i + 1) + ': počet kusů musí být aspoň 1.'; if (r.cena <= 0) return 'Položka ' + (i + 1) + ': doplňte cenu za kus.'; }
    if (limited() && (st.rows.length > 1 || st.rows[0].ks !== 1)) return 'Na jednu adresu lze ' + (OBJ ? 'objednat' : 'nabídnout') + ' jen jedny dveře (' + (st.kat === 'byt' ? 'do bytu' : 'do domu') + '). Další dveře dejte do samostatné ' + (OBJ ? 'objednávky' : 'nabídky') + '.';
    }
    if (!d.jmeno || !d.prijmeni) return 'Doplňte jméno a příjmení zákazníka.';
    if (hasFak() && firma()) {
      if (!d.ic || (window.BDAres && !window.BDAres.icoOk(d.ic))) return 'Doplňte platné IČ firmy.';
      if (!d.fak_jmeno || !d.fak_adresa) return 'Doplňte název firmy a fakturační adresu (tlačítko Ověřit v ARES je doplní samo).';
      if (d.dph_rezim === 'pdp' && !d.platce_dph) return 'Přenesená daňová povinnost jde jen u odběratele, který je plátce DPH.';
    }
    if (OBJ && (!d.ulice || !d.mesto)) return 'Doplňte adresu montáže (ulice a město).';
    if (OBJ && !(int(fv('zaloha_proc')) >= 0 && int(fv('zaloha_proc')) <= 100)) return 'Záloha v procentech musí být 0 až 100.';
    if (d.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) return 'E-mail zákazníka nevypadá správně.';
    if (!d.obj_cislo) return 'Doplňte číslo ' + (OBJ ? 'objednávky' : 'nabídky') + '.';
    if (!d.prodavac) return 'Vyberte prodavače.';
    if (!$('#nbConsent').checked) return 'Potvrďte, že je ' + (OBJ ? 'objednávka' : 'nabídka') + ' zkontrolovaná.';
    return '';
  }

  /* ── PDF ── */
  function bonusOn() {
    var on = Date.now() >= new Date('2026-10-25T00:00:00+01:00').getTime();
    try { if (localStorage.getItem('bd-bonus') === '1') on = true; } catch (e) {}
    return on;
  }
  /* výsledné PDF: naše strany + za nimi celý rozpočet z jiného programu (pdf-lib). Když PDF rozpočtu nejde připojit (zaheslované apod.), vrátí merged: false a rozpočet se pošle jako samostatná příloha. */
  function buildPdf(d) {
    return buildOwn(d).then(function (b) {
      if (!d.rozpocet || !st.rozpBuf) return b;
      var P = window.PoptavkaPDF;
      return P.preparePL().then(function () { return b.blob.arrayBuffer(); }).then(function (own) {
        var L = window.PDFLib;
        return Promise.all([L.PDFDocument.load(own), L.PDFDocument.load(st.rozpBuf.slice(0), { ignoreEncryption: true })]).then(function (x) {
          return x[0].copyPages(x[1], x[1].getPageIndices()).then(function (ps) {
            ps.forEach(function (pg) { x[0].addPage(pg); });
            return x[0].save().then(function (bytes) { return { blob: new Blob([bytes], { type: 'application/pdf' }), pages: x[0].getPageCount(), merged: true }; });
          });
        });
      }).catch(function (er) { try { console.error(er); } catch (e) {} b.merged = false; return b; });
    });
  }
  function buildOwn(d) {
    var P = window.PoptavkaPDF; if (!P || !P.prepare) return Promise.reject(new Error('pdf'));
    var imgJobs = st.rows.map(function (r) { return Promise.all([toJpeg(r.imgData || r.img, 600), (r.shadeImg && r.shadeImg.indexOf('color:') !== 0) ? toJpeg(r.shadeImg, 120) : Promise.resolve(null)]); });
    return Promise.all([P.prepare(), toJpeg('logo.jpg', 600), Promise.all(imgJobs)]).then(function (res) {
      var fonts = res[0], lg = res[1], ims = res[2];
      var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
      doc.addFileToVFS('Inter-Regular.ttf', fonts[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
      doc.addFileToVFS('Inter-Bold.ttf', fonts[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
      var W = 210, M = 14, CW = W - 2 * M, GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], PALE = [246, 250, 247], LINE = [208, 224, 213], page = 1, y;
      var font = function (s, z, c) { doc.setFont('Inter', s); doc.setFontSize(z); doc.setTextColor.apply(doc, c || [15, 42, 28]); };
      var money = function (n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; };
      var dt = function (v) { return v ? new Date(v).toLocaleDateString('cs-CZ') : ''; };
      var hexc = function (c) { c = c.replace('#', ''); return [parseInt(c.substr(0, 2), 16), parseInt(c.substr(2, 2), 16), parseInt(c.substr(4, 2), 16)]; };
      function footer() { font('normal', 7.5, MUTED); doc.text((OBJ ? 'Objednávka č. ' : 'Nabídka č. ') + d.obj_cislo + ' · strana ' + page, M, 289); doc.text('Dotazy: ' + CC + ' · +420 725 559 235 · www.bezpecne-dvere.cz', W - M, 289, { align: 'right' }); }
      function ensure(h) { if (y + h > 280) { footer(); doc.addPage(); page++; y = 18; } }
      function section(t) { y += 2.5; ensure(16); font('bold', 9, ORANGE); doc.text(t.toUpperCase(), M, y); doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.2); doc.line(M, y + 1.6, W - M, y + 1.6); y += 6.2; }
      function para(text, size, color, lh) { font('normal', size, color); var ls = doc.splitTextToSize(text, CW); ls.forEach(function (l) { ensure(lh + .2); doc.text(l, M, y); y += lh; }); y += 1.2; }

      /* hlavička */
      doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
      if (lg) { var lh = 16; doc.addImage(lg.data, 'JPEG', M, 7, lh * lg.w / lg.h, lh); }
      font('bold', 16, [255, 255, 255]); doc.text(OBJ ? 'Objednávka' : 'Cenová nabídka', W - M, 13, { align: 'right' });
      font('normal', 9.5, [210, 230, 218]); doc.text(KAT[d.kategorie] + '  ·  č. ' + d.obj_cislo + '  ·  ' + dt(d.obj_datum || d.odeslano), W - M, 19.5, { align: 'right' });
      doc.text('Prodavač: ' + d.prodavac, W - M, 24.5, { align: 'right' });
      font('normal', 7.5, MUTED);
      var zl = doc.splitTextToSize('Zhotovitel: Zdeněk Kučera, Zd. Štěpánka 2764/1, Most. IČO: 64682676, DIČ: CZ7801242768. Vzorkovna: KK SYSTEM, Obchodní 36, Most, www.bezpecne-dvere.cz', CW);
      doc.text(zl, M, 36); y = 36 + zl.length * 3.2 + 5;

      /* zákazník + platnost */
      var top0 = y;
      font('bold', 9, ORANGE); doc.text('ZÁKAZNÍK', M, y); y += 5;
      font('bold', 10.5); doc.text((d.jmeno + ' ' + d.prijmeni).trim(), M, y); y += 4.8;
      font('normal', 9.5); [[d.ulice, d.psc, d.mesto].filter(Boolean).join(', '), [d.telefon, d.email].filter(Boolean).join(' · ')].filter(Boolean).forEach(function (l) { doc.text(l, M, y); y += 4.4; });
      if (d.ic) { font('normal', 8.5, MUTED); doc.splitTextToSize('Fakturace: ' + d.fak_jmeno + ', ' + d.fak_adresa + ', IČ ' + d.ic + (d.dic ? ', DIČ ' + d.dic : ''), W - 2 * M - 70).forEach(function (l) { doc.text(l, M, y); y += 3.9; }); }
      doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(W - M - 62, top0 - 4, 62, 19, 2, 2, 'FD');
      font('bold', 7.5, MUTED); doc.text(OBJ ? 'ZÁLOHA ' + d.zaloha_proc + ' %' : 'PLATNOST NABÍDKY', W - M - 58, top0 + 0.5);
      font('bold', 13, ORANGE); doc.text(OBJ ? money(d.zaloha) : (d.nabidka_do ? 'do ' + dt(d.nabidka_do) : 'do odvolání'), W - M - 58, top0 + 7);
      font('normal', 7.5, MUTED); doc.text(OBJ ? 'doplatek po montáži ' + money(d.doplatek) : 'vystaveno ' + dt(d.obj_datum || d.odeslano), W - M - 58, top0 + 11.8);
      y = Math.max(y, top0 + 17) + 4;

      /* rozpočet z jiného programu: úvodní text (nabídka) a předmět místo karet položek */
      if (d.rozpocet) {
        if (!OBJ && d.uvod) { y += 1; para(d.uvod, 9.6, [15, 42, 28], 4.6); y += 1; }
        section(OBJ ? 'Předmět objednávky' : 'Předmět nabídky');
        var rz = d.rozpocet, rTxt = (OBJ && d.z_nabidky ? 'Dle nabídky č. ' + d.z_nabidky + ' a přiloženého rozpočtu' : 'Dle přiloženého rozpočtu') + (rz.cislo ? ' č. ' + rz.cislo : '') + (rz.strany ? ' (' + rz.strany + ' ' + (rz.strany === 1 ? 'strana' : rz.strany < 5 ? 'strany' : 'stran') + ')' : '') +
          '. Rozpočet obsahuje rozpis položek, rozměrů, provedení a cen a je nedílnou součástí této ' + (OBJ ? 'objednávky' : 'nabídky') + ' – je připojen za ' + (OBJ ? 'smluvním ujednáním a podpisy.' : 'touto stranou.');
        font('bold', 11.5); var rtl = doc.splitTextToSize(rz.popis, CW - 10); font('normal', 9); var rdl = doc.splitTextToSize(rTxt, CW - 10);
        var rh = rtl.length * 5.2 + rdl.length * 4.3 + 9; ensure(rh + 4);
        doc.setFillColor(255, 255, 255); doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.roundedRect(M, y - 2, CW, rh, 2.5, 2.5, 'FD');
        var ry = y + 5; font('bold', 11.5, GREEN); doc.text(rtl, M + 5, ry); ry += rtl.length * 5.2 + 1;
        font('normal', 9, [40, 60, 50]); doc.text(rdl, M + 5, ry); y += rh + 4;
      }

      /* karty položek */
      if (!d.rozpocet) section(OBJ ? 'Objednané dveře' : 'Nabízené dveře');
      d.polozky.forEach(function (p, i) {
        if (p.ks == null) return;
        var r = st.rows[i], im = ims[i] || [null, null];
        var hasImg = !!im[0], IW = hasImg ? 36 : 0, IH = hasImg ? 52 : 0, x0 = M + (hasImg ? IW + 6 : 5), wTxt = W - M - x0 - 2, wLab = 30;
        var sp = p.spec || [];
        font('bold', 11.5); var tl = doc.splitTextToSize(p.nazev, wTxt);
        var h = tl.length * 5.2 + 2;
        var rows = sp.map(function (s) {
          font('bold', 9); var v = s[1] + (s[2] > 0 ? '  (+ ' + money(s[2]) + ')' : (s[3] ? '  (' + s[3] + ')' : '')), ls = doc.splitTextToSize(v, wTxt - wLab);
          h += Math.max(1, ls.length) * 4.3 + 1.1; return [s[0], ls];
        });
        var pl = []; if (p.popis) { font('normal', 8.8); pl = doc.splitTextToSize(p.popis, wTxt); h += pl.length * 4 + 2; }
        h += 11 + (p.sleva ? 4.5 : 0);
        var imgCol = hasImg ? IH + (im[1] || (r && r.shadeImg) ? 14 : 2) : 0, cardH = Math.max(h, imgCol) + 3;
        ensure(cardH + 4);
        var top = y - 2;
        doc.setFillColor(255, 255, 255); doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.roundedRect(M, top, CW, cardH, 2.5, 2.5, 'FD');
        /* obrázek */
        if (hasImg) { doc.setFillColor.apply(doc, PALE); doc.roundedRect(M + 3, top + 3, IW, IH, 2, 2, 'F'); }
        if (im[0]) { var rr = Math.min((IW - 4) / im[0].w, (IH - 4) / im[0].h), iw = im[0].w * rr, ih = im[0].h * rr; doc.addImage(im[0].data, 'JPEG', M + 3 + (IW - iw) / 2, top + 3 + (IH - ih) / 2, iw, ih); }
        if (hasImg && (im[1] || (r && r.shadeImg && r.shadeImg.indexOf('color:') === 0))) {
          var sy = top + 3 + IH + 2.5;
          if (im[1]) doc.addImage(im[1].data, 'JPEG', M + 3, sy, 9, 9); else { doc.setFillColor.apply(doc, hexc(r.shadeImg.slice(6))); doc.roundedRect(M + 3, sy, 9, 9, 1, 1, 'F'); }
          font('normal', 7, MUTED); doc.text('Barva', M + 14, sy + 3.2); font('bold', 8.5); doc.text(doc.splitTextToSize(p.odstin || '', IW - 12), M + 14, sy + 7.2);
        }
        /* text */
        var yy = top + 8;
        font('bold', 11.5, GREEN); doc.text(tl, x0, yy); yy += tl.length * 5.2 + 1.2;
        rows.forEach(function (rw) { font('normal', 8.5, MUTED); doc.text(rw[0], x0, yy); font('bold', 9); doc.text(rw[1], x0 + wLab, yy); yy += Math.max(1, rw[1].length) * 4.3 + 1.1; });
        if (pl.length) { font('normal', 8.8, [40, 60, 50]); doc.text(pl, x0, yy + 1); yy += pl.length * 4 + 2; }
        /* cena položky – dole vpravo v kartě */
        var by = top + cardH - 6;
        if (p.sleva) { font('normal', 8.5, ORANGE); doc.text('sleva na položku -' + money(p.sleva), W - M - 4, by - 9, { align: 'right' }); }
        font('normal', 9, MUTED); doc.text(p.ks + ' ks × ' + money(p.cena_ks) + ' vč. DPH', W - M - 4, by - 4.3, { align: 'right' });
        font('bold', 12.5, ORANGE); doc.text('Celkem ' + money(p.ks * p.cena_ks - p.sleva), W - M - 4, by + 1.2, { align: 'right' });
        y = top + cardH + 4;
      });

      /* celkem */
      ensure(26); y += 4;
      if (d.sleva_celkem) {
        font('normal', 9.5); doc.text('Mezisoučet', W - M - 70, y); doc.text(money(d.mezisoucet), W - M - 1, y, { align: 'right' }); y += 5;
        font('normal', 9.5, ORANGE); doc.text('Celková sleva' + (d.sleva_text ? ' (' + d.sleva_text + ')' : ''), W - M - 70, y); doc.text('-' + money(d.sleva_celkem), W - M - 1, y, { align: 'right' }); y += 6;
      }
      doc.setFillColor.apply(doc, GREEN); doc.roundedRect(M, y - 5, CW, 11, 2, 2, 'F');
      font('bold', 11, [255, 255, 255]); doc.text(d.dph_rezim === 'pdp' ? 'CENA CELKEM bez DPH' : d.dph_rezim ? 'CENA CELKEM vč. ' + d.dph_sazba + ' % DPH' : 'CENA CELKEM vč. DPH', M + 4, y + 1.6); doc.text(money(d.cena_celkem), W - M - 4, y + 1.6, { align: 'right' }); y += 14;
      if (d.dph_rezim) { font('normal', 8.5, MUTED); doc.text(d.dph_rezim === 'pdp' ? 'Režim přenesení daňové povinnosti – daň odvede zákazník.' : 'Základ ' + money(d.cena_bez_dph) + ' + DPH ' + d.dph_sazba + ' % ' + money(d.dph_kc) + '.', M, y - 2); y += 4; }

      /* co cena zahrnuje, platba, termín */
      if (!OBJ && d.zahrnuje) { section('Co cena zahrnuje'); para(d.zahrnuje, 9.2, [15, 42, 28], 4.2); }
      if (!OBJ && (d.platba || d.termin_text)) {
        section('Platba a termín');
        if (d.platba) para(d.platba, 9.2, [15, 42, 28], 4.2);
        if (d.termin_text) para('Termín dodání / montáže: ' + d.termin_text, 9.2, [15, 42, 28], 4.2);
      }
      if (d.poznamka) { section('Poznámka'); para(d.poznamka, 9.5, [15, 42, 28], 4.4); }

      /* bonus Moje dveře */
      if (d.kategorie === 'byt' && bonusOn()) {
        ensure(15);
        doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(M, y - 3.5, CW, 11, 2, 2, 'FD');
        font('bold', 9.5, GREEN); doc.text('V ceně: aplikace Moje dveře', M + 4, y + 0.6);
        font('normal', 8, MUTED); doc.text('Přehled dveří a záruky, návod na údržbu a vlastní servis, hlášení závady, roční kontrola.', M + 4, y + 4.9); y += 13;
      }

      /* objednávka: platba, smluvní ujednání, podpisy (text vychází z ujednání objednávek bezpečnostních dveří, upravený pro dveře do domu a interiérové) */
      if (OBJ) {
        var LG = P.LEGAL || [];
        var cutTo = function (t, m) { var i = t.indexOf(m); return i < 0 ? t : t.slice(0, i); };
        var legal = [];
        if (LG[0]) legal.push(LG[0].replace('dodávku a montáž bezpečnostních bytových dveří dle výše uvedených parametrů', d.rozpocet ? 'dodávku a montáž dveří dle přiloženého rozpočtu' : 'dodávku a montáž dveří dle výše uvedených položek'));
        if (LG[1]) legal.push(d.kategorie === 'interier' ? LG[1].replace(/; podmínky bonusu a prodloužení záruky na 5 nebo 10 let jsou na [^ ]+\./, '.') : LG[1]);
        if (LG[2]) legal.push(LG[2]);
        if (LG[3]) legal.push(LG[3]);
        if (LG[4]) { var gd = LG[4].indexOf('Osobní údaje objednatele'), gz = LG[4].indexOf(' číslo občanského průkazu'); legal.push(cutTo(LG[4], ' Objednatel bere na vědomí, že čistý průchod') + (gd >= 0 ? ' ' + LG[4].slice(gd, gz > gd ? gz : undefined).replace(/;\s*$/, '.') : '')); }
        section('Platba');
        var pi = window.BDPlatba && d.zaloha > 0 ? window.BDPlatba.info(d.obj_cislo, d.zaloha) : null;
        para('Záloha ' + d.zaloha_proc + ' % z ceny zakázky: ' + money(d.zaloha) + (pi ? ' – účet ' + pi.ucet + (pi.vs ? ', variabilní symbol ' + pi.vs : '') : '') + '. Doplatek ' + money(d.doplatek) + ' hotově po montáži.', 9.2, [15, 42, 28], 4.2);
        if (d.zahrnuje) { section('Co cena zahrnuje'); para(d.zahrnuje, 9.2, [15, 42, 28], 4.2); }
        if (d.termin_text) para('Termín dodání / montáže: ' + d.termin_text, 9.2, [15, 42, 28], 4.2);
        section('Smluvní ujednání');
        legal.forEach(function (par) { font('normal', 7.2, [40, 60, 50]); doc.splitTextToSize(par, CW).forEach(function (line) { ensure(3.2); doc.text(line, M, y); y += 3.0; }); y += 1.3; });
        ensure(30); y += 3;
        font('normal', 9, MUTED); doc.text('Dne ' + dt(d.obj_datum || d.odeslano), M, y); y += 12;
        doc.setDrawColor(120, 140, 130); doc.setLineWidth(0.3); doc.line(M, y, M + 75, y); doc.line(W - M - 75, y, W - M, y); y += 4.5;
        font('bold', 9); doc.text('Závazně objednávám – objednatel', M, y); doc.text('Zhotovitel', W - M - 75, y); y += 4.2;
        font('normal', 8.5, MUTED); doc.text((d.jmeno + ' ' + d.prijmeni).trim(), M, y);
        doc.text(['Zdeněk Kučera, KK SYSTEM', 'Zd. Štěpánka 2764/1, 434 01 Most', '+420 725 559 235'], W - M - 75, y);
        footer();
        return { blob: doc.output('blob'), pages: doc.getNumberOfPages() };
      }

      /* podmínky */
      ensure(48);
      section('Podmínky nabídky');
      var cond = [
        'Nabídka platí ' + (d.nabidka_do ? 'do ' + dt(d.nabidka_do) : 'do odvolání') + '. Po tomto datu se může změnit cena i dostupnost.',
        'Ceny jsou uvedeny včetně DPH.',
        'Nabídka je informativní. Závazně se dveře objednávají podpisem objednávky; po Vašem odsouhlasení nabídku na objednávku převedeme.',
        'Záruka a reklamace se řídí občanským zákoníkem a Reklamačním řádem zhotovitele. Obchodní podmínky najdete na www.bezpecne-dvere.cz a na naší vzorkovně.'
      ];
      cond.forEach(function (c) { font('normal', 8.8, [40, 60, 50]); var ls = doc.splitTextToSize(c, CW - 5); ensure(ls.length * 4 + 1); doc.text('•', M + 1, y); doc.text(ls, M + 5, y); y += ls.length * 4 + 1.2; });
      ensure(17); y += 2;
      font('bold', 9.5, GREEN); doc.text('Nabídka Vám vyhovuje?', M, y); y += 4.6;
      font('normal', 9, [15, 42, 28]); doc.text('Zavolejte +420 725 559 235 nebo napište na ' + CC + '. Převedeme ji na objednávku a domluvíme termín.', M, y); y += 4.4;
      doc.text('Prodavač: ' + d.prodavac + ' · KK SYSTEM, Obchodní 36, Most', M, y);
      footer();
      return { blob: doc.output('blob'), pages: doc.getNumberOfPages() };
    });
  }

  /* ── odeslání ── */
  function fieldsTelo(list) { return window.BDForm.bodyFromFields(list); }
  function dec(b) { return String(b); }
  function customerList(d) {
    if (OBJ) {
      var pi = window.BDPlatba && d.zaloha > 0 ? window.BDPlatba.info(d.obj_cislo, d.zaloha) : null;
      var lo = [['_subject', 'Objednávka ' + d.obj_cislo + ' – ' + KAT[d.kategorie].toLowerCase() + ' – Bezpečné dveře'],
        ['Dobrý den', 'děkujeme za objednávku. V příloze posíláme objednávku v PDF. S dotazy se obracejte na ' + CC + ' nebo +420 725 559 235.'],
        ['Objednávka', d.obj_cislo], ['Celkem ' + dphTxt(), kc(d.cena_celkem)], ['Záloha ' + d.zaloha_proc + ' %', kc(d.zaloha) + (pi ? ' – účet ' + pi.ucet + (pi.vs ? ', variabilní symbol ' + pi.vs : '') + '. QR kód a údaje k platbě: ' + pi.odkaz : '')],
        ['Doplatek po montáži', kc(d.doplatek) + ' hotově']];
      return lo.concat(d.polozky.map(function (p, i) { return ['Položka ' + (i + 1), p.co + (p.volba ? ' – ' + p.volba : '') + ': ' + kc(p.priplatek)]; }));
    }
    return [['_subject', 'Cenová nabídka ' + d.obj_cislo + ' – ' + KAT[d.kategorie].toLowerCase() + ' – Bezpečné dveře'],
      ['Dobrý den', 'děkujeme za zájem. V příloze posíláme cenovou nabídku v PDF' + (d.nabidka_do ? ' (platí do ' + new Date(d.nabidka_do).toLocaleDateString('cs-CZ') + ')' : '') + '. Pokud Vám vyhovuje, ozvěte se nám a převedeme ji na objednávku. Dotazy: ' + CC + ' nebo +420 725 559 235.'],
      ['Nabídka', d.obj_cislo], ['Celkem vč. DPH', kc(d.cena_celkem)]].concat(d.polozky.map(function (p, i) { return ['Položka ' + (i + 1), p.co + (p.volba ? ' – ' + p.volba : '') + ': ' + kc(p.priplatek)]; }));
  }
  function internalList(d) {
    return [['_subject', (OBJ ? 'Objednávka ' : 'Nabídka ') + d.obj_cislo + ' (interní) – ' + KAT[d.kategorie] + ' – ' + d.prijmeni + (d.mesto ? ', ' + d.mesto : '')],
      [OBJ ? 'Číslo objednávky' : 'Číslo nabídky', d.obj_cislo], ['Kategorie', KAT[d.kategorie]], ['Prodavač', d.prodavac], [OBJ ? 'Z nabídky / poptávky' : 'Z poptávky', (OBJ ? d.z_nabidky : d.z_poptavky) || '–'], ['Interní poznámka', d.interni || '–'],
      ['Cena celkem', kc(d.cena_celkem) + (d.dph_rezim ? ' ' + dphTxt() : '')]].concat(d.dph_rezim ? [['DPH (pro účetnictví)', d.dph_rezim === 'pdp' ? 'přenesená daňová povinnost, základ ' + kc(d.cena_bez_dph) : d.dph_sazba + ' %, základ ' + kc(d.cena_bez_dph) + ', DPH ' + kc(d.dph_kc)]] : []).concat(d.ic ? [['Fakturace', d.fak_jmeno + ', ' + d.fak_adresa + ', IČ ' + d.ic + (d.dic ? ', DIČ ' + d.dic : '') + (d.platce_dph ? ', plátce DPH' : '')]] : []).concat(OBJ ? [['Záloha / doplatek', kc(d.zaloha) + ' (' + d.zaloha_proc + ' %) / ' + kc(d.doplatek)]] : [['Vytvořit objednávku', d.objednavka_odkaz]]).concat([['data_json', JSON.stringify(d)]]).concat(d.polozky.map(function (p, i) { return ['Položka ' + (i + 1), p.co + (p.volba ? ' – ' + p.volba : '') + ': ' + kc(p.priplatek)]; }));
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
    var pdf = null, extraFiles = [], custOk = true, fn = (OBJ ? 'Objednavka-' : 'Nabidka-') + d.obj_cislo + '.pdf';
    var TYP = OBJ ? 'objednavka_interni' : 'nabidka';
    buildPdf(d).catch(function (er) { try { console.error(er); } catch (e2) {} return null; }).then(function (b) {
      pdf = b ? b.blob : null; window.__nbPages = b ? b.pages : 0; btn.textContent = 'Odesílám…';
      if (d.rozpocet && st.rozpBuf && !(b && b.merged)) extraFiles = [{ name: 'rozpocet_pdf', file: 'Rozpocet-' + d.obj_cislo + '.pdf', blob: new Blob([st.rozpBuf], { type: 'application/pdf' }) }];
      var files = (pdf ? [{ name: 'objednavka_pdf', file: fn, blob: pdf }] : []).concat(extraFiles);
      var ix = d.email ? { reply_to: d.email } : {};
      return send(TYP, internalList(d), files, d.obj_cislo, ix).catch(function () { return send(TYP, internalList(d), [], d.obj_cislo, ix); });
    }).then(function () {
      if (!d.email) return;
      var files = (pdf ? [{ name: 'objednavka_pdf', file: fn, blob: pdf }] : []).concat(extraFiles);
      return send('objednavka_zakaznik', customerList(d), files, d.obj_cislo, { email_zakaznik: d.email })
        .catch(function () { custOk = false; return send('objednavka_zakaznik', customerList(d), [], d.obj_cislo, { email_zakaznik: d.email }); });
    }).then(function () {
      $('#nbRef').textContent = d.obj_cislo; var w = $('#nbWarn');
      var to = $('#nbToOrder'); if (to && !OBJ && d.objednavka_odkaz) { to.href = d.objednavka_odkaz; to.hidden = false; }
      if (OBJ && d.zaloha > 0 && window.BDPlatba && $('#nbPay')) {
        try {
          var pi2 = window.BDPlatba.info(d.obj_cislo, d.zaloha), bx = $('#nbPayQr'); bx.innerHTML = ''; var cv = window.BDPlatba.canvas(pi2.spd, 360); cv.style.cssText = 'width:min(300px,80vw);height:auto;image-rendering:pixelated'; bx.appendChild(cv);
          $('#nbPayTxt').innerHTML = '<b>' + kc(d.zaloha) + '</b> (' + d.zaloha_proc + ' %) · účet ' + pi2.ucet + (pi2.vs ? ' · VS ' + pi2.vs : ''); $('#nbPay').hidden = false;
        } catch (e) {}
      }
      if (!d.email) { w.textContent = 'Zákazník nemá vyplněný e-mail – nabídka odešla jen vám. PDF si vytiskněte a předejte mu ho.'; w.hidden = false; }
      else if (!custOk) { w.textContent = 'E-mail zákazníkovi odešel bez přiloženého PDF. Stáhněte si PDF a pošlete mu ho ručně.'; w.hidden = false; }
      if (pdf) { var u = URL.createObjectURL(pdf), a = $('#nbPdf'); a.href = u; a.download = fn; a.hidden = false; var pr = $('#nbPrint'); pr.hidden = false; pr.onclick = function () { var wn = window.open(u, '_blank'); if (wn) wn.focus(); }; }
      form.hidden = true; $('.pq-hero').hidden = true; $('#nbDone').hidden = false; window.scrollTo(0, 0);
    }).catch(function () {
      msg.textContent = 'Odeslání se nepodařilo – zkontrolujte připojení a zkuste to znovu.'; msg.hidden = false; btn.disabled = false; btn.textContent = 'Odeslat nabídku zákazníkovi';
    });
  });

  /* ── start ── */
  if (hasFak()) {
    form.addEventListener('change', function (e) { if (e.target.name === 'fakturace' || e.target.name === 'dph_rezim') { fakToggle(); sums(); } });
    if (window.BDAres) window.BDAres.attach($('#nbFirma'));
    fakToggle();
  }
  $('#nbSeller').innerHTML = PRODAVACI.map(function (p) { return '<label class="pq-opt pq-chip"><input type="radio" name="prodavac" value="' + esc(p) + '">' + esc(p) + '</label>'; }).join('');
  form.obj_cislo.placeholder = 'načítám další číslo…';
  (window.BDForm && window.BDForm.nextNumber ? window.BDForm.nextNumber(OBJ ? 'objednavka' : 'nabidka') : Promise.resolve('')).then(function (c) { if (!form.obj_cislo.value) form.obj_cislo.value = c; form.obj_cislo.placeholder = c ? '' : 'např. N 12/2026'; });
  form.obj_datum.value = new Date().toISOString().slice(0, 10);
  var nd = new Date(); nd.setDate(nd.getDate() + 14); if (form.nabidka_do) form.nabidka_do.value = nd.toISOString().slice(0, 10);   // výchozí platnost 14 dní – lze změnit
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
      if (o.typ === 'nabidka' && Array.isArray(o.polozky) && KATS.indexOf(o.kategorie) >= 0) {
        st.kat = o.kategorie;
        o.polozky.filter(function (p0) { return p0 && p0.ks != null; }).forEach(function (p0) {
          var r0 = newRow(); r0.nazev = p0.nazev || ''; r0.ks = int(p0.ks) || 1; r0.cena = int(p0.cena_ks); r0.sleva = int(p0.sleva); r0.popis = p0.popis || ''; r0.img = p0.obrazek || ''; r0.model = p0.model_kod || ''; r0.odstin = p0.odstin || ''; r0.cfg = p0.cfg || null;
          (p0.spec || []).forEach(function (x) { var v = String(x[1] || ''); if (x[0] === 'Provedení') r0.prov = v; else if (x[0] === 'Odstín / dekor') r0.odstin = v; else if (x[0] === 'Šířka křídla') r0.sirka = v.replace(/\s*cm$/i, ''); else if (x[0] === 'Otevírání') r0.otev = v; else if (x[0] === 'Zárubeň') r0.zarub = v; else if (x[0] === 'Tloušťka zárubně') r0.tl = v; else if (x[0] === 'Kování') r0.kov = v; });
          r0.montaz = (p0.spec || []).some(function (x) { return x[0] === 'Montáž'; });
          var hit = ''; catList().forEach(function (c) { if (!hit && r0.nazev.toUpperCase().indexOf(String(c[0]).toUpperCase()) >= 0) hit = c[0]; }); r0.cat = hit || '__jiny'; r0.cenaRuc = true; autoPrice(r0);
          st.rows.push(r0);
        });
        if (o.sleva_celkem) { form.sleva_kc.value = o.sleva_celkem; form.sleva_text.value = o.sleva_text || ''; }
        if (o.zahrnuje && form.zahrnuje) form.zahrnuje.value = o.zahrnuje;
        if (o.termin_text && form.termin_text) form.termin_text.value = o.termin_text;
        if (o.prodavac) { var pr0 = document.querySelector('input[name="prodavac"][value="' + o.prodavac + '"]'); if (pr0) pr0.checked = true; }
        if (o.rozpocet && (o.kategorie === 'dum' || o.kategorie === 'interier')) {   // nabídka s rozpočtem z jiného programu → stejný režim, PDF rozpočtu se nahraje znovu
          ensureRozpUI(); st.rozp = true;
          form.elements.rozp_popis.value = o.rozpocet.popis || ''; form.elements.rozp_cislo.value = o.rozpocet.cislo || ''; form.elements.rozp_cena.value = o.cena_celkem || '';
          st.rozpNote = ' Nahrajte k objednávce znovu rozpočet v PDF' + (o.rozpocet.soubor ? ' (v nabídce byl „' + esc(o.rozpocet.soubor) + '“)' : '') + ' – stejný, nebo upravený podle domluvy.';
        }
      } else if (o.ai && (o.kategorie === 'interier' || o.kategorie === 'dum') && Array.isArray(o.polozky) && o.polozky.length) {
        /* návrh nabídky od pomocníka v kartotéce (navrh-nabidky.php): model a odstín z katalogu, cena z akčního ceníku (u interiérů), nejistoty žlutě */
        st.kat = o.kategorie;
        o.polozky.forEach(function (p2) {
          var rw = newRow(), nm = String(p2.model || '');
          if (nm && catFind(nm)) applyCat(rw, nm); else { rw.cat = '__jiny'; rw.nazev = String(p2.nazev || nm || ''); }
          if (p2.nazev && rw.cat === '__jiny') rw.nazev = String(p2.nazev);
          var cx = catFind(rw.cat);
          if (p2.odstin) { rw.odstin = String(p2.odstin); if (cx && cx[5]) cx[5].forEach(function (d2) { if (d2[0] === rw.odstin) { rw.shadeImg = 'aplikace/' + d2[1]; rw.img = rw.shadeImg; } }); }
          rw.ks = limited() ? 1 : (int(p2.ks) || 1);
          if (p2.sirka) rw.sirka = String(p2.sirka).replace(/\s*cm$/i, '');
          if (p2.otev) rw.otev = String(p2.otev); if (p2.zarub) rw.zarub = String(p2.zarub); if (p2.kov) rw.kov = String(p2.kov); if (p2.popis) rw.popis = String(p2.popis);
          if (p2.tl) rw.tl = String(p2.tl);
          if (st.kat === 'interier') rw.montaz = p2.montaz !== false;
          rw.cenaRuc = false; autoPrice(rw);
          rw.ai = (p2.nejiste || []).map(String).filter(Boolean);
          if (!rw.cena) rw.ai.push(st.kat === 'dum' ? 'Cenu doplňte ručně (rozpočet ERKADO z jiného programu).' : 'Cena se z ceníku nedoplnila – zadejte ji ručně.');
          st.rows.push(rw);
        });
      } else if (m) {
        st.kat = 'byt'; var r = newRow(); r.model = m.k; r.odstin = o.odstin || ''; r.nazev = m.name + (r.odstin ? ' – ' + r.odstin : '');
        r.cena = int(o.cena_celkem) || modelPrice(r) || m.price; r.img = m.img || '';
        m.shades.forEach(function (s0) { if (s0.n === r.odstin) r.shadeImg = s0.img || ''; });
        r.cfg = {}; ['model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'tl_zarubne'].forEach(function (k) { if (o[k]) r.cfg[k] = o[k]; });
        r.spec = fixSpec((o.polozky || []).filter(function (p0) { return p0 && p0.co; }).map(function (p0) { return [p0.co, p0.volba || '', p0.priplatek || 0, p0.info || '']; }));
        if (o.otevirani && !r.spec.some(function (x) { return x[0] === 'Otevírání'; })) r.spec.push(['Otevírání', o.otevirani, 0, '']);
        if (o.tl_zarubne && !r.spec.some(function (x) { return /zárubn/i.test(x[0]) && /Tlou/i.test(x[0]); })) r.spec.push(['Tloušťka zárubně', o.tl_zarubne, 0, '']);
        if (!r.spec.length) ['zarubne:Zárubeň', 'kovani:Kování', 'kukatko:Kukátko', 'vlozky:Vložky', 'sire:Šířka', 'zaruka:Záruka'].forEach(function (q) { var k = q.split(':'); if (o[k[0]]) r.spec.push([k[1], o[k[0]] + (k[0] === 'sire' && /^\d+$/.test(o[k[0]]) ? ' cm' : ''), 0, '']); });
        st.rows.push(r);
      } else if ((o.kategorie === 'interier' || o.kategorie === 'dum') && /^web-poptavka-/.test(o.zdroj || '') && Array.isArray(o.polozky) && o.polozky.length) {
        /* poptávka z webu (poptavka-kat.js): každá položka = model + odstín + počet kusů */
        st.kat = o.kategorie;
        o.polozky.forEach(function (p1) {
          var rw = newRow(), tx = String(p1.co || '').toUpperCase(), ht = '', best = 0;
          catList().forEach(function (c) { var n = String(c[0]).toUpperCase(); if (tx === n || (tx.indexOf(n) >= 0 && n.length > best && !ht)) { ht = c[0]; best = n.length; } });
          if (ht) applyCat(rw, ht); else { rw.cat = '__jiny'; rw.nazev = String(p1.co || ''); }
          var cx = catFind(ht); if (p1.odstin) { rw.odstin = p1.odstin; if (cx && cx[5]) cx[5].forEach(function (d1) { if (d1[0] === p1.odstin) { rw.shadeImg = 'aplikace/' + d1[1]; rw.img = rw.shadeImg; } }); }
          rw.ks = int(p1.ks) || 1; autoPrice(rw); st.rows.push(rw);
        });
      } else if (/interi/i.test(o.model || o.typ_dveri || '') || /dom|erkado/i.test((o.model || '') + ' ' + (o.typ_dveri || ''))) {
        st.kat = /interi/i.test((o.model || '') + ' ' + (o.typ_dveri || '')) ? 'interier' : 'dum';
        var r2 = newRow(), txt = String(o.model || '').toUpperCase(), hit = '';
        catList().forEach(function (c) { if (!hit && txt.indexOf(c[0].toUpperCase()) >= 0) hit = c[0]; });
        if (hit) applyCat(r2, hit); else { r2.cat = '__jiny'; r2.nazev = String(o.model || '').replace(/^.*?[–-]\s*/, '') || String(o.model || ''); }
        var c2 = catFind(hit); if (o.odstin) { r2.odstin = o.odstin; if (c2 && c2[5]) c2[5].forEach(function (d0) { if (d0[0] === o.odstin) { r2.shadeImg = 'aplikace/' + d0[1]; r2.img = r2.shadeImg; } }); }
        if (o.provedeni && !r2.prov) r2.prov = o.provedeni;
        r2.ks = int(o.pocet_ks) || 1; st.rows.push(r2);
      }
      setDefaults();
      if (o.ai) {
        st.aiNavrh = o.ai_navrh || ''; if (!st.kat && KATS.indexOf(o.kategorie) >= 0) { st.kat = o.kategorie; setDefaults(); }
        if (o.interni && form.elements.interni) form.elements.interni.value = o.interni;
        var inf = $('#nbLoaded'), pzn = (o.ai_poznamky || []).map(String).filter(Boolean);
        st.rows.forEach(function (rw, i) { (rw.ai || []).forEach(function (t) { pzn.push('Položka ' + (i + 1) + ': ' + t); }); });
        inf.hidden = false; inf.style.background = '#fff7d6'; inf.style.borderColor = '#e0b100';
        inf.innerHTML = '🤖 <b>Návrh nabídky připravil pomocník</b> z poptávky <b>' + esc(o.cislo || '') + '</b>. Nic se neodeslalo – vše zkontrolujte, doplňte a teprve pak odešlete.' +
          (pzn.length ? '<br><b>Ověřte / doplňte:</b><ul style="margin:.3rem 0 0 1.2rem;padding:0">' + pzn.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>' : '');
        return;
      }
      var info = $('#nbLoaded'); info.hidden = false; info.innerHTML = '✓ Načteno z ' + (o.typ === 'nabidka' ? 'nabídky' : 'poptávky') + ' <b>' + esc(o.cislo || '') + '</b>. ' + (st.rozp ? 'Předmět a cena jsou přenesené.' + (st.rozpNote || '') : 'Položky a ceny jsou přenesené; zkontrolujte je a doplňte, co chybí.');
    } catch (e) {}
  })();
  renderKat(); renderRows();
})();
