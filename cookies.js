/* Cookie lišta (informační) + TOPlist (jednoduché počítadlo návštěv, spouští se vždy).
   Potvrzení lišty se ukládá do localStorage: cookiesConsent = 'necessary'. */
(function () {
  var K = 'cookiesConsent', TL_ID = '1834438';
  function get() { try { return localStorage.getItem(K); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(K, v); localStorage.setItem('cookiesOk', '1'); } catch (e) {} }
  function toplist() {
    if (window.__toplistLoaded) return; window.__toplistLoaded = true;
    var i = new Image(1, 1); i.alt = 'TOPlist';
    i.src = 'https://toplist.cz/dot.asp?id=' + TL_ID + '&http=' + encodeURIComponent(document.referrer || '') +
      '&wi=' + screen.width + '&he=' + screen.height + '&cd=' + screen.colorDepth + '&t=' + encodeURIComponent(document.title);
  }
  function bar() {
    var b = document.getElementById('cookieBar');
    if (!b) { b = document.createElement('div'); b.className = 'cookie-bar'; b.id = 'cookieBar'; document.body.appendChild(b); }
    b.classList.remove('hidden');
    b.innerHTML = '<span>Tento web používá jen nezbytné cookies (např. pamatuje si toto potvrzení). Návštěvnost měříme jednoduchým počítadlem TOPlist. ' +
      '<a href="cookies.html" style="color:inherit;text-decoration:underline">Více informací</a></span>' +
      '<span style="display:flex;gap:.5rem;flex-wrap:wrap">' +
      '<button type="button" class="btn btn-primary" id="ckNec" style="padding:0.5rem 1.2rem;font-size:0.82rem;">Rozumím</button></span>';
    document.getElementById('ckNec').onclick = function () { set('necessary'); b.classList.add('hidden'); };
  }
  window.bdCookies = { reset: function () { try { localStorage.removeItem(K); localStorage.removeItem('cookiesOk'); } catch (e) {} bar(); } };
  function init() { toplist(); if (get() === null) bar(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
