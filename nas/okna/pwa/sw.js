// Servis oken – service worker jen kvůli instalaci aplikace na telefon. Nic neukládá do mezipaměti,
// všechno jde vždy rovnou na server (data musí být aktuální). Bez připojení ukáže krátké upozornění.
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font:17px system-ui;padding:24px;color:#1b2430"><h2>Servis oken</h2><p>Nejste připojeni k internetu. Připojte se a zkuste to znovu.</p><button onclick="location.reload()" style="font:600 16px system-ui;padding:10px 16px;border:0;border-radius:10px;background:#0e7490;color:#fff">Zkusit znovu</button>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }));
});
