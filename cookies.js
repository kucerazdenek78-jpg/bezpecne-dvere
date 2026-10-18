/* Cookie lišta se souhlasem + TOPlist (jednoduché počítadlo návštěv, spouští se vždy)
   + Meta Pixel (jen po souhlasu „Přijmout vše“).
   Volba se ukládá do localStorage: cookiesConsent = 'necessary' | 'all', cookiesV = '2' (verze lišty se souhlasem).
   Měření událostí z formulářů: window.bdTrack('Lead', {content_name: '…'}) – bez souhlasu nic neodešle. */
(function () {
  var K = 'cookiesConsent', V = 'cookiesV', TL_ID = '1834438', PIXEL_ID = '1054926990916638';
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(K, v); localStorage.setItem(V, '2'); localStorage.setItem('cookiesOk', '1'); } catch (e) {} }
  function toplist() {
    if (window.__toplistLoaded) return; window.__toplistLoaded = true;
    var i = new Image(1, 1); i.alt = 'TOPlist';
    i.src = 'https://toplist.cz/dot.asp?id=' + TL_ID + '&http=' + encodeURIComponent(document.referrer || '') +
      '&wi=' + screen.width + '&he=' + screen.height + '&cd=' + screen.colorDepth + '&t=' + encodeURIComponent(document.title);
  }
  /* Meta Pixel – standardní kód od Mety, načte se až po souhlasu */
  function pixel() {
    if (window.fbq) return;
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0;
      t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', PIXEL_ID);
    window.fbq('track', 'PageView');
  }
  window.bdTrack = function (ev, params) {
    try { if (get(K) === 'all' && get(V) === '2') { pixel(); window.fbq('track', ev, params || {}); } } catch (e) {}
  };
  function bar() {
    var b = document.getElementById('cookieBar');
    if (!b) { b = document.createElement('div'); b.className = 'cookie-bar'; b.id = 'cookieBar'; document.body.appendChild(b); }
    b.classList.remove('hidden');
    b.innerHTML = '<span>Používáme nezbytné cookies a jednoduché počítadlo návštěv TOPlist. S vaším souhlasem i Meta Pixel (Facebook), ' +
      'abychom věděli, které naše reklamy fungují. <a href="/cookies.html" style="color:inherit;text-decoration:underline">Více informací</a></span>' +
      '<span style="display:flex;gap:.5rem;flex-wrap:wrap">' +
      '<button type="button" class="btn" id="ckNec" style="padding:0.5rem 1.2rem;font-size:0.82rem;background:transparent;color:#fff;border-color:rgba(255,255,255,.55);">Jen nezbytné</button>' +
      '<button type="button" class="btn btn-primary" id="ckAll" style="padding:0.5rem 1.2rem;font-size:0.82rem;">Přijmout vše</button></span>';
    document.getElementById('ckNec').onclick = function () { set('necessary'); b.classList.add('hidden'); };
    document.getElementById('ckAll').onclick = function () { set('all'); b.classList.add('hidden'); pixel(); };
  }
  window.bdCookies = { reset: function () { try { localStorage.removeItem(K); localStorage.removeItem(V); localStorage.removeItem('cookiesOk'); } catch (e) {} bar(); } };
  function init() {
    toplist();
    if (get(V) !== '2' || get(K) === null) bar();   // starší „informační“ potvrzení se nepočítá jako souhlas
    else if (get(K) === 'all') pixel();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
