/* Štítek „Aplikace Moje dveře v ceně“ na kartách a v detailech dveří. Texty a podmínky bonusu jsou na moje-dvere.html. */
(function () {
  /* Štítek se na ostrém webu zapne samo od 1. 11. 2026. Náhled dřív: otevřete jakoukoli stránku s ?bonus=1 (vypnutí ?bonus=0), platí jen v tom prohlížeči. */
  var on = Date.now() >= new Date('2026-11-01T00:00:00+01:00').getTime();
  try {
    var q = location.search.match(/[?&]bonus=([01])/);
    if (q) { if (q[1] === '1') localStorage.setItem('bd-bonus', '1'); else localStorage.removeItem('bd-bonus'); }
    if (localStorage.getItem('bd-bonus') === '1') on = true;
  } catch (e) {}
  if (!on) return;
  function badge(long) {
    var a = document.createElement('a');
    a.className = 'md-bonus'; a.href = 'moje-dvere.html';
    a.title = 'Aplikace Moje dveře: přehled dveří a záruky, návod na údržbu a vlastní servis, hlášení závady, připomínka roční kontroly';
    a.textContent = 'Aplikace Moje dveře v ceně';
    if (long) { var s = document.createElement('small'); s.textContent = '· záruka, servis, připomínka kontroly'; a.appendChild(document.createTextNode(' ')); a.appendChild(s); }
    return a;
  }
  function before(sel, long) { document.querySelectorAll(sel).forEach(function (el) { var p = el.previousElementSibling; if (!(p && p.classList.contains('md-bonus'))) el.parentNode.insertBefore(badge(long), el); }); }
  /* interiér: před tlačítky karty; dům: před odkazem do katalogu; byt a detaily: před tlačítky / za štítky */
  before('.int-card-body .int-card-actions', false);
  before('.e-card-body .e-card-more', false);
  before('.prod-cta', true);
  document.querySelectorAll('.detail-hero-info .feature-pills').forEach(function (el) { if (!el.nextElementSibling || !el.nextElementSibling.classList.contains('md-bonus')) el.insertAdjacentElement('afterend', badge(true)); });
  /* formuláře poptávky a objednávky: štítek pod nadpisem */
  var fh = document.querySelector('form#pq');
  if (fh) { var h1 = document.querySelector('h1'); if (h1 && !(h1.nextElementSibling && h1.nextElementSibling.classList.contains('md-bonus'))) h1.insertAdjacentElement('afterend', badge(true)); }
})();
