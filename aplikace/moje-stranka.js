/* Stránka „Moje dveře“ (formulare.dvere.app/moje.php): kolotoč reklam a upřesnění obrázku dveří z katalogu.
   Bez tohoto skriptu stránka funguje – obrázek i kolotoč jsou jen vylepšení. */
(function () {
  'use strict';
  var BASE = 'https://bezpecne-dvere.cz/';
  var APP = BASE + 'aplikace/';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* obrázek dveří: model + odstín → fotka z katalogu (stejná pravidla jako v aplikaci) */
  var SHADES = {
    premium60: { 'černá perleť': 'premium60-cerna-perlet', 'dub': 'premium60-dub', 'ořech': 'premium60-orech', 'světlé wenge': 'premium60-svetle-wenge', 'bílá': 'premium60-bila', 'antracit': 'premium60-antracit' },
    extreme54: { 'dub natura': 'extreme54-dub-natura', 'ořech natura': 'extreme54-orech-natura', 'antracit': 'extreme54-antracit' }
  };
  var hero = document.getElementById('hero');
  if (hero) {
    var img = hero.querySelector('.hero-i');
    var kod = hero.getAttribute('data-kod') || '', model = (hero.getAttribute('data-model') || '').toUpperCase(), odstin = (hero.getAttribute('data-odstin') || '').trim().toLowerCase();
    var D = window.VIZ_DATA || {}, src = '';
    if (SHADES[kod] && SHADES[kod][odstin]) src = 'img/' + SHADES[kod][odstin] + '.jpg';
    if (!src && !/^(premium60|extreme54|magnum56|securido65)$/.test(kod)) {
      ['domovni', 'interier'].forEach(function (c) {
        (D[c] || []).forEach(function (x) { if (!src && x[0] && model.indexOf(String(x[0]).toUpperCase()) >= 0) src = x[1]; });
      });
    }
    if (img && src) img.src = APP + src;
  }

  /* kolotoč reklam (stejný jako v aplikaci) */
  var box = document.getElementById('promo'), tr = document.getElementById('promoTrack'), dots = document.getElementById('promoDots');
  if (!box || !tr || !dots) return;
  var tok = box.getAttribute('data-t') || '';
  var appMoje = APP + '#moje' + (tok ? '?t=' + encodeURIComponent(tok) : '');
  var list = [
    { t: 'PREMIUM 60', s: 'Bezpečnostní dveře do bytu', img: 'img/premium60-dub.jpg', href: BASE + 'dvere-do-bytu.html', c1: '#123d27', c2: '#1f8a55' },
    { t: 'ERKADO – dveře do domu', s: 'Vchodové dveře, 31 provedení', img: 'img/domovni/arendal.jpg', href: BASE + 'dvere-do-domu.html', c1: '#0f3a4d', c2: '#2a8aa8' },
    { t: 'Interiérové dveře', s: '33 provedení, skladové ihned', img: 'img/interier/frezia1.jpg', href: BASE + 'dvere-do-interieru.html', c1: '#6b3410', c2: '#d9822b' },
    { t: 'Doporuč a získej 1 500 Kč', s: 'Odměna za každé doporučení', img: 'img/premium60.jpg', href: appMoje, c1: '#8a4b00', c2: '#e8a317' },
    { t: 'Servis a roční prohlídka', s: 'Nahlaste závadu zde na stránce', img: 'img/premium60.jpg', href: '#servis', c1: '#7a1d3b', c2: '#d6456f' }
  ];
  tr.innerHTML = list.map(function (p) {
    return '<a class="ps" href="' + esc(p.href) + '"' + (/^https?:/.test(p.href) ? ' target="_blank" rel="noopener"' : '') + ' style="--g1:' + p.c1 + ';--g2:' + p.c2 + '"><img src="' + APP + p.img + '" alt="" width="56" height="56"><span class="pt2"><b>' + esc(p.t) + '</b><small>' + esc(p.s) + '</small></span><i aria-hidden="true">›</i></a>';
  }).join('');
  dots.innerHTML = list.map(function (p, i) { return '<u' + (i ? '' : ' class="on"') + '></u>'; }).join('');
  box.hidden = false;
  var cur = 0, timer = null;
  function go(i) { cur = (i + list.length) % list.length; tr.scrollTo({ left: tr.clientWidth * cur, behavior: 'smooth' }); }
  function mark() { var i = Math.round(tr.scrollLeft / Math.max(1, tr.clientWidth)); cur = i; Array.prototype.forEach.call(dots.children, function (u, k) { u.className = k === i ? 'on' : ''; }); }
  tr.addEventListener('scroll', mark, { passive: true });
  function stop() { if (timer) { clearInterval(timer); timer = null; } }
  function start() { stop(); if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return; timer = setInterval(function () { go(cur + 1); }, 5000); }
  ['touchstart', 'mouseenter', 'focusin'].forEach(function (ev) { box.addEventListener(ev, stop, { passive: true }); });
  ['touchend', 'mouseleave', 'focusout'].forEach(function (ev) { box.addEventListener(ev, start, { passive: true }); });
  document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
  start();
})();
