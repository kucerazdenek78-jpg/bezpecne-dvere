/* Konfigurátor ERKADO na stránce Dveře do domu – zapne se sám od 1. 11. 2026 (do té doby jen zkušební provoz přes panel prodejce).
   Náhled dřív: stránka s ?bonus=1 (vypnutí ?bonus=0), stejný přepínač bd-bonus jako bonus.js; platí jen v tom prohlížeči. */
(function () {
  var on = Date.now() >= new Date('2026-11-01T00:00:00+01:00').getTime();
  try {
    var q = location.search.match(/[?&]bonus=([01])/);
    if (q) { if (q[1] === '1') localStorage.setItem('bd-bonus', '1'); else localStorage.removeItem('bd-bonus'); }
    if (localStorage.getItem('bd-bonus') === '1') on = true;
  } catch (e) {}
  if (!on) return;
  var URL0 = 'konfigurator-erkado.html';
  function model(el) { var m = /ERKADO ([A-ZÁ-Ž]+)/.exec(el.getAttribute('onclick') || ''); return m ? m[1].toLowerCase() : ''; }
  /* banner nad doporučenými modely */
  var feat = document.querySelector('.erkado-featured');
  if (feat && !document.querySelector('.konf-banner')) {
    var b = document.createElement('div'); b.className = 'konf-banner';
    b.style.cssText = 'background:linear-gradient(135deg,#0f3a24,#1f6b43);color:#fff;border-radius:var(--radius-lg);padding:1.1rem 1.5rem;margin:0 0 1rem;display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap';
    b.innerHTML = '<span style="flex:1 1 260px;font-size:.95rem;line-height:1.5"><strong style="display:block;font-size:1.05rem">🛠 Nakonfigurujte si dveře a hned uvidíte cenu</strong>Všech 31 řad ERKADO: vzor, dekor, rozměr, světlíky, kování a madlo, s montáží i bez. Na závěr si je vyzkoušíte na fotce svých dveří.</span><a href="' + URL0 + '" class="btn btn-primary">Otevřít konfigurátor →</a>';
    feat.parentNode.insertBefore(b, feat);
  }
  /* karty modelů: odkaz do konfigurátoru s předvybranou řadou */
  document.querySelectorAll('.e-card-body a.btn[href="#poptavka"]').forEach(function (a) {
    var m = model(a); if (!m || a.previousElementSibling && a.previousElementSibling.classList.contains('konf-go')) return;
    var k = document.createElement('a'); k.className = 'btn btn-outline konf-go'; k.href = URL0 + '#' + m; k.textContent = 'Nakonfigurovat a spočítat cenu →';
    k.style.cssText = 'width:100%;justify-content:center;font-size:0.82rem;padding:0.55rem;margin-bottom:.45rem';
    a.parentNode.insertBefore(k, a);
  });
  document.querySelectorAll('a.e-mini-btn[href="#poptavka"]').forEach(function (a) {
    var m = model(a); if (!m || a.previousElementSibling && a.previousElementSibling.classList.contains('konf-go')) return;
    var k = document.createElement('a'); k.className = 'e-mini-btn konf-go'; k.href = URL0 + '#' + m; k.textContent = 'Konfigurátor →';
    a.parentNode.insertBefore(k, a); a.style.paddingTop = '.25rem';
  });
})();
