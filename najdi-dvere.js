/* Hledač dveří podle rozměru otvoru (dvere-do-domu.html).
   Pravidlo majitele (4. 11. 2026): pro usazení potřebujeme aspoň +20 mm na šířku a +15 mm na výšku (od podlahy)
   nad rozměrem zárubně. Hodnoty změníte tady: */
(function () {
  'use strict';
  var NAD_SIRKA = 20;   // mm navíc na šířku otvoru
  var NAD_VYSKA = 15;   // mm navíc na výšku otvoru (od podlahy)

  /* OPTIMAL (skladem) – rozměry z letáků dokumenty/optimal-60.pdf a optimal-75.pdf (výkresy):
     sirky = rozměry z výkresu v mm, vyska = výška z výkresu v mm. */
  var OPTIMAL = [
    { id: 'optimal-60', nazev: 'OPTIMAL 60', sirky: [918, 1031], vyska: 2027, barvy: 'bílá, antracit, ořech', odkaz: '#optimal-60' },
    { id: 'optimal-75', nazev: 'OPTIMAL 75', sirky: [1031, 1131], vyska: 2087, barvy: 'bílá, antracit', odkaz: '#optimal-75' }
  ];

  var E = window.ERKADO;
  var KON = [
    { id: 'h88', klic: ['h88-hlinik'] },
    { id: 'h78', klic: ['h78-hlinik'] },
    { id: 't78', klic: ['t78-ocel', 't78-hlinik'] },
    { id: 't64', klic: ['t64-ocel', 't64-hlinik'] }
  ];

  function $(id) { return document.getElementById(id); }
  function num(v) {
    v = String(v || '').replace(',', '.').replace(/[^0-9.]/g, '');
    var n = parseFloat(v);
    if (!isFinite(n) || n <= 0) return 0;
    return n < 400 ? Math.round(n * 10) : Math.round(n); // do 400 = centimetry, jinak milimetry
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function render() {
    var W = num($('ndSirka').value), H = num($('ndVyska').value), out = $('ndVysledek');
    if (!W || !H) { out.innerHTML = ''; return; }
    var html = '<p class="nd-vstup">Zadaný otvor: <strong>' + W + ' × ' + H + ' mm</strong> (šířka × výška od podlahy). Dveře včetně zárubně musí být o ' + NAD_SIRKA + ' mm užší a o ' + NAD_VYSKA + ' mm nižší.</p>';

    /* OPTIMAL */
    var optHtml = '', optNo = [];
    OPTIMAL.forEach(function (o) {
      var ok = o.sirky.filter(function (s) { return W >= s + NAD_SIRKA; });
      if (ok.length && H >= o.vyska + NAD_VYSKA) {
        optHtml += '<div class="nd-karta nd-ok"><span class="stock-tag">✓ Skladem</span><h3>' + o.nazev + '</h3>' +
          '<p>Vyhovuje rozměr zárubně: ' + ok.map(function (s) { return '<strong>' + s + ' × ' + o.vyska + ' mm</strong>'; }).join(', ') +
          '. Barvy skladem: ' + o.barvy + '.</p><p><a href="' + o.odkaz + '">Ceny a popis ' + o.nazev + '</a></p></div>';
      } else {
        var why = H < o.vyska + NAD_VYSKA ? 'otvor je nižší, než je nutné (aspoň ' + (o.vyska + NAD_VYSKA) + ' mm)' : 'otvor je užší, než je nutné (aspoň ' + (o.sirky[0] + NAD_SIRKA) + ' mm)';
        optNo.push(o.nazev + ': ' + why);
      }
    });
    html += '<h3 class="nd-h">Hliníkové dveře skladem</h3>' + (optHtml || '<p class="nd-ne">Do tohoto otvoru žádné skladové dveře OPTIMAL nevyhovují.</p>');
    if (optNo.length && optHtml) html += '<p class="nd-ne">Nevyhovuje: ' + optNo.join('; ') + '.</p>';
    else if (optNo.length) html += '<p class="nd-ne">' + optNo.join('; ') + '.</p>';

    /* ERKADO */
    var erkHtml = '';
    if (E && E.rozmery) {
      KON.forEach(function (k) {
        var kon = E.konstrukce.filter(function (c) { return c.id === k.id; })[0];
        var radky = [];
        k.klic.forEach(function (key) {
          var R = E.rozmery[key]; if (!R) return;
          var okH = H >= R.Hc + NAD_VYSKA;
          var s = okH ? E.sirky.filter(function (w) { return W >= R.s[w][1] + NAD_SIRKA; }) : [];
          if (s.length) {
            var zar = /hlinik/.test(key) ? 'hliníková zárubeň' + (kon.zarubenStd === 'ocel' ? ' (příplatek)' : '') : 'ocelová zárubeň';
            radky.push('<li><strong>' + zar + '</strong>, Hc ' + R.Hc + ' mm: ' + s.map(function (w) { return '„' + w + '“ (Sc ' + R.s[w][1] + ' mm)'; }).join(', ') + '</li>');
          }
        });
        if (radky.length) erkHtml += '<div class="nd-karta"><h3>' + esc(kon.nazev) + '</h3><ul>' + radky.join('') + '</ul></div>';
      });
    }
    html += '<h3 class="nd-h">Ocelové dveře ERKADO (na objednávku)</h3>';
    if (erkHtml) html += '<div class="nd-mrizka">' + erkHtml + '</div><p class="nd-ne"><a href="konfigurator-erkado.html">Sestavit dveře ERKADO v konfigurátoru</a>. Čísla v uvozovkách jsou označení šířky z katalogu, Sc je celková šířka zárubně, Hc výška zárubně.</p>';
    else {
      var M = E && E.naMiru, miruOk = M && W - NAD_SIRKA >= M.sMin && H - NAD_VYSKA >= M.hMin;
      html += '<p class="nd-ne">Standardní rozměry ERKADO sem nevyhovují.' + (miruOk
        ? ' Dveře ERKADO se dají vyrobit na míru (zárubeň do ' + Math.min(W - NAD_SIRKA, M.sMax) + ' × ' + Math.min(H - NAD_VYSKA, M.hMax) + ' mm, s příplatkem): <a href="konfigurator-erkado.html">konfigurátor</a> nebo <a href="#poptavka">poptávka</a>.'
        : ' Na míru je ERKADO od zárubně ' + (M ? M.sMin + ' × ' + M.hMin : '?') + ' mm, takže ani to do tohoto otvoru nepůjde.') + '</p>';
    }

    html += '<p class="nd-ne">Orientační výběr podle rozměrů. Zaměření a konečné potvrzení rozměrů provádíme před objednávkou.</p>';
    out.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!$('ndSirka')) return;
    ['ndSirka', 'ndVyska'].forEach(function (id) { $(id).addEventListener('input', render); });
    $('ndForm').addEventListener('submit', function (e) { e.preventDefault(); render(); });
    render();
  });
})();
