/* Dárkový poukaz na dveře – grafika a PDF (generuje se v prohlížeči, jsPDF 2.5.2).
   Stejné kreslení slouží pro náhled na webu (canvas), PDF poukazu, potvrzení o platbě a reklamní obrázky.

   Poukaz.ready()                      → Promise (načte písma)
   Poukaz.kresli(canvas, data, pxNaMm) → nakreslí celý poukaz do canvasu (náhled)
   Poukaz.pdf(data)                    → Promise<Blob> PDF poukazu (A4 na výšku)
   Poukaz.doklad(data)                 → Promise<Blob> potvrzení o přijetí platby (A4 na výšku)
   Poukaz.scena(ctx, motiv, hodnota, x, y, w, h, S) → jen obrázek dveří se scénou (pro reklamy)

   data = { motiv: 'vanoce'|'narozeniny'|'darkovy', hodnota: 10000, cislo: 'DP 1/2026', kod: 'ABCD-EFGH',
            platnost_do: '2027-12-01', obdarovany: '', venovani: '', od_koho: '' }
   Písma: Inter (poptavka-pdf/), Playfair Display a Caveat (OFL, poukaz/). */
(function (w) {
  'use strict';
  var BASE = (function () { var s = document.currentScript && document.currentScript.src; return s ? s.replace(/[^/]*$/, '') : 'poukaz/'; })();
  var PDFDIR = new URL('../poptavka-pdf/', BASE).href;
  var W = 210, H = 297;                       // A4 na výšku v mm
  var VZOR_CENA = 38890;                      // PREMIUM 60 (dvere-do-bytu.html, 10/2026) – jen pro ilustraci „kolik dveří je zaplaceno“

  var MOTIVY = {
    vanoce: {
      nazev: 'Vánoční', kicker: 'VÁNOČNÍ DÁRKOVÝ POUKAZ',
      vtip: 'Ježíšek už kus nových dveří zaplatil. Zbytek vybereme spolu.',
      bg: ['#081b14', '#123d27', '#1d5a3a'], akcent: '#a3161f', kicker_c: '#a87b25', ram: '#c9a24a', dvere: ['#5b3a24', '#7a4f31', '#4a2f1d']
    },
    narozeniny: {
      nazev: 'Narozeninový', kicker: 'DÁRKOVÝ POUKAZ K NAROZENINÁM',
      vtip: 'K narozeninám kus nových dveří. Zbytek vybereme spolu.',
      bg: ['#7c1d4f', '#db2777', '#f97316'], akcent: '#e8650a', kicker_c: '#be185d', ram: '#f59e0b', dvere: ['#5b3a24', '#7a4f31', '#4a2f1d']
    },
    darkovy: {
      nazev: 'Dárkový', kicker: 'DÁRKOVÝ POUKAZ',
      vtip: 'Kus nových dveří už je zaplacený. Zbytek vybereme spolu.',
      bg: ['#0b1d33', '#0f2747', '#123d27'], akcent: '#e8650a', kicker_c: '#123d27', ram: '#e8650a', dvere: ['#2b3138', '#3b434c', '#22272d']
    }
  };
  function motiv(m) { return MOTIVY[m] || MOTIVY.darkovy; }
  /* do Vánoc (1. 10. – 26. 12.) je předvolený vánoční motiv, jinak dárkový */
  function vychoziMotiv(d) { d = d || new Date(); var md = (d.getMonth() + 1) * 100 + d.getDate(); return md >= 1001 && md <= 1226 ? 'vanoce' : 'darkovy'; }
  function podil(hodnota) { var f = (Number(hodnota) || 0) / VZOR_CENA; return f >= 1 ? 1 : Math.max(0.1, Math.min(0.95, f)); }
  function kc(n) { return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč'; }
  function datum(s) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? (+m[3]) + '. ' + (+m[2]) + '. ' + m[1] : (s || ''); }

  /* ── písma ── */
  var FONTS = [
    ['PKInter', 'normal', PDFDIR + 'Inter-Regular.ttf', 'Inter-Regular.ttf', 'Inter'],
    ['PKInter', 'bold', PDFDIR + 'Inter-Bold.ttf', 'Inter-Bold.ttf', 'Inter'],
    ['PKPlay', 'bold', BASE + 'PlayfairDisplay-Bold.ttf', 'PlayfairDisplay-Bold.ttf', 'Playfair'],
    ['PKCaveat', 'bold', BASE + 'Caveat-Bold.ttf', 'Caveat-Bold.ttf', 'Caveat']
  ];
  var bufs = null, ready = null, IMG = {};
  function b64(buf) { var s = '', u = new Uint8Array(buf), i; for (i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); }
  function loadScript(src) { return new Promise(function (ok, ko) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); }); }
  function nactiObr(src) { return new Promise(function (ok) { var i = new Image(); i.crossOrigin = 'anonymous'; i.onload = function () { ok(i); }; i.onerror = function () { ok(null); }; i.src = src; }); }
  function prepare() {
    if (ready) return ready;
    ready = Promise.all(FONTS.map(function (f) { return fetch(f[2]).then(function (r) { if (!r.ok) throw new Error(f[3]); return r.arrayBuffer(); }); }))
      .then(function (arr) {
        bufs = arr;
        if (w.FontFace && document.fonts) arr.forEach(function (b, i) { var f = new FontFace(FONTS[i][0], b, { weight: FONTS[i][1] === 'bold' ? '700' : '400' }); document.fonts.add(f); });
        return Promise.all([nactiObr(BASE + 'dvere-premium60.jpg'), nactiObr(BASE + 'logo.png')]);
      }).then(function (img) { IMG.dvere = img[0]; IMG.logo = img[1]; if (!IMG.dvere || !IMG.logo) throw new Error('obrazky'); });
    ready.catch(function () { ready = null; });
    return ready;
  }

  /* ── pomocné kreslení ── */
  function rnd(seed) { var s = seed >>> 0 || 1; return function () { s = (s * 1664525 + 1013904223) >>> 0; return s / 4294967296; }; }
  function rr(g, x, y, w2, h2, r) { g.beginPath(); g.moveTo(x + r, y); g.arcTo(x + w2, y, x + w2, y + h2, r); g.arcTo(x + w2, y + h2, x, y + h2, r); g.arcTo(x, y + h2, x, y, r); g.arcTo(x, y, x + w2, y, r); g.closePath(); }
  function hvezda4(g, x, y, r) { g.beginPath(); g.moveTo(x, y - r); g.quadraticCurveTo(x, y, x + r, y); g.quadraticCurveTo(x, y, x, y + r); g.quadraticCurveTo(x, y, x - r, y); g.quadraticCurveTo(x, y, x, y - r); g.fill(); }
  function nuzky(g, x, y, s, barva) {   // nůžky otevřené doprava
    g.save(); g.translate(x, y); g.strokeStyle = barva; g.fillStyle = barva; g.lineWidth = s * 0.12; g.lineCap = 'round';
    g.beginPath(); g.arc(-s * 0.55, -s * 0.32, s * 0.24, 0, Math.PI * 2); g.stroke();
    g.beginPath(); g.arc(-s * 0.55, s * 0.32, s * 0.24, 0, Math.PI * 2); g.stroke();
    g.beginPath(); g.moveTo(-s * 0.36, -s * 0.2); g.lineTo(s * 0.75, s * 0.18); g.stroke();
    g.beginPath(); g.moveTo(-s * 0.36, s * 0.2); g.lineTo(s * 0.75, -s * 0.18); g.stroke();
    g.beginPath(); g.arc(0, 0, s * 0.07, 0, Math.PI * 2); g.fill(); g.restore();
  }
  /* logo Bezpečné dveře (logo.jpg z webu, převedené do RGB: poukaz/logo.png, 400×191) – w = šířka v jednotkách kreslení */
  function logo(g, x, y, w2) { if (IMG.logo) g.drawImage(IMG.logo, x, y, w2, w2 * IMG.logo.naturalHeight / IMG.logo.naturalWidth); }
  function fnt(g, rodina, tloustka, pt, S) { g.font = (tloustka === 'bold' ? '700 ' : '400 ') + (pt * 0.3528 * S) + 'px ' + rodina + ', Arial, sans-serif'; }

  /* ── scéna: dveře s „odstřiženým“ zaplaceným kusem + motiv (x, y, w, h v mm; S = px na mm) ── */
  function scena(g, mot, hodnota, X, Y, Wd, Hd, S, cx, opt) {
    var M = motiv(mot), r = rnd(mot === 'vanoce' ? 12 : mot === 'narozeniny' ? 34 : 56), f = podil(hodnota);
    g.save(); g.scale(S, S); g.translate(X, Y);
    g.beginPath(); g.rect(0, 0, Wd, Hd); g.clip();
    var gr = g.createLinearGradient(0, 0, Wd * 0.3, Hd); gr.addColorStop(0, M.bg[0]); gr.addColorStop(0.55, M.bg[1]); gr.addColorStop(1, M.bg[2]);
    g.fillStyle = gr; g.fillRect(0, 0, Wd, Hd);
    // rozměry dveří podle panelu
    var dw = opt && opt.dw ? opt.dw : Math.min(Wd * 0.4, Hd * 0.235), dh = dw * 2.5, dx = (cx == null ? Wd / 2 : cx) - dw / 2, dy = (opt && opt.zem ? opt.zem : Hd * 0.86) - dh, zem = dy + dh;
    // pozadí motivu
    if (mot === 'vanoce') {
      g.fillStyle = 'rgba(255,255,255,.9)'; g.beginPath(); g.arc(dx - dw * 1.15, Math.max(14, dy - dw * 0.55), dw * 0.2, 0, Math.PI * 2); g.fill();
      g.fillStyle = M.bg[0]; g.beginPath(); g.arc(dx - dw * 1.07, Math.max(14, dy - dw * 0.55) - dw * 0.03, dw * 0.17, 0, Math.PI * 2); g.fill();
    }
    // zeď (světlá omítka) kolem dveří
    var zx = dx - dw * 0.42, zw = dw * 1.84, zy = dy - dw * 0.38;
    g.fillStyle = mot === 'narozeniny' ? '#fde7d4' : '#efe6d6'; g.fillRect(zx, zy, zw, zem - zy);
    g.fillStyle = 'rgba(0,0,0,.06)'; for (var i = 0; i < 9; i++) { var by = zy + 4 + i * ((zem - zy) / 9); g.fillRect(zx, by, zw, 0.35); }
    // střecha nad zdí
    g.fillStyle = mot === 'darkovy' ? '#1f2937' : '#3b2a20'; g.beginPath(); g.moveTo(zx - dw * 0.18, zy + 0.5); g.lineTo(zx + zw / 2, zy - dw * 0.62); g.lineTo(zx + zw + dw * 0.18, zy + 0.5); g.closePath(); g.fill();
    // dveře PREMIUM 60 Ořech (fotka z premium60.cz včetně zárubně)
    var zr = dw * 0.07, ph = dh + zr, pw = ph * (IMG.dvere ? IMG.dvere.naturalWidth / IMG.dvere.naturalHeight : 0.42), px = dx + dw / 2 - pw / 2, py = dy - zr;
    var yp = zem - dh * f;                    // hranice zaplaceno / zbývá
    // nezaplacená část: jen bledý obrys dveří
    g.save(); g.beginPath(); g.rect(px - 1, py - 1, pw + 2, yp - py + 1); g.clip();
    g.fillStyle = '#ffffff'; g.fillRect(px, py, pw, ph);
    g.globalAlpha = 0.5; try { g.filter = 'grayscale(1)'; } catch (e) {} if (IMG.dvere) g.drawImage(IMG.dvere, px, py, pw, ph); try { g.filter = 'none'; } catch (e) {} g.globalAlpha = 1;
    g.fillStyle = 'rgba(226,232,240,.45)'; g.fillRect(px, py, pw, ph);
    g.setLineDash([1.6, 1.2]); g.strokeStyle = 'rgba(60,60,60,.6)'; g.lineWidth = 0.45; g.strokeRect(px + 0.4, py + 0.4, pw - 0.8, ph);
    g.setLineDash([]); g.restore();
    // zaplacená část: skutečné dveře
    g.save(); g.beginPath(); g.rect(px - 1, yp, pw + 2, zem - yp + 1); g.clip();
    if (IMG.dvere) g.drawImage(IMG.dvere, px, py, pw, ph);
    g.restore();
    g.strokeStyle = M.akcent; g.lineWidth = 0.9; g.strokeRect(px - 0.45, yp, pw + 0.9, zem - yp);   // zvýraznění zaplaceného kusu
    var kx = px + pw * 0.86, ky = py + ph * 0.45;   // klika na fotce (pro balonky)
    // práh + zem
    g.fillStyle = '#6b7280'; g.fillRect(px - zr, zem, pw + 2 * zr, 1.2);
    // motiv – dekorace
    if (mot === 'vanoce') {
      // girlanda se světýlky nad dveřmi
      g.strokeStyle = '#1f6b3d'; g.lineWidth = 2.2; g.lineCap = 'round';
      g.beginPath(); g.moveTo(dx - zr * 1.5, dy - zr * 1.2); g.quadraticCurveTo(dx + dw / 2, dy + dh * 0.04, dx + dw + zr * 1.5, dy - zr * 1.2); g.stroke();
      var barvy = ['#fde047', '#ef4444', '#60a5fa', '#fde047', '#f97316'];
      for (var b = 0; b < 9; b++) { var t = (b + 0.5) / 9, bx = (1 - t) * (1 - t) * (dx - zr * 1.5) + 2 * (1 - t) * t * (dx + dw / 2) + t * t * (dx + dw + zr * 1.5), byy = (1 - t) * (1 - t) * (dy - zr * 1.2) + 2 * (1 - t) * t * (dy + dh * 0.04) + t * t * (dy - zr * 1.2); g.fillStyle = barvy[b % barvy.length]; g.beginPath(); g.arc(bx, byy + 1.2, 0.85, 0, Math.PI * 2); g.fill(); }
      // věnec
      var vx = dx + dw / 2, vy = dy + dh * 0.17, vr = dw * 0.19;
      g.strokeStyle = '#1f6b3d'; g.lineWidth = vr * 0.45; g.beginPath(); g.arc(vx, vy, vr, 0, Math.PI * 2); g.stroke();
      g.strokeStyle = '#2f8a50'; g.lineWidth = vr * 0.16; for (var v = 0; v < 18; v++) { var a = v / 18 * Math.PI * 2; g.beginPath(); g.moveTo(vx + Math.cos(a) * vr * 0.82, vy + Math.sin(a) * vr * 0.82); g.lineTo(vx + Math.cos(a + 0.25) * vr * 1.18, vy + Math.sin(a + 0.25) * vr * 1.18); g.stroke(); }
      g.fillStyle = '#dc2626'; for (var bb = 0; bb < 7; bb++) { var a2 = bb / 7 * Math.PI * 2 + 0.3; g.beginPath(); g.arc(vx + Math.cos(a2) * vr, vy + Math.sin(a2) * vr, vr * 0.1, 0, Math.PI * 2); g.fill(); }
      g.fillStyle = '#b91c1c'; g.beginPath(); g.moveTo(vx, vy + vr); g.lineTo(vx - vr * 0.45, vy + vr * 0.7); g.lineTo(vx - vr * 0.4, vy + vr * 1.25); g.closePath(); g.fill();
      g.beginPath(); g.moveTo(vx, vy + vr); g.lineTo(vx + vr * 0.45, vy + vr * 0.7); g.lineTo(vx + vr * 0.4, vy + vr * 1.25); g.closePath(); g.fill();
      // sníh
      g.fillStyle = '#ffffff'; g.beginPath(); g.moveTo(0, Hd); g.lineTo(0, zem + 2); g.quadraticCurveTo(Wd * 0.25, zem - 3, Wd * 0.5, zem + 1.5); g.quadraticCurveTo(Wd * 0.78, zem + 5, Wd, zem - 1); g.lineTo(Wd, Hd); g.closePath(); g.fill();
      g.fillStyle = '#ffffff'; for (var s = 0; s < 170; s++) { var sx = r() * Wd, sy = r() * Hd * 0.95, sr = 0.25 + r() * 0.9; if (sx > zx - 2 && sx < zx + zw + 2 && sy > zy - dw * 0.62 && sy < zem) continue; g.globalAlpha = 0.5 + r() * 0.5; g.beginPath(); g.arc(sx, sy, sr, 0, Math.PI * 2); g.fill(); }
      g.globalAlpha = 1;
      g.fillStyle = '#ffffff'; g.beginPath(); g.moveTo(zx - dw * 0.2, zy + 0.6); g.quadraticCurveTo(zx + zw / 2, zy - dw * 0.7, zx + zw + dw * 0.2, zy + 0.6); g.lineTo(zx + zw + dw * 0.2, zy + 2.2); g.quadraticCurveTo(zx + zw / 2, zy - dw * 0.45, zx - dw * 0.2, zy + 2.2); g.closePath(); g.fill();
    } else if (mot === 'narozeniny') {
      // praporky
      var px0 = zx - 4, px1 = zx + zw + 4, py = zy + 3, n = 9, pb = ['#facc15', '#22c55e', '#3b82f6', '#ef4444', '#a855f7'];
      g.strokeStyle = '#7c2d12'; g.lineWidth = 0.35; g.beginPath(); g.moveTo(px0, py); g.quadraticCurveTo((px0 + px1) / 2, py + 7, px1, py); g.stroke();
      for (var p = 0; p < n; p++) { var tt = (p + 0.5) / n, qx = (1 - tt) * (1 - tt) * px0 + 2 * (1 - tt) * tt * (px0 + px1) / 2 + tt * tt * px1, qy = (1 - tt) * (1 - tt) * py + 2 * (1 - tt) * tt * (py + 7) + tt * tt * py, sw = (px1 - px0) / n * 0.42; g.fillStyle = pb[p % pb.length]; g.beginPath(); g.moveTo(qx - sw, qy); g.lineTo(qx + sw, qy); g.lineTo(qx, qy + sw * 1.8); g.closePath(); g.fill(); }
      // balonky přivázané ke klice
      var bal = [['#ef4444', -0.55, 0.06], ['#facc15', -0.1, 0.0], ['#3b82f6', 0.3, 0.08]];
      bal.forEach(function (q, j) {
        var bx2 = dx + dw / 2 + q[1] * dw * 2.2 + dw * 0.2, by2 = Hd * (0.12 + q[2]), br = dw * 0.26;
        g.strokeStyle = 'rgba(255,255,255,.85)'; g.lineWidth = 0.3; g.beginPath(); g.moveTo(kx - dw * 0.1, ky); g.quadraticCurveTo(bx2 - 4 + j * 3, (by2 + ky) / 2, bx2, by2 + br * 1.15); g.stroke();
        g.fillStyle = q[0]; g.beginPath(); g.ellipse(bx2, by2, br * 0.85, br, 0, 0, Math.PI * 2); g.fill();
        g.fillStyle = 'rgba(255,255,255,.35)'; g.beginPath(); g.ellipse(bx2 - br * 0.3, by2 - br * 0.35, br * 0.18, br * 0.3, -0.5, 0, Math.PI * 2); g.fill();
      });
      // konfety
      var kb = ['#fde047', '#ffffff', '#22d3ee', '#a3e635', '#f0abfc'];
      for (var c = 0; c < 150; c++) { var cx = r() * Wd, cy = r() * Hd * 0.95; if (cx > zx && cx < zx + zw && cy > zy && cy < zem) continue; g.save(); g.translate(cx, cy); g.rotate(r() * 6); g.fillStyle = kb[c % kb.length]; g.fillRect(-0.9, -0.4, 1.8, 0.8); g.restore(); }
    } else {
      // stuha přes dveře jako přes dárek + mašle
      var sw2 = dw * 0.11;
      g.fillStyle = '#dc2626'; g.fillRect(dx + dw / 2 - sw2 / 2, dy - zr, sw2, dh + zr); g.fillRect(dx - zr, dy + dh * 0.62 - sw2 / 2, dw + 2 * zr, sw2);
      g.fillStyle = '#facc15'; g.fillRect(dx + dw / 2 - sw2 / 2, dy - zr, sw2 * 0.12, dh + zr); g.fillRect(dx + dw / 2 + sw2 * 0.38, dy - zr, sw2 * 0.12, dh + zr);
      var mx = dx + dw / 2, my = dy - zr;
      g.fillStyle = '#dc2626'; g.beginPath(); g.ellipse(mx - dw * 0.17, my - dw * 0.08, dw * 0.18, dw * 0.1, -0.35, 0, Math.PI * 2); g.fill();
      g.beginPath(); g.ellipse(mx + dw * 0.17, my - dw * 0.08, dw * 0.18, dw * 0.1, 0.35, 0, Math.PI * 2); g.fill();
      g.fillStyle = '#991b1b'; g.beginPath(); g.arc(mx, my - dw * 0.03, dw * 0.06, 0, Math.PI * 2); g.fill();
      g.fillStyle = '#fde68a'; for (var h = 0; h < 40; h++) { var hx = r() * Wd, hy = r() * Hd * 0.9; if (hx > zx - 2 && hx < zx + zw + 2 && hy > zy - dw * 0.62 && hy < zem) continue; g.globalAlpha = 0.5 + r() * 0.5; hvezda4(g, hx, hy, 0.8 + r() * 1.6); }
      g.globalAlpha = 1;
    }
    // střih: čárkovaná linka s nůžkami na hranici zaplacené části
    var tagH = Math.min(7.5, dh * 0.09);
    if (f < 1) {
      g.setLineDash([1.8, 1.2]); g.strokeStyle = '#ffffff'; g.lineWidth = 0.7; g.beginPath(); g.moveTo(dx - zr * 3, yp); g.lineTo(dx + dw + zr * 3, yp); g.stroke();
      g.setLineDash([1.8, 1.2]); g.strokeStyle = '#111827'; g.lineWidth = 0.35; g.lineDashOffset = 1.5; g.beginPath(); g.moveTo(dx - zr * 3, yp); g.lineTo(dx + dw + zr * 3, yp); g.stroke();
      g.setLineDash([]); g.lineDashOffset = 0;
      nuzky(g, dx - zr * 3 - 3.2, yp, 4.2, '#111827');
      g.fillStyle = '#111827'; g.textAlign = 'center'; g.font = '700 ' + (dw * 0.12) + 'px PKCaveat, cursive';
      g.fillText('…a zbytek doplatíte', dx + dw / 2, yp - 2.2);
    }
    // štítek „Tenhle kus už je zaplacený“
    var txt = f < 1 ? 'TENHLE KUS UŽ JE ZAPLACENÝ' : 'CELÉ DVEŘE ZAPLACENÉ!';
    g.font = '700 ' + (tagH * 0.42) + 'px PKInter, Arial'; var tw = g.measureText(txt).width + tagH * 0.9;
    var ty = f < 1 ? (zem - yp < tagH + 5 ? zem + 2.2 : yp + 2.2) : dy + dh * 0.8;
    g.fillStyle = M.akcent; rr(g, dx + dw / 2 - tw / 2, ty, tw, tagH, tagH / 2); g.fill();
    g.fillStyle = '#ffffff'; g.textAlign = 'center'; g.fillText(txt, dx + dw / 2, ty + tagH * 0.66);
    g.restore();
  }

  /* ── rozvržení textů karty (společné pro canvas i PDF). measure(rodina, tloustka, pt, text) → šířka v mm ── */
  var CARD = { x: 12, y: 134, w: 186, h: 160 };
  function texty(d, measure) {
    var M = motiv(d.motiv), L = CARD.x + 12, R = CARD.x + CARD.w - 12, Y = CARD.y, o = [];
    var T = function (t, x, y, rod, tl, pt, col, al, cs) { o.push({ t: t, x: x, y: y, r: rod, tl: tl, pt: pt, c: col, a: al || 'left', cs: cs || 0 }); };
    T(M.kicker, L, Y + 36, 'Inter', 'bold', 11, M.kicker_c, 'left', 0.55);
    T('na nové dveře', L, Y + 48, 'Playfair', 'bold', 25, '#14261c');
    T(kc(d.hodnota), L, Y + 70, 'Inter', 'bold', 46, M.akcent);
    T(M.vtip, L, Y + 79, 'Inter', 'normal', 10.5, '#4b5d52');
    T('Pro:', L, Y + 93, 'Inter', 'normal', 10, '#6b7c71');
    var pro = String(d.obdarovany || '').trim();
    if (pro) T(pro, L + 9, Y + 93.3, 'Playfair', 'bold', 16, '#14261c');
    // věnování – zalamování do max. 3 řádků
    var ven = String(d.venovani || '').replace(/\s+/g, ' ').trim(), rad = [], maxW = R - L - 4, pt = 19;
    if (ven) {
      for (var pokus = 0; pokus < 5; pokus++) {
        rad = []; var slova = ven.split(' '), akt = '';
        slova.forEach(function (sl) { var z = akt ? akt + ' ' + sl : sl; if (measure('Caveat', 'bold', pt, z) > maxW && akt) { rad.push(akt); akt = sl; } else akt = z; });
        if (akt) rad.push(akt);
        if (rad.length <= 3) break; pt -= 1.5;
      }
      rad = rad.slice(0, 3);
      rad.forEach(function (rr2, i) { T(rr2, L + 2, Y + 102 + i * 8.4, 'Caveat', 'bold', pt, '#1e3a5f'); });
    }
    var od = String(d.od_koho || '').trim();
    if (od) T(od, R - 2, Y + 127.3, 'Caveat', 'bold', 19, '#1e3a5f', 'right');
    // spodní údaje
    var sl3 = (R - L) / 3;
    [['Číslo poukazu', d.cislo || '—'], ['Ověřovací kód', d.kod || '—'], ['Platí do', d.platnost_do ? datum(d.platnost_do) : '—']].forEach(function (x, i) {
      T(x[0], L + i * sl3, Y + 138.5, 'Inter', 'normal', 8, '#6b7c71'); T(x[1], L + i * sl3, Y + 144.5, 'Inter', 'bold', 12.5, '#14261c');
    });
    T('Uplatníte ve vzorkovně KK SYSTEM, Obchodní 36, Most, nebo při objednávce dveří na www.bezpecne-dvere.cz.', L, Y + 150.6, 'Inter', 'normal', 7.3, '#4b5d52');
    T('Odečte se z ceny dveří (bezpečnostních, vchodových i interiérových) včetně montáže. Tel. +420 725 559 235.', L, Y + 154.2, 'Inter', 'normal', 7.3, '#4b5d52');
    return o;
  }
  var ROD = { Inter: 'PKInter', Playfair: 'PKPlay', Caveat: 'PKCaveat' };

  /* pozadí celé strany (scéna + karta + linky) do canvasu */
  function pozadi(g, d, S) {
    var M = motiv(d.motiv);
    g.save(); g.scale(S, S);
    g.restore();
    scena(g, d.motiv, d.hodnota, 0, 0, W, H, S, W / 2, { dw: 35, zem: 124 });
    g.save(); g.scale(S, S);
    // karta
    g.shadowColor = 'rgba(0,0,0,.35)'; g.shadowBlur = 4 * S / S; g.shadowOffsetY = 1;
    g.fillStyle = '#ffffff'; rr(g, CARD.x, CARD.y, CARD.w, CARD.h, 5); g.fill(); g.shadowColor = 'transparent';
    g.strokeStyle = M.ram; g.lineWidth = 0.6; rr(g, CARD.x + 3, CARD.y + 3, CARD.w - 6, CARD.h - 6, 3.5); g.stroke();
    // logo
    logo(g, CARD.x + 11, CARD.y + 8, 34);
    var L = CARD.x + 12, R = CARD.x + CARD.w - 12, Y = CARD.y;
    // ozdobný roh karty podle motivu
    g.fillStyle = M.ram; g.globalAlpha = 0.9;
    if (d.motiv === 'vanoce') { g.font = '700 ' + 9 + 'px PKInter, Arial'; for (var i = 0; i < 3; i++) hvezda4(g, R - 4 - i * 7, CARD.y + 17 - (i % 2) * 4, 2.2 - i * 0.4); }
    else if (d.motiv === 'narozeniny') { for (var j = 0; j < 4; j++) hvezda4(g, R - 3 - j * 6.5, CARD.y + 16 - (j % 2) * 4, 2 - j * 0.3); }
    else { for (var k = 0; k < 3; k++) hvezda4(g, R - 4 - k * 7, CARD.y + 16 - (k % 2) * 4, 2 - k * 0.3); }
    g.globalAlpha = 1;
    // linka pod textem
    g.fillStyle = '#e1e8e3'; g.fillRect(L, Y + 84, R - L, 0.35);
    // řádky pro věnování
    g.strokeStyle = '#dbe3ec'; g.lineWidth = 0.3; for (var r2 = 0; r2 < 4; r2++) { g.beginPath(); g.moveTo(L, Y + 103.8 + r2 * 8.4); g.lineTo(R, Y + 103.8 + r2 * 8.4); g.stroke(); }
    // spodní pruh
    g.fillStyle = '#f5f7f5'; rr(g, L - 3, Y + 133, R - L + 6, 13.8, 2.5); g.fill();
    g.restore();
  }

  function kresli(canvas, d, S) {
    S = S || 4; canvas.width = Math.round(W * S); canvas.height = Math.round(H * S);
    var g = canvas.getContext('2d');
    pozadi(g, d, S);
    var meas = function (rod, tl, pt, t) { fnt(g, ROD[rod], tl, pt, S); return g.measureText(t).width / S; };
    texty(d, meas).forEach(function (o) {
      fnt(g, ROD[o.r], o.tl, o.pt, S); g.fillStyle = o.c; g.textBaseline = 'alphabetic';
      if (o.cs) { g.textAlign = 'left'; var x = o.x * S; for (var i = 0; i < o.t.length; i++) { g.fillText(o.t[i], x, o.y * S); x += g.measureText(o.t[i]).width + o.cs * S; } }
      else { g.textAlign = o.a; g.fillText(o.t, o.x * S, o.y * S); }
    });
    return canvas;
  }

  function jsPdfDoc(orient) {
    var doc = new w.jspdf.jsPDF({ orientation: orient, unit: 'mm', format: 'a4', compress: true });
    FONTS.forEach(function (f, i) { doc.addFileToVFS(f[3], b64(bufs[i])); doc.addFont(f[3], f[4], f[1]); });
    return doc;
  }
  function hex(c) { var m = /^#?(..)(..)(..)$/.exec(c); return m ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)] : [0, 0, 0]; }

  function pdf(d) {
    return prepare().then(function () { return w.jspdf ? null : loadScript(PDFDIR + 'jspdf.umd.min.js'); }).then(function () {
      var S = 11.8;                                   // ≈ 300 dpi
      var c = document.createElement('canvas'); c.width = Math.round(W * S); c.height = Math.round(H * S);
      pozadi(c.getContext('2d'), d, S);
      var doc = jsPdfDoc('portrait');
      doc.setProperties({ title: 'Dárkový poukaz ' + (d.cislo || ''), author: 'Bezpečné dveře · KK SYSTEM', subject: 'Dárkový poukaz na dveře' });
      doc.addImage(c.toDataURL('image/jpeg', 0.9), 'JPEG', 0, 0, W, H);
      var meas = function (rod, tl, pt, t) { doc.setFont(rod, tl); doc.setFontSize(pt); return doc.getTextWidth(t); };
      texty(d, meas).forEach(function (o) {
        doc.setFont(o.r, o.tl); doc.setFontSize(o.pt); doc.setTextColor.apply(doc, hex(o.c));
        doc.text(o.t, o.x, o.y, o.cs ? { charSpace: o.cs } : { align: o.a });
      });
      return doc.output('blob');
    });
  }

  /* Potvrzení o přijetí platby za poukaz (pro kupujícího, zůstává mu – poukaz daruje). */
  var PRODAVAJICI = ['Zdeněk Kučera', 'Zd. Štěpánka 2764/1, 434 01 Most', 'IČO: 64682676, DIČ: CZ7801242768', 'Vzorkovna KK SYSTEM, Obchodní 36, Most', 'tel. +420 725 559 235, chci@bezpecne-dvere.cz'];
  function doklad(d) {
    return prepare().then(function () { return w.jspdf ? null : loadScript(PDFDIR + 'jspdf.umd.min.js'); }).then(function () {
      var doc = jsPdfDoc('portrait'), y = 22, G = [18, 61, 39], MU = [79, 106, 91];
      var t = function (s, x, yy, st, pt, col, al) { doc.setFont('Inter', st || 'normal'); doc.setFontSize(pt || 10); doc.setTextColor.apply(doc, col || [20, 38, 28]); doc.text(s, x, yy, al ? { align: al } : undefined); };
      var lc = document.createElement('canvas'); lc.width = IMG.logo.naturalWidth; lc.height = IMG.logo.naturalHeight; lc.getContext('2d').drawImage(IMG.logo, 0, 0);
      doc.addImage(lc.toDataURL('image/png'), 'PNG', 20, 12, 40, 40 * lc.height / lc.width);
      t('Potvrzení o přijetí platby', 190, 22, 'bold', 16, G, 'right'); t('za dárkový poukaz ' + (d.cislo || ''), 190, 29, 'normal', 11, MU, 'right');
      y = 48; doc.setDrawColor(220, 233, 225); doc.line(20, y - 6, 190, y - 6);
      t('Prodávající', 20, y, 'bold', 10, G); PRODAVAJICI.forEach(function (s, i) { t(s, 20, y + 6 + i * 5, 'normal', 9.5); });
      t('Kupující', 110, y, 'bold', 10, G);
      var k = [d.kupujici_firma, d.kupujici_jmeno, d.kupujici_ulice, [d.kupujici_psc, d.kupujici_mesto].filter(Boolean).join(' '), d.kupujici_ic ? 'IČO: ' + d.kupujici_ic + (d.kupujici_dic ? ', DIČ: ' + d.kupujici_dic : '') : '', d.email].filter(function (s) { return s && String(s).trim(); });
      k.forEach(function (s, i) { t(String(s), 110, y + 6 + i * 5, 'normal', 9.5); });
      y = 92; doc.setFillColor(246, 250, 247); doc.roundedRect(20, y, 170, 52, 3, 3, 'F');
      var rows = [['Předmět', 'Dárkový poukaz na dveře (' + motiv(d.motiv).nazev.toLowerCase() + ' motiv)'], ['Číslo poukazu', d.cislo || ''], ['Hodnota poukazu', kc(d.hodnota)],
        ['Přijatá platba', kc(d.zaplaceno_kc || d.hodnota) + (d.zaplaceno_dne ? ', připsáno ' + datum(d.zaplaceno_dne) : '')], ['Způsob úhrady', d.zpusob_uhrady || ('bankovním převodem, VS ' + (d.vs || ''))], ['Platnost poukazu', d.platnost_do ? 'do ' + datum(d.platnost_do) : '12 měsíců']];
      rows.forEach(function (r2, i) { t(r2[0], 26, y + 9 + i * 7.5, 'normal', 9.5, MU); t(r2[1], 72, y + 9 + i * 7.5, 'bold', 10); });
      y = 156;
      var pozn = ['Poukaz je víceúčelový – lze ho uplatnit na dveře s různou sazbou DPH. Tento doklad potvrzuje přijetí platby za poukaz a není daňovým dokladem; daňový doklad se vystaví při uplatnění poukazu na konkrétní zakázku.',
        'Poukaz lze uplatnit ve vzorkovně KK SYSTEM, Obchodní 36, Most, nebo při objednávce dveří na www.bezpecne-dvere.cz. Hodnota se odečte z ceny dveří (bezpečnostních, vchodových i interiérových, včetně montáže). Poukaz nelze vyměnit za hotovost.'];
      doc.setFont('Inter', 'normal'); doc.setFontSize(8.6); doc.setTextColor.apply(doc, MU);
      pozn.forEach(function (s2) { var lines = doc.splitTextToSize(s2, 170); doc.text(lines, 20, y); y += lines.length * 4.4 + 3; });
      t('Vystaveno: ' + datum(d.vystaveno || new Date().toISOString().slice(0, 10)), 20, 270, 'normal', 9, MU);
      t('Bezpečné dveře · KK SYSTEM · www.bezpecne-dvere.cz', 190, 270, 'normal', 9, MU, 'right');
      return doc.output('blob');
    });
  }

  w.Poukaz = { MOTIVY: MOTIVY, VZOR_CENA: VZOR_CENA, vychoziMotiv: vychoziMotiv, podil: podil, kc: kc, datum: datum, ready: prepare, kresli: kresli, pdf: pdf, doklad: doklad,
    scena: function (g, m, h, x, y, ww, hh, S) { return prepare().then(function () { scena(g, m, h, x, y, ww, hh, S); }); } };
})(window);
