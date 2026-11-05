/* Odkazy na konfigurátor ERKADO nesou ?zam= (odkaz ze zaměření), pokud ho zákazník přinesl. Hlavní vstupy a tlačítka u modelů jsou od 5. 11. 2026 přímo v HTML. */
(function () {
  var zv = ''; try { zv = new URLSearchParams(location.search).get('zam') || ''; } catch (e) {}
  if (!/^\d{1,9}-[0-9a-f]{8}$/.test(zv)) return;
  document.querySelectorAll('a[href^="konfigurator-erkado.html"]').forEach(function (a) {
    var h = a.getAttribute('href'), i = h.indexOf('#'), base = i < 0 ? h : h.slice(0, i), hash = i < 0 ? '' : h.slice(i);
    if (base.indexOf('?') < 0) a.setAttribute('href', base + '?zam=' + zv + hash);
  });
})();
