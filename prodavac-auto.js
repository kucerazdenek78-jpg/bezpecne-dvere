/* Prodavač podle přihlášení v kartotéce: odkazy z kartotéky nesou ?p=Jméno. Výběr prodavače se předvyplní a ostatní možnosti se skryjí.
   Bez parametru (otevřeno napřímo přes panel s PIN) zůstává výběr jako dřív. */
(function () {
  var p = '';
  try {
    var q = new URLSearchParams(location.search).get('p');
    if (q) { p = q; sessionStorage.setItem('bd-prodavac', q); } else { p = sessionStorage.getItem('bd-prodavac') || ''; }
  } catch (e) {}
  if (!p) return;
  var n = 0, t = setInterval(function () {
    n++;
    var box = document.getElementById('poSeller') || document.getElementById('nbSeller');
    var all = box ? box.querySelectorAll('input[name="prodavac"]') : [];
    if (all.length) {
      var found = null;
      for (var i = 0; i < all.length; i++) if (all[i].value === p) found = all[i];
      if (found) {
        if (!found.checked) { found.checked = true; found.dispatchEvent(new Event('change', { bubbles: true })); }
        for (var j = 0; j < all.length; j++) if (all[j] !== found) { var l = all[j].closest('label') || all[j].parentNode; if (l) l.style.display = 'none'; }
      }
    }
    if (n > 50) clearInterval(t);
  }, 100);
})();
