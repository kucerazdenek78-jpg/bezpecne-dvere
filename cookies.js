/* Cookie lišta + TOPlist (měření návštěvnosti jen po souhlasu).
   Volba se ukládá do localStorage: cookiesConsent = 'all' | 'necessary'. */
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
    b.innerHTML = '<span>Tento web používá nezbytné cookies. Se vaším souhlasem měříme návštěvnost službou TOPlist. ' +
      '<a href="cookies.html" style="color:inherit;text-decoration:underline">Více informací</a></span>' +
      '<span style="display:flex;gap:.5rem;flex-wrap:wrap">' +
      '<button type="button" class="btn btn-primary" id="ckAll" style="padding:0.5rem 1.2rem;font-size:0.82rem;">Souhlasím</button>' +
      '<button type="button" class="btn" id="ckNec" style="padding:0.5rem 1.2rem;font-size:0.82rem;background:transparent;color:#fff;border:1px solid rgba(255,255,255,.55);">Pouze nezbytné</button></span>';
    document.getElementById('ckAll').onclick = function () { set('all'); b.classList.add('hidden'); toplist(); };
    document.getElementById('ckNec').onclick = function () { set('necessary'); b.classList.add('hidden'); };
  }
  window.bdCookies = { reset: function () { try { localStorage.removeItem(K); localStorage.removeItem('cookiesOk'); } catch (e) {} bar(); } };
  function init() { var v = get(); if (v === 'all') toplist(); else if (v === null) bar(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
