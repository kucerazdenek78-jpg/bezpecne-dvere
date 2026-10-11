/* Poptávka dveří – vlastní formulář (náhrada Jotform 253084834051050)
   Ceny a volby: dvere-do-bytu.html (ceny) + Jotform poptávka (volby), stav 11. 10. 2026.
   Odeslání: FormSubmit (klasicky přes skrytý iframe kvůli přílohám: PDF + fotky) → kucerazd@kksystem.cz + kopie chci@bezpecne-dvere.cz.
   Pole „data_json“ nese strojově čitelná data pro pozdější import do systému na NAS. */
(function () {
  'use strict';
  var TO = 'kucerazd@kksystem.cz';
  var CC = 'chci@bezpecne-dvere.cz';
  var CC_OBJ = 'kucerovadn@kksystem.cz,obchod@bezpecne-dvere.cz';   // objednávky: oba prodavači + vždy kopie obchod@
  var PRODAVACI = ['Kučerová', 'Kučera Zd.'];
  var C = 'userFiles/barva-dveri/';
  /* Mapy.com REST API (adresy + trasa). Klíč je veřejný v prohlížeči – omezit v developer.mapy.com
     („Security by HTTP referers“) na kucerazdenek78-jpg.github.io a bezpecne-dvere.cz. */
  var MAPY_KEY = '2J4qFfalnxab9uD2PnIQrF57PRw2Qnmel9a5Vt4x6-k';
  var MAPY = 'https://api.mapy.com/v1/';
  var SHOWROOM = 'Obchodní 36, Most';      // odkud se měří doprava
  var DOPRAVA = [[40, 0], [80, 1200]];     // do 40 km zdarma, do 80 km 1 200 Kč, nad 80 km upřesníme
  var SCHODY = 250;                        // Kč za patro bez výtahu

  /* ── barvy kování pro kreslené obrázky ── */
  var COL = { inox: '#b9c0c6', chrom: '#cfd4d8', cerna: '#26292b', bronz: '#8a6a43' };

  var MODELS = {
    premium60: {
      name: 'PREMIUM 60', spec: 'RC3 · EI 60 · 42 dB', tag: 'TOP',
      img: 'poptavka-imgs/premium60.jpg', price: 38890, regular: 42500,
      baseWarranty: '2 roky + 1 rok za registraci',
      shades: [
        ['Černá perleť', C + 'premium60/ctverce/premium-60-cerna-perlet.png', 1],
        ['Dub', C + 'premium60/ctverce/premium-60-dub.png', 1],
        ['Ořech', C + 'premium60/ctverce/premium-60-brazilsky-orech.png', 1],
        ['Světlé wenge', C + 'premium60/ctverce/premium-60-svetla-wenge.png', 1],
        ['Bílá', C + 'premium60/ctverce/premium-60-bila.png', 1],
        ['Antracit', C + 'premium60/ctverce/premium-60-antracit.png', 1],
        ['Bílá wenge', C + 'premium60/ctverce/premium-60-bila-wenge.png', 0, 4000],
        ['Ocelově šedá', C + 'premium60/ctverce/premium-60-ocelove-seda.png', 0, 4000]
      ],
      shadeHint: 'skladem = montáž do 14 dnů · příplatkové odstíny 8–12 týdnů',
      hwLegend: 'Kování a kukátko',
      hardware: [
        { n: 'Zaoblené černé kování, černé krytky a černé kukátko', d: ['round', 'cerna'] },
        { n: 'Zaoblené INOX kování, inox krytky a stříbrné kukátko', d: ['round', 'inox'] },
        { n: 'Hranaté INOX kování, inox krytky a stříbrné kukátko', d: ['square', 'inox'] }
      ],
      widths: [['80'], ['90'], ['Nevím']],
      widthHint: 'Standardní šířka je 80 cm. Šířku 90 cm s vámi ověříme.',
      warranty: [['Ne, stačí základní 2 roky', 0], ['Ano, na 5 let', 1000], ['Ano, na 10 let', 2000]],
      included: 'Nová zárubeň a práh v dekoru dveří, kování, 5 klíčů s kartou, kukátko, montáž na klíč, odvoz starých dveří a hrubý úklid.'
    },
    extreme54: {
      name: 'EXTREME 54', spec: 'RC3 · EI 30 · 40 dB', tag: 'Výhodná cena',
      img: 'poptavka-imgs/extreme54.jpg', price: 31890, regular: 32890,
      baseWarranty: '5 let + 1 rok za registraci',
      shades: [
        ['Ořech natura', C + 'extreme50/extreme-rc3-orech-naturalny.png.tn.jpg', 1],
        ['Dub natura', C + 'extreme50/extreme-rc3-dub-naturalny.png.tn.jpg', 1],
        ['Ořech bělený', C + 'extreme50/extreme-rc3-orech-beleny.png.tn.jpg', 0],
        ['Bílá', C + 'extreme50/extreme-rc3-bila.png.tn.jpg', 0],
        ['Antracit', C + 'extreme50/extreme-rc3-antracit.png.tn.jpg', 0],
        ['Beton tmavý', 'color:#5d5f60', 0, 5000],
        ['Dub Milano 4', 'color:#a07a52', 0, 5000]
      ],
      shadeHint: 'skladem = montáž do 14 dnů',
      frames: [
        ['Hnědá matná', '#5a3b26', 1], ['Béžová matná', '#d8c7a8', 0], ['Bílá matná', '#f4f4f1', 0],
        ['Šedá', '#8f9396', 0], ['Antracit', '#3b3f42', 0], ['Černá', '#151515', 0]
      ],
      hwLegend: 'Kování a kukátko',
      hardware: [
        { n: 'INOX kování, stříbrné kukátko', d: ['round', 'inox'], p: 0 },
        { n: 'Černé kování, černé kukátko', d: ['round', 'cerna'], p: 500 },
        { n: 'Bronzové kování, bronzové kukátko', d: ['round', 'bronz'], p: 500 }
      ],
      widths: [['80'], ['90'], ['Nevím']],
      widthHint: 'Standardní šířka je 80 cm. Šířku 90 cm s vámi ověříme.',
      warranty: [['Ne, stačí 5 let', 0], ['Ano, na 10 let', 2000]],
      included: 'Nová lakovaná zárubeň, nerezový práh, kování, 3 klíče s kartou, kukátko, montáž na klíč, odvoz starých dveří a hrubý úklid.'
    },
    magnum56: {
      name: 'MAGNUM 56', spec: 'RC3 · EI 30 · 44 dB',
      img: 'poptavka-imgs/magnum56.jpg', price: 36990,
      baseWarranty: '2 roky',
      shades: [
        ['Dub sonoma', C + 'magnum56/magnum56_dub_sonoma.png.tn.jpg', 0],
        ['Bílá', C + 'magnum56/magnum56_bila.png.tn.jpg', 0],
        ['Světlý ořech', C + 'magnum56/magnum56_svetly_orech.png.tn.jpg', 0],
        ['Zlatý dub', C + 'magnum56/magnum56_zlaty_dub.png.tn.jpg', 0],
        ['Antracit', C + 'magnum56/magnum56_antracit.png.tn.jpg', 0, 1000],
        ['Tmavý ořech', C + 'magnum56/magnum56_tmavy_orech.png.tn.jpg', 0],
        ['Venge', C + 'magnum56/magnum56_venge.png.tn.jpg', 0],
        ['Šedá', C + 'magnum56/magnum56_seda.png.tn.jpg', 0],
        ['Dub přírodní', C + 'magnum56/magnum56_dub_prirodni.png.tn.jpg', 0],
        ['Beton', C + 'magnum56/magnum56_beton.png.tn.jpg', 0, 1000]
      ],
      shadeHint: 'všechny odstíny na objednávku',
      hwLegend: 'Kování',
      hardware: [
        { n: 'Klika – koule', d: ['knob', 'inox'], p: 0 },
        { n: 'Klika – koule černá', d: ['knob', 'cerna'], p: 1500 },
        { n: 'Klika – klika', d: ['lever2', 'inox'], p: 500 },
        { n: 'Klika – klika černá', d: ['lever2', 'cerna'], p: 2000 }
      ],
      peep: [
        { n: 'Delta', d: ['delta', 'inox'], p: 0 },
        { n: 'Delta černá', d: ['delta', 'cerna'], p: 150 },
        { n: 'Kyklop', d: ['kyklop', 'inox'], p: 600 },
        { n: 'Kyklop černá', d: ['kyklop', 'cerna'], p: 750 }
      ],
      locks: [
        { n: 'Nesjednocené zámky, ke každé vložce vlastní klíče', d: 2, p: 0 },
        { n: 'Sjednocené zámky s vrtulkou nahoře, 5 klíčů', d: 1, p: 1500 },
        { n: 'Sjednocené zámky s vrtulkami dole i nahoře, 7 klíčů', d: 0, p: 2000 }
      ],
      widths: [['80'], ['90'], ['Nevím']],
      widthHint: 'Šířku 90 cm s vámi ověříme.',
      warranty: [['Ne, stačí základní 2 roky', 0], ['Ano, na 5 let', 1000], ['Ano, na 10 let', 2000]],
      masonry: true,
      included: 'Nová zárubeň v dekoru dveří, integrovaný nerezový práh, kování, 2× 5 klíčů, kukátko, montáž na klíč, odvoz starých dveří a hrubý úklid.'
    },
    securido65: {
      name: 'SECURIDO 65', spec: 'RC3 · EI 30 · 39 dB',
      img: 'poptavka-imgs/securido65.jpg', price: 35890, regular: 37890,
      baseWarranty: '2 roky',
      shades: [
        ['Kombi (bílá, černá zárubeň a kování)', C + 'securido/kombi.jpg', 0, 2000],
        ['Bílá', C + 'securido/bile-hladke.png.tn.jpg', 0],
        ['Šedá', C + 'securido/seda.jpg', 0],
        ['Mocca', C + 'securido/mocca.jpg', 0],
        ['Antracit', C + 'securido/antracit-hladke.png.tn.jpg', 0],
        ['Dub Artisan hladký', C + 'securido/dub-artison-hladke.png.tn.jpg', 0],
        ['Dub Artisan elegant', C + 'securido/dub-artison-elegance.png.tn.jpg', 0],
        ['Ořech hladký', C + 'securido/orech-hladke.png.tn.jpg', 0],
        ['Ořech elegant', C + 'securido/orech-elegant.png.tn.jpg', 0],
        ['Mahagon hladký', C + 'securido/mahagon-hladke.jpg', 0],
        ['Mahagon elegant', C + 'securido/mahagon-elegant.jpg', 0]
      ],
      shadeHint: 'všechny odstíny na objednávku',
      hwLegend: 'Kování (vše v ceně)',
      hardware: [
        { n: 'Hranaté kování, chrom matný', d: ['square', 'chrom'], p: 0 },
        { n: 'Oblé kování, chrom matný', d: ['round', 'chrom'], p: 0 },
        { n: 'Hranaté kování, černé', d: ['square', 'cerna'], p: 0 },
        { n: 'Oblé kování, černé', d: ['round', 'cerna'], p: 0 }
      ],
      widths: [['80'], ['90', 1500], ['Nevím']],
      widthHint: '',
      warranty: [['Ne, stačí základní 2 roky', 0], ['Ano, na 5 let', 1000], ['Ano, na 10 let', 2000]],
      masonry: true,
      included: 'Nová zárubeň v dekoru dveří, integrovaný nerezový práh, bezpečnostní kování, 6 klíčů + 2 montážní, kukátko, montáž na klíč, odvoz starých dveří a hrubý úklid.'
    }
  };
  /* údaje jen pro objednávku (podle papírových vzorů prodejce/objednavka-*.pdf, revize 10/2026) */
  MODELS.premium60.order = { frame: ['11,5 cm'], zaloha: 10000, zalohaLabel: 'Rezervace', extras: [
    { id: 'klice', n: 'Klíče navíc', p: 400, type: 'qty' }] };
  MODELS.extreme54.order = { frame: ['15 cm', '10 cm'], zaloha: 'sklad', zalohaLabel: 'Záloha', extras: [
    { id: 'vrtulka', n: 'Vložka s vrtulkou a 5 klíči', p: 1000, type: 'check' },
    { id: 'klice', n: 'Klíče navíc', p: 400, type: 'qty' }] };
  MODELS.magnum56.order = { frame: ['15 cm', '9 cm'], zaloha: null, zalohaLabel: 'Záloha', openOut: true, extras: [
    { id: 'klice', n: 'Klíče navíc', p: 400, type: 'qty' },
    { id: 'omezovac', n: 'Omezovač otevírání (jen pro 15 cm zárubeň)', p: 800, type: 'check' },
    { id: 'venecek', n: 'Držák věnečku', p: 299, type: 'check' },
    { id: 'zednicke', n: 'Zednické práce', type: 'amount' }] };
  MODELS.securido65.order = { frame: ['10 cm', '15 cm'], zaloha: 10000, zalohaLabel: 'Rezervace', extras: [
    { id: 'klice', n: 'Klíče navíc', p: 400, type: 'qty' },
    { id: 'zednicke', n: 'Zednické práce', type: 'choice', opts: [['Panel 11 cm pouzdro', 0], ['Zapuštěné pouzdro', 2000], ['Cihla do 16 cm', 2000], ['Cihla 17 cm a víc', 3000]] }] };

  var ORDER = ['premium60', 'extreme54', 'magnum56', 'securido65'];
  var OPENING = ['Levé dovnitř (klika na pravé straně)', 'Pravé dovnitř (klika na levé straně)'];
  var OPENING_OUT = ['Levé ven (klika na pravé straně)', 'Pravé ven (klika na levé straně)'];
  var BILLING = ['Stejná jako instalační', 'Jiná adresa – soukromá osoba', 'Jiná adresa – firma nebo OSVČ'];
  var WALL = ['Beton / panel', 'Cihla', 'Nevím'];

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var kc = function (n) { return n.toLocaleString('cs-CZ').replace(/ /g, ' ') + ' Kč'; };
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var form = $('#pq');
  var MODE = form.getAttribute('data-mode') === 'objednavka' ? 'objednavka' : 'poptavka';
  var OBJ = MODE === 'objednavka';
  function fv(n) { var el = form.elements[n]; return el && el.value != null ? String(el.value).trim() : ''; }
  function num(n) { var x = parseInt(fv(n).replace(/\s/g, ''), 10); return isNaN(x) ? 0 : x; }
  var state = { model: null, step: 1, photos: {}, thumbs: {}, addr: null, trip: null };

  /* ── kreslené obrázky (SVG) ── */
  function svgHardware(shape, col) {
    var c = COL[col], o = col === 'cerna' ? '#000' : 'rgba(0,0,0,.25)';
    var plate = '<rect x="58" y="6" width="22" height="72" rx="' + (shape === 'square' ? 2 : 11) + '" fill="' + c + '" stroke="' + o + '"/>';
    var cyl = '<rect x="65" y="52" width="8" height="14" rx="' + (shape === 'square' ? 1 : 4) + '" fill="#555"/>';
    var lever = shape === 'square'
      ? '<rect x="22" y="22" width="52" height="11" rx="1.5" fill="' + c + '" stroke="' + o + '"/>'
      : '<rect x="22" y="22" width="52" height="11" rx="5.5" fill="' + c + '" stroke="' + o + '"/>';
    var bg = '<rect x="0" y="0" width="140" height="84" fill="#f2f5f3" rx="8"/>';
    if (shape === 'knob') {
      return svg(bg + '<rect x="28" y="6" width="22" height="72" rx="11" fill="' + c + '" stroke="' + o + '"/><circle cx="39" cy="28" r="12" fill="' + c + '" stroke="' + o + '"/><rect x="35" y="52" width="8" height="14" rx="4" fill="#555"/>' +
        '<rect x="88" y="6" width="22" height="72" rx="11" fill="' + c + '" stroke="' + o + '"/><rect x="62" y="22" width="44" height="11" rx="5.5" fill="' + c + '" stroke="' + o + '"/>');
    }
    if (shape === 'lever2') {
      return svg(bg + '<rect x="40" y="6" width="22" height="72" rx="11" fill="' + c + '" stroke="' + o + '"/><rect x="8" y="22" width="48" height="11" rx="5.5" fill="' + c + '" stroke="' + o + '"/><rect x="47" y="52" width="8" height="14" rx="4" fill="#555"/>' +
        '<rect x="100" y="6" width="22" height="72" rx="11" fill="' + c + '" stroke="' + o + '"/><rect x="74" y="22" width="44" height="11" rx="5.5" fill="' + c + '" stroke="' + o + '"/>');
    }
    return svg(bg + plate + lever + cyl + '<circle cx="112" cy="26" r="7" fill="' + c + '" stroke="' + o + '"/><circle cx="112" cy="26" r="3" fill="#2a3a44"/>');
  }
  function svgPeep(kind, col) {
    var c = COL[col], o = col === 'cerna' ? '#000' : 'rgba(0,0,0,.3)';
    var r = kind === 'kyklop' ? 26 : 16;
    return svg('<rect x="0" y="0" width="140" height="84" fill="#f2f5f3" rx="8"/><circle cx="70" cy="42" r="' + r + '" fill="' + c + '" stroke="' + o + '" stroke-width="1.5"/><circle cx="70" cy="42" r="' + (r * 0.55) + '" fill="#1c2b36"/><circle cx="' + (70 - r * 0.2) + '" cy="' + (42 - r * 0.2) + '" r="' + (r * 0.15) + '" fill="rgba(255,255,255,.6)"/>');
  }
  function svgLocks(unified) {
    // 2 vložky na dveřích; čísla klíčů
    var keyCol = ['#c2410c', '#15803d'];
    var cyl = function (y, k) { return '<rect x="52" y="' + y + '" width="14" height="22" rx="7" fill="#c8cdd1" stroke="rgba(0,0,0,.3)"/><rect x="57" y="' + (y + 4) + '" width="4" height="14" rx="1" fill="' + keyCol[k] + '"/>'; };
    var keyIco = function (x, y, k) { return '<g transform="translate(' + x + ' ' + y + ')"><circle cx="0" cy="0" r="7" fill="none" stroke="' + keyCol[k] + '" stroke-width="3"/><rect x="6" y="-1.5" width="22" height="3" fill="' + keyCol[k] + '"/><rect x="22" y="1" width="3" height="5" fill="' + keyCol[k] + '"/></g>'; };
    var k2 = unified ? 0 : 1;
    return svg('<rect x="0" y="0" width="140" height="84" fill="#f2f5f3" rx="8"/><rect x="30" y="4" width="58" height="76" rx="3" fill="#fff" stroke="#c9d6ce"/>' +
      cyl(10, 0) + cyl(50, k2) + keyIco(104, 22, 0) + keyIco(104, 60, k2) + (unified ? '<text x="98" y="45" font-size="11" font-weight="700" fill="#123d27">=</text>' : ''));
  }
  function svgOpening(left) {
    // pohled zvenku: pantem na jedné straně, klika na druhé, šipka dovnitř
    var hx = left ? 34 : 106, kx = left ? 96 : 44;
    return svg('<rect x="0" y="0" width="140" height="84" fill="#f2f5f3" rx="8"/><rect x="28" y="6" width="84" height="74" fill="#fff" stroke="#123d27" stroke-width="2.5"/>' +
      '<rect x="' + (hx - 3) + '" y="16" width="6" height="10" fill="#123d27"/><rect x="' + (hx - 3) + '" y="58" width="6" height="10" fill="#123d27"/>' +
      '<rect x="' + (kx - (left ? 0 : 18)) + '" y="40" width="18" height="5" rx="2.5" fill="#c2410c"/><circle cx="' + kx + '" cy="42.5" r="4" fill="#c2410c"/>' +
      '<path d="M70 30v22m-6-6 6 6 6-6" fill="none" stroke="#1f6b43" stroke-width="2.5" stroke-linecap="round" transform="rotate(0)"/>');
  }
  function svgWall(kind) {
    var bg = '<rect x="0" y="0" width="140" height="84" fill="#f2f5f3" rx="8"/>';
    if (kind === 0) {
      var dots = ''; for (var i = 0; i < 26; i++) dots += '<circle cx="' + (22 + (i * 37) % 96) + '" cy="' + (14 + (i * 23) % 58) + '" r="' + (1 + i % 3) + '" fill="#8d9296"/>';
      return svg(bg + '<rect x="16" y="8" width="108" height="68" rx="3" fill="#c4c8cb"/>' + dots);
    }
    if (kind === 1) {
      var b = ''; for (var r = 0; r < 5; r++) for (var c = -1; c < 5; c++) { var x = 16 + c * 26 + (r % 2 ? 13 : 0); if (x + 24 < 16 || x > 124) continue; var x1 = Math.max(16, x), x2 = Math.min(124, x + 24); b += '<rect x="' + x1 + '" y="' + (8 + r * 13.6) + '" width="' + (x2 - x1) + '" height="11.6" fill="#b5532f"/>'; }
      return svg(bg + '<rect x="16" y="8" width="108" height="68" rx="3" fill="#e9dccf"/>' + b);
    }
    return svg(bg + '<text x="70" y="58" text-anchor="middle" font-size="44" font-weight="700" fill="#5f7568">?</text>');
  }
  function svg(inner) { return '<svg viewBox="0 0 140 84" aria-hidden="true">' + inner + '</svg>'; }

  /* ── stavění voleb ── */
  function radio(name, value, html, cls, extra) {
    return '<label class="pq-opt ' + cls + '"><input type="radio" name="' + name + '" value="' + esc(value) + '"' + (extra || '') + '>' + html + '</label>';
  }
  function plus(p) { return p ? '<em>+ ' + kc(p) + '</em>' : '<em class="free">v ceně</em>'; }

  function buildModels() {
    $('#pqModels').innerHTML = ORDER.map(function (k) {
      var m = MODELS[k];
      return radio('model', k,
        (m.tag ? '<span class="pq-tag">' + m.tag + '</span>' : '') +
        '<img src="' + m.img + '" alt="' + m.name + '" loading="lazy"><b>' + m.name + '</b><span class="pq-spec">' + m.spec + '</span>' +
        '<span class="pq-price">' + (m.regular ? '<s>' + kc(m.regular) + '</s>' : '') + kc(m.price) + '</span>', 'pq-model');
    }).join('');
  }

  function buildOptions(k) {
    var m = MODELS[k];
    $('#pqShadeHint').textContent = m.shadeHint ? '(' + m.shadeHint + ')' : '';
    $('#pqShades').innerHTML = m.shades.map(function (s) {
      var bg = s[1].indexOf('color:') === 0 ? 'background-color:' + s[1].slice(6) : 'background-image:url(\'' + s[1] + '\')';
      var note = s[3] ? '<small class="pl">na objednávku + ' + kc(s[3]) + '</small>' : (s[2] ? '<small class="sk">skladem</small>' : '<small class="ob">na objednávku</small>');
      return radio('odstin', s[0], '<div class="pq-swimg" style="' + bg + '"></div><span>' + esc(s[0]) + '</span>' + note, 'pq-sw', ' data-p="' + (s[3] || 0) + '"');
    }).join('');

    $('#pqFrameBlock').hidden = !m.frames;
    $('#pqFrames').innerHTML = (m.frames || []).map(function (f) {
      return radio('zarubne', f[0], '<i style="background:' + f[1] + '"></i>' + esc(f[0]) + ' <small>' + (f[2] ? 'skladem' : 'na objednávku') + '</small>', 'pq-chip');
    }).join('');

    $('#pqHwLegend').textContent = m.hwLegend;
    $('#pqHardware').innerHTML = m.hardware.map(function (h) {
      return radio('kovani', h.n, svgHardware(h.d[0], h.d[1]) + '<span>' + esc(h.n) + '</span>' + (h.p === undefined ? '' : plus(h.p)), 'pq-tile', ' data-p="' + (h.p || 0) + '"');
    }).join('');

    $('#pqPeepBlock').hidden = !m.peep;
    $('#pqPeep').innerHTML = (m.peep || []).map(function (h) {
      return radio('kukatko', h.n, svgPeep(h.d[0], h.d[1]) + '<span>' + esc(h.n) + '</span>' + plus(h.p), 'pq-tile', ' data-p="' + h.p + '"');
    }).join('');

    $('#pqLockBlock').hidden = !m.locks;
    $('#pqLocks').innerHTML = (m.locks || []).map(function (h) {
      return radio('vlozky', h.n, svgLocks(h.d < 2) + '<span>' + esc(h.n) + '</span>' + plus(h.p), 'pq-tile', ' data-p="' + h.p + '"');
    }).join('');

    var opens = OPENING.slice(); if (OBJ && m.order && m.order.openOut) opens = opens.concat(OPENING_OUT);
    $('#pqOpening').innerHTML = opens.map(function (o, i) {
      return radio('otevirani', o, svgOpening(i % 2 === 0) + '<span>' + esc(o) + '</span>', 'pq-tile');
    }).join('');
    if (OBJ) buildOrderOptions(k);

    $('#pqWidth').innerHTML = m.widths.map(function (w) {
      var lbl = w[0] === 'Nevím' ? 'Nevím' : w[0] + ' cm';
      return radio('sire', w[0], lbl + (w[1] ? ' <em>+ ' + kc(w[1]) + '</em>' : ''), 'pq-chip', ' data-p="' + (w[1] || 0) + '"');
    }).join('');
    $('#pqWidthHint').textContent = m.widthHint || '';
    $('#pqWidthHint').hidden = !m.widthHint;

    $('#pqWarranty').innerHTML = m.warranty.map(function (w, i) {
      return radio('zaruka', w[0], esc(w[0]) + (w[1] ? ' <em>+ ' + kc(w[1]) + '</em>' : ''), 'pq-chip', ' data-p="' + w[1] + '"' + (i === 0 ? ' checked' : ''));
    }).join('');

    $('#pqModelOptions').hidden = false;
  }

  function buildStatic() {
    $('#pqBilling').innerHTML = BILLING.map(function (b, i) { return radio('fakturace', b, esc(b), 'pq-chip', i === 0 ? ' checked' : ''); }).join('');
    $('#pqWall').innerHTML = WALL.map(function (w, i) { return radio('zed', w, svgWall(i) + '<span>' + w + '</span>', 'pq-tile'); }).join('');
  }

  /* ── objednávka: volby navíc ── */
  function buildOrderOptions(k) {
    var m = MODELS[k], o = m.order;
    var fr = $('#poFrame');
    fr.innerHTML = o.frame.map(function (f) { return radio('tl_zarubne', f, esc(f), 'pq-chip', o.frame.length === 1 ? ' checked' : ''); }).join('');
    $('#poExtras').innerHTML = (o.extras || []).map(function (x) {
      if (x.type === 'check') return '<label class="pq-check"><input type="checkbox" name="x_' + x.id + '"> ' + esc(x.n) + ' <em class="po-p">+ ' + kc(x.p) + '</em></label>';
      if (x.type === 'qty') return '<label class="pq-f pq-f-short">' + esc(x.n) + ' (' + kc(x.p) + '/ks)<input name="x_' + x.id + '" inputmode="numeric" placeholder="0"></label>';
      if (x.type === 'amount') return '<div class="pq-grid2"><label class="pq-f">' + esc(x.n) + ' – popis<input name="x_' + x.id + '_text" placeholder="např. vybourání, zednické začištění"></label><label class="pq-f">Cena (Kč)<input name="x_' + x.id + '" inputmode="numeric" placeholder="0"></label></div>';
      if (x.type === 'choice') return '<div class="pq-block-in"><p class="pq-help" style="margin:0 0 .6rem">' + esc(x.n) + ' *</p><div class="pq-chips">' + x.opts.map(function (op) {
        return radio('x_' + x.id, op[0], esc(op[0]) + ' <em>' + (op[1] ? '+ ' + kc(op[1]) : '0 Kč') + '</em>', 'pq-chip', ' data-p="' + op[1] + '"');
      }).join('') + '</div></div>';
      return '';
    }).join('') || '<p class="pq-help" style="margin:0">K tomuto modelu není žádná příplatková výbava navíc.</p>';
    if (!state.zalohaTouched) setZaloha();
  }
  function zalohaLabel() { var m = MODELS[state.model]; return m && m.order ? m.order.zalohaLabel : 'Záloha'; }
  function defaultZaloha() {
    var m = MODELS[state.model]; if (!m) return 0; var z = m.order.zaloha;
    if (z === 'sklad') { var sk = false; m.shades.forEach(function (sh) { if (sh[0] === val('odstin') && sh[2]) sk = true; }); return val('odstin') ? (sk ? 0 : 10000) : 0; }
    return z == null ? 0 : z;
  }
  function setZaloha() { var el = form.elements.zaloha_kc; if (el) el.value = defaultZaloha(); }
  function zaloha() { return form.elements.zaloha_kc ? num('zaloha_kc') : 0; }

  /* ── objednávka: načtení poptávky z odkazu ── */
  function setRadio(name, value) {
    if (value == null || value === '') return;
    var el = $$('input[name="' + name + '"]').filter(function (i) { return i.value === String(value); })[0];
    if (el) el.checked = true; else state.missing.push(name + ': ' + value);
  }
  function prefill() {
    var h = (location.hash.match(/[#&]d=([A-Za-z0-9_-]+)/) || [])[1];
    if (!h) return false;
    var d; try { d = JSON.parse(unb64u(h)); } catch (e) { $('#poLoaded').hidden = false; $('#poLoaded').className = 'pq-note pq-note-warn'; $('#poLoaded').textContent = 'Odkaz z poptávky se nepodařilo přečíst – vyplňte objednávku ručně.'; return false; }
    state.missing = [];
    if (!MODELS[d.model_kod]) return false;
    var mr = form.querySelector('input[name="model"][value="' + d.model_kod + '"]'); mr.checked = true; state.model = d.model_kod; buildOptions(d.model_kod);
    ['odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'fakturace', 'zed', 'vytah'].forEach(function (n) { setRadio(n, d[n]); });
    ['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc', 'fak_jmeno', 'fak_adresa', 'ic', 'poznamka'].forEach(function (n) { if (form.elements[n] && d[n] != null) form.elements[n].value = d[n]; });
    if (d.platce_dph && form.platce_dph) form.platce_dph.checked = true;
    if (d.patro != null) { form.patro.value = String(d.patro); $('#pqLift').hidden = !(+d.patro > 0); }
    $('#pqBillingFields').hidden = val('fakturace') === BILLING[0] || !val('fakturace');
    $('#pqCompany').hidden = val('fakturace') !== BILLING[2];
    if (d.souradnice) state.addr = { lon: d.souradnice[0], lat: d.souradnice[1], picked: true, key: addrKey() };
    if (d.doprava_km != null) { state.trip = { km: d.doprava_km, fee: d.doprava_cena == null ? null : d.doprava_cena, forKey: addrKey() }; showTrip(); }
    state.fromRef = d.cislo || '';
    var fm = location.hash.match(/&f=([A-Za-z0-9_.~-]+)/), nPh = 0;
    if (fm) fm[1].split('~').forEach(function (part) {
      var i = part.indexOf('.'), name = part.slice(0, i), data = part.slice(i + 1);
      var box = $('.pq-photo[data-photo="' + name + '"]'); if (!box || !data) return;
      try {
        var b = data.replace(/-/g, '+').replace(/_/g, '/'); while (b.length % 4) b += '=';
        var bin = atob(b), u = new Uint8Array(bin.length); for (var j = 0; j < bin.length; j++) u[j] = bin.charCodeAt(j);
        var blob = new Blob([u], { type: 'image/jpeg' }); state.photos[name] = blob; showPhoto(box, blob); nPh++;
      } catch (e) {}
    });
    state.photosFromLink = nPh;
    setZaloha();
    var info = $('#poLoaded'); info.hidden = false;
    info.innerHTML = '✓ Načteno z poptávky <b>' + esc(d.cislo || '') + '</b> – ' + esc((d.jmeno || '') + ' ' + (d.prijmeni || '')) + '. Vše můžete upravit.' +
      (nPh ? ' Načteny i fotky z poptávky (' + nPh + ').' : '') +
      (state.missing.length ? '<br><small>Nenašel jsem volbu (mezitím se mohla změnit nabídka): ' + esc(state.missing.join('; ')) + ' – vyberte prosím znovu.</small>' : '');
    return true;
  }
  function orderNumber() {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'OBJ-' + t.getFullYear() + '-' + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes());
  }

  /* ── cena a souhrn ── */
  function val(name) { var el = form.querySelector('input[name="' + name + '"]:checked'); return el ? el.value : ''; }
  function price(name) { var el = form.querySelector('input[name="' + name + '"]:checked'); return el ? +(el.getAttribute('data-p') || 0) : 0; }
  var PRICED = [['odstin', 'Barva'], ['zarubne', 'Zárubeň'], ['kovani', 'Kování'], ['kukatko', 'Kukátko'], ['vlozky', 'Vložky'], ['sire', 'Šířka'], ['zaruka', 'Záruka']];

  function calc() {
    var m = MODELS[state.model]; if (!m) return null;
    var total = m.price, lines = [];
    PRICED.forEach(function (p) {
      var v = val(p[0]); if (!v) return;
      var x = price(p[0]); total += x;
      lines.push([p[1], p[0] === 'sire' && v !== 'Nevím' ? v + ' cm' : v, x]);
    });
    var t = state.trip;
    if (t && t.km != null) {
      if (t.fee == null) lines.push(['Doprava', String(t.km).replace('.', ',') + ' km', 0, 'upřesníme']);
      else { total += t.fee; lines.push(['Doprava', String(t.km).replace('.', ',') + ' km', t.fee, t.fee ? '' : 'zdarma']); }
    } else if (t && t.failed) lines.push(['Doprava', 'podle adresy', 0, 'upřesníme']);
    var st = stairs();
    if (st.fee) { total += st.fee; lines.push(['Schody', st.patro + '. patro bez výtahu', st.fee]); }
    if (OBJ) {
      // doprava: ruční cena přepíše vypočtenou
      if (fv('doprava_kc') !== '') {
        lines = lines.filter(function (l) { return l[0] !== 'Doprava'; });
        if (t && t.fee) total -= t.fee;
        var dk = num('doprava_kc'); total += dk;
        lines.push(['Doprava', (t && t.km != null ? String(t.km).replace('.', ',') + ' km' : 'dle dohody'), dk, dk ? '' : 'zdarma']);
      }
      (m.order.extras || []).forEach(function (x) {
        var el = form.elements['x_' + x.id]; if (!el) return;
        if (x.type === 'check' && el.checked) { total += x.p; lines.push([x.n, 'ano', x.p]); }
        if (x.type === 'qty' && num('x_' + x.id) > 0) { var q = num('x_' + x.id); total += q * x.p; lines.push([x.n, q + ' ks × ' + kc(x.p), q * x.p]); }
        if (x.type === 'amount' && num('x_' + x.id) > 0) { total += num('x_' + x.id); lines.push([x.n, fv('x_' + x.id + '_text') || 'dle zaměření', num('x_' + x.id)]); }
        if (x.type === 'choice' && val('x_' + x.id)) { var cp = price('x_' + x.id); total += cp; lines.push([x.n, val('x_' + x.id), cp, cp ? '' : '0 Kč']); }
      });
      if (num('vybava_kc') || fv('vybava_text')) { total += num('vybava_kc'); lines.push(['Další výbava', fv('vybava_text') || '–', num('vybava_kc')]); }
      if (num('sleva_kc') > 0) { total -= num('sleva_kc'); lines.push(['Sleva', fv('sleva_text') || '', -num('sleva_kc')]); }
    }
    return { total: total, lines: lines };
  }

  function updateSummary() {
    var m = MODELS[state.model], r = calc();
    if (!m) return;
    var img = $('#sumImg'); img.src = m.img; img.alt = m.name; img.hidden = false;
    $('#sumModel').textContent = m.name;
    $('#sumLines').innerHTML = '<li>Základní cena<b>' + kc(m.price) + '</b></li>' + r.lines.map(function (l) {
      return '<li><span>' + esc(l[0]) + ': ' + esc(l[1]) + '</span><b>' + (l[2] > 0 ? '+ ' + kc(l[2]) : l[2] < 0 ? '− ' + kc(-l[2]) : esc(l[3] || '')) + '</b></li>';
    }).join('');
    $('#sumPrice').textContent = kc(r.total);
    if (OBJ) { var z = zaloha(); $('#sumPay').innerHTML = (zalohaLabel() + ': <b>' + kc(z) + '</b> · doplatek po montáži: <b>' + kc(Math.max(0, r.total - z)) + '</b>'); }
    $('#sumNote').textContent = 'Vč. 12 % DPH, montáže na klíč a odvozu starých dveří. ' +
      (state.trip && state.trip.km != null ? '' : 'Doprava do 40 km zdarma, přesně ji spočítáme podle adresy montáže.') +
      (m.masonry ? ' Případné zednické práce upřesníme podle stavu zdi.' : ' Bez příplatku za cihlu a zapuštěnou zárubeň.');
  }

  /* ── kroky a validace ── */
  function required1() {
    var m = MODELS[state.model]; var need = ['model'];
    if (m) { need.push('odstin', 'kovani', 'otevirani', 'sire', 'zaruka'); if (m.frames) need.push('zarubne'); if (m.peep) need.push('kukatko'); if (m.locks) need.push('vlozky'); }
    return need;
  }
  function markBlock(name, bad) {
    var input = form.querySelector('input[name="' + name + '"]') || $('#pqModels');
    var block = input.closest('.pq-block'); if (!block) return;
    block.classList.toggle('pq-err', bad);
    var t = $('.pq-errtxt', block);
    if (bad && !t) { t = document.createElement('div'); t.className = 'pq-errtxt'; t.textContent = 'Vyberte prosím jednu možnost.'; block.appendChild(t); }
    if (!bad && t) t.remove();
  }
  function validate(step) {
    var first = null;
    if (step === 1) {
      required1().forEach(function (n) { var bad = !val(n); markBlock(n, bad); if (bad && !first) first = (form.querySelector('input[name="' + n + '"]') || $('#pqModels')).closest('.pq-block'); });
    }
    if (step === 2) {
      var bill = val('fakturace');
      $$('[data-step="2"] input').forEach(function (i) {
        var req = i.required || (i.hasAttribute('data-req') && bill !== BILLING[0]) || (i.hasAttribute('data-req-firma') && bill === BILLING[2]);
        var bad = (req && !i.value.trim()) || (i.value.trim() && !i.checkValidity());
        if (i.type === 'radio' || i.type === 'checkbox') bad = false;
        i.classList.toggle('bad', bad);
        if (bad && !first) first = i;
      });
      if (form.patro.value === '') { form.patro.classList.add('bad'); first = first || form.patro; }
      if (+form.patro.value > 0) { var bv = !val('vytah'); markBlock('vytah', bv); if (bv && !first) first = $('#pqLift'); }
      var tel = form.telefon.value.replace(/\D/g, '');
      if (form.telefon.value && tel.length < 9) { form.telefon.classList.add('bad'); first = first || form.telefon; }
    }
    if (step === 3 && OBJ) {
      ['obj_cislo'].forEach(function (n) { var i = form.elements[n]; var bad = !i.value.trim(); i.classList.toggle('bad', bad); if (bad && !first) first = i; });
      var bp = !val('prodavac'); markBlock('prodavac', bp); if (bp && !first) first = $('#poSeller');
      var m3 = MODELS[state.model];
      if (m3.order.frame.length > 1) { var bf = !val('tl_zarubne'); markBlock('tl_zarubne', bf); if (bf && !first) first = $('#poFrame'); }
      (m3.order.extras || []).forEach(function (x) { if (x.type === 'choice') { var bc = !val('x_' + x.id); markBlock('x_' + x.id, bc); if (bc && !first) first = $('#poExtras'); } });
    }
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus && first.tagName === 'INPUT') setTimeout(function () { first.focus({ preventScroll: true }); }, 350); }
    return !first;
  }
  function go(step) {
    state.step = step;
    $$('.pq-step').forEach(function (s) { s.hidden = +s.getAttribute('data-step') !== step; });
    $$('[data-step-ind]').forEach(function (li) { var n = +li.getAttribute('data-step-ind'); li.classList.toggle('on', n === step); li.classList.toggle('done', n < step); });
    if (step === 4) { buildReview(); if (window.PoptavkaPDF) { window.PoptavkaPDF.prepare(); if (OBJ && window.PoptavkaPDF.preparePL) window.PoptavkaPDF.preparePL(); } }
    var top = $('.pq-steps').getBoundingClientRect().top + window.scrollY - 90;
    window.scrollTo({ top: top, behavior: 'smooth' });
  }

  function collect() {
    var m = MODELS[state.model], r = calc(), f = form;
    var d = {
      verze: 1, cislo: state.ref, odeslano: new Date().toISOString(), zdroj: 'web-poptavka-dvere',
      model: m.name, model_kod: state.model,
      odstin: val('odstin'), zarubne: val('zarubne'), kovani: val('kovani'), kukatko: val('kukatko'), vlozky: val('vlozky'),
      otevirani: val('otevirani'), sire: val('sire'), zaruka: val('zaruka'),
      cena_zaklad: m.price, cena_celkem: r.total, polozky: r.lines.map(function (l) { return { co: l[0], volba: l[1], priplatek: l[2], info: l[3] || '' }; }),
      jmeno: fv('jmeno'), prijmeni: fv('prijmeni'), telefon: fv('telefon'), email: fv('email'),
      ulice: fv('ulice'), mesto: fv('mesto'), psc: fv('psc'),
      patro: f.patro.value === '' ? null : +f.patro.value, vytah: +f.patro.value > 0 ? val('vytah') : '',
      doprava_km: state.trip ? state.trip.km : null, doprava_cena: state.trip && state.trip.km != null ? state.trip.fee : null,
      souradnice: state.addr ? [state.addr.lon, state.addr.lat] : null, adresa_overena: !!(state.addr && state.addr.picked),
      fakturace: val('fakturace'), fak_jmeno: fv('fak_jmeno'), fak_adresa: fv('fak_adresa'), ic: fv('ic'), platce_dph: !!(f.platce_dph && f.platce_dph.checked),
      zed: val('zed'), vyska_cm: fv('vyska_cm'), poznamka: fv('poznamka'),
      fotky: Object.keys(state.photos)
    };
    if (OBJ) {
      d.zdroj = 'web-objednavka'; d.typ = 'objednavka';
      d.z_poptavky = state.fromRef || '';
      d.obj_cislo = fv('obj_cislo'); d.obj_datum = fv('obj_datum'); d.prodavac = val('prodavac');
      d.op = fv('op'); d.zvonek = fv('zvonek'); d.tl_zarubne = val('tl_zarubne') || (m.order.frame.length === 1 ? m.order.frame[0] : '');
      d.tl_zdi = fv('tl_zdi'); d.termin = fv('termin'); d.interni = fv('interni');
      d.zaloha_label = zalohaLabel(); d.zaloha = zaloha(); d.doplatek = Math.max(0, r.total - d.zaloha);
      d.cena_zakladni_bezna = m.regular || m.price;
    }
    return d;
  }

  function reviewRows(d) {
    var rows = [
      ['Dveře', d.model, 1], ['Barva', d.odstin, 1], ['Zárubeň', d.zarubne, 1], ['Kování', d.kovani, 1], ['Kukátko', d.kukatko, 1], ['Vložky', d.vlozky, 1],
      ['Otevírání', d.otevirani, 1], ['Šířka', d.sire && d.sire !== 'Nevím' ? d.sire + ' cm' : d.sire, 1], ['Záruka', d.zaruka, 1],
      ['Orientační cena', kc(d.cena_celkem) + ' vč. DPH a montáže', 1],
      ['Jméno', d.jmeno + ' ' + d.prijmeni, 2], ['Telefon', d.telefon, 2], ['E-mail', d.email, 2],
      ['Adresa montáže', [d.ulice, d.psc, d.mesto].filter(Boolean).join(', '), 2],
      ['Patro', d.patro == null ? '' : (d.patro === 0 ? 'přízemí' : d.patro + '. patro, ' + (d.vytah === 'Ano' ? 's výtahem' : 'bez výtahu')), 2],
      ['Doprava', d.doprava_km != null ? String(d.doprava_km).replace('.', ',') + ' km – ' + (d.doprava_cena == null ? 'upřesníme' : d.doprava_cena ? kc(d.doprava_cena) : 'zdarma') : 'upřesníme', 2],
      ['Fakturace', d.fakturace, 2]
    ];
    if (d.fakturace !== BILLING[0]) { rows.push(['Objednatel', d.fak_jmeno, 2], ['Fakturační adresa', d.fak_adresa, 2]); if (d.ic) rows.push(['IČ', d.ic + (d.platce_dph ? ', plátce DPH' : ''), 2]); }
    if (OBJ) {
      rows[9] = ['Cena celkem', kc(d.cena_celkem) + ' vč. 12 % DPH a montáže', 1];
      rows.splice(10, 0, ['Záloha / doplatek', kc(d.zaloha) + ' / ' + kc(d.doplatek) + ' po montáži hotově', 1]);
      rows.push(['Číslo OP', d.op, 2], ['Zvonek', d.zvonek, 2]);
      rows.push(['Číslo objednávky', d.obj_cislo, 3], ['Datum', d.obj_datum ? new Date(d.obj_datum).toLocaleDateString('cs-CZ') : '', 3], ['Prodavač', d.prodavac, 3],
        ['Tloušťka zárubně', d.tl_zarubne, 3], ['Zeď', [d.zed, d.tl_zdi ? d.tl_zdi + ' cm' : ''].filter(Boolean).join(', '), 3],
        ['Termín montáže', d.termin, 3], ['Poznámka pro zákazníka', d.poznamka, 3], ['Interní poznámka', d.interni, 3], ['Z poptávky', d.z_poptavky, 3]);
    } else
    rows.push(['Zeď', d.zed, 3], ['Výška k nadpraží', d.vyska_cm ? d.vyska_cm + ' cm' : '', 3], ['Fotky', d.fotky.length ? d.fotky.length + ' ks' : '', 3], ['Poznámka', d.poznamka, 3]);
    return rows.filter(function (r) { return r[1]; });
  }

  function buildReview() {
    var d = collect(), html = '', last = 0;
    reviewRows(d).forEach(function (r) {
      if (r[2] !== last && last) html += '<button type="button" class="edit" data-goto="' + last + '">Upravit ✎</button>';
      last = r[2];
      html += '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>';
    });
    html += '<button type="button" class="edit" data-goto="' + last + '">Upravit ✎</button>';
    $('#pqReview').innerHTML = html;
  }

  /* ── fotky: zmenšení v prohlížeči (max 1600 px, JPEG) ── */
  function shrink(file) {
    return new Promise(function (resolve) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, 1600 / Math.max(img.width, img.height));
        var cv = document.createElement('canvas'); cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
        URL.revokeObjectURL(url);
        cv.toBlob(function (b) { resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }
  /* malý náhled (max 200 px) do odkazu „Vytvořit objednávku“ */
  function thumb(blob) {
    return new Promise(function (resolve) {
      var img = new Image(), url = URL.createObjectURL(blob);
      img.onload = function () {
        var sc = Math.min(1, 200 / Math.max(img.width, img.height));
        var cv = document.createElement('canvas'); cv.width = Math.round(img.width * sc); cv.height = Math.round(img.height * sc);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(url);
        try { resolve(cv.toDataURL('image/jpeg', 0.55).split(',')[1].replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')); } catch (e) { resolve(''); }
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(''); };
      img.src = url;
    });
  }
  function showPhoto(box, blob) { var pv = $('img', box); pv.src = URL.createObjectURL(blob); pv.hidden = false; box.classList.add('has'); }
  $$('.pq-photo').forEach(function (box) {
    var input = $('input', box), name = box.getAttribute('data-photo');
    input.addEventListener('change', function () {
      var f = input.files && input.files[0]; if (!f) return;
      shrink(f).then(function (b) {
        state.photos[name] = b; showPhoto(box, b);
        if (!OBJ) thumb(b).then(function (t) { if (t) state.thumbs[name] = t; });
      });
    });
  });

  /* ── odeslání ── */
  function makeRef() {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'W' + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase();
  }
  function fields(d) {
    var f = [
      ['_subject', 'Poptávka dveří ' + d.cislo + ' – ' + d.model + ' – ' + d.prijmeni + ', ' + d.mesto],
      ['_template', 'table'], ['_captcha', 'false'], ['_cc', CC], ['_honey', form._honey.value]
    ];
    if (d.email) f.push(['_replyto', d.email]);
    f.push(['Číslo poptávky', d.cislo]);
    reviewRows(d).forEach(function (r) { f.push([r[0], r[1]]); });
    if (!OBJ) f.push(['Vytvořit objednávku', orderLink(d)]);
    f.push(['Odesláno', new Date(d.odeslano).toLocaleString('cs-CZ')], ['data_json', JSON.stringify(d)]);
    return f;
  }
  /* data poptávky v odkazu (za #, na server se neposílají) → objednavka-dvere.html si je načte */
  var LINK_KEYS = ['cislo', 'model_kod', 'odstin', 'zarubne', 'kovani', 'kukatko', 'vlozky', 'otevirani', 'sire', 'zaruka', 'jmeno', 'prijmeni', 'telefon', 'email',
    'ulice', 'mesto', 'psc', 'patro', 'vytah', 'doprava_km', 'doprava_cena', 'souradnice', 'fakturace', 'fak_jmeno', 'fak_adresa', 'ic', 'platce_dph', 'zed', 'vyska_cm', 'poznamka'];
  function b64u(str) { var b = new TextEncoder().encode(str), s2 = ''; for (var i = 0; i < b.length; i++) s2 += String.fromCharCode(b[i]); return btoa(s2).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function unb64u(t) { t = t.replace(/-/g, '+').replace(/_/g, '/'); while (t.length % 4) t += '='; var bin = atob(t), u = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i); return new TextDecoder().decode(u); }
  function orderLink(d) {
    var o = {}; LINK_KEYS.forEach(function (k) { if (d[k] !== '' && d[k] != null && d[k] !== false) o[k] = d[k]; });
    var url = new URL('objednavka-dvere.html', location.href).href + '#d=' + b64u(JSON.stringify(o));
    // náhledy fotek až za daty – kdyby e-mail dlouhý odkaz zkrátil, data poptávky zůstanou celá
    var th = Object.keys(state.thumbs).map(function (k) { return k + '.' + state.thumbs[k]; });
    if (th.length && (url.length + th.join('~').length) < 24000) url += '&f=' + th.join('~');
    return url;
  }
  /* AJAX – spolehlivé, ale FormSubmit přílohy zahodí → jen záloha bez souborů */
  function sendAjax(d) {
    var fd = new FormData();
    fields(d).forEach(function (x) { fd.append(x[0], x[1]); });
    return fetch('https://formsubmit.co/ajax/' + TO, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!(j && (j.success === true || j.success === 'true'))) throw new Error('fail'); });
  }
  /* klasické odeslání (multipart) do skrytého iframe – tak FormSubmit přílohy doručí;
     úspěch = iframe skončí na naší stránce poptavka-odeslano.html (_next) */
  var canFiles = (function () { try { return !!new DataTransfer().items; } catch (e) { return false; } })();
  function sendClassic(d, files) { return sendClassicFields(fields(d), files, d.cislo); }
  function sendClassicFields(fieldList, files, ref) {
    return new Promise(function (ok, ko) {
      var name = 'pqSend' + Date.now(), done = false;
      var ifr = document.createElement('iframe'); ifr.name = name; ifr.hidden = true; ifr.setAttribute('aria-hidden', 'true');
      var f = document.createElement('form');
      f.action = 'https://formsubmit.co/' + TO; f.method = 'POST'; f.enctype = 'multipart/form-data'; f.target = name; f.hidden = true;
      var next = new URL('poptavka-odeslano.html?ref=' + encodeURIComponent(ref), location.href).href;
      fieldList.concat([['_next', next]]).forEach(function (x) {
        var i = document.createElement('input'); i.type = 'hidden'; i.name = x[0]; i.value = x[1]; f.appendChild(i);
      });
      files.forEach(function (fl) {
        var i = document.createElement('input'); i.type = 'file'; i.name = fl.name;
        var dt = new DataTransfer(); dt.items.add(new File([fl.blob], fl.file, { type: fl.blob.type || 'application/octet-stream' })); i.files = dt.files;
        f.appendChild(i);
      });
      var timer = setTimeout(function () { if (!done) { done = true; cleanup(); ko(new Error('timeout')); } }, 35000);
      function cleanup() { setTimeout(function () { ifr.remove(); f.remove(); }, 1000); }
      ifr.addEventListener('load', function () {
        var href = ''; try { href = ifr.contentWindow.location.href; } catch (e) { return; }
        if (!done && href.indexOf('poptavka-odeslano') > -1) { done = true; clearTimeout(timer); cleanup(); ok(); }
      });
      document.body.appendChild(ifr); document.body.appendChild(f);
      f.submit();
    });
  }
  function pdfExtra(d) {
    var m = MODELS[state.model], sh = null;
    m.shades.forEach(function (s) { if (s[0] === d.odstin) sh = s[1]; });
    return { modelImg: m.img, shadeImg: sh, photos: state.photos,
      priceNote: m.masonry ? 'Případné zednické práce podle stavu zdi nejsou v ceně.' : 'Bez příplatku za cihlu a zapuštěnou zárubeň.' };
  }

  /* objednávka: 1) interní e-mail nám (data_json, interní poznámka), 2) e-mail s PDF nám + kopie zákazníkovi */
  function customerFields(d) {
    var f = [
      ['_subject', 'Objednávka ' + d.obj_cislo + ' – bezpečnostní dveře ' + d.model + ' – Bezpečné dveře'],
      ['_template', 'table'], ['_captcha', 'false'],
      ['_cc', [CC_OBJ, d.email].filter(Boolean).join(',')], ['_replyto', CC]
    ];
    f.push(['Dobrý den', 'děkujeme za objednávku. V příloze posíláme objednávku v PDF. S dotazy se obracejte na ' + CC + ' nebo +420 725 559 235.']);
    reviewRows(d).forEach(function (r) { if (['Interní poznámka', 'Z poptávky', 'Číslo OP'].indexOf(r[0]) < 0 && r[2] !== 'x') f.push([r[0], r[1]]); });
    return f;
  }
  function sendAjaxFields(f) {
    var fd = new FormData(); f.forEach(function (x) { fd.append(x[0], x[1]); });
    return fetch('https://formsubmit.co/ajax/' + TO, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); }).then(function (j) { if (!(j && (j.success === true || j.success === 'true'))) throw new Error('fail'); });
  }
  function submitOrder() {
    var msg = $('#pqMsg'), btn = $('#pqSubmit');
    btn.disabled = true; btn.textContent = 'Připravuji PDF…';
    var d = collect(), pdf = null, plist = null, voucher = null, custOk = true;
    d.odeslano = new Date().toISOString(); d.cislo = d.obj_cislo;
    var P = window.PoptavkaPDF;
    var pdfP = P ? P.buildOrder(d, pdfExtra(d)).catch(function () { return null; }) : Promise.resolve(null);
    var plP = P && P.buildHandover ? P.buildHandover(d).catch(function () { return null; }) : Promise.resolve(null);
    var vP = P && P.buildVoucher ? P.buildVoucher(d).catch(function () { return null; }) : Promise.resolve(null);
    Promise.all([pdfP, plP, vP]).then(function (res) {
      pdf = res[0]; plist = res[1]; voucher = res[2]; btn.textContent = 'Odesílám…';
      var internal = [['_subject', 'Objednávka ' + d.obj_cislo + ' (interní) – ' + d.model + ' – ' + d.prijmeni + ', ' + d.mesto], ['_template', 'table'], ['_captcha', 'false'], ['_cc', CC_OBJ],
        ['Číslo objednávky', d.obj_cislo], ['Prodavač', d.prodavac], ['Z poptávky', d.z_poptavky || '–'], ['Číslo OP', d.op || '–'], ['Interní poznámka', d.interni || '–'],
        ['Cena celkem', kc(d.cena_celkem)], ['Záloha / doplatek', kc(d.zaloha) + ' / ' + kc(d.doplatek)],
        ['Předávací list', plist ? 'v příloze – předvyplněný, jen pro prodejce' : 'nepodařilo se vytvořit – použijte prázdný z panelu'],
        ['Voucher Doporuč a získej', voucher ? 'poslán zákazníkovi s objednávkou' : '–'], ['data_json', JSON.stringify(d)]];
      // předávací list jen prodejcům (interní e-mail), zákazník ho nedostane; voucher jde jen zákazníkovi
      var intFiles = []; if (plist) intFiles.push({ name: 'predavaci_list_pdf', file: 'Predavaci-list-' + d.obj_cislo + '.pdf', blob: plist });
      if (intFiles.length && canFiles) return sendClassicFields(internal, intFiles, d.obj_cislo + '-int')
        .catch(function () { return sendAjaxFields(internal); });
      return sendAjaxFields(internal);
    }).then(function () {
      var files = pdf ? [{ name: 'objednavka_pdf', file: 'Objednavka-' + d.obj_cislo + '.pdf', blob: pdf }] : [];
      if (voucher) files.push({ name: 'voucher_doporuc_a_ziskej_pdf', file: 'Voucher-Doporuc-a-ziskej.pdf', blob: voucher });
      var cf = customerFields(d);
      if (voucher) cf.push(['Doporuč a získej', 'V příloze posíláme vouchery na slevu 1 000 Kč pro vaše známé na dveře PREMIUM 60. Za každé úspěšné doporučení dostanete po montáži 1 500 Kč (nejvýše za 3 doporučení). Pravidla: www.bezpecne-dvere.cz']);
      if (!canFiles || !files.length) { custOk = !files.length; return sendAjaxFields(cf); }
      return sendClassicFields(cf, files, d.obj_cislo).catch(function () { custOk = false; return sendAjaxFields(cf); });
    }).then(function () {
      $('#doneRef').textContent = d.obj_cislo;
      var w = $('#doneWarn');
      if (!d.email) { w.innerHTML = 'Zákazník nemá vyplněný e-mail – objednávka odešla jen vám. PDF si stáhněte a předejte mu ho.'; w.hidden = false; }
      else if (!custOk) { w.innerHTML = 'E-mail odešel, ale bez přiloženého PDF. Stáhněte si PDF a pošlete ho zákazníkovi tlačítkem „Poslat zákazníkovi“.'; w.hidden = false; }
      if (pdf) {
        var url = URL.createObjectURL(pdf), a = $('#donePdf'); a.href = url; a.download = 'Objednavka-' + d.obj_cislo + '.pdf'; a.hidden = false;
        var pr = $('#donePrint'); if (pr) { pr.hidden = false; pr.onclick = function () { var wnd = window.open(url, '_blank'); if (wnd) wnd.focus(); }; }
        var sh = $('#doneShare'); if (sh) { sh.hidden = false; sh.onclick = function () { shareToCustomer(d, pdf); }; }
      }
      if (voucher) { var vl = $('#doneVoucher'); if (vl) { vl.href = URL.createObjectURL(voucher); vl.download = 'Voucher-Doporuc-a-ziskej-' + d.obj_cislo + '.pdf'; vl.hidden = false; } }
      if (plist) { var hl = $('#doneHandover'); if (hl) { hl.href = URL.createObjectURL(plist); hl.download = 'Predavaci-list-' + d.obj_cislo + '.pdf'; hl.hidden = false; } }
      $('.pq-layout').hidden = true; $('.pq-steps').hidden = true; $('#pqDone').hidden = false;
      window.scrollTo({ top: $('.pq-wrap').offsetTop - 80, behavior: 'smooth' });
    }).catch(function () {
      msg.innerHTML = 'Odeslání se nepodařilo – zkontrolujte připojení a zkuste to znovu. PDF si můžete stáhnout tlačítkem níže.'; msg.hidden = false;
      btn.disabled = false; btn.textContent = 'Potvrdit a odeslat objednávku';
    });
  }
  function shareToCustomer(d, pdf) {
    var file = new File([pdf], 'Objednavka-' + d.obj_cislo + '.pdf', { type: 'application/pdf' });
    var text = 'Dobrý den, v příloze posíláme objednávku ' + d.obj_cislo + ' (bezpečnostní dveře ' + d.model + '). S pozdravem, Bezpečné dveře, +420 725 559 235';
    if (navigator.canShare && navigator.canShare({ files: [file] })) { navigator.share({ files: [file], title: 'Objednávka ' + d.obj_cislo, text: text }).catch(function () {}); return; }
    var a = document.createElement('a'); a.href = URL.createObjectURL(pdf); a.download = file.name; document.body.appendChild(a); a.click(); a.remove();
    location.href = 'mailto:' + encodeURIComponent(d.email || '') + '?subject=' + encodeURIComponent('Objednávka ' + d.obj_cislo + ' – Bezpečné dveře') + '&body=' + encodeURIComponent(text + '\n\n(PDF se stáhlo do počítače – přiložte ho prosím k e-mailu.)');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#pqMsg'); msg.hidden = true;
    if (OBJ) { if (!$('#pqConsent').checked) { msg.innerHTML = 'Potvrďte prosím, že objednávka je zkontrolovaná.'; msg.hidden = false; return; } submitOrder(); return; }
    if (!$('#pqConsent').checked) { msg.innerHTML = 'Pro odeslání prosím potvrďte souhlas se zpracováním osobních údajů.'; msg.hidden = false; return; }
    if (form._honey.value) return;
    var btn = $('#pqSubmit'); btn.disabled = true; btn.textContent = 'Připravuji…';
    state.ref = state.ref || makeRef();
    var d = collect(), pdf = null, filesLost = false;
    var pdfP = window.PoptavkaPDF ? window.PoptavkaPDF.build(d, pdfExtra(d)).catch(function () { return null; }) : Promise.resolve(null);
    pdfP.then(function (blob) {
      pdf = blob; btn.textContent = 'Odesílám…';
      var files = [];
      if (pdf) files.push({ name: 'poptavka_pdf', file: 'Poptavka-' + d.cislo + '.pdf', blob: pdf });
      Object.keys(state.photos).forEach(function (k) { files.push({ name: k, file: k + '-' + d.cislo + '.jpg', blob: state.photos[k] }); });
      d.pdf = !!pdf;
      if (!canFiles) { filesLost = files.length > 0; return sendAjax(d); }
      return sendClassic(d, files).catch(function () { filesLost = files.length > 0; d.poznamka_system = 'Přílohy se nepodařilo odeslat'; return sendAjax(d); });
    }).then(function () {
      $('#doneRef').textContent = d.cislo;
      if (filesLost && d.fotky.length) { var w = $('#doneWarn'); w.innerHTML = 'Poptávka dorazila, ale fotky se nepodařilo přiložit. Pošlete nám je prosím přes <a href="https://wa.me/420725559235">WhatsApp</a> nebo na <a href="mailto:' + CC + '">' + CC + '</a> s číslem poptávky.'; w.hidden = false; }
      if (pdf) { var a = $('#donePdf'); a.href = URL.createObjectURL(pdf); a.download = 'Poptavka-' + d.cislo + '.pdf'; a.hidden = false; }
      $('.pq-layout').hidden = true; $('.pq-steps').hidden = true; $('#pqDone').hidden = false;
      window.scrollTo({ top: $('.pq-wrap').offsetTop - 80, behavior: 'smooth' });
    }).catch(function () {
      msg.innerHTML = 'Odeslání se nepodařilo. Zkuste to prosím znovu, nebo nám zavolejte na <a href="tel:+420725559235">+420 725 559 235</a> či napište na <a href="mailto:' + CC + '">' + CC + '</a>.';
      msg.hidden = false;
      btn.disabled = false; btn.textContent = 'Odeslat poptávku';
    });
  });

  /* ── doprava (Mapy.com) a schody ── */
  function stairs() {
    var p = form.patro ? form.patro.value : '';
    var n = p === '' ? 0 : +p;
    return { patro: n, fee: n > 0 && val('vytah') === 'Ne' ? n * SCHODY : 0 };
  }
  function addrKey() { return [form.ulice.value, form.mesto.value, form.psc.value].join('|').toLowerCase().replace(/\s+/g, ' ').trim(); }
  function mapy(path, params) {
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    var ctrl = window.AbortController ? new AbortController() : null;
    var to = setTimeout(function () { if (ctrl) ctrl.abort(); }, 7000);
    return fetch(MAPY + path + '?' + q + '&apikey=' + MAPY_KEY, ctrl ? { signal: ctrl.signal } : {})
      .then(function (r) { clearTimeout(to); if (!r.ok) throw new Error('mapy ' + r.status); return r.json(); });
  }
  var showroomP = null;
  function showroom() {
    if (!showroomP) showroomP = mapy('geocode', { query: SHOWROOM, lang: 'cs', limit: 1, type: 'regional.address' })
      .then(function (j) { var it = j.items && j.items[0]; if (!it) throw new Error('showroom'); return it.position; })
      .catch(function (e) { showroomP = null; throw e; });
    return showroomP;
  }
  function geocodeTyped() {
    var q = [form.ulice.value, form.psc.value, form.mesto.value].map(function (x) { return x.trim(); }).filter(Boolean).join(', ');
    return mapy('geocode', { query: q, lang: 'cs', limit: 1, type: 'regional.address', locality: 'cz' }).then(function (j) {
      var it = j.items && j.items[0];
      if (it) return { lon: it.position.lon, lat: it.position.lat, picked: false };
      // adresa nenalezena → aspoň obec (na pásma 40/80 km stačí)
      return mapy('geocode', { query: form.mesto.value.trim(), lang: 'cs', limit: 1, type: 'regional.municipality', locality: 'cz' }).then(function (j2) {
        var m = j2.items && j2.items[0]; if (!m) throw new Error('nenalezeno');
        return { lon: m.position.lon, lat: m.position.lat, picked: false, approx: true };
      });
    });
  }
  function computeTrip() {
    var key = addrKey();
    var addrP = state.addr && state.addr.key === key ? Promise.resolve(state.addr) : geocodeTyped().then(function (a) { a.key = key; state.addr = a; return a; });
    return Promise.all([showroom(), addrP]).then(function (r) {
      return mapy('routing/route', { start: r[0].lon + ',' + r[0].lat, end: r[1].lon + ',' + r[1].lat, routeType: 'car_fast', format: 'polyline' });
    }).then(function (j) {
      if (typeof j.length !== 'number') throw new Error('route');
      var km = Math.round(j.length / 100) / 10, fee = null;
      for (var i = 0; i < DOPRAVA.length; i++) if (km <= DOPRAVA[i][0]) { fee = DOPRAVA[i][1]; break; }
      state.trip = { km: km, fee: fee, forKey: key, approx: !!(state.addr && state.addr.approx) };
    }).catch(function () {
      state.trip = { km: null, fee: null, failed: true, forKey: key };
    }).then(showTrip);
  }
  function showTrip() {
    showTrip2();
    var po = $('#poTripInfo'); if (po) { po.hidden = $('#pqTrip').hidden; po.className = $('#pqTrip').className; po.innerHTML = $('#pqTrip').innerHTML; }
  }
  function showTrip2() {
    var el = $('#pqTrip'), t = state.trip;
    if (!t) { el.hidden = true; return; }
    el.hidden = false;
    if (t.km == null) { el.className = 'pq-trip'; el.innerHTML = '🚚 Vzdálenost se nepodařilo spočítat – cenu dopravy vám potvrdíme.'; return; }
    el.className = 'pq-trip ' + (t.fee === 0 ? 'ok' : '');
    el.innerHTML = '🚚 Vzdálenost od vzorkovny: <b>' + String(t.km).replace('.', ',') + ' km</b> – doprava ' +
      (t.fee == null ? '<b>upřesníme</b>' : t.fee ? '<b>' + kc(t.fee) + '</b>' : '<b>zdarma</b>') + (t.approx ? ' <small>(podle obce)</small>' : '');
  }

  /* našeptávač adres */
  (function () {
    var input = form.ulice, box = $('#pqSuggest'), items = [], active = -1, timer = null, cache = {}, seq = 0;
    function close() { box.hidden = true; active = -1; }
    function render() {
      if (!items.length) { close(); return; }
      box.innerHTML = items.map(function (it, i) {
        return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="on"' : '') + '><b>' + esc(it.name) + '</b><span>' + esc(it.location || '') + '</span></li>';
      }).join('') + '<li class="pq-sg-logo" aria-hidden="true">Adresy: <img src="https://api.mapy.com/img/api/logo-small.svg" alt="Mapy.com" width="60" height="15"></li>';
      box.hidden = false;
    }
    function pick(it) {
      var reg = it.regionalStructure || [], muni = '';
      reg.forEach(function (r) { if (!muni && r.type === 'regional.municipality') muni = r.name; });
      input.value = it.name;
      if (muni) form.mesto.value = muni;
      if (it.zip) form.psc.value = it.zip;
      [input, form.mesto, form.psc].forEach(function (x) { x.classList.remove('bad'); });
      state.addr = { lon: it.position.lon, lat: it.position.lat, picked: true, key: addrKey() };
      close();
      computeTrip().then(updateSummary);
    }
    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 3) { items = []; close(); return; }
      timer = setTimeout(function () {
        if (cache[q]) { items = cache[q]; active = -1; render(); return; }
        var my = ++seq;
        mapy('suggest', { query: q.slice(0, 150), lang: 'cs', limit: 5, type: 'regional.address', locality: 'cz' }).then(function (j) {
          cache[q] = (j.items || []).filter(function (it) { return it.position; });
          if (my !== seq) return;
          items = cache[q]; active = -1; render();
        }).catch(function () { items = []; close(); });
      }, 280);
    });
    input.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter' && active > -1) { pick(items[active]); e.preventDefault(); }
      else if (e.key === 'Escape') close();
    });
    box.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(items[+li.getAttribute('data-i')]); } });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    // ruční změna adresy → doprava se přepočítá při pokračování
    ['ulice', 'mesto', 'psc'].forEach(function (n) {
      form[n].addEventListener('input', function () { if (state.trip && state.trip.forKey !== addrKey()) { state.trip = null; showTrip(); updateSummary(); } });
    });
  })();

  /* ── události ── */
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'model') {
      state.model = t.value; buildOptions(t.value);
      markBlock('model', false);
      setTimeout(function () { $('#pqModelOptions').scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 120);
    } else if (t.type === 'radio') markBlock(t.name, false);
    if (t.name === 'zaloha_kc') state.zalohaTouched = true;
    if (OBJ && t.name === 'odstin' && !state.zalohaTouched) setZaloha();
    if (t.name === 'patro') { $('#pqLift').hidden = !(+t.value > 0); t.classList.remove('bad'); }
    if (t.name === 'fakturace') {
      $('#pqBillingFields').hidden = t.value === BILLING[0];
      $('#pqCompany').hidden = t.value !== BILLING[2];
    }
    if (t.classList && t.classList.contains('bad')) t.classList.remove('bad');
    updateSummary();
  });
  form.addEventListener('input', function (e) {
    if (e.target.classList.contains('bad') && e.target.value.trim()) e.target.classList.remove('bad');
    if (OBJ && /^(x_|vybava_|sleva_|doprava_|zaloha_)/.test(e.target.name || '')) { if (e.target.name === 'zaloha_kc') state.zalohaTouched = true; updateSummary(); }
  });
  form.addEventListener('click', function (e) {
    var t = e.target.closest('[data-next],[data-prev],[data-goto]'); if (!t) return;
    if (t.hasAttribute('data-next')) {
      if (!validate(state.step)) return;
      if (state.step === 2 && !(state.trip && state.trip.forKey === addrKey())) {
        var lbl = t.textContent; t.disabled = true; t.textContent = 'Počítám dopravu…';
        computeTrip().then(function () { t.disabled = false; t.textContent = lbl; updateSummary(); go(3); });
      } else go(state.step + 1);
    }
    else if (t.hasAttribute('data-prev')) go(state.step - 1);
    else go(+t.getAttribute('data-goto'));
  });

  buildModels(); buildStatic();
  if (OBJ) {
    $('#poSeller').innerHTML = PRODAVACI.map(function (p) { return radio('prodavac', p, esc(p), 'pq-chip'); }).join('');
    form.obj_cislo.value = orderNumber();
    form.obj_datum.value = new Date().toISOString().slice(0, 10);
    if (prefill()) updateSummary();
  }
  // předvolba modelu z odkazu: poptavka-dvere.html?model=premium60
  var pre = (location.search.match(/[?&]model=([a-z0-9]+)/) || [])[1];
  if (pre && MODELS[pre]) { var r = form.querySelector('input[name="model"][value="' + pre + '"]'); r.checked = true; state.model = pre; buildOptions(pre); updateSummary(); }
})();
