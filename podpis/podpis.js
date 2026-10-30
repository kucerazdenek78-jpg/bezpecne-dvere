/* Podpis na displeji (prstem / perem / myší) pro objednávky a předávací list.
   BDPodpis.volba(kotva, {nadpis, zakaznik, zhotovitel, papirText, displejText, vychozi}) → vloží před element `kotva` volbu
   „Na papíře“ / „Na displeji“ a pole pro podpisy. Vrací { rezim(), zakaznik(), zhotovitel(), chyba(), vymaz() }:
   - rezim(): 'papir' | 'displej'
   - zakaznik() / zhotovitel(): PNG data URL (oříznutý podpis) nebo null
   - chyba(): text chyby, když je zvolen displej a chybí podpis zákazníka, jinak ''
   BDPodpis.pole(el, popisek) → samotné pole pro podpis { png(), prazdne(), vymaz() }. */
(function () {
  'use strict';
  var CSS = '.bdp{border:1px solid #d6dde6;border-radius:12px;padding:12px 14px;margin:12px 0;background:#fff}' +
    '.bdp h4{margin:0 0 8px;font-size:16px}.bdp-seg{display:flex;gap:8px;flex-wrap:wrap}.bdp-seg label{flex:1;min-width:150px;border:2px solid #c9d1dc;border-radius:10px;padding:10px;text-align:center;cursor:pointer;font-weight:500;margin:0}' +
    '.bdp-seg input{position:absolute;opacity:0;pointer-events:none}.bdp-seg label.on{border-color:#1d8a4a;background:#e9f7ee;font-weight:700}' +
    '.bdp-pole{margin-top:10px}.bdp-pole b{display:block;font-size:14px;margin-bottom:4px}.bdp-w{position:relative}' +
    '.bdp canvas{display:block;width:100%;height:170px;border:2px dashed #9aa7b8;border-radius:10px;background:#fcfdff;touch-action:none;cursor:crosshair}' +
    '.bdp-hint{position:absolute;left:12px;top:10px;color:#b1bac6;font-size:14px;pointer-events:none}.bdp-x{margin-top:4px;background:#e6ebf3;color:#1b2430;border:0;border-radius:8px;padding:6px 12px;font:inherit;font-size:14px;cursor:pointer}' +
    '.bdp-mut{color:#6b7787;font-size:13px;margin:6px 0 0}';
  function css() { if (document.getElementById('bdp-css')) return; var s = document.createElement('style'); s.id = 'bdp-css'; s.textContent = CSS; document.head.appendChild(s); }

  function pole(el, popisek) {
    css();
    el.className = (el.className ? el.className + ' ' : '') + 'bdp-pole';
    el.innerHTML = '<b></b><div class="bdp-w"><canvas></canvas><span class="bdp-hint">Podepište se sem prstem</span></div><button type="button" class="bdp-x">Smazat podpis</button>';
    el.querySelector('b').textContent = popisek || 'Podpis';
    var cv = el.querySelector('canvas'), hint = el.querySelector('.bdp-hint'), ctx = null, kresli = false, last = null, tahy = 0;
    function priprav() {   // velikost plátna podle skutečné šířky (ostrý podpis i na retina displeji)
      var r = cv.getBoundingClientRect(); if (!r.width) return false;
      var k = Math.max(1, Math.min(3, window.devicePixelRatio || 1)), w = Math.round(r.width * k), h = Math.round(r.height * k);
      if (cv.width !== w || cv.height !== h) {
        var stary = tahy ? cv.toDataURL() : null;
        cv.width = w; cv.height = h; ctx = cv.getContext('2d');
        ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b2a6b'; ctx.lineWidth = 2.6 * k;
        if (stary) { var im = new Image(); im.onload = function () { ctx.drawImage(im, 0, 0, w, h); }; im.src = stary; }
      }
      return true;
    }
    function bod(e) { var r = cv.getBoundingClientRect(), k = cv.width / r.width; return { x: (e.clientX - r.left) * k, y: (e.clientY - r.top) * k }; }
    cv.addEventListener('pointerdown', function (e) { if (!priprav()) return; e.preventDefault(); kresli = true; last = bod(e); try { cv.setPointerCapture(e.pointerId); } catch (x) {}
      ctx.beginPath(); ctx.arc(last.x, last.y, ctx.lineWidth / 2, 0, Math.PI * 2); ctx.fillStyle = ctx.strokeStyle; ctx.fill(); tahy++; hint.hidden = true; });
    cv.addEventListener('pointermove', function (e) { if (!kresli) return; e.preventDefault(); var p = bod(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; });
    function konec() { kresli = false; }
    cv.addEventListener('pointerup', konec); cv.addEventListener('pointercancel', konec); cv.addEventListener('pointerleave', konec);
    function vymaz() { if (ctx) ctx.clearRect(0, 0, cv.width, cv.height); tahy = 0; hint.hidden = false; }
    el.querySelector('.bdp-x').addEventListener('click', vymaz);
    window.addEventListener('resize', function () { if (cv.offsetParent) priprav(); });
    setTimeout(priprav, 0);
    function png() {   // oříznutí na podpis (průhledné pozadí), aby se do PDF vešel bez prázdných okrajů
      if (!tahy || !ctx) return null;
      var w = cv.width, h = cv.height, data = ctx.getImageData(0, 0, w, h).data, x0 = w, y0 = h, x1 = -1, y1 = -1;
      for (var y = 0; y < h; y++) for (var x = 0; x < w; x++) if (data[(y * w + x) * 4 + 3] > 10) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
      if (x1 < 0) return null;
      var o = 6; x0 = Math.max(0, x0 - o); y0 = Math.max(0, y0 - o); x1 = Math.min(w - 1, x1 + o); y1 = Math.min(h - 1, y1 + o);
      var c2 = document.createElement('canvas'); c2.width = x1 - x0 + 1; c2.height = y1 - y0 + 1;
      c2.getContext('2d').drawImage(cv, x0, y0, c2.width, c2.height, 0, 0, c2.width, c2.height);
      return c2.toDataURL('image/png');
    }
    return { png: png, prazdne: function () { return !tahy; }, vymaz: vymaz, priprav: priprav };
  }

  function volba(kotva, o) {
    css(); o = o || {};
    var box = document.createElement('div'); box.className = 'bdp';
    var id = 'bdp' + Math.random().toString(36).slice(2, 7);
    box.innerHTML = '<h4></h4><div class="bdp-seg">' +
      '<label class="on"><input type="radio" name="' + id + '" value="papir" checked> 🖨 Na papíře (vytisknu)</label>' +
      '<label><input type="radio" name="' + id + '" value="displej"> ✍️ Na displeji (podpis prstem)</label></div>' +
      '<p class="bdp-mut bdp-info"></p><div class="bdp-pole-zak"></div><div class="bdp-pole-zhot"></div>';
    box.querySelector('h4').textContent = o.nadpis || 'Podpis';
    kotva.parentNode.insertBefore(box, kotva);
    var info = box.querySelector('.bdp-info'), pz = box.querySelector('.bdp-pole-zak'), ph = box.querySelector('.bdp-pole-zhot');
    var zak = pole(pz, o.zakaznik || 'Podpis zákazníka'), zhot = o.zhotovitel === false ? null : pole(ph, o.zhotovitel || 'Podpis za zhotovitele (nepovinné)');
    if (!zhot) ph.remove();
    function nastav(r) {
      box.querySelectorAll('.bdp-seg label').forEach(function (l) { var i = l.querySelector('input'); i.checked = i.value === r; l.classList.toggle('on', i.checked); });
      var disp = r === 'displej'; pz.hidden = !disp; if (zhot) ph.hidden = !disp;
      info.textContent = disp ? (o.displejText || 'Zákazník se podepíše prstem do pole níže, podpis se vloží do PDF.') : (o.papirText || 'PDF se vytiskne a podepíše na papíře.');
      if (disp) setTimeout(function () { zak.priprav(); if (zhot) zhot.priprav(); }, 0);
    }
    box.querySelectorAll('.bdp-seg input').forEach(function (i) { i.addEventListener('change', function () { nastav(i.value); }); });
    nastav(o.vychozi === 'displej' ? 'displej' : 'papir');
    function rezim() { var i = box.querySelector('.bdp-seg input:checked'); return i ? i.value : 'papir'; }
    return {
      el: box, rezim: rezim,
      zakaznik: function () { return rezim() === 'displej' ? zak.png() : null; },
      zhotovitel: function () { return rezim() === 'displej' && zhot ? zhot.png() : null; },
      chyba: function () { return rezim() === 'displej' && zak.prazdne() ? 'Chybí podpis zákazníka – podepište se do pole, nebo zvolte „Na papíře“.' : ''; },
      vymaz: function () { zak.vymaz(); if (zhot) zhot.vymaz(); }
    };
  }
  window.BDPodpis = { volba: volba, pole: pole };
})();
