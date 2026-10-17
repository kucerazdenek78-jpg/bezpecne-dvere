/* Offline pro aplikaci: shell a obrázky odstínů z mezipaměti, HTML vždy nejdřív ze sítě. Při změně souborů zvýšit VERZE. */
var VERZE = 'bd-app-20261017d';
var SHELL = ['./', './index.html', './app.css?v=20261017c', './app.js?v=20261017d', './viz-data.js?v=20261017a', './bd-form.js?v=20261015a', './manifest.webmanifest',
  './img/premium60-cerna-perlet.jpg', './img/premium60-dub.jpg', './img/premium60-orech.jpg', './img/premium60-svetle-wenge.jpg', './img/premium60-bila.jpg', './img/premium60-antracit.jpg',
  './img/extreme54-dub-natura.jpg', './img/extreme54-orech-natura.jpg', './img/extreme54-antracit.jpg',
  './img/premium60.jpg', './img/extreme54.jpg', './img/magnum56.jpg', './img/securido65.jpg', '../logo-white.svg', '../icon-192.png'];
self.addEventListener('install', function (e) { e.waitUntil(caches.open(VERZE).then(function (c) { return c.addAll(SHELL); }).then(function () { return self.skipWaiting(); })); });
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (k) { return Promise.all(k.filter(function (n) { return n !== VERZE; }).map(function (n) { return caches.delete(n); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return; // odesílání formulářů a API nikdy z mezipaměti
  if (r.mode === 'navigate') { e.respondWith(fetch(r).catch(function () { return caches.match('./index.html'); })); return; }
  e.respondWith(caches.match(r).then(function (hit) { return hit || fetch(r); }));
});
