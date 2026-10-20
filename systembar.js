/* Horní lišta „Úvod systému“ – jen když se stránka otevře ze systému (kartoteka.dvere.app, panel prodejce) v tomto okně.
   Zákazníci ji nevidí. Všechny odkazy se otevírají ve stejném okně, takže se systém chová jako aplikace. */
(function () {
  if (window.top !== window.self) return; /* okno výběru dveří v nabídce */
  var K = 'bd-sys', on = false;
  try {
    if (/[?&]sys=1/.test(location.search) || /panel\.html$/.test(location.pathname) || /kartoteka\.dvere\.app/.test(document.referrer)) sessionStorage.setItem(K, '1');
    on = sessionStorage.getItem(K) === '1';
  } catch (e) { on = /[?&]sys=1/.test(location.search); }
  if (!on) return;
  var S = 'https://kartoteka.dvere.app/';
  var L = [[S, '🏠 Úvod systému', 'u'], [S + 'kartoteka.php', '📋 Kartotéka'], ['panel.html', 'Panel prodejce'], ['poptavka-nova.html', '➕ Nová poptávka'], ['nabidka-nova.html', '➕ Nová nabídka'], ['objednavka-vice.html', '➕ Objednávka interiér/dům'], ['objednavka-nova.html', '➕ Nová objednávka'], [S + 'sklad.php', '📦 Sklad'], [S + 'montaze.php', '📅 Montáže'], [S + 'servis.php', '🔧 Servis']];
  var st = document.createElement('style');
  st.textContent = '.bdsys{position:sticky;top:0;z-index:2147483000;display:flex;gap:4px;align-items:center;overflow-x:auto;white-space:nowrap;background:#0f2747;padding:8px max(12px,env(safe-area-inset-left,0px));-webkit-overflow-scrolling:touch;margin:0}' +
    '.bdsys a{color:#dbe6f7;text-decoration:none;font:600 14px/1 system-ui,Arial,sans-serif;padding:9px 12px;border-radius:8px}.bdsys a:hover{background:#1d3f6e}.bdsys a.u{background:#16a34a;color:#fff;margin-right:6px}';
  document.head.appendChild(st);
  var nav = document.createElement('nav'); nav.className = 'bdsys';
  L.forEach(function (x) { var a = document.createElement('a'); a.href = x[0]; a.textContent = x[1]; if (x[2]) a.className = x[2]; nav.appendChild(a); });
  function add() { document.body.insertBefore(nav, document.body.firstChild); }
  if (document.body) add(); else document.addEventListener('DOMContentLoaded', add);
})();
