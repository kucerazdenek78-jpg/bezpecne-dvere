/* Motivy webu: sezóna (jaro/léto/podzim/zima) × denní doba (den/noc).
   Automaticky podle data a hodiny. Zkoušení: ?motiv=zima-noc (uloží se v prohlížeči), ?motiv=auto vrátí automatiku,
   ?motivy=1 zobrazí přepínač. Vypnout celé: MOTIVY_ZAPNUTO = false. */
(function () {
  var MOTIVY_ZAPNUTO = true;
  var NOC_OD = 19, NOC_DO = 6;           /* hodiny: noc je od 19:00 do 6:00 */
  var KEY = 'bd-motiv';
  var root = document.documentElement;
  function get() { try { return localStorage.getItem(KEY) || ''; } catch (e) { return ''; } }
  function set(v) { try { if (v && v !== 'auto') localStorage.setItem(KEY, v); else localStorage.removeItem(KEY); } catch (e) {} }
  var q = /[?&]motiv=([a-z-]+)/.exec(location.search);
  if (q) set(q[1]);
  if (!MOTIVY_ZAPNUTO && !get() && !/[?&]motivy=1/.test(location.search)) return;
  function auto() {
    var d = new Date(), m = d.getMonth() + 1, den = d.getDate(), md = m * 100 + den, h = d.getHours();
    var s = md >= 320 && md < 621 ? 'jaro' : md >= 621 && md < 923 ? 'leto' : md >= 923 && md < 1221 ? 'podzim' : 'zima';
    return s + '-' + ((h >= NOC_OD || h < NOC_DO) ? 'noc' : 'den');
  }
  function apply() {
    var v = get(); var m = /^(jaro|leto|podzim|zima)-(den|noc)$/.exec(v) || /^(jaro|leto|podzim|zima)-(den|noc)$/.exec(auto());
    root.setAttribute('data-sezona', m[1]); root.setAttribute('data-doba', m[2]); return m;
  }
  var m = apply();
  function fx(m) {
    var old = document.querySelector('.mt-fx'); if (old) old.remove();
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var box = document.createElement('div'); box.className = 'mt-fx'; box.setAttribute('aria-hidden', 'true');
    var s = m[1], noc = m[2] === 'noc', n = 0, glyph = [], size = [14, 22];
    if (noc) { box.className += ' hvezdy' + (s === 'zima' ? '' : ' mesic'); n = 34; glyph = ['✦', '·', '✧']; size = [8, 15]; }
    else if (s === 'jaro') { n = 16; glyph = ['🌸', '🌼', '🍃']; }
    else if (s === 'leto') { box.className += ' slunce'; n = 0; }
    else if (s === 'podzim') { n = 16; glyph = ['🍂', '🍁', '🍂']; size = [16, 26]; }
    else { n = 34; glyph = ['❄', '❅', '❆']; size = [10, 20]; }
    if (noc && s === 'zima') { box.className += ' hvezdy'; }
    for (var i = 0; i < n; i++) {
      var e = document.createElement('i'); e.textContent = glyph[i % glyph.length];
      e.style.left = (Math.random() * 100) + 'vw'; e.style.fontSize = (size[0] + Math.random() * (size[1] - size[0])) + 'px';
      e.style.setProperty('--dx', ((Math.random() - .5) * 18) + 'vw'); e.style.setProperty('--rot', ((Math.random() - .5) * 360) + 'deg');
      if (noc) { e.style.top = (Math.random() * 45) + 'vh'; e.style.animationDuration = (2.5 + Math.random() * 4) + 's'; e.style.animationDelay = (-Math.random() * 5) + 's'; }
      else { e.style.animationDuration = (11 + Math.random() * 12) + 's'; e.style.animationDelay = (-Math.random() * 20) + 's'; }
      box.appendChild(e);
    }
    document.body.appendChild(box);
  }
  function panel() {
    var p = document.createElement('div'); p.className = 'mt-panel'; p.innerHTML = '<b>🎨 Motiv webu</b>';
    var cur = get() || 'auto';
    ['auto', 'jaro-den', 'jaro-noc', 'leto-den', 'leto-noc', 'podzim-den', 'podzim-noc', 'zima-den', 'zima-noc'].forEach(function (k) {
      var b = document.createElement('button'); b.textContent = k === 'auto' ? 'Auto' : k.replace('-', ' · ').replace('leto', 'léto').replace('den', 'den').replace(/^(\w)/, function (c) { return c.toUpperCase(); });
      if (k === cur) b.className = 'on';
      b.onclick = function () { set(k); var mm = apply(); fx(mm); Array.prototype.forEach.call(p.querySelectorAll('button'), function (x) { x.className = ''; }); b.className = 'on'; };
      p.appendChild(b);
    });
    document.body.appendChild(p);
  }
  document.addEventListener('DOMContentLoaded', function () { fx(m); if (/[?&]motivy=1/.test(location.search) || get()) { if (/[?&]motivy=1/.test(location.search)) panel(); } });
})();
