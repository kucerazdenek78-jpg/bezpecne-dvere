/* Poptávka dveří – vlastní formulář (náhrada Jotform 253084834051050)
   Ceny a volby: dvere-do-bytu.html (ceny) + Jotform poptávka (volby), stav 11. 10. 2026.
   Odeslání: FormSubmit → kucerazd@kksystem.cz + kopie chci@bezpecne-dvere.cz.
   Pole „data_json“ nese strojově čitelná data pro pozdější import do systému na NAS. */
(function () {
  'use strict';
  var TO = 'kucerazd@kksystem.cz';
  var CC = 'chci@bezpecne-dvere.cz';
  var C = 'userFiles/barva-dveri/';

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
        ['Šedá', C + 'magnum56/magnum56_seda.png.tn.jpg', 0]
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
  var ORDER = ['premium60', 'extreme54', 'magnum56', 'securido65'];
  var OPENING = ['Levé dovnitř (klika na pravé straně)', 'Pravé dovnitř (klika na levé straně)'];
  var BILLING = ['Stejná jako instalační', 'Jiná adresa – soukromá osoba', 'Jiná adresa – firma nebo OSVČ'];
  var WALL = ['Beton / panel', 'Cihla', 'Nevím'];

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var kc = function (n) { return n.toLocaleString('cs-CZ').replace(/ /g, ' ') + ' Kč'; };
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var form = $('#pq');
  var state = { model: null, step: 1, photos: {} };

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

    $('#pqOpening').innerHTML = OPENING.map(function (o, i) {
      return radio('otevirani', o, svgOpening(i === 0) + '<span>' + esc(o) + '</span>', 'pq-tile');
    }).join('');

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
    return { total: total, lines: lines };
  }

  function updateSummary() {
    var m = MODELS[state.model], r = calc();
    if (!m) return;
    var img = $('#sumImg'); img.src = m.img; img.alt = m.name; img.hidden = false;
    $('#sumModel').textContent = m.name;
    $('#sumLines').innerHTML = '<li>Základní cena<b>' + kc(m.price) + '</b></li>' + r.lines.map(function (l) {
      return '<li><span>' + esc(l[0]) + ': ' + esc(l[1]) + '</span><b>' + (l[2] ? '+ ' + kc(l[2]) : '') + '</b></li>';
    }).join('');
    $('#sumPrice').textContent = kc(r.total);
    $('#sumNote').textContent = 'Vč. 12 % DPH, montáže na klíč, odvozu starých dveří a dopravy do 40 km.' +
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
      var tel = form.telefon.value.replace(/\D/g, '');
      if (form.telefon.value && tel.length < 9) { form.telefon.classList.add('bad'); first = first || form.telefon; }
    }
    if (first) { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus && first.tagName === 'INPUT') setTimeout(function () { first.focus({ preventScroll: true }); }, 350); }
    return !first;
  }
  function go(step) {
    state.step = step;
    $$('.pq-step').forEach(function (s) { s.hidden = +s.getAttribute('data-step') !== step; });
    $$('[data-step-ind]').forEach(function (li) { var n = +li.getAttribute('data-step-ind'); li.classList.toggle('on', n === step); li.classList.toggle('done', n < step); });
    if (step === 4) buildReview();
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
      cena_zaklad: m.price, cena_celkem: r.total, polozky: r.lines.map(function (l) { return { co: l[0], volba: l[1], priplatek: l[2] }; }),
      jmeno: f.jmeno.value.trim(), prijmeni: f.prijmeni.value.trim(), telefon: f.telefon.value.trim(), email: f.email.value.trim(),
      ulice: f.ulice.value.trim(), mesto: f.mesto.value.trim(), psc: f.psc.value.trim(),
      fakturace: val('fakturace'), fak_jmeno: f.fak_jmeno.value.trim(), fak_adresa: f.fak_adresa.value.trim(), ic: f.ic.value.trim(), platce_dph: f.platce_dph.checked,
      zed: val('zed'), vyska_cm: f.vyska_cm.value.trim(), poznamka: f.poznamka.value.trim(),
      fotky: Object.keys(state.photos)
    };
    return d;
  }

  function reviewRows(d) {
    var rows = [
      ['Dveře', d.model, 1], ['Barva', d.odstin, 1], ['Zárubeň', d.zarubne, 1], ['Kování', d.kovani, 1], ['Kukátko', d.kukatko, 1], ['Vložky', d.vlozky, 1],
      ['Otevírání', d.otevirani, 1], ['Šířka', d.sire && d.sire !== 'Nevím' ? d.sire + ' cm' : d.sire, 1], ['Záruka', d.zaruka, 1],
      ['Orientační cena', kc(d.cena_celkem) + ' vč. DPH a montáže', 1],
      ['Jméno', d.jmeno + ' ' + d.prijmeni, 2], ['Telefon', d.telefon, 2], ['E-mail', d.email, 2],
      ['Adresa montáže', [d.ulice, d.psc, d.mesto].filter(Boolean).join(', '), 2], ['Fakturace', d.fakturace, 2]
    ];
    if (d.fakturace !== BILLING[0]) { rows.push(['Objednatel', d.fak_jmeno, 2], ['Fakturační adresa', d.fak_adresa, 2]); if (d.ic) rows.push(['IČ', d.ic + (d.platce_dph ? ', plátce DPH' : ''), 2]); }
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
  $$('.pq-photo').forEach(function (box) {
    var input = $('input', box), name = box.getAttribute('data-photo');
    input.addEventListener('change', function () {
      var f = input.files && input.files[0]; if (!f) return;
      shrink(f).then(function (b) {
        state.photos[name] = b;
        var pv = $('img', box); pv.src = URL.createObjectURL(b); pv.hidden = false; box.classList.add('has');
      });
    });
  });

  /* ── odeslání ── */
  function makeRef() {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'W' + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase();
  }
  function payload(d, withPhotos) {
    var fd = new FormData();
    fd.append('_subject', 'Poptávka dveří ' + d.cislo + ' – ' + d.model + ' – ' + d.prijmeni + ', ' + d.mesto);
    fd.append('_template', 'table');
    fd.append('_captcha', 'false');
    fd.append('_cc', CC);
    if (d.email) fd.append('_replyto', d.email);
    fd.append('_honey', form._honey.value);
    reviewRows(d).forEach(function (r) { fd.append(r[0], r[1]); });
    fd.append('Číslo poptávky', d.cislo);
    fd.append('Odesláno', new Date().toLocaleString('cs-CZ'));
    fd.append('data_json', JSON.stringify(d));
    if (withPhotos) Object.keys(state.photos).forEach(function (k) { fd.append(k, state.photos[k], k + '.jpg'); });
    return fd;
  }
  function send(fd) {
    return fetch('https://formsubmit.co/ajax/' + TO, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!(j && (j.success === true || j.success === 'true'))) throw new Error('fail'); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var msg = $('#pqMsg'); msg.hidden = true;
    if (!$('#pqConsent').checked) { msg.innerHTML = 'Pro odeslání prosím potvrďte souhlas se zpracováním osobních údajů.'; msg.hidden = false; return; }
    if (form._honey.value) return;
    var btn = $('#pqSubmit'); btn.disabled = true; btn.textContent = 'Odesílám…';
    state.ref = state.ref || makeRef();
    var d = collect(), hasPhotos = d.fotky.length > 0, photoLost = false;
    send(payload(d, true))
      .catch(function () {
        if (!hasPhotos) throw new Error('fail');
        photoLost = true; d.fotky = []; d.poznamka_system = 'Fotky se nepodařilo přiložit';
        return send(payload(d, false));
      })
      .then(function () {
        $('#doneRef').textContent = d.cislo;
        if (photoLost) { var w = $('#doneWarn'); w.innerHTML = 'Poptávka dorazila, ale fotky se nepodařilo přiložit. Pošlete nám je prosím přes <a href="https://wa.me/420725559235">WhatsApp</a> nebo na <a href="mailto:' + CC + '">' + CC + '</a> s číslem poptávky.'; w.hidden = false; }
        $('.pq-layout').hidden = true; $('.pq-steps').hidden = true; $('#pqDone').hidden = false;
        window.scrollTo({ top: $('.pq-wrap').offsetTop - 80, behavior: 'smooth' });
      })
      .catch(function () {
        msg.innerHTML = 'Odeslání se nepodařilo. Zkuste to prosím znovu, nebo nám zavolejte na <a href="tel:+420725559235">+420 725 559 235</a> či napište na <a href="mailto:' + CC + '">' + CC + '</a>.';
        msg.hidden = false;
        btn.disabled = false; btn.textContent = 'Odeslat poptávku';
      });
  });

  /* ── události ── */
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'model') {
      state.model = t.value; buildOptions(t.value);
      markBlock('model', false);
      setTimeout(function () { $('#pqModelOptions').scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 120);
    } else if (t.type === 'radio') markBlock(t.name, false);
    if (t.name === 'fakturace') {
      $('#pqBillingFields').hidden = t.value === BILLING[0];
      $('#pqCompany').hidden = t.value !== BILLING[2];
    }
    if (t.classList && t.classList.contains('bad')) t.classList.remove('bad');
    updateSummary();
  });
  form.addEventListener('input', function (e) { if (e.target.classList.contains('bad') && e.target.value.trim()) e.target.classList.remove('bad'); });
  form.addEventListener('click', function (e) {
    var t = e.target.closest('[data-next],[data-prev],[data-goto]'); if (!t) return;
    if (t.hasAttribute('data-next')) { if (validate(state.step)) go(state.step + 1); }
    else if (t.hasAttribute('data-prev')) go(state.step - 1);
    else go(+t.getAttribute('data-goto'));
  });

  buildModels(); buildStatic();
  // předvolba modelu z odkazu: poptavka-dvere.html?model=premium60
  var pre = (location.search.match(/[?&]model=([a-z0-9]+)/) || [])[1];
  if (pre && MODELS[pre]) { var r = form.querySelector('input[name="model"][value="' + pre + '"]'); r.checked = true; state.model = pre; buildOptions(pre); updateSummary(); }
})();
