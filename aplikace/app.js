/* Aplikace Bezpečné dveře (PWA) – vizualizace PREMIUM 60 na fotce, nabídka, moje dveře (záruka, roční prohlídka, servis).
   Odesílání: bd-form.js → formulare.dvere.app (kartotéka na NAS), záložně FormSubmit. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var store = {
    get: function (k, d) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  };

  /* ── data o modelech (z poptavka-dvere.js / dvere-do-bytu.html, stav 15. 10. 2026) ── */
  var MODELS = {
    premium60: { name: 'PREMIUM 60', spec: 'RC3 · EI 60 · 42 dB', tag: 'TOP', img: 'img/premium60.jpg', base: 2, bonus: true, ext: [5, 10],
      txt: 'Naše nejlepší dveře do bytu. Nejvyšší požární odolnost, připravené pro chytrý zámek tedee.' },
    extreme54: { name: 'EXTREME 54', spec: 'RC3 · EI 30 · 40 dB', tag: 'Výhodná cena', img: 'img/extreme54.jpg', base: 2, bonus: true, ext: [10],
      txt: 'Bezpečnostní třída RC3 za výhodnou cenu, základní záruka 2 roky.' },
    magnum56: { name: 'MAGNUM 56', spec: 'RC3 · EI 30 · 44 dB', img: 'img/magnum56.jpg', base: 2, bonus: true, ext: [5, 10],
      txt: 'Nejtišší z nabídky – zvuková izolace 44 dB.' },
    securido65: { name: 'SECURIDO 65', spec: 'RC3 · EI 30 · 39 dB', img: 'img/securido65.jpg', base: 2, bonus: true, ext: [5, 10],
      txt: 'Široký výběr dekorů a kování.' }
  };
  /* vizualizace: obrázky celých křídel zepředu (panty vlevo, klika vpravo).
     PREMIUM 60: dekory z premium60.cz; zárubeň v dekoru dveří.
     EXTREME 54: fotky z akčního katalogu Porta (str. 10 a 32, „fotky dekorů nemusí odpovídat skutečnosti“); lakovaná zárubeň v samostatné barvě (z poptavka-dvere.js). */
  var VIZ = {
    premium60: { shades: [
        ['Černá perleť', 'img/premium60-cerna-perlet.jpg'], ['Dub', 'img/premium60-dub.jpg'], ['Ořech', 'img/premium60-orech.jpg'],
        ['Světlé wenge', 'img/premium60-svetle-wenge.jpg'], ['Bílá', 'img/premium60-bila.jpg'], ['Antracit', 'img/premium60-antracit.jpg']],
      def: 1, note: 'Bílá wenge a Ocelově šedá (příplatkové odstíny) zatím na fotce vyzkoušet nejdou.' },
    extreme54: { shades: [['Dub natura', 'img/extreme54-dub-natura.jpg'], ['Ořech natura', 'img/extreme54-orech-natura.jpg'], ['Antracit', 'img/extreme54-antracit.jpg']],
      def: 0, note: 'Skladem: Dub natura a Ořech natura. Ořech bělený, Bílá, Beton tmavý a Dub Milano 4 zatím na fotce vyzkoušet nejdou.',
      frames: [['Hnědá matná', '#5a3b26', 1], ['Béžová matná', '#d8c7a8'], ['Bílá matná', '#f4f4f1'], ['Šedá', '#8f9396'], ['Antracit', '#3b3f42'], ['Černá', '#151515']] }
  };
  /* dveře do domu a interiérové dveře: provedení z viz-data.js (názvy a orientační ceny z webu) */
  var DD = window.VIZ_DATA || {};
  if (DD.domovni) VIZ.domovni = { cat: 'domu', name: 'Dveře do domu ERKADO', label: 'Provedení', noFrame: true, def: 0,
    shades: DD.domovni.map(function (x) { return [x[0], x[1], x[2], x[3], false]; }), web: '../dvere-do-domu.html', ask: '../dvere-do-domu.html#poptavka', priceNote: 'Orientační cena z katalogu ERKADO; přesnou podle rozměru a provedení vám spočítáme.',
    note: 'Vyberte provedení a uvidíte ho na fotce svých dveří. Barevné varianty a rozměry upřesníme v nabídce.' };
  if (DD.interier) VIZ.interier = { cat: 'int', name: 'Interiérové dveře', label: 'Provedení', noFrame: true, def: 0,
    shades: DD.interier.slice().sort(function (a, b) { return (b[4] ? 1 : 0) - (a[4] ? 1 : 0); }).map(function (x) { return [x[0], x[1], x[2], x[3], x[4], x[5]]; }),
    web: '../dvere-do-interieru.html', ask: '../dvere-do-interieru.html#poptavka', priceNote: 'Cena komplet se zárubní, bez kování a bez DPH.',
    note: 'Nejdřív jsou dveře skladem – u nich si vyberete i odstín. Na fotce nahradíte křídlo; zárubeň zůstává vaše.' };
  VIZ.premium60.cat = VIZ.extreme54.cat = 'byt';
  var vizModel = 'premium60', frameIdx = 0, locked = false;
  function SH() { return VIZ[vizModel].shades; }
  /* odstíny interiérových dveří (6. pole provedení): [[název, obrázek], …] */
  function DEC(i) { var x = SH()[i == null ? shadeIdx : i]; return x && x[5] && x[5].length ? x[5] : null; }
  function srcOf(i) { var d = DEC(i); return d ? d[Math.min(decIdx, d.length - 1)][1] : SH()[i][1]; }
  function hex2rgb(h) { return [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16) / 255; }); }

  /* ═══════════════ anonymní počítadlo využití (NAS: aplikace-api.php, akce „udalost“) ═══════════════
     Posílají se jen počty: druh události, druh dveří, model/odstín a zda jde o prodejce. Žádné jméno, fotka ani ID zařízení. */
  var statN = 0;
  function stat(udalost, kat, model) {
    try {
      if (statN++ > 60 || !window.BDApp || !BDApp.BASE || !navigator.sendBeacon || /^(localhost|127\.)/.test(location.hostname)) return;
      var fd = new FormData(); fd.append('akce', 'udalost'); fd.append('udalost', udalost); fd.append('kat', kat || '');
      fd.append('model', String(model || '').slice(0, 60)); fd.append('prodejce', sessionStorage.getItem('bd-app-prodejce') === '1' ? '1' : '0');
      navigator.sendBeacon(BDApp.BASE + 'aplikace-api.php', fd);
    } catch (e) {}
  }
  function statDen() { // jednou za den na zařízení = „návštěva“ (datum se drží jen v tomto telefonu)
    var d = new Date().toISOString().slice(0, 10), was = store.get('bd-stat-den', '');
    if (was !== d) { store.set('bd-stat-den', d); stat(was ? 'navsteva' : 'navsteva_nova'); }
  }
  var vizDone = false; // jedna vizualizace = první nasazené dveře na nové fotce
  function statViz() {
    if (pts.length !== 4) return; var V = VIZ[vizModel];
    var m = V.cat === 'byt' ? MN() + ' – ' + shadeName() : shadeName();
    if (!vizDone) { vizDone = true; stat('vizualizace', V.cat, m); } else stat('vyber', V.cat, m);
  }

  /* ═══════════════ navigace ═══════════════ */
  /* Moje dveře: zapne se samo od 1. 11. 2026 (jen dveře z kartotéky, tj. koupené od tohoto data).
     Náhled dřív: #moje?test=1 (platí jen v tomto prohlížeči, vypnutí #moje?test=0) nebo stejný přepínač bd-bonus jako na webu (?bonus=1). */
  function mojeOn() {
    var on = Date.now() >= new Date('2026-11-01T00:00:00+01:00').getTime();
    try {
      var t = /[?&]test=([01])/.exec(location.hash);
      if (t && /^#moje/.test(location.hash)) { if (t[1] === '1') localStorage.setItem('bd-bonus', '1'); else localStorage.removeItem('bd-bonus'); }
      if (localStorage.getItem('bd-bonus') === '1') on = true;
    } catch (e) {}
    var tab = document.getElementById('tabMoje'); if (tab) tab.hidden = !on;
    document.querySelector('.tabs').style.gridTemplateColumns = 'repeat(' + (on ? 4 : 3) + ',1fr)';
    return on;
  }
  /* QR z předání: #moje?t=<kód dveří> aplikaci otevře a dveře rovnou spáruje (zapne i Moje dveře, protože zákazník dveře od nás opravdu má) */
  function pairByCode(t) {
    $('#mojeEmpty').hidden = false; $('#mojeDoor').hidden = true; var m = $('#pairMsg'); if (m) { m.className = 'msg'; m.textContent = 'Spárovávám vaše dveře…'; }
    BDApp.api('sparovat_kod', { t: t }).then(function (j) {
      var d = j.dvere || {};
      store.set('bd-app-dvere', { model_kod: d.model_kod, odstin: d.odstin, cislo: d.cislo, model: d.model || '', zaruka_do: /^\d{4}-\d{2}-\d{2}$/.test(d.zaruka_do || '') ? d.zaruka_do : '',
        predano: /^\d{4}-\d{2}-\d{2}$/.test(d.predano || '') ? d.predano : '', prodlouzena: d.prodlouzeno_let || 0, registrace: false, zdroj: 'kartoteka', token: t, moje_url: j.moje_url || '' });
      if (j.kontakt) store.set('bd-app-kontakt', Object.assign({ jmeno: j.kontakt.jmeno, prijmeni: j.kontakt.prijmeni }, kontakt()));
      try { localStorage.setItem('bd-bonus', '1'); } catch (e) {}
      mojeOn(); try { history.replaceState(null, '', '#moje'); } catch (e) {} renderMoje();
    }).catch(function (e) { if (m) { m.className = 'msg err'; m.textContent = e.code === 'nenalezeno' ? 'Kód dveří není platný. Zadejte číslo objednávky níže, nebo nám zavolejte: 725 559 235.' : 'Spárování se nepovedlo. Zkuste to prosím znovu.'; } });
  }
  function route() {
    mojeOn();
    var v = (location.hash || '#viz').slice(1).split('?')[0];
    if (!$('[data-view="' + v + '"]')) v = 'viz';
    if (v === 'moje' && !mojeOn() && !/[?&]t=[a-f0-9]{24}/.test(location.hash)) v = 'viz';
    $$('.view').forEach(function (s) { s.hidden = s.getAttribute('data-view') !== v; });
    $$('.tabs a').forEach(function (a) { if (a.getAttribute('data-tab') === v) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
    if (/[?&]p=1/.test(location.hash)) { try { sessionStorage.setItem('bd-app-prodejce', '1'); } catch (e) {} }
    if (v === 'viz') { var mm = /[?&]m=([a-z0-9]+)/.exec(location.hash); if (mm && VIZ[mm[1]]) { if (mm[1] !== vizModel) setModel(mm[1]); setLock(true); } else setLock(false); layout(); }
    if (v === 'moje') {
      var tm = /[?&]t=([a-f0-9]{24})/.exec(location.hash), cd = door();
      if (tm && !(cd && doorToken(cd) === tm[1])) pairByCode(tm[1]); else renderMoje();
    }
    if (v === 'viz' && /[?&]navod=1/.test(location.hash)) setTimeout(openHowto, 50);
    window.scrollTo(0, 0);
  }
  window.addEventListener('hashchange', route);

  /* ═══════════════ VIZUALIZACE ═══════════════ */
  var canvas = $('#gl'), ov = $('#ov'), hint = $('#hint'), stage = $('#stage'), splitEl = $('#split');
  var gl, loc = {}, texPhoto, texDoor, photo = null, gray = null, small = null, pts = [], snapped = [false, false, false, false];
  var shadeIdx = 1, decIdx = 0, flip = false, split = -1, doorImgs = {}, light = { gain: [1, 1, 1], grad: [0, 0] };

  var VS = 'attribute vec2 p;varying vec2 t;void main(){t=vec2(p.x*.5+.5,.5-p.y*.5);gl_Position=vec4(p,0.,1.);}';
  var FS = [
    'precision highp float;varying vec2 t;',
    'uniform sampler2D ph,dr;uniform mat3 Hi;uniform float on,fl,soft,split,fr,fw,fwy;uniform vec3 gain,fcol;uniform vec2 grad;',
    'void main(){vec4 c=texture2D(ph,t);',
    ' if(on>.5&&t.x>=split){vec3 q=Hi*vec3(t,1.);vec2 uv=q.xy/q.z;float s=max(soft,.0005);',
    '  float lf=1.+grad.x*(uv.x-.5)+grad.y*(uv.y-.5);vec3 col=c.rgb;',
    '  if(fr>.5){float ex=min(uv.x+fw,1.+fw-uv.x);float ey=min(uv.y+fwy,1.-uv.y);float mf=smoothstep(0.,s,min(ex,ey));',
    '   float inner=clamp(-min(min(uv.x,1.-uv.x),uv.y)/max(fw,1e-4),0.,1.);',
    '   col=mix(col,clamp(fcol*gain*lf*(.84+.16*inner),0.,1.),mf);}',
    '  float e=min(min(uv.x,1.-uv.x),min(uv.y,1.-uv.y));float m=smoothstep(0.,s,e);',
    '  vec2 du=uv;if(fl>.5)du.x=1.-du.x;vec3 d=texture2D(dr,clamp(du,0.,1.)).rgb*gain*lf;',
    '  c=vec4(mix(col,clamp(d,0.,1.),m),1.);}',
    ' gl_FragColor=c;}'
  ].join('\n');

  function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s)); return s; }
  function initGL() {
    var o = { preserveDrawingBuffer: true, antialias: false };
    gl = canvas.getContext('webgl', o) || canvas.getContext('experimental-webgl', o);
    if (!gl) { hint.textContent = 'Tento prohlížeč neumí WebGL. Zkuste Chrome nebo Safari.'; return false; }
    var prog = gl.createProgram(); gl.attachShader(prog, sh(gl.VERTEX_SHADER, VS)); gl.attachShader(prog, sh(gl.FRAGMENT_SHADER, FS)); gl.linkProgram(prog); gl.useProgram(prog);
    var b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    var a = gl.getAttribLocation(prog, 'p'); gl.enableVertexAttribArray(a); gl.vertexAttribPointer(a, 2, gl.FLOAT, false, 0, 0);
    ['ph', 'dr', 'Hi', 'on', 'fl', 'soft', 'split', 'fr', 'fw', 'fwy', 'gain', 'fcol', 'grad'].forEach(function (n) { loc[n] = gl.getUniformLocation(prog, n); });
    texPhoto = mkTex(); texDoor = mkTex(); gl.uniform1i(loc.ph, 0); gl.uniform1i(loc.dr, 1);
    return true;
  }
  function mkTex() { var t = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, t);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); return t; }
  function upload(tex, unit, src) { gl.activeTexture(gl.TEXTURE0 + unit); gl.bindTexture(gl.TEXTURE_2D, tex); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, src); }

  /* homografie jednotkový čtverec → čtyřúhelník TL,TR,BR,BL (souřadnice 0–1 fotky); matice sloupcově */
  function squareToQuad(q) {
    var x0 = q[0][0], y0 = q[0][1], x1 = q[1][0], y1 = q[1][1], x2 = q[2][0], y2 = q[2][1], x3 = q[3][0], y3 = q[3][1];
    var dx1 = x1 - x2, dx2 = x3 - x2, dx3 = x0 - x1 + x2 - x3, dy1 = y1 - y2, dy2 = y3 - y2, dy3 = y0 - y1 + y2 - y3;
    var det = dx1 * dy2 - dx2 * dy1, g = (dx3 * dy2 - dx2 * dy3) / det, h = (dx1 * dy3 - dx3 * dy1) / det;
    return [x1 - x0 + g * x1, y1 - y0 + g * y1, g, x3 - x0 + h * x3, y3 - y0 + h * y3, h, x0, y0, 1];
  }
  function inv3(m) {
    var a = m[0], b = m[3], c = m[6], d = m[1], e = m[4], f = m[7], g = m[2], h = m[5], i = m[8];
    var A = e * i - f * h, B = -(d * i - f * g), C = d * h - e * g, D = -(b * i - c * h), E = a * i - c * g, F = -(a * h - b * g), G = b * f - c * e, H = -(a * f - c * d), I = a * e - b * d;
    var det = a * A + b * B + c * C;
    return [A / det, B / det, C / det, D / det, E / det, F / det, G / det, H / det, I / det];
  }
  function mapUV(H, u, v) { var x = H[0] * u + H[3] * v + H[6], y = H[1] * u + H[4] * v + H[7], w = H[2] * u + H[5] * v + H[8]; return [x / w, y / w]; }

  /* šíře zárubně v uv (vodorovně podíl šířky křídla; nahoře přepočet podle poměru stran křídla) */
  function frameUV() {
    var fw = +$('#fwid').value / 100;
    if (pts.length !== 4 || !photo) return [fw, fw * 0.45];
    var W = photo.width, Hh = photo.height;
    var wTop = Math.hypot((pts[1][0] - pts[0][0]) * W, (pts[1][1] - pts[0][1]) * Hh), hL = Math.hypot((pts[3][0] - pts[0][0]) * W, (pts[3][1] - pts[0][1]) * Hh);
    return [fw, hL > 0 ? fw * wTop / hL : fw * 0.45];
  }

  function render() {
    if (!gl || !photo) return;
    gl.viewport(0, 0, canvas.width, canvas.height);
    var di = doorImgs[srcOf(shadeIdx)], ready = pts.length === 4 && di && di.ok;
    gl.uniform1f(loc.on, ready ? 1 : 0);
    if (ready) {
      gl.uniformMatrix3fv(loc.Hi, false, new Float32Array(inv3(squareToQuad(pts))));
      gl.uniform1f(loc.fl, flip ? 1 : 0);
      var b = $('#bri').value / 100, auto = $('#autolight').checked;
      var g = auto ? light.gain : [1, 1, 1], gr = auto ? light.grad : [0, 0];
      gl.uniform3f(loc.gain, g[0] * b, g[1] * b, g[2] * b); gl.uniform2f(loc.grad, gr[0], gr[1]);
      gl.uniform1f(loc.soft, +$('#soft').value / 100 * 0.012);
      gl.uniform1f(loc.split, split);
      var fu = frameUV(); gl.uniform1f(loc.fr, $('#frame').checked ? 1 : 0); gl.uniform1f(loc.fw, fu[0]); gl.uniform1f(loc.fwy, fu[1]);
      var fr = VIZ[vizModel].frames, fc = fr ? hex2rgb(fr[frameIdx][1]) : di.mean; gl.uniform3f(loc.fcol, fc[0], fc[1], fc[2]);
    }
    gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, texPhoto);
    gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, texDoor);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
  }
  var rq = 0; function renderSoon() { if (rq) return; rq = requestAnimationFrame(function () { rq = 0; render(); }); }

  function loadDoor(i, cb) {
    var src = srcOf(i), o = doorImgs[src];
    if (o && o.ok) { upload(texDoor, 1, o.img); return cb && cb(); }
    if (o) { o.cbs.push(cb); return; }
    var im = new Image(); o = doorImgs[src] = { img: im, ok: false, cbs: [cb] };
    im.onload = function () {
      // průměrná barva vnitřku křídla → barva nové zárubně (PREMIUM 60)
      var c = document.createElement('canvas'); c.width = 8; c.height = 8; var x = c.getContext('2d');
      x.drawImage(im, im.width * .1, im.height * .05, im.width * .8, im.height * .9, 0, 0, 8, 8);
      var d = x.getImageData(0, 0, 8, 8).data, sm = [0, 0, 0]; for (var k = 0; k < d.length; k += 4) { sm[0] += d[k]; sm[1] += d[k + 1]; sm[2] += d[k + 2]; }
      o.mean = sm.map(function (v) { return v / 64 / 255; }); o.ok = true;
      if (SH()[shadeIdx] && srcOf(shadeIdx) === src) upload(texDoor, 1, im);
      o.cbs.forEach(function (f) { f && f(); }); o.cbs = [];
    };
    im.onerror = function () { hint.textContent = 'Obrázek odstínu se nepodařilo načíst. Zkontrolujte připojení.'; };
    im.src = src;
  }

  /* ── fotka ── */
  function setPhoto(bmp) {
    var MAX = 1800, w = bmp.width, h = bmp.height, sc = Math.min(1, MAX / Math.max(w, h));
    var c = document.createElement('canvas'); c.width = Math.round(w * sc); c.height = Math.round(h * sc);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    photo = c; canvas.width = c.width; canvas.height = c.height;
    // zmenšenina pro přichycení k rohům a odhad světla
    var gs = Math.min(1, 800 / Math.max(c.width, c.height)), gc = document.createElement('canvas');
    gc.width = Math.max(1, Math.round(c.width * gs)); gc.height = Math.max(1, Math.round(c.height * gs));
    var gx = gc.getContext('2d'); gx.drawImage(c, 0, 0, gc.width, gc.height);
    var id = gx.getImageData(0, 0, gc.width, gc.height).data, g = new Float32Array(gc.width * gc.height);
    for (var i = 0, j = 0; i < id.length; i += 4, j++) g[j] = (0.299 * id[i] + 0.587 * id[i + 1] + 0.114 * id[i + 2]) / 255;
    gray = { w: gc.width, h: gc.height, d: g }; small = { w: gc.width, h: gc.height, d: id };
    upload(texPhoto, 0, c); pts = []; snapped = [false, false, false, false]; setSplit(-1);
    $('#empty').hidden = true; ov.hidden = false; $('#cornerBtns').hidden = false;
    layout(); buildOverlay(); state(); render();
    setTimeout(function () { stage.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
  }
  function onFile(e) {
    var f = e.target.files && e.target.files[0]; if (!f) return; e.target.value = '';
    hint.className = 'hint'; hint.textContent = 'Načítám fotku…';
    var p = window.createImageBitmap ? createImageBitmap(f, { imageOrientation: 'from-image' }).catch(function () { return viaImg(f); }) : viaImg(f);
    p.then(function (b) { vizDone = false; stat('foto', VIZ[vizModel].cat); return setPhoto(b); }).catch(function () { hint.textContent = 'Fotku se nepodařilo otevřít. Zkuste jinou (JPG nebo PNG).'; });
  }
  function viaImg(f) { return new Promise(function (res, rej) { var u = URL.createObjectURL(f), im = new Image(); im.onload = function () { URL.revokeObjectURL(u); res(im); }; im.onerror = rej; im.src = u; }); }

  /* velikost plátna podle šířky obrazovky a 72 % výšky; překryv a rozdělovač přesně na plátno */
  function layout() {
    if (!photo) return;
    var sw = stage.clientWidth, maxH = Math.round(window.innerHeight * 0.72), ar = photo.width / photo.height;
    var w = sw, h = w / ar; if (h > maxH) { h = maxH; w = h * ar; }
    canvas.style.width = Math.round(w) + 'px'; canvas.style.height = Math.round(h) + 'px';
    var left = Math.round((sw - w) / 2);
    ov.style.left = left + 'px'; ov.style.top = '0px'; ov.style.width = Math.round(w) + 'px'; ov.style.height = Math.round(h) + 'px';
    placeSplit();
  }
  window.addEventListener('resize', function () { layout(); buildOverlay(); });

  /* ── přichycení k rohu (Harrisův detektor v okolí bodu) ── */
  function snapPoint(p) {
    if (!gray || !$('#snap').checked) return null;
    var W = gray.w, H = gray.h, g = gray.d, cx = Math.round(p[0] * W), cy = Math.round(p[1] * H);
    var r = Math.max(5, Math.round(Math.max(W, H) * 0.016)), b = 2, x0 = cx - r - b - 1, y0 = cy - r - b - 1, n = 2 * (r + b + 1) + 1;
    var Ixx = new Float32Array(n * n), Iyy = new Float32Array(n * n), Ixy = new Float32Array(n * n);
    function px(x, y) { x = x < 0 ? 0 : x >= W ? W - 1 : x; y = y < 0 ? 0 : y >= H ? H - 1 : y; return g[y * W + x]; }
    for (var yy = 1; yy < n - 1; yy++) for (var xx = 1; xx < n - 1; xx++) {
      var X = x0 + xx, Y = y0 + yy;
      var gx = (px(X + 1, Y - 1) + 2 * px(X + 1, Y) + px(X + 1, Y + 1)) - (px(X - 1, Y - 1) + 2 * px(X - 1, Y) + px(X - 1, Y + 1));
      var gy = (px(X - 1, Y + 1) + 2 * px(X, Y + 1) + px(X + 1, Y + 1)) - (px(X - 1, Y - 1) + 2 * px(X, Y - 1) + px(X + 1, Y - 1));
      var k = yy * n + xx; Ixx[k] = gx * gx; Iyy[k] = gy * gy; Ixy[k] = gx * gy;
    }
    var best = 0, bx = cx, by = cy, sig = r * 0.5;
    for (var y = -r; y <= r; y++) for (var x = -r; x <= r; x++) {
      var sxx = 0, syy = 0, sxy = 0, ix = x + r + b + 1, iy = y + r + b + 1;
      for (var v = -b; v <= b; v++) for (var u = -b; u <= b; u++) { var kk = (iy + v) * n + ix + u; sxx += Ixx[kk]; syy += Iyy[kk]; sxy += Ixy[kk]; }
      var R = sxx * syy - sxy * sxy - 0.05 * (sxx + syy) * (sxx + syy);
      var sc = R * Math.exp(-(x * x + y * y) / (2 * sig * sig));
      if (sc > best) { best = sc; bx = cx + x; by = cy + y; }
    }
    if (best < 0.02) return null; // v okolí není výrazný roh
    return [Math.min(1, Math.max(0, (bx + .5) / W)), Math.min(1, Math.max(0, (by + .5) / H))];
  }

  /* ── odhad světla z okolí dveří: expozice, barevný nádech, přechod zleva doprava a shora dolů ── */
  function estimateLight() {
    if (!small || pts.length !== 4) return;
    var H = squareToQuad(pts), W = small.w, Hh = small.h, d = small.d;
    var acc = { all: [0, 0, 0, 0], l: [0, 0], r: [0, 0], t: [0, 0], b: [0, 0] };
    for (var v = -0.2; v <= 1.0; v += 0.04) for (var u = -0.45; u <= 1.45; u += 0.04) {
      if (u > -0.18 && u < 1.18 && v > -0.12) continue; // jen okolí, ne dveře a zárubeň
      var q = mapUV(H, u, v), x = Math.round(q[0] * W), y = Math.round(q[1] * Hh);
      if (x < 0 || y < 0 || x >= W || y >= Hh) continue;
      var i = (y * W + x) * 4, R = d[i] / 255, G = d[i + 1] / 255, B = d[i + 2] / 255, L = 0.299 * R + 0.587 * G + 0.114 * B;
      if (L < 0.04 || L > 0.98) continue; // tma a přepaly nepočítat
      acc.all[0] += R; acc.all[1] += G; acc.all[2] += B; acc.all[3]++;
      if (u < 0.5) { acc.l[0] += L; acc.l[1]++; } else { acc.r[0] += L; acc.r[1]++; }
      if (v < 0.45) { acc.t[0] += L; acc.t[1]++; } else { acc.b[0] += L; acc.b[1]++; }
    }
    var n = acc.all[3]; if (n < 12) { light = { gain: [1, 1, 1], grad: [0, 0] }; return; }
    var m = [acc.all[0] / n, acc.all[1] / n, acc.all[2] / n], Ls = 0.299 * m[0] + 0.587 * m[1] + 0.114 * m[2];
    var cl = function (x, a, b) { return Math.max(a, Math.min(b, x)); };
    var e = cl(Ls / 0.66, 0.55, 1.12);
    var tint = m.map(function (c) { return cl(1 + 0.35 * (c / Ls - 1), 0.88, 1.12); });
    var avg = function (a) { return a[1] ? a[0] / a[1] : Ls; };
    light = { gain: tint.map(function (t) { return t * e; }),
      grad: [cl((avg(acc.r) - avg(acc.l)) / Ls, -0.5, 0.5) * 0.5, cl((avg(acc.b) - avg(acc.t)) / Ls, -0.5, 0.5) * 0.5] };
  }

  /* ── označení rohů ── */
  var NAMES = ['levý horní', 'pravý horní', 'pravý dolní', 'levý dolní'], dragging = -1;
  function pos(e) { var r = ov.getBoundingClientRect(); return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))]; }
  function buildOverlay() {
    $$('.pt', ov).forEach(function (n) { n.remove(); });
    pts.forEach(function (p, i) {
      var d = document.createElement('div'); d.className = 'pt' + (snapped[i] ? ' snapped' : ''); d.textContent = i + 1; d.setAttribute('data-i', i);
      d.style.left = p[0] * 100 + '%'; d.style.top = p[1] * 100 + '%'; ov.appendChild(d);
    });
    var s = $('svg', ov); if (s) s.remove();
    if (pts.length > 1 && split < 0) {
      var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'); svg.setAttribute('viewBox', '0 0 100 100'); svg.setAttribute('preserveAspectRatio', 'none');
      var pl = document.createElementNS(ns, pts.length === 4 ? 'polygon' : 'polyline');
      pl.setAttribute('points', pts.map(function (p) { return p[0] * 100 + ',' + p[1] * 100; }).join(' '));
      pl.setAttribute('fill', 'none'); pl.setAttribute('stroke', '#c2410c'); pl.setAttribute('vector-effect', 'non-scaling-stroke'); pl.style.strokeWidth = '2px';
      if (pts.length === 4) pl.style.strokeDasharray = '6 5';
      svg.appendChild(pl); ov.appendChild(svg);
    }
    ov.classList.toggle('hidepts', split >= 0);
    $$('.pt', ov).forEach(function (n) { n.style.display = split >= 0 ? 'none' : ''; });
  }
  function loupe(p, show) {
    var L = $('#loupe'); if (!show || !photo) { L.style.display = 'none'; return; }
    var lc = $('#lp'), x = lc.getContext('2d'), z = Math.max(50, Math.round(Math.max(photo.width, photo.height) * 0.04));
    x.fillStyle = '#000'; x.fillRect(0, 0, 220, 220); x.drawImage(photo, p[0] * photo.width - z / 2, p[1] * photo.height - z / 2, z, z, 0, 0, 220, 220);
    var r = ov.getBoundingClientRect(), sr = stage.getBoundingClientRect(), px = r.left - sr.left + p[0] * r.width, py = p[1] * r.height;
    L.style.left = Math.min(Math.max(0, px - 60 + (px > sr.width / 2 ? -90 : 90)), sr.width - 120) + 'px'; L.style.top = Math.max(0, py - 160) + 'px'; L.style.display = 'block';
  }
  function afterCorners() { if (pts.length === 4) { estimateLight(); if (!vizDone) statViz(); } buildOverlay(); state(); render(); }
  ov.addEventListener('pointerdown', function (e) {
    if (split >= 0) return;
    var t = e.target.closest && e.target.closest('.pt'); e.preventDefault();
    if (t) { dragging = +t.getAttribute('data-i'); t.classList.add('drag'); ov.setPointerCapture(e.pointerId); loupe(pts[dragging], true); return; }
    if (pts.length < 4) {
      var p = pos(e), s = snapPoint(p); pts.push(s || p); snapped[pts.length - 1] = !!s;
      if (pts.length === 4) orderPts();
      afterCorners();
    }
  });
  ov.addEventListener('pointermove', function (e) {
    if (dragging < 0) return; pts[dragging] = pos(e); snapped[dragging] = false;
    var n = $('.pt[data-i="' + dragging + '"]', ov); n.style.left = pts[dragging][0] * 100 + '%'; n.style.top = pts[dragging][1] * 100 + '%'; n.classList.remove('snapped');
    var svgp = $('svg > *', ov); if (svgp) svgp.setAttribute('points', pts.map(function (p) { return p[0] * 100 + ',' + p[1] * 100; }).join(' '));
    loupe(pts[dragging], true); renderSoon();
  });
  function endDrag() {
    if (dragging < 0) return; var i = dragging; dragging = -1; loupe(null, false);
    var s = snapPoint(pts[i]); if (s) { pts[i] = s; snapped[i] = true; }
    afterCorners();
  }
  ov.addEventListener('pointerup', endDrag); ov.addEventListener('pointercancel', endDrag);
  /* body seřadit na LH, PH, PD, LD bez ohledu na pořadí klepnutí */
  function orderPts() {
    var c = pts.reduce(function (a, p) { return [a[0] + p[0] / 4, a[1] + p[1] / 4]; }, [0, 0]);
    var idx = [0, 1, 2, 3].sort(function (a, b) { return Math.atan2(pts[a][1] - c[1], pts[a][0] - c[0]) - Math.atan2(pts[b][1] - c[1], pts[b][0] - c[0]); });
    // úhel od -π: začneme bodem vlevo nahoře (nejmenší x+y)
    var o = idx.map(function (i) { return pts[i]; }), so = idx.map(function (i) { return snapped[i]; });
    var start = 0, mn = 9; o.forEach(function (p, i) { if (p[0] + p[1] < mn) { mn = p[0] + p[1]; start = i; } });
    pts = [0, 1, 2, 3].map(function (k) { return o[(start + k) % 4]; }); snapped = [0, 1, 2, 3].map(function (k) { return so[(start + k) % 4]; });
  }

  function state() {
    var n = pts.length, done = n === 4;
    hint.className = 'hint' + (done ? ' ok' : '');
    if (!photo) hint.textContent = 'Začněte fotkou dveří.';
    else hint.textContent = done ? 'Hotovo! Rohy můžete doladit tažením (s lupou), odstín vyberete níže.' :
      'Klepněte na ' + (n + 1) + '. roh křídla dveří (' + NAMES[n] + ') – ne na zárubeň. Nebo použijte „Rámeček k posunutí“.';
    ['save', 'cmp', 'ask'].forEach(function (id) { $('#' + id).disabled = !done; });
    var sb = $('#share'); sb.disabled = !done; sb.hidden = !(navigator.canShare && navigator.share);
  }

  /* ── porovnání před/po (posuvný předěl) ── */
  function setSplit(v) {
    split = v; splitEl.hidden = v < 0; $('#cmp').setAttribute('aria-pressed', v >= 0); placeSplit(); buildOverlay(); render();
  }
  function placeSplit() { if (split < 0) return; splitEl.style.left = (parseFloat(ov.style.left) || 0) + split * ov.clientWidth + 'px'; splitEl.style.height = ov.clientHeight + 'px'; }
  (function () {
    var drag = false;
    splitEl.addEventListener('pointerdown', function (e) { drag = true; splitEl.setPointerCapture(e.pointerId); e.preventDefault(); });
    splitEl.addEventListener('pointermove', function (e) {
      if (!drag) return; var r = ov.getBoundingClientRect(); split = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)); placeSplit(); renderSoon();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) { splitEl.addEventListener(ev, function () { drag = false; }); });
  })();

  /* ── ovládání ── */
  function buildShades() {
    var V = VIZ[vizModel];
    $('#shades').innerHTML = V.shades.map(function (x, i) {
      return '<button class="sh" data-i="' + i + '" aria-pressed="' + (i === shadeIdx) + '"><img src="' + x[1] + '" alt="" loading="lazy">' + esc(x[0]) +
        (x[3] ? '<small>' + esc(x[3]) + '</small>' : '') + (x[4] ? '<em class="sk">skladem</em>' : '') + (x[5] && x[5].length > 1 ? '<i class="ndec">' + x[5].length + (x[5].length > 4 ? ' odstínů' : ' odstíny') + '</i>' : '') + '</button>'; }).join('');
    $('#shadeNote').textContent = V.note;
    $('#shadeH').textContent = V.label || 'Odstín';
    $('#frameBox').hidden = !V.frames;
    var fl = $('#frame').closest('label'); fl.hidden = !!V.noFrame; if (V.noFrame) $('#frame').checked = false; else $('#frame').checked = true;
    $('.split-r').textContent = V.cat === 'byt' ? (MODELS[vizModel] || {}).name : (V.cat === 'domu' ? 'ERKADO' : 'Nové dveře');
    $('#frameLbl').textContent = V.frames ? 'Nová zárubeň (barva podle výběru)' : 'Nová zárubeň v odstínu dveří';
    if (V.frames) $('#frames').innerHTML = V.frames.map(function (f, i) {
      return '<button class="fr" data-i="' + i + '" aria-pressed="' + (i === frameIdx) + '"><i style="background:' + f[1] + '"></i>' + f[0] + (f[2] ? ' (skladem)' : '') + '</button>'; }).join('');
    $('#shades').classList.toggle('long', V.shades.length > 12); showPick();
  }
  function showPick() {
    var V = VIZ[vizModel], x = V.shades[shadeIdx], el = $('#pick');
    if (V.cat === 'byt' || !x) { el.hidden = true; return; }
    el.hidden = false;
    el.style.marginBottom = '.6rem'; el.innerHTML = '<b>' + esc(x[0]) + '</b>' + (x[4] ? ' <em class="sk">skladem</em>' : '') + '<br><span>' + esc(x[2]) + '</span>' +
      (x[3] ? '<br><strong>' + esc(x[3]) + '</strong> <small>' + esc(V.priceNote) + '</small>' : '') + decHtml() + '<br><a href="' + V.web + '">Katalog a ceny na webu →</a>';
  }
  function decHtml() {
    var d = DEC(); if (!d || d.length < 2) return '';
    return '<div class="decs" role="group" aria-label="Odstín"><b class="dech">Odstín: ' + esc(d[decIdx][0]) + '</b>' + d.map(function (o, i) {
      return '<button type="button" class="dec" data-d="' + i + '" aria-pressed="' + (i === decIdx) + '" title="' + esc(o[0]) + '"><img src="' + o[1] + '" alt="" loading="lazy"><span>' + esc(o[0]) + '</span></button>'; }).join('') +
      '</div><span class="decn">Fotky odstínů z katalogu výrobce – skutečný odstín uvidíte na vzorku v prodejně.</span>';
  }
  /* zamčený režim: z webu (nebo z Nabídky) vedeme na konkrétní dveře, ostatní nenabízíme */
  function setLock(on) {
    locked = on; var V = VIZ[vizModel], byt = V.cat === 'byt';
    $('#catBox').hidden = on; $('#vizModel').hidden = on ? true : !byt;
    $('#lockBar').hidden = !on;
    if (on) $('#lockTxt').textContent = MN();
    $$('#vizCat .cat').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-c') === V.cat); });
  }
  function setModel(m) {
    if (!VIZ[m]) return; vizModel = m; shadeIdx = VIZ[m].def; decIdx = 0; frameIdx = 0;
    $$('#vizModel .b').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-m') === m); });
    buildShades(); setLock(locked); loadDoor(shadeIdx, function () { render(); });
  }
  $('#pick').addEventListener('click', function (e) {
    var b = e.target.closest('.dec'); if (!b) return; decIdx = +b.getAttribute('data-d');
    showPick(); loadDoor(shadeIdx, function () { render(); }); statViz();
  });
  $('#shades').addEventListener('click', function (e) {
    var b = e.target.closest('.sh'); if (!b) return; shadeIdx = +b.getAttribute('data-i'); decIdx = 0;
    $$('.sh').forEach(function (x) { x.setAttribute('aria-pressed', x === b); });
    showPick(); loadDoor(shadeIdx, function () { render(); }); statViz();
  });
  $('#frames').addEventListener('click', function (e) {
    var b = e.target.closest('.fr'); if (!b) return; frameIdx = +b.getAttribute('data-i');
    $$('.fr').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); $('#frame').checked = true; render();
  });
  $('#vizModel').addEventListener('click', function (e) { var b = e.target.closest('[data-m]'); if (b) setModel(b.getAttribute('data-m')); });
  $('#vizCat').addEventListener('click', function (e) {
    var b = e.target.closest('[data-c]'); if (!b) return; var c = b.getAttribute('data-c');
    setModel(c === 'byt' ? (VIZ[vizModel].cat === 'byt' ? vizModel : 'premium60') : c === 'domu' ? 'domovni' : 'interier');
  });
  function MN() { return (MODELS[vizModel] || VIZ[vizModel]).name; }
  function shadeName() { var d = DEC(); return SH()[shadeIdx][0] + (d ? ' – ' + d[Math.min(decIdx, d.length - 1)][0] : ''); }
  function frameName() { var f = VIZ[vizModel].frames; return !$('#frame').checked ? 'původní' : f ? 'nová, ' + f[frameIdx][0] : 'nová v dekoru dveří'; }

  $('#fileCam').addEventListener('change', onFile); $('#fileGal').addEventListener('change', onFile);
  $('#reset').addEventListener('click', function () { pts = []; snapped = [false, false, false, false]; setSplit(-1); afterCorners(); });
  $('#quick').addEventListener('click', function () {
    if (!photo) return;
    var w = 0.3, h = Math.min(0.82, w * photo.width * 2.1 / photo.height), x = (1 - w) / 2, y = Math.max(0.04, (1 - h) / 2);
    pts = [[x, y], [x + w, y], [x + w, y + h], [x, y + h]]; snapped = [false, false, false, false]; setSplit(-1); afterCorners();
    hint.textContent = 'Přetáhněte 4 body na rohy křídla dveří. Při tažení se ukáže lupa.';
  });
  $('#flip').addEventListener('click', function () { flip = !flip; this.setAttribute('aria-pressed', flip); render(); });
  ['bri', 'soft', 'fwid'].forEach(function (id) { $('#' + id).addEventListener('input', renderSoon); });
  ['frame', 'autolight'].forEach(function (id) { $('#' + id).addEventListener('change', render); });
  $('#cmp').addEventListener('click', function () { setSplit(split >= 0 ? -1 : 0.5); });

  function resultBlob(maxSide, quality) {
    return new Promise(function (res) {
      var keep = split; split = -1; render();
      var src = canvas;
      if (maxSide && Math.max(canvas.width, canvas.height) > maxSide) {
        var s = maxSide / Math.max(canvas.width, canvas.height), c = document.createElement('canvas');
        c.width = Math.round(canvas.width * s); c.height = Math.round(canvas.height * s); c.getContext('2d').drawImage(canvas, 0, 0, c.width, c.height); src = c;
      }
      src.toBlob(function (b) { split = keep; render(); res(b); }, 'image/jpeg', quality || 0.92);
    });
  }
  function photoBlob(maxSide) {
    return new Promise(function (res) {
      var s = Math.min(1, maxSide / Math.max(photo.width, photo.height)), c = document.createElement('canvas');
      c.width = Math.round(photo.width * s); c.height = Math.round(photo.height * s); c.getContext('2d').drawImage(photo, 0, 0, c.width, c.height);
      c.toBlob(res, 'image/jpeg', 0.85);
    });
  }
  function slug(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-'); }
  $('#save').addEventListener('click', function () {
    resultBlob().then(function (b) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = vizModel + '-' + slug(shadeName()) + '.jpg';
      document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
    });
  });
  $('#share').addEventListener('click', function () {
    resultBlob().then(function (b) {
      var f = new File([b], vizModel + '-' + slug(shadeName()) + '.jpg', { type: 'image/jpeg' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) navigator.share({ files: [f], title: MN() + ' – ' + shadeName(), text: 'Takhle by u nás vypadaly dveře ' + MN() + ' (' + shadeName() + ').' }).catch(function () {});
    });
  });
  /* režim prodejce (odkaz z panelu: #viz?p=1): tlačítko vede rovnou na formulář nabídky s předvyplněným modelem a odstínem */
  var seller = false; try { seller = sessionStorage.getItem('bd-app-prodejce') === '1'; } catch (e) {}
  if (seller) { $('#ask').textContent = 'Vytvořit nabídku pro zákazníka'; document.body.classList.add('seller'); }
  $('#ask').addEventListener('click', function () {
    var V = VIZ[vizModel];
    if (seller && V.cat === 'byt') { location.href = '../poptavka-dvere.html?model=' + vizModel + '&odstin=' + encodeURIComponent(shadeName()); return; }
    openAsk();
  });

  /* ═══════════════ návod: focení a označení rohů ═══════════════ */
  function openHowto() {
    var C = ['#c2410c', '#c2410c', '#c2410c', '#c2410c'];
    var svg = '<svg viewBox="0 0 220 250" width="200" height="228" role="img" aria-label="Pořadí rohů: 1 levý horní, 2 pravý horní, 3 pravý dolní, 4 levý dolní">' +
      '<rect x="40" y="14" width="140" height="226" fill="#8a6b4e"/><rect x="54" y="26" width="112" height="214" fill="#d9c7b0" stroke="#6b4f36" stroke-width="2"/>' +
      '<rect x="146" y="128" width="12" height="5" rx="2" fill="#555"/><path d="M8 240h204" stroke="#999" stroke-width="3"/>' +
      '<text x="110" y="10" font-size="11" fill="#666" text-anchor="middle">zárubeň</text><text x="80" y="140" font-size="12" fill="#5a4330">křídlo</text>' +
      [[54, 26, 1], [166, 26, 2], [166, 240, 3], [54, 240, 4]].map(function (p) {
        return '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="11" fill="' + C[p[2] - 1] + '" stroke="#fff" stroke-width="3"/><text x="' + p[0] + '" y="' + (p[1] + 4) + '" font-size="12" font-weight="700" fill="#fff" text-anchor="middle">' + p[2] + '</text>'; }).join('') + '</svg>';
    openDlg('<h2>Jak na to</h2><div class="howto-fig">' + svg + '</div>' +
      '<ol class="howto-list">' +
      '<li><b>Vyfoťte dveře zepředu a kolmo.</b> Postavte se doprostřed před dveře, celé dveře i se zárubní musí být na fotce. Ideálně za denního světla, bez blesku.</li>' +
      '<li><b>Klepněte postupně na 4 rohy křídla</b> – v tomto pořadí: <b>1</b> levý horní, <b>2</b> pravý horní, <b>3</b> pravý dolní, <b>4</b> levý dolní. Klepejte na roh samotných dveří (křídla), <b>ne na zárubeň</b> kolem nich.</li>' +
      '<li><b>Doladění:</b> bod chyťte prstem a posuňte – ukáže se lupa, ať trefíte roh přesně. „Přichytit k rohům“ bod sám dotáhne na hranu.</li>' +
      '<li><b>Nedaří se?</b> Klepněte na „▭ Rámeček k posunutí“ – objeví se 4 body, které jen přetáhnete na rohy. „↺ Označit znovu“ začne od začátku.</li>' +
      '<li><b>Panty máte vpravo?</b> V části „Doladit vzhled“ zapněte „⇄ Panty na druhé straně“.</li>' +
      '<li>Pak už jen vyberte dveře a odstín. Tlačítkem „⇆ Porovnat před / po“ uvidíte rozdíl.</li></ol>' +
      '<p class="small">Fotka se zpracuje jen ve vašem telefonu. Nejde to? Zavolejte <a href="tel:+420725559235">725 559 235</a>, rádi poradíme.</p>' +
      '<button class="b pri wide" type="button" onclick="this.closest(\'dialog\').close()">Rozumím</button>');
  }
  $('#howto').addEventListener('click', openHowto);

  /* ═══════════════ formuláře (dialog) ═══════════════ */
  var dlg = $('#dlg');
  function openDlg(html) { $('#dlgBody').innerHTML = html; if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); }
  function closeDlg() { if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); }
  function kontakt() { return store.get('bd-app-kontakt', {}); }
  function contactFields(k, needAddr) {
    k = k || {};
    return '<label>Jméno *<input name="jmeno" required autocomplete="given-name" value="' + esc(k.jmeno) + '"></label>' +
      '<label>Příjmení *<input name="prijmeni" required autocomplete="family-name" value="' + esc(k.prijmeni) + '"></label>' +
      '<label>Telefon *<input name="telefon" type="tel" required autocomplete="tel" value="' + esc(k.telefon) + '"></label>' +
      '<label>E-mail<input name="email" type="email" autocomplete="email" value="' + esc(k.email) + '"></label>' +
      '<label>' + (needAddr ? 'Adresa (ulice, obec) *' : 'Obec') + '<input name="adresa" ' + (needAddr ? 'required ' : '') + 'autocomplete="street-address" value="' + esc(k.adresa) + '"></label>';
  }
  var CONSENT = '<label class="chk"><input type="checkbox" name="souhlas" required> <span>Souhlasím se zpracováním údajů pro vyřízení požadavku (<a href="../zasady-osobnich-udaju.html" target="_blank">zásady</a>).</span></label>' +
    '<div class="hp" aria-hidden="true"><input name="_honey" tabindex="-1" autocomplete="off"></div>';
  function makeRef(prefix) {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return prefix + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase();
  }
  function readForm(f) { var o = {}; new FormData(f).forEach(function (v, k) { if (typeof v === 'string') o[k] = v.trim(); }); return o; }
  function saveKontakt(o) { store.set('bd-app-kontakt', { jmeno: o.jmeno, prijmeni: o.prijmeni, telefon: o.telefon, email: o.email, adresa: o.adresa }); }
  function addHist(text, ref) { var h = store.get('bd-app-historie', []); h.unshift({ d: new Date().toISOString(), co: text, ref: ref || '' }); store.set('bd-app-historie', h.slice(0, 30)); }
  /* odeslání: f = formulář, build(o) → {predmet, telo, cislo, data, files} */
  function submitForm(f, build, okHtml) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!f.reportValidity()) return;
      var o = readForm(f), btn = $('button[type=submit]', f), msg = $('.msg', f);
      btn.disabled = true; btn.textContent = 'Odesílám…'; msg.className = 'msg'; msg.textContent = '';
      saveKontakt(o);
      Promise.resolve(build(o)).then(function (x) {
        return BDApp.send({ typ: 'poptavka', predmet: x.predmet, telo: x.telo, reply_to: o.email, cislo: x.cislo, data_json: JSON.stringify(x.data), honey: o._honey, files: x.files || [] })
          .then(function (r) { if (window.bdTrack) window.bdTrack('Lead', { content_name: x.hist || 'Aplikace' }); addHist(x.hist, x.cislo); stat('odeslano', (x.data && x.data.zdroj) || '', x.data && x.data.model); $('#dlgBody').innerHTML = okHtml(x, r); });
      }).catch(function () {
        btn.disabled = false; btn.textContent = 'Zkusit znovu';
        msg.className = 'msg err'; msg.innerHTML = 'Odeslání se nepovedlo. Zkontrolujte připojení, nebo nám zavolejte na <a href="tel:+420725559235">725 559 235</a>.';
      });
    });
  }
  function lines(arr) { return arr.filter(function (x) { return x[0] && x[1]; }).map(function (x) { return x[0] + ': ' + x[1]; }).join('\n'); }

  /* odkaz „Vytvořit objednávku“ (jako u poptávek z webu): načte model, odstín a údaje zákazníka do nového formuláře objednávky; jen pro dveře do bytu */
  function b64u(str) { var b = new TextEncoder().encode(str), s2 = ''; for (var i = 0; i < b.length; i++) s2 += String.fromCharCode(b[i]); return btoa(s2).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function orderLink(o) {
    var d = {}; Object.keys(o).forEach(function (k) { if (o[k] !== '' && o[k] != null && o[k] !== false) d[k] = o[k]; });
    return new URL('../objednavka-nova.html', location.href).href + '#d=' + b64u(JSON.stringify(d));
  }
  /* poptávka z vizualizace */
  function openAsk() {
    var k = kontakt(), shade = shadeName(), mn = MN(), mk = vizModel, zar = frameName(), V = VIZ[vizModel], byt = V.cat === 'byt', sx = V.shades[shadeIdx], shadeFull = byt ? shade : shade + (sx[2] ? ' (' + sx[2] + ')' : '');
    var webUrl = byt ? '../poptavka-dvere.html?model=' + mk : V.ask;
    /* interiérové a domovní: typ (provedení), odstín a počet kusů zvlášť, ať se v poptávce/kartotéce dobře čtou */
    var dd = DEC(), typ = byt ? '' : sx[0], barva = byt ? shade : (dd ? dd[Math.min(decIdx, dd.length - 1)][0] : ''), isInt = V.cat === 'int';
    var thumbsP = Promise.all([resultBlob(900, 0.85), photoBlob(900)]);
    openDlg('<h2>Nezávazná nabídka ' + esc(mn) + '</h2><p class="small">' + (byt ? 'Odstín' : 'Provedení') + ' <b>' + esc(shade) + '</b>' + (byt ? ', zárubeň ' + esc(zar) : '') + '. Vizualizaci i původní fotku přiložíme – podle fotky vám rovnou upřesníme cenu.</p>' +
      '<div class="thumbs" id="askThumbs"></div><form class="form" id="askForm" novalidate>' + contactFields(k, false) +
      (byt ? '' : '<label>Počet kusů *<input name="pocet_ks" type="number" inputmode="numeric" min="1" max="99" step="1" value="1" required></label>' +
        '<fieldset class="opt"><legend>Chcete i montáž? *</legend><label class="chk"><input type="radio" name="montaz" value="ano" required> Ano, s montáží</label><label class="chk"><input type="radio" name="montaz" value="ne"> Ne, jen dodání</label></fieldset>' +
        (isInt ? '<fieldset class="opt"><legend>Chcete i zárubeň? *</legend><label class="chk"><input type="radio" name="zarubne" value="ano" required> Ano, dveře i se zárubní</label><label class="chk"><input type="radio" name="zarubne" value="ne"> Ne, jen dveřní křídlo</label></fieldset>' : '')) +
      '<label>Poznámka<textarea name="poznamka" placeholder="' + (isInt ? 'např. šířky dveří (60/70/80/90), levé/pravé, tloušťka zdi, patro' : 'např. patro, výtah, kdy se vám hodí zaměření') + '"></textarea></label>' + CONSENT +
      '<button class="b pri wide" type="submit">Odeslat poptávku</button><p class="msg" role="status"></p></form>' +
      '<p class="small">' + (byt ? 'Chcete si dveře rovnou sestavit (kování, vložky, záruka)? <a href="' + webUrl + '">Podrobná poptávka na webu →</a>' : 'Ceny a další provedení najdete na <a href="' + V.web + '">webu →</a>') + '</p>');
    thumbsP.then(function (b) { var t = $('#askThumbs'); if (t) t.innerHTML = '<img alt="Vizualizace" src="' + URL.createObjectURL(b[0]) + '"><img alt="Původní fotka" src="' + URL.createObjectURL(b[1]) + '">'; });
    submitForm($('#askForm'), function (o) {
      return Promise.all([resultBlob(1600, 0.85), photoBlob(1600)]).then(function (b) {
        var cislo = makeRef('A');
        var ks = byt ? '' : String(Math.max(1, Math.min(99, parseInt(o.pocet_ks, 10) || 1))) + ' ks';
        var mont = byt ? '' : (o.montaz === 'ano' ? 'ANO, s montáží' : o.montaz === 'ne' ? 'NE, jen dodání' : '');
        var zrb = isInt ? (o.zarubne === 'ano' ? 'ANO, dveře i se zárubní' : o.zarubne === 'ne' ? 'NE, jen dveřní křídlo' : '') : '';
        var data = { verze: 1, cislo: cislo, odeslano: new Date().toISOString(), zdroj: 'aplikace-vizualizace', model: byt ? mn : mn + ' – ' + typ, model_kod: mk, odstin: byt ? shadeFull : (barva || 'podle fotky z katalogu'),
          typ_dveri: typ, provedeni: byt ? '' : sx[2], pocet_ks: byt ? 1 : parseInt(ks, 10), montaz: byt ? '' : o.montaz, zarubne: isInt ? o.zarubne : '',
          jmeno: o.jmeno, prijmeni: o.prijmeni, telefon: o.telefon, email: o.email, mesto: o.adresa, poznamka: o.poznamka, fotky: ['vizualizace', 'puvodni_foto'],
          vizualizace: { zarubne: zar, zrcadleno: flip } };
        return { cislo: cislo, data: data, hist: 'Poptávka ' + mn + ' – ' + shadeFull + (ks ? ', ' + ks : ''),
          predmet: 'Poptávka z aplikace ' + cislo + ' – ' + mn + ' ' + shade + (ks ? ', ' + ks : '') + (o.montaz === 'ano' ? ', s montáží' : o.montaz === 'ne' ? ', bez montáže' : '') + (isInt && o.zarubne === 'ne' ? ', jen křídlo' : '') + ' – ' + o.prijmeni + (o.adresa ? ', ' + o.adresa : ''),
          telo: lines([['Číslo poptávky', cislo], ['Zdroj', 'Aplikace – vizualizace na fotce'], ['Dveře', mn], [byt ? 'Odstín' : 'Typ', byt ? shadeFull : typ], [byt ? '' : 'Barva / odstín', barva], [byt ? '' : 'Provedení', byt ? '' : sx[2]], [byt ? '' : 'Počet kusů', ks], ['Montáž', mont], ['Zárubeň', zrb],
            [byt ? 'Zárubeň na vizualizaci' : '', zar], ['Jméno', o.jmeno + ' ' + o.prijmeni], ['Telefon', o.telefon], ['E-mail', o.email],
            ['Obec / adresa', o.adresa], ['Poznámka', o.poznamka], [byt ? 'Vytvořit objednávku' : '', byt ? orderLink({ cislo: cislo, model_kod: mk, odstin: shade, jmeno: o.jmeno, prijmeni: o.prijmeni, telefon: o.telefon, email: o.email, mesto: o.adresa, poznamka: o.poznamka }) : ''], ['Přílohy', 'vizualizace + původní fotka dveří'], ['Odesláno', new Date().toLocaleString('cs-CZ')]]),
          files: [{ name: 'vizualizace', file: 'vizualizace-' + cislo + '.jpg', blob: b[0] }, { name: 'puvodni_foto', file: 'puvodni-dvere-' + cislo + '.jpg', blob: b[1] }] };
      });
    }, function (x) {
      return '<h2>Děkujeme, poptávka je odeslaná ✓</h2><p>Číslo poptávky <b>' + esc(x.cislo) + '</b>. Ozveme se vám obvykle do jednoho pracovního dne.</p>' +
        '<p class="small">Spěcháte? Volejte <a href="tel:+420725559235">725 559 235</a>.</p><button class="b wide" onclick="this.closest(\'dialog\').close()">Zavřít</button>';
    });
  }

  /* ═══════════════ NABÍDKA ═══════════════ */
  function buildModels() {
    $('#models').innerHTML = Object.keys(MODELS).map(function (k) {
      var m = MODELS[k];
      return '<div class="card model"><img src="' + m.img + '" alt="' + esc(m.name) + '" loading="lazy"><div><h3>' + esc(m.name) + (m.tag ? '<span class="tag">' + esc(m.tag) + '</span>' : '') + '</h3>' +
        '<p><b>' + esc(m.spec) + '</b></p><p>' + esc(m.txt) + '</p><p>Záruka ' + m.base + (m.base === 2 ? ' roky' : ' let') + (m.bonus ? ' + až 12 měsíců bonus (registrace a recenze v aplikaci)' : '') + '</p>' +
        '<div class="btns">' + (VIZ[k] ? '<a class="b sm" href="#viz?m=' + k + '">Vyzkoušet na fotce</a>' : '') +
        '<a class="b sm" href="../dvere-do-bytu.html#' + k + '">Detail a cena</a><a class="b sm pri" href="../poptavka-dvere.html?model=' + k + '">Sestavit</a></div></div></div>';
    }).join('');
  }

  /* ═══════════════ BANNER (kolotoč reklam nahoře) ═══════════════ */
  var PROMOS = [
    { t: 'PREMIUM 60', s: 'Bezpečnostní dveře do bytu', img: 'img/premium60-dub.jpg', href: '../dvere-do-bytu.html', c1: '#123d27', c2: '#1f8a55' },
    { t: 'ERKADO – dveře do domu', s: 'Vchodové dveře, 31 provedení', img: 'img/domovni/arendal.jpg', href: '../dvere-do-domu.html', c1: '#0f3a4d', c2: '#2a8aa8' },
    { t: 'Interiérové dveře', s: '33 provedení, skladové ihned', img: 'img/interier/frezia1.jpg', href: '../dvere-do-interieru.html', c1: '#6b3410', c2: '#d9822b' },
    { t: 'Něco se chystá.', s: '1. listopadu 2026', tz: true, pred: true },
    { t: 'Moje dveře – online rodokmen', s: 'Rodný list dveří, záruka a servis na jednom místě', img: 'img/premium60.jpg', href: '#moje', c1: '#14532d', c2: '#0e7490', moje: true },
    { t: 'Doporuč a získej 1 500 Kč', s: 'Odměna za každé doporučení', img: 'img/premium60.jpg', href: '#moje', c1: '#8a4b00', c2: '#e8a317', moje: true },
    { t: 'Servis a roční prohlídka', s: 'Nahlaste závadu přímo z aplikace', img: 'img/premium60.jpg', href: '#moje', c1: '#7a1d3b', c2: '#d6456f', moje: true }
  ];
  (function promo() {
    var box = $('#promo'), tr = $('#promoTrack'), dots = $('#promoDots'); if (!box) return;
    var on = mojeOn(), list = PROMOS.filter(function (p) { return p.pred ? !on : (!p.moje || on); }), cur = 0, timer = null;
    tr.innerHTML = list.map(function (p) {
      if (p.tz) return '<div class="ps tz"><span class="pt2"><b>' + esc(p.t) + '</b><small>' + esc(p.s) + '</small></span></div>';
      return '<a class="ps" href="' + p.href + '" style="--g1:' + p.c1 + ';--g2:' + p.c2 + '"><img src="' + p.img + '" alt="" width="56" height="56"><span class="pt2"><b>' + esc(p.t) + '</b><small>' + esc(p.s) + '</small></span><i aria-hidden="true">›</i></a>'; }).join('');
    dots.innerHTML = list.map(function (p, i) { return '<u' + (i ? '' : ' class="on"') + '></u>'; }).join('');
    box.hidden = false;
    function go(i) { cur = (i + list.length) % list.length; tr.scrollTo({ left: tr.clientWidth * cur, behavior: 'smooth' }); }
    function mark() { var i = Math.round(tr.scrollLeft / Math.max(1, tr.clientWidth)); cur = i; $$('u', dots).forEach(function (u, k) { u.className = k === i ? 'on' : ''; }); }
    tr.addEventListener('scroll', mark, { passive: true });
    function start() { stop(); if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return; timer = setInterval(function () { go(cur + 1); }, 5000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    ['touchstart', 'mouseenter', 'focusin'].forEach(function (ev) { box.addEventListener(ev, stop, { passive: true }); });
    ['touchend', 'mouseleave', 'focusout'].forEach(function (ev) { box.addEventListener(ev, start, { passive: true }); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
    start();
  })();

  /* ═══════════════ MOJE DVEŘE ═══════════════ */
  function door() { var d = store.get('bd-app-dvere', null); return d && d.zdroj === 'kartoteka' ? d : null; } // jen dveře z kartotéky (koupené od 1. 11. 2026)
  function parseDate(s) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; }
  function addYears(d, y) { var r = new Date(d); r.setFullYear(r.getFullYear() + y); return r; }
  function fmt(d) { return d ? d.toLocaleDateString('cs-CZ') : ''; }
  function ymd(d) { var p = function (n) { return String(n).padStart(2, '0'); }; return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()); }
  function today() { var t = new Date(); return new Date(t.getFullYear(), t.getMonth(), t.getDate()); }
  function daysBetween(a, b) { return Math.round((b - a) / 86400000); }

  /* záruka: platný konec záruky vede kartotéka (zaruka_do; počítá s bonusem i prodloužením), bez něj odhad = základní 2 roky od předání */
  function warranty(dv) {
    var pred = parseDate(dv.predano); if (!pred) return null;
    var zd = parseDate(dv.zaruka_do);
    if (!zd) { if (!MODELS[dv.model_kod]) return null; zd = addYears(pred, 2); zd.setDate(zd.getDate() - 1); }
    return { base: zd, ext: dv.prodlouzena ? +dv.prodlouzena : 0 };
  }
  function nextInspection(dv) {
    var pred = parseDate(dv.predano); if (!pred) return null;
    var n = addYears(pred, 1), t = today(); while (n < t) n = addYears(n, 1);
    if (dv.prohlidka) { var last = parseDate(dv.prohlidka); if (last) { var k = addYears(last, 1); if (k > t) n = k; } }
    return n;
  }
  function icsHref(dv, date) {
    var m = MODELS[dv.model_kod] || { name: 'dveře' };
    var ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Bezpecne dvere//Aplikace//CS', 'BEGIN:VEVENT',
      'UID:prohlidka-' + (dv.cislo || 'dvere').replace(/[^A-Za-z0-9-]/g, '') + '@bezpecne-dvere.cz', 'DTSTAMP:' + ymd(new Date()) + 'T000000Z',
      'DTSTART;VALUE=DATE:' + ymd(date), 'DTEND;VALUE=DATE:' + ymd(new Date(date.getTime() + 86400000)), 'RRULE:FREQ=YEARLY',
      'SUMMARY:Roční prohlídka dveří ' + m.name, 'DESCRIPTION:Objednejte roční prohlídku: +420 725 559 235 nebo v aplikaci Bezpečné dveře.',
      'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Roční prohlídka dveří', 'TRIGGER:-P14D', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'].join('\r\n');
    return 'data:text/calendar;charset=utf-8,' + encodeURIComponent(ics);
  }
  function gcalHref(dv, date) {
    var m = MODELS[dv.model_kod] || { name: 'dveře' };
    return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent('Roční prohlídka dveří ' + m.name) +
      '&dates=' + ymd(date) + '/' + ymd(new Date(date.getTime() + 86400000)) + '&recur=' + encodeURIComponent('RRULE:FREQ=YEARLY') +
      '&details=' + encodeURIComponent('Objednejte roční prohlídku: +420 725 559 235 nebo v aplikaci Bezpečné dveře.');
  }

  /* všechny druhy dveří (byt, dům, interiér): model z MODELS, jinak podle názvu v objednávce najdeme obrázek v katalogu vizualizace */
  function doorInfo(dv) {
    var m = MODELS[dv.model_kod]; if (m) return { name: m.name, spec: m.spec, img: m.img, kind: 'byt', label: 'Bezpečnostní dveře do bytu' };
    var txt = String(dv.model || '').toUpperCase(), key = String(dv.model_kod || ''), found = null, kind = '';
    var D = window.VIZ_DATA || {};
    ['domovni', 'interier'].forEach(function (c) { (D[c] || []).forEach(function (x) { if (!found && x[0] && txt.indexOf(String(x[0]).toUpperCase()) >= 0) { found = x; kind = c; } }); });
    if (!kind) kind = /ERKADO|DOM|VCHOD|RODINN/.test(txt + ' ' + key.toUpperCase()) ? 'domovni' : /INTER|VNITR|VNITŘ/.test(txt + ' ' + key.toUpperCase()) ? 'interier' : '';
    var label = kind === 'domovni' ? 'Vchodové dveře do domu' : kind === 'interier' ? 'Interiérové dveře' : 'Dveře od Bezpečné dveře';
    return { name: dv.model || (kind === 'domovni' ? 'Dveře do domu' : kind === 'interier' ? 'Interiérové dveře' : 'Vaše dveře'), spec: '', kind: kind || 'jine', label: label,
      img: found ? found[1] : kind === 'interier' ? 'img/interier/frezia1.jpg' : kind === 'domovni' ? 'img/domovni/arendal.jpg' : 'img/premium60.jpg' };
  }

  /* servis v aplikaci: tajný kód dveří (z odkazu Moje dveře) → stav požadavků a „rodný list“ ze serveru; poslední stav se pamatuje pro offline */
  function doorToken(dv) { if (!dv) return ''; if (/^[a-f0-9]{24}$/.test(dv.token || '')) return dv.token; var m = /[?&]t=([a-f0-9]{24})/.exec(dv.moje_url || ''); return m ? m[1] : ''; }
  function svHtml(j) {
    var h = '', open = (j.pozadavky || []).filter(function (r) { return r.stav !== 'hotovo'; }), tl = j.rodny_list || [];
    open.forEach(function (r) {
      var plan = r.stav === 'naplanovano';
      h += '<div class="status ' + (plan ? 'ok' : 'info') + '"><span>' + (plan ? '📅' : '🛠') + '</span><div><b>' + esc(r.druh_text) + ' ' + esc(r.cislo) + ' – ' + esc(r.stav_text) + '</b>' + esc(r.popis) +
        (plan ? '<br>Návštěva: <b style="display:inline">' + fmt(parseDate(r.termin_datum)) + '</b>' + (r.termin_cas ? ' · ' + esc(r.termin_cas) : '') + (r.zprava ? '<br>' + esc(r.zprava) : '') : '') + '</div></div>';
    });
    if (tl.length) h += '<h4 class="rl">Rodný list dveří</h4><ul class="tl">' + tl.map(function (ev) {
      return '<li class="' + (ev.budouci ? 'bud' : '') + '"><b>' + fmt(parseDate(ev.datum)) + '</b> · ' + esc(ev.text) + '</li>'; }).join('') + '</ul>';
    return h;
  }
  /* bonus k záruce, prodloužení s QR platbou a doporučení – vše přes API (kód dveří) */
  var svData = null, svMsg = '';
  function qrDraw(canvas, text) {
    function go() {
      try { var q = qrcode(0, 'M'); q.addData(text); q.make(); var n = q.getModuleCount(), qt = 4, cell = Math.max(2, Math.floor(240 / (n + 2 * qt))), s = cell * (n + 2 * qt), g = canvas.getContext('2d');
        canvas.width = canvas.height = s; g.fillStyle = '#fff'; g.fillRect(0, 0, s, s); g.fillStyle = '#000';
        for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (q.isDark(r, k)) g.fillRect((k + qt) * cell, (r + qt) * cell, cell, cell); } catch (e) {}
    }
    if (window.qrcode) return go();
    var s = document.createElement('script'); s.src = '../platba/qrcode.js'; s.onload = go; document.head.appendChild(s);
  }
  function kc(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč'; }
  function bnHtml(j) {
    var b = j.bonus || {}, p = j.prodlouzeni || {}, h = '';
    if (svMsg) h += '<div class="status ok"><span>✓</span><div>' + esc(svMsg) + '</div></div>';
    if (b.mozne || b.reg || b.rec) {
      h += '<div class="bn"><h4>🎁 Bonus k záruce: až +12 měsíců</h4><p class="small">Za registraci dveří <b>6 měsíců</b> a za recenzi <b>dalších 6 měsíců</b> k základní záruce. Obojí do 30 dnů od předání.</p>';
      if (b.reg) h += '<p class="bn-ok">✅ Registrace – záruka prodloužena o 6 měsíců</p>';
      else if (b.lze_reg) h += '<p>📝 Registrace (do ' + fmt(parseDate(b.reg_lhuta)) + ')</p><button class="b pri wide" data-act="bonus-reg">Zaregistrovat dveře (+6 měsíců)</button>';
      else if (b.reg_prosla) h += '<p class="small">Lhůta 30 dnů od předání pro registraci už uplynula.</p>';
      if (b.rec_schvaleno) h += '<p class="bn-ok">✅ Recenze – záruka prodloužena o dalších 6 měsíců</p>';
      else if (b.rec) h += '<p>⏳ Recenze – čeká na ověření, pak záruku prodloužíme o 6 měsíců.</p>';
      else if (b.rec_prosla) h += '<p class="small">Lhůta 30 dnů od předání pro recenzi už uplynula.</p>';
      else if (b.lze_rec) h += '<p>⭐ Napište nám recenzi na Google (do ' + fmt(parseDate(b.reg_lhuta)) + ') a získejte dalších 6 měsíců.</p><div class="btns"><a class="b sm" href="https://search.google.com/local/writereview?placeid=ChIJESw78t4hCkcRR1ygmiDY8ac" target="_blank" rel="noopener">Napsat recenzi na Google</a>' +
        '<button class="b sm pri" data-act="bonus-rec">Recenzi jsem napsal/a</button></div>';
      h += '</div>';
    }
    if ((p.moznosti || []).length) {
      h += '<div class="bn"><h4>➕ Prodloužit záruku</h4><p class="small">Do ' + fmt(parseDate(p.lhuta)) + ' (6 měsíců od předání). Zaplatíte QR kódem a záruka se po připsání platby prodlouží automaticky.</p><div class="btns">' +
        p.moznosti.map(function (o) { return '<button class="b sm" data-act="prodl" data-let="' + o.let + '">Na ' + o.let + ' let · ' + kc(o.kc) + '</button>'; }).join('') + '</div><div id="qrBox"></div></div>';
    }
    return h;
  }
  function dpHtml(j) {
    var l = j.doporuceni || [], k = kontakt();
    return '<p class="small">Za každé doporučení dveří <b>PREMIUM 60</b> s montáží získáte <b>1 500 Kč</b> (nejvýše za 3), váš kamarád <b>slevu 1 000 Kč</b>. <a href="../doporuc-nas.html" target="_blank">Pravidla akce</a></p>' +
      '<div class="btns"><button class="b sm pri" data-act="dop-share">📤 Poslat doporučení kamarádovi</button><a class="b sm" href="../prodejce/voucher-doporuc-a-ziskej.pdf" target="_blank" rel="noopener">Voucher (PDF)</a></div>' +
      '<form class="form" id="dpForm"><h4 class="rl">Nebo nám dejte kontakt na kamaráda</h4><label>Jméno a příjmení<input name="jmeno" required autocomplete="off"></label><label>Telefon<input name="telefon" type="tel" required autocomplete="off"></label>' +
      '<label class="chk"><input type="checkbox" name="souhlas" value="1" required> <span>Kamarád souhlasí s předáním kontaktu.</span></label><button class="b pri wide" type="submit">Odeslat doporučení</button><p class="msg" id="dpMsg" role="status"></p></form>' +
      (l.length ? '<h4 class="rl">Vaše doporučení</h4><ul class="hist">' + l.map(function (x) { return '<li>' + fmt(parseDate(x.datum)) + ' – ' + esc(x.jmeno) + ' · <b>' + esc(x.stav_text) + '</b></li>'; }).join('') + '</ul>' : '');
  }
  function extraBoxes(dv, j) {
    svData = j; var b = $('#bnBox'), d = $('#dpBox');
    if (b) b.innerHTML = bnHtml(j);
    var ub = $('#udBox'); if (ub) ub.innerHTML = udHtml(j);
    if (d && !$('#dpForm', d)) d.innerHTML = dpHtml(j); else if (d) { var u = $('.hist', d); }
  }
  function bonusAkce(druh) {
    var dv = door(), t = doorToken(dv); if (!t) return;
    BDApp.api('bonus_akce', { t: t, druh: druh }).then(function (j) { svMsg = j.zprava || ''; try { var c = store.get('bd-app-servis', {}); delete c[t]; store.set('bd-app-servis', c); } catch (e) {} renderMoje(); })
      .catch(function (e) { svMsg = ''; var bx = $('#bnBox'); if (bx) bx.insertAdjacentHTML('afterbegin', '<div class="status warn"><span>!</span><div>' + esc(e.message || 'Nepodařilo se, zkuste to prosím později.') + '</div></div>'); });
  }
  /* pravidelná údržba každých 6 měsíců: potvrzení provedení a zapnutí/vypnutí e-mailových připomínek (server: lib/udrzba.php) */
  var udMsg = '';
  function udHtml(j) {
    var u = j && j.udrzba, h = ''; if (!u || !u.plati) return '';
    if (udMsg) h += '<div class="status ok"><span>✓</span><div>' + esc(udMsg) + '</div></div>';
    var p = parseDate(u.pristi), dd = p ? daysBetween(today(), p) : null;
    h += '<div class="status ' + (dd !== null && dd <= 14 ? 'warn' : 'info') + '"><span>🔧</span><div><b>' + (dd !== null && dd < 0 ? 'Údržba je na řadě (od ' + fmt(p) + ')' : 'Příští údržba ' + fmt(p)) + '</b>' +
      (u.posledni ? 'naposledy provedena ' + fmt(parseDate(u.posledni)) : 'každých ' + (u.mesicu || 6) + ' měsíců') + '</div></div>' +
      '<div class="btns"><button class="b sm pri" data-act="ud-hotovo">Údržbu jsem provedl/a</button><a class="b sm" href="../udrzba-dveri.html" target="_blank">Návod k údržbě</a></div>' +
      '<label class="chk"><input type="checkbox" data-act="ud-toggle"' + (u.pripominky ? ' checked' : '') + '> <span>Připomínat údržbu e-mailem každých ' + (u.mesicu || 6) + ' měsíců</span></label>';
    if (!u.pripominky) h += '<p class="note">Připomínky máte vypnuté' + (u.vypnuto_dne ? ' od ' + fmt(parseDate(u.vypnuto_dne)) : '') + '. Údržbu podle návodu je potřeba dělat i tak – na vady způsobené zanedbanou údržbou se záruka nevztahuje.</p>';
    else if (!u.email) h += '<p class="note">U objednávky nemáme váš e-mail, připomínka proto nepřijde. Napište nám ho na chci@bezpecne-dvere.cz nebo zavolejte 725 559 235.</p>';
    return h;
  }
  function udAkce(co) {
    var dv = door(), t = doorToken(dv); if (!t) return;
    BDApp.api('udrzba_akce', { t: t, co: co }).then(function (j) { udMsg = j.zprava || ''; try { var c = store.get('bd-app-servis', {}); delete c[t]; store.set('bd-app-servis', c); } catch (e) {} renderMoje(); })
      .catch(function (e) { var bx = $('#udBox'); if (bx) bx.insertAdjacentHTML('afterbegin', '<div class="status warn"><span>!</span><div>' + esc(e.message || 'Nepodařilo se, zkuste to prosím později.') + '</div></div>'); });
  }
  function sdilet() {
    var k = kontakt(), kdo = (k.jmeno ? (k.jmeno + ' ' + (k.prijmeni || '')).trim() : ''), dv = door();
    var txt = 'Doporučuji Bezpečné dveře – bezpečnostní dveře PREMIUM 60 včetně montáže. Při objednávce uveď, že tě doporučuje ' + (kdo || 'zákazník s objednávkou č. ' + (dv && dv.cislo || '')) + ' – dostaneš slevu 1 000 Kč. Tel. 725 559 235, https://bezpecne-dvere.cz/doporuc-nas.html';
    if (navigator.share) navigator.share({ title: 'Bezpečné dveře', text: txt }).catch(function () {}); else window.open('https://wa.me/?text=' + encodeURIComponent(txt), '_blank');
  }
  function svLoad(dv) {
    var t = doorToken(dv), box = $('#svBox'); if (!t || !box) return;
    var cache = store.get('bd-app-servis', {}); if (cache[t]) { box.innerHTML = svHtml(cache[t]); extraBoxes(dv, cache[t]); } else box.innerHTML = '<p class="small">Načítám stav…</p>';
    BDApp.api('servis_stav', { t: t }).then(function (j) {
      cache = store.get('bd-app-servis', {}); cache[t] = { pozadavky: j.pozadavky, rodny_list: j.rodny_list, bonus: j.bonus, prodlouzeni: j.prodlouzeni, doporuceni: j.doporuceni, udrzba: j.udrzba }; store.set('bd-app-servis', cache);
      var cur = store.get('bd-app-dvere', null), ch = false;
      if (cur && cur.zdroj === 'kartoteka') { // skutečné údaje z kartotéky mají přednost před ručně zadanými
        if (/^\d{4}-\d{2}-\d{2}$/.test(j.predano || '') && cur.predano !== j.predano) { cur.predano = j.predano; ch = true; }
        if (j.prodlouzeno_let && cur.prodlouzena !== j.prodlouzeno_let) { cur.prodlouzena = j.prodlouzeno_let; ch = true; }
        if (/^\d{4}-\d{2}-\d{2}$/.test(j.zaruka_do || '') && cur.zaruka_do !== j.zaruka_do) { cur.zaruka_do = j.zaruka_do; ch = true; }
        if (!cur.token) { cur.token = t; ch = true; }
        if (ch) store.set('bd-app-dvere', cur);
      }
      var b2 = $('#svBox'); if (b2) b2.innerHTML = svHtml(cache[t]); extraBoxes(cur || dv, cache[t]);
    }).catch(function (err) { var b2 = $('#svBox'); if (b2 && !cache[t]) b2.innerHTML = err.code === 'nenalezeno' ? '<p class="small">Dveře se nepodařilo najít v kartotéce. Spárujte je prosím znovu.</p>' : '<p class="small">Stav servisu se teď nepodařilo načíst.</p>'; });
  }
  function renderMoje() {
    var dv = door();
    $('#mojeEmpty').hidden = !!dv; $('#mojeDoor').hidden = !dv;
    if (!dv) return;
    var m = doorInfo(dv), w = warranty(dv), ni = nextInspection(dv), hist = store.get('bd-app-historie', []);
    var sImg = null; if (VIZ[dv.model_kod]) VIZ[dv.model_kod].shades.forEach(function (x) { if (x[0].toLowerCase() === String(dv.odstin || '').trim().toLowerCase()) sImg = x[1]; });
    var h = '<div class="hero k-' + m.kind + '"><div class="hero-t"><span class="hero-k">' + esc(m.label) + '</span><h2>' + esc(m.name) + '</h2>' + (m.spec ? '<p class="hero-s">' + esc(m.spec) + '</p>' : '') +
      '<div class="chips">' + (dv.odstin ? '<span class="chip">🎨 ' + esc(dv.odstin) + '</span>' : '') + (dv.cislo ? '<span class="chip">№ ' + esc(dv.cislo) + '</span>' : '') +
      (dv.predano ? '<span class="chip">📅 předáno ' + fmt(parseDate(dv.predano)) + '</span>' : '') + (w ? '<span class="chip ' + (w.base >= today() ? 'g' : 'o') + '">🛡 záruka do ' + fmt(w.base) + '</span>' : '') + '</div></div>' +
      '<img class="hero-i" src="' + (sImg || m.img) + '" alt="" width="120"></div>';

    // záruka
    h += '<div class="card c-z"><h3>🛡 Záruka</h3>';
    if (!w) h += '<p class="small">Záruka se zobrazí po předání dveří.</p>';
    else {
      var active = w.base >= today();
      h += '<div class="status ' + (active ? 'ok' : 'info') + '"><span>🛡</span><div><b>' + (active ? 'Záruka platí do ' : 'Záruka skončila ') + fmt(w.base) + '</b>' +
        (w.ext ? 'prodloužená záruka na ' + w.ext + ' let od předání' : 'základní záruka 2 roky' + (dv.zaruka_do ? ' (včetně případného bonusu)' : '')) + '</div></div>';
    }
    h += (doorToken(dv) ? '<div id="bnBox"></div>' : '<p class="small">Bonus za registraci a prodloužení záruky najdete po spárování dveří s objednávkou.</p>') +
      '<p class="small"><a href="../podminky-prodlouzeni-zaruky.html" target="_blank">Podmínky bonusu a prodloužení záruky</a></p></div>';

    // roční prohlídka
    h += '<div class="card c-p"><h3>🗓 Roční prohlídka</h3>';
    if (ni) {
      var dd = daysBetween(today(), ni);
      h += '<div class="status ' + (dd <= 30 ? 'warn' : 'info') + '"><span>🗓</span><div><b>Příští prohlídka ' + fmt(ni) + '</b>' + (dd === 0 ? 'dnes' : 'za ' + dd + ' dní') + '</div></div>' +
        '<div class="btns"><button class="b sm pri" data-act="prohlidka">Objednat prohlídku</button><a class="b sm" href="' + gcalHref(dv, ni) + '" target="_blank" rel="noopener">Připomenout v Google Kalendáři</a>' +
        '<a class="b sm" href="' + icsHref(dv, ni) + '" download="rocni-prohlidka-dveri.ics">Do jiného kalendáře (.ics)</a></div>';
    } else h += '<p class="small">Doplňte datum předání, ať víme, kdy je prohlídka.</p>';
    h += '<p class="note">Všechny naše bezpečnostní dveře do bytu mají požární odolnost (PREMIUM 60 EI 60, ostatní EI 30). Pokud jsou ve vašem domě podle požárně bezpečnostního řešení požárním uzávěrem ' +
      '(typicky vstupní dveře z bytu do společné chodby), kontroluje se jejich provozuschopnost jednou za rok (vyhláška č. 246/2001 Sb.). Zeptejte se ve svém SVJ nebo družstvu, jestli kontrolu zajišťuje dům – jinak ji rádi provedeme. ' +
      'Prohlídka se hodí i kvůli seřízení a promazání.</p></div>';

    // servis
    h += '<div class="card c-s"><h3>🛠 Servis a reklamace</h3><p class="small">Něco nefunguje? Popište závadu a přiložte fotku. Údaje o vašich dveřích už máme, ozveme se a domluvíme termín.</p>' +
      (doorToken(dv) ? '<div id="svBox"></div>' : '') +
      '<button class="b pri wide" data-act="servis">Nahlásit závadu</button><a class="b wide" href="../objednani-klicu.html">Objednat klíče</a></div>';

    // doporuč a získej
    h += '<div class="card c-d"><h3>🤝 Doporuč a získej 1 500 Kč</h3>' + (doorToken(dv) ? '<div id="dpBox"></div>' : '<p class="small">Za každé doporučení dveří PREMIUM 60 s montáží 1 500 Kč. <a href="../doporuc-nas.html" target="_blank">Pravidla a formulář</a></p>') + '</div>';

    // péče a pravidelná údržba (předávací list + výrobce; celý návod udrzba-dveri.html)
    h += '<div class="card c-c"><h3>🧽 Péče a údržba dveří</h3>' + (doorToken(dv) ? '<div id="udBox"></div>' : '') + '<ul class="care">' +
      '<li>Povrch myjte teplou vodou s trochou saponátu a měkkou houbou nebo hadříkem. Nedrhněte, nepoužívejte rozpouštědla ani brusné prostředky.</li>' +
      '<li>Každých 6 měsíců ošetřete těsnění dveří a zárubně (pokud je) silikonovým mazivem.</li>' +
      '<li>Každých 6 měsíců promažte kluznou lištu samozavírače (pokud ho dveře mají) plastickým tukem ŁT34 nebo podobným.</li>' +
      '<li>1× ročně promažte zamykací čepy a krycí misku vložky (pokud je) olejem nebo WD-40.</li>' +
      '<li><b>Pozor – samotná vložka (cylindr):</b> když jdou klíče špatně zasouvat, použijte pouze teflonový nebo grafitový sprej. Nic jiného do vložky nepatří, ani olej, ani WD-40.</li></ul>' +
      '<p class="small"><a href="../udrzba-dveri.html" target="_blank">Celý návod k užívání a údržbě</a> · <a href="../udrzba-dveri.html#certifikaty" target="_blank">Certifikáty dveří</a></p></div>';

    if (hist.length) h += '<div class="card"><h3>Odeslané z aplikace</h3><ul class="hist">' + hist.slice(0, 10).map(function (x) {
      return '<li>' + fmt(new Date(x.d)) + ' – ' + esc(x.co) + (x.ref ? ' (' + esc(x.ref) + ')' : '') + '</li>'; }).join('') + '</ul></div>';
    h += '<h2 class="ph">Mohlo by vás zajímat</h2><div class="strip">' + PROMOS.filter(function (p) { return !p.moje && !p.pred; }).map(function (p) {
      return '<a class="tile" href="' + p.href + '" style="--g1:' + p.c1 + ';--g2:' + p.c2 + '"><img src="' + p.img + '" alt="" loading="lazy"><b>' + esc(p.t) + '</b><span>' + esc(p.s) + '</span></a>'; }).join('') + '</div>';
    h += '<div class="btns"><button class="b sm" data-act="edit">Upravit údaje</button><button class="b sm" data-act="remove">Odebrat dveře z telefonu</button></div>';
    $('#mojeDoor').innerHTML = h; svLoad(dv);
  }
  $('#mojeDoor').addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return; var dv = door(), a = b.getAttribute('data-act');
    if (a === 'bonus-reg') bonusAkce('reg');
    else if (a === 'bonus-rec') bonusAkce('recenze');
    else if (a === 'dop-share') sdilet();
    else if (a === 'prodl') { var o = ((svData || {}).prodlouzeni || {}).moznosti || [], pick = null; o.forEach(function (x) { if (String(x.let) === b.getAttribute('data-let')) pick = x; });
      if (pick) { $('#qrBox').innerHTML = '<p style="margin:10px 0 4px"><b>Platba ' + kc(pick.kc) + '</b> – naskenujte QR kód v bankovní aplikaci:</p><div class="qr"><canvas id="qrC" width="240" height="240"></canvas></div><dl class="dl"><dt>Účet</dt><dd>' + esc(pick.ucet) + '</dd><dt>Částka</dt><dd>' + kc(pick.kc) + '</dd><dt>Var. symbol</dt><dd>' + esc(pick.vs) + '</dd></dl><p class="small">Variabilní symbol neměňte. Po připsání platby se záruka prodlouží sama a pošleme potvrzení.</p>'; qrDraw($('#qrC'), pick.spd); } }
    else if (a === 'ud-hotovo') udAkce('hotovo');
    else if (a === 'ud-toggle') udAkce(b.checked ? 'zapnout' : 'vypnout');
    else if (a === 'regdone') { dv.registrace = true; store.set('bd-app-dvere', dv); renderMoje(); }
    else if (a === 'remove') { openDlg('<h2>Odebrat dveře?</h2><p>Údaje se smažou jen z tohoto telefonu. U nás v kartotéce zůstávají.</p><div class="btns"><button class="b pri" id="rmYes">Odebrat</button><button class="b" onclick="this.closest(\'dialog\').close()">Zpět</button></div>');
      $('#rmYes').onclick = function () { try { localStorage.removeItem('bd-app-dvere'); } catch (er) {} closeDlg(); renderMoje(); }; }
    else if (a === 'edit') openManual(dv);
    else if (a === 'servis') openService(dv);
    else if (a === 'prohlidka') openInspection(dv);
  });

  function doorLines(dv) {
    var m = doorInfo(dv);
    return [['Dveře', m.name], ['Odstín', dv.odstin], ['Číslo objednávky', dv.cislo], ['Datum předání', dv.predano ? fmt(parseDate(dv.predano)) : ''], ['Spárováno s kartotékou', dv.zdroj === 'kartoteka' ? 'ano' : 'ne (zadáno zákazníkem)']];
  }
  function doorData(dv) { return { model_kod: dv.model_kod, model: doorInfo(dv).name, odstin: dv.odstin || '', cislo_objednavky: dv.cislo || '', predano: dv.predano || '' }; }

  /* odeslání požadavku přes API: dveře se poznají podle kódu, zákazník nepřepisuje nic kromě popisu */
  function submitApi(f, token, druh, popisFn, okHtml) {
    f.addEventListener('submit', function (e) {
      e.preventDefault(); if (!f.reportValidity()) return;
      var o = readForm(f), btn = $('button[type=submit]', f), msg = $('.msg', f);
      btn.disabled = true; btn.textContent = 'Odesílám…'; msg.className = 'msg'; msg.textContent = '';
      var k = kontakt(); store.set('bd-app-kontakt', Object.assign({}, k, { telefon: o.telefon || k.telefon, email: o.email || k.email }));
      var files = f.fotky ? Array.prototype.slice.call(f.fotky.files || [], 0, 3) : [];
      Promise.all(files.map(shrinkFile)).then(function (bl) {
        return BDApp.api('servis_nahlasit', { t: token, druh: druh, popis: popisFn(o), preferovany: o.termin || '', telefon: o.telefon || '', email: o.email || '', _honey: o._honey || '' },
          bl.map(function (b, i) { return { name: 'foto' + (i + 1), file: 'zavada-' + (i + 1) + '.jpg', blob: b }; }));
      }).then(function (j) {
        if (window.bdTrack) window.bdTrack('Lead', { content_name: druh === 'prohlidka' ? 'Objednávka roční prohlídky' : 'Hlášení závady' });
        addHist(druh === 'prohlidka' ? 'Objednávka roční prohlídky' : 'Hlášení závady', j.cislo); stat('odeslano', 'aplikace-servis', '');
        var c = store.get('bd-app-servis', {}); c[token] = { pozadavky: j.pozadavky, rodny_list: j.rodny_list }; store.set('bd-app-servis', c);
        $('#dlgBody').innerHTML = okHtml(j); renderMoje();
      }).catch(function (err) {
        btn.disabled = false; btn.textContent = 'Zkusit znovu'; msg.className = 'msg err';
        msg.innerHTML = err.code === 'data' ? esc(err.message) : err.code === 'limit' ? 'Příliš mnoho odeslání. Zkuste to za hodinu, nebo nám zavolejte na <a href="tel:+420725559235">725 559 235</a>.' :
          'Odeslání se nepovedlo. Zkontrolujte připojení, nebo nám zavolejte na <a href="tel:+420725559235">725 559 235</a>.';
      });
    });
  }
  function svOk(j) { return '<h2>Odesláno ✓</h2><p>Číslo požadavku <b>' + esc(j.cislo) + '</b>. Stav uvidíte přímo v aplikaci u svých dveří – jakmile požadavek přijmeme a domluvíme termín návštěvy, zobrazí se tam.</p><button class="b wide" onclick="this.closest(\'dialog\').close()">Zavřít</button>'; }
  function contactLite(k) {
    k = k || {};
    return '<label>Telefon (nepovinné – jinak zavoláme na číslo z objednávky)<input name="telefon" type="tel" autocomplete="tel" value="' + esc(k.telefon) + '"></label>' +
      '<label>E-mail (pro potvrzení termínu)<input name="email" type="email" autocomplete="email" value="' + esc(k.email) + '"></label>';
  }
  function openService(dv) {
    var tok = doorToken(dv);
    if (tok) {
      openDlg('<h2>Nahlásit závadu</h2><p class="small">Údaje o objednávce a adresu už máme z kartotéky – stačí popsat, co se děje.</p><form class="form" id="svForm" novalidate>' +
        '<label>Co se děje? *<textarea name="popis" required minlength="5" placeholder="např. dveře drhnou nahoře, klíč jde ztuha, …"></textarea></label>' +
        '<label>Fotky (až 3)<input type="file" name="fotky" accept="image/*" multiple></label>' + contactLite(kontakt()) +
        '<label>Kdy se vám hodí návštěva<input name="termin" placeholder="např. odpoledne po 15. hod."></label>' + CONSENT +
        '<button class="b pri wide" type="submit">Odeslat</button><p class="msg" role="status"></p></form>');
      return submitApi($('#svForm'), tok, 'zavada', function (o) { return o.popis; }, svOk);
    }
    openDlg('<h2>Nahlásit závadu</h2><form class="form" id="svForm" novalidate>' +
      '<label>Co se děje? *<textarea name="popis" required placeholder="např. dveře drhnou nahoře, klíč jde ztuha, …"></textarea></label>' +
      '<label>Fotky (až 3)<input type="file" name="fotky" accept="image/*" multiple></label>' + contactFields(kontakt(), true) +
      '<label>Kdy se vám hodí návštěva<input name="termin" placeholder="např. odpoledne po 15. hod."></label>' + CONSENT +
      '<button class="b pri wide" type="submit">Odeslat</button><p class="msg" role="status"></p></form>');
    var f = $('#svForm');
    submitForm(f, function (o) {
      var files = Array.prototype.slice.call(f.fotky.files || [], 0, 3), cislo = makeRef('S');
      return Promise.all(files.map(shrinkFile)).then(function (bl) {
        var data = Object.assign({ verze: 1, cislo: cislo, odeslano: new Date().toISOString(), zdroj: 'aplikace-servis', druh: 'servis', popis: o.popis, termin: o.termin,
          jmeno: o.jmeno, prijmeni: o.prijmeni, telefon: o.telefon, email: o.email, adresa: o.adresa, fotky: bl.map(function (_, i) { return 'foto' + (i + 1); }) }, doorData(dv));
        return { cislo: cislo, data: data, hist: 'Hlášení závady',
          predmet: 'Servis z aplikace ' + cislo + ' – ' + doorInfo(dv).name + (dv.cislo ? ' – obj. ' + dv.cislo : '') + ' – ' + o.prijmeni,
          telo: lines([['Číslo požadavku', cislo], ['Druh', 'Servis / reklamace (z aplikace)']].concat(doorLines(dv)).concat([['Popis závady', o.popis], ['Jméno', o.jmeno + ' ' + o.prijmeni],
            ['Telefon', o.telefon], ['E-mail', o.email], ['Adresa', o.adresa], ['Vhodný termín', o.termin], ['Fotky', bl.length ? bl.length + ' ks v příloze' : ''], ['Odesláno', new Date().toLocaleString('cs-CZ')]])),
          files: bl.map(function (b, i) { return { name: 'foto' + (i + 1), file: 'zavada-' + cislo + '-' + (i + 1) + '.jpg', blob: b }; }) };
      });
    }, function (x) { return '<h2>Odesláno ✓</h2><p>Číslo požadavku <b>' + esc(x.cislo) + '</b>. Ozveme se a domluvíme termín.</p><button class="b wide" onclick="this.closest(\'dialog\').close()">Zavřít</button>'; });
  }
  function openInspection(dv) {
    var tok = doorToken(dv);
    if (tok) {
      openDlg('<h2>Objednat roční prohlídku</h2><p class="small">Zkontrolujeme funkčnost dveří, seřídíme je a promažeme. Cenu vám sdělíme při domluvě termínu.</p><form class="form" id="inForm" novalidate>' +
        contactLite(kontakt()) + '<label>Kdy se vám hodí<input name="termin" placeholder="např. příští týden dopoledne"></label>' +
        '<label class="chk"><input type="checkbox" name="protokol" value="ano"> <span>Potřebuji doklad o kontrole (např. pro SVJ)</span></label>' +
        '<label>Poznámka<textarea name="poznamka"></textarea></label>' + CONSENT + '<button class="b pri wide" type="submit">Objednat</button><p class="msg" role="status"></p></form>');
      return submitApi($('#inForm'), tok, 'prohlidka', function (o) { return 'Roční prohlídka dveří.' + (o.protokol === 'ano' ? ' Zákazník potřebuje doklad o kontrole (např. pro SVJ).' : '') + (o.poznamka ? ' ' + o.poznamka : ''); }, svOk);
    }
    openDlg('<h2>Objednat roční prohlídku</h2><p class="small">Zkontrolujeme funkčnost dveří, seřídíme je a promažeme. Cenu vám sdělíme při domluvě termínu.</p><form class="form" id="inForm" novalidate>' +
      contactFields(kontakt(), true) + '<label>Kdy se vám hodí<input name="termin" placeholder="např. příští týden dopoledne"></label>' +
      '<label class="chk"><input type="checkbox" name="protokol" value="ano"> <span>Potřebuji doklad o kontrole (např. pro SVJ)</span></label>' +
      '<label>Poznámka<textarea name="poznamka"></textarea></label>' + CONSENT + '<button class="b pri wide" type="submit">Objednat</button><p class="msg" role="status"></p></form>');
    submitForm($('#inForm'), function (o) {
      var cislo = makeRef('P');
      var data = Object.assign({ verze: 1, cislo: cislo, odeslano: new Date().toISOString(), zdroj: 'aplikace-prohlidka', druh: 'rocni_prohlidka', termin: o.termin, doklad: o.protokol === 'ano',
        poznamka: o.poznamka, jmeno: o.jmeno, prijmeni: o.prijmeni, telefon: o.telefon, email: o.email, adresa: o.adresa }, doorData(dv));
      return { cislo: cislo, data: data, hist: 'Objednávka roční prohlídky',
        predmet: 'Roční prohlídka z aplikace ' + cislo + ' – ' + doorInfo(dv).name + (dv.cislo ? ' – obj. ' + dv.cislo : '') + ' – ' + o.prijmeni,
        telo: lines([['Číslo požadavku', cislo], ['Druh', 'Roční prohlídka (z aplikace)']].concat(doorLines(dv)).concat([['Doklad o kontrole', o.protokol === 'ano' ? 'ANO, zákazník potřebuje doklad' : 'ne'],
          ['Jméno', o.jmeno + ' ' + o.prijmeni], ['Telefon', o.telefon], ['E-mail', o.email], ['Adresa', o.adresa], ['Vhodný termín', o.termin], ['Poznámka', o.poznamka], ['Odesláno', new Date().toLocaleString('cs-CZ')]])) };
    }, function (x) { return '<h2>Objednáno ✓</h2><p>Číslo požadavku <b>' + esc(x.cislo) + '</b>. Ozveme se a domluvíme termín.</p><button class="b wide" onclick="this.closest(\'dialog\').close()">Zavřít</button>'; });
  }
  function shrinkFile(file) {
    return new Promise(function (resolve) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, 1600 / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * s); cv.height = Math.round(img.height * s);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(url);
        cv.toBlob(function (b) { resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  /* ruční zadání / úprava dveří */
  function openManual(dv, info) {
    dv = dv || {};
    var opt = function (v, t, sel) { return '<option value="' + esc(v) + '"' + (sel ? ' selected' : '') + '>' + esc(t) + '</option>'; };
    var mk = dv.model_kod || 'premium60';
    openDlg('<h2>' + (dv.model_kod ? 'Upravit údaje' : 'Moje dveře') + '</h2><form class="form" id="mnForm" novalidate>' +
      '<label>Model *<select name="model_kod" required>' + Object.keys(MODELS).map(function (k) { return opt(k, MODELS[k].name, k === mk); }).join('') + '</select></label>' +
      '<label>Odstín<input name="odstin" value="' + esc(dv.odstin) + '"></label>' +
      '<label>Číslo objednávky<input name="cislo" value="' + esc(dv.cislo) + '"></label>' +
      '<label>Datum předání (montáže) *<input type="date" name="predano" required value="' + esc(dv.predano) + '"></label>' +
      '<label>Prodloužená záruka<select name="prodlouzena" id="mnExt"></select></label>' +
      '<label class="chk"><input type="checkbox" name="registrace" value="1"' + (dv.registrace ? ' checked' : '') + '> <span>Dveře už mám zaregistrované (bonus +6 měsíců)</span></label>' +
      '<button class="b pri wide" type="submit">Uložit</button><p class="msg"></p></form><p class="note">Údaje se uloží jen v tomto telefonu.</p>');
    if (info) $('#mnForm').insertAdjacentHTML('beforebegin', '<div class="status info"><span>✓</span><div><b>Dveře jsme našli v kartotéce.</b>' + esc(info) + '</div></div>');
    var f = $('#mnForm');
    function fillExt() { var m = MODELS[f.model_kod.value]; $('#mnExt').innerHTML = opt(0, 'Ne', !dv.prodlouzena) + m.ext.map(function (y) { return opt(y, 'Ano, na ' + y + ' let', +dv.prodlouzena === y); }).join(''); }
    fillExt(); f.model_kod.addEventListener('change', fillExt);
    f.addEventListener('submit', function (e) {
      e.preventDefault(); if (!f.reportValidity()) return; var o = readForm(f);
      store.set('bd-app-dvere', Object.assign({}, dv, { model_kod: o.model_kod, odstin: o.odstin, cislo: o.cislo, predano: o.predano, prodlouzena: +o.prodlouzena || 0,
        registrace: !!f.registrace.checked, zdroj: dv.zdroj || 'rucne' }));
      closeDlg(); renderMoje();
    });
  }
  $('#mojeDoor').addEventListener('submit', function (e) {
    if (e.target.id !== 'dpForm') return; e.preventDefault();
    var f = e.target, t = doorToken(door()), msg = $('#dpMsg'), btn = $('button[type=submit]', f); if (!t || !f.reportValidity()) return; btn.disabled = true; msg.className = 'msg'; msg.textContent = 'Odesílám…';
    BDApp.api('doporucit', { t: t, jmeno: f.jmeno.value, telefon: f.telefon.value, souhlas: f.souhlas.checked ? '1' : '' }).then(function (j) {
      svMsg = ''; var c = store.get('bd-app-servis', {}); delete c[t]; store.set('bd-app-servis', c);
      var d = $('#dpBox'); d.innerHTML = '<div class="status ok"><span>✓</span><div>Děkujeme! Doporučenému se ozveme a po namontování dveří vyplatíme odměnu podle pravidel akce.</div></div>' + dpHtml(j);
    }).catch(function (er) { btn.disabled = false; msg.className = 'msg err'; msg.textContent = er.message || 'Odeslání se nepodařilo, zkuste to prosím později.'; });
  });

  /* ruční zadání dveří bez objednávky zrušeno (rozhodnutí 17. 10. 2026): Moje dveře jen pro dveře z kartotéky */

  /* spárování s objednávkou v kartotéce (API na NAS) */
  $('#pairForm').addEventListener('submit', function (e) {
    e.preventDefault(); var f = e.target; if (!f.reportValidity()) return;
    var o = readForm(f), msg = $('#pairMsg'), btn = $('button', f); btn.disabled = true; msg.className = 'msg'; msg.textContent = 'Hledám…';
    BDApp.api('sparovat', { cislo: o.cislo, tel4: o.tel4 }).then(function (j) {
      var d = j.dvere || {}, ext = /10 let/.test(d.zaruka || '') ? 10 : /5 let/.test(d.zaruka || '') ? 5 : 0;
      var dv = { model_kod: d.model_kod, odstin: d.odstin, cislo: d.cislo, model: d.model || '', zaruka_do: /^\d{4}-\d{2}-\d{2}$/.test(d.zaruka_do || '') ? d.zaruka_do : '', predano: /^\d{4}-\d{2}-\d{2}$/.test(d.predano || '') ? d.predano : '', prodlouzena: d.prodlouzeno_let || ext, registrace: !!d.registrace, zdroj: 'kartoteka', token: j.token || (/[?&]t=([a-f0-9]{24})/.exec(j.moje_url || '') || [])[1] || '', moje_url: /^https:\/\/formulare\.dvere\.app\//.test(j.moje_url || '') ? j.moje_url : '' };
      store.set('bd-app-dvere', dv); btn.disabled = false; msg.textContent = '';
      if (j.kontakt) { var k = kontakt(); store.set('bd-app-kontakt', Object.assign({ jmeno: j.kontakt.jmeno, prijmeni: j.kontakt.prijmeni }, k)); }
      renderMoje(); if (dv.predano) return; openManual(Object.assign({}, dv, { predano: /^\d{4}-\d{2}-\d{2}$/.test(d.termin || '') ? d.termin : '' }), 'Doplňte prosím skutečné datum předání (montáže) – podle něj počítáme záruku a prohlídku.');
    }).catch(function (err) {
      btn.disabled = false; msg.className = 'msg err';
      msg.innerHTML = err.code === 'nenalezeno' ? 'Objednávku jsme nenašli. Zkontrolujte číslo objednávky a telefon. Dveře koupené před 1. 11. 2026 v aplikaci nejsou – servis vyřídíte přes <a href="../servis.html">web</a> nebo na tel. 725 559 235.' :
        err.code === 'limit' ? 'Příliš mnoho pokusů. Zkuste to za hodinu.' :
        err.code === 'nedostupne' ? 'Hledání objednávek je dočasně nedostupné. Zkuste to prosím později.' :
        'Spojení se nepovedlo. Zkuste to prosím znovu.';
    });
  });

  /* ═══════════════ instalace + offline ═══════════════ */
  var deferred = null;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; $('#install').hidden = false; });
  $('#install').addEventListener('click', function () { if (!deferred) return; deferred.prompt(); deferred.userChoice.finally(function () { deferred = null; $('#install').hidden = true; }); });
  if ('serviceWorker' in navigator && location.protocol === 'https:') navigator.serviceWorker.register('sw.js').catch(function () {});

  /* start */
  buildModels(); statDen();
  if (initGL()) { buildShades(); loadDoor(shadeIdx); state(); }
  route();
  window.__app = { setPhoto: setPhoto, setPts: function (p) { pts = p; snapped = [false, false, false, false]; afterCorners(); }, snapPoint: snapPoint, get light() { return light; }, render: render,
    setShade: function (i, cb, di) { shadeIdx = i; decIdx = di || 0; showPick(); loadDoor(i, function () { render(); cb && cb(); }); }, setModel: setModel, setSplit: setSplit };
})();
