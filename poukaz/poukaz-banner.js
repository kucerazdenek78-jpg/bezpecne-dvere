/* Pruh „Dárkový poukaz“ na úvodní stránce (vloží se před pruh Doporuč a získej).
   ZAPNUTO = false → pro návštěvníky skryto; náhled ?poukaz=1 (uloží se v tomto prohlížeči), vypnutí náhledu ?poukaz=0.
   Do 26. 12. vánoční text a obrázek, potom dárkový. */
(function () {
  'use strict';
  var ZAPNUTO = false;
  var KEY = 'bd-poukaz-nahled';
  try { var q = /[?&]poukaz=([01])/.exec(location.search); if (q) { if (q[1] === '1') localStorage.setItem(KEY, '1'); else localStorage.removeItem(KEY); } } catch (e) {}
  var nahled = false; try { nahled = localStorage.getItem(KEY) === '1'; } catch (e) {}
  if (!ZAPNUTO && !nahled) return;
  var d = new Date(), md = (d.getMonth() + 1) * 100 + d.getDate(), vanoce = md >= 1001 && md <= 1226;
  var css = '.pk-band{padding:0!important;background:linear-gradient(110deg,#0e2a1f,#123d27 60%,#1d5a3a);color:#fff;overflow:hidden}' +
    '.pk-in{display:flex;align-items:center;gap:1.5rem;padding:1.2rem 0}.pk-img{width:110px;flex:none;border-radius:10px;overflow:hidden}.pk-img img{display:block;width:100%;height:auto}' +
    '.pk-txt{flex:1;min-width:0}.pk-pill{display:inline-block;background:#f5c56b;color:#3b2a0a;font-weight:800;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase;border-radius:99px;padding:.2rem .7rem;margin-bottom:.4rem}' +
    '.pk-txt strong{display:block;font-size:1.25rem;line-height:1.25}.pk-txt span{opacity:.9;font-size:.95rem}.pk-band .btn{flex:none;background:#e8650a;border-color:#e8650a;color:#fff}' +
    '@media(max-width:700px){.pk-in{flex-wrap:wrap;gap:.8rem}.pk-img{display:none}.pk-band .btn{width:100%;text-align:center}}';
  function vloz() {
    if (document.querySelector('.pk-band')) return;
    var cil = document.querySelector('.refer-band') || document.querySelector('footer'); if (!cil) return;
    var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
    var s = document.createElement('section'); s.className = 'pk-band';
    s.innerHTML = '<div class="container pk-in"><div class="pk-img"><img src="poukaz/banner-' + (vanoce ? 'vanoce' : 'darkovy') + '.png" width="240" height="300" alt="" loading="lazy"></div>' +
      '<div class="pk-txt"><span class="pk-pill">' + (vanoce ? '🎄 Vánoční dárkový poukaz' : '🎁 Dárkový poukaz') + '</span><strong>Darujte kus nových dveří.</strong>' +
      '<span>Poukaz na bezpečnostní, vchodové i interiérové dveře s montáží – od 500 Kč, s vlastním věnováním, v PDF hned po zaplacení.</span></div>' +
      '<a class="btn" href="darkovy-poukaz.html' + (vanoce ? '?motiv=vanoce' : '') + '">Koupit poukaz →</a></div>';
    cil.parentNode.insertBefore(s, cil);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', vloz); else vloz();
})();
