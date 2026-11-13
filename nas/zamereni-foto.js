/* Zavaděč pro stránku Zaměření (zamereni-nove.php načítá <script src="zamereni-foto.js">) – 13. 11. 2026.
   1) Připomínka e-mailu zákazníka (přání Zdeňka: „do zaměřování mi připomeň, že mám chtít e-mail“):
      u prázdného pole E-mail žlutá výzva a při ukládání bez e-mailu dotaz, jestli se má uložit i tak.
   2) Načte původní skript „📷 Vyfotit a dopsat rozměry do fotky“, který je beze změny v zf/zamereni-foto.js.
   Proč takhle: zamereni-nove.php (65 kB) nejde přes MCP spolehlivě přepsat, tenhle malý soubor ano. */
(function () {
  'use strict';
  if (!window.__zamEmail) {
    window.__zamEmail = 1;
    var em = document.getElementById('f_email');
    if (em) {
      var note = document.createElement('div');
      note.textContent = '📧 Zeptejte se zákazníka na e-mail – bez něj mu nepůjde poslat nabídka, objednávka ani termín montáže.';
      note.style.cssText = 'margin-top:5px;padding:7px 9px;border-radius:8px;background:#fff4c2;border:1px solid #e0b100;color:#5a4300;font-size:13.5px;font-weight:600;line-height:1.35';
      em.insertAdjacentElement('afterend', note);
      var stav = function () {
        var prazdne = !em.value.trim();
        note.hidden = !prazdne;
        em.style.borderColor = prazdne ? '#e0b100' : ''; em.style.background = prazdne ? '#fffbea' : '';
      };
      ['input', 'change', 'blur'].forEach(function (t) { em.addEventListener(t, stav); });
      stav(); setTimeout(stav, 600); setTimeout(stav, 2500);   /* po obnovení konceptu / výběru stávajícího zákazníka */
      document.addEventListener('click', function (ev) {
        var t = ev.target && ev.target.closest ? ev.target : null; if (!t) return;
        if (t.closest('.najit, #najit, [data-zak]') || t.closest('button')) setTimeout(stav, 150);
        var b = t.closest('#ulozit, #podepsat'); if (!b || em.value.trim()) return;
        if (!window.confirm('Chybí e-mail zákazníka.\n\nBez e-mailu mu nepůjde poslat nabídka ani objednávka.\n\nOK = uložit bez e-mailu\nZrušit = vrátit se a e-mail doplnit')) {
          ev.preventDefault(); ev.stopImmediatePropagation();
          try { em.scrollIntoView({ block: 'center' }); em.focus(); } catch (e) {}
        }
      }, true);
    }
  }
  var s = document.createElement('script'); s.src = 'zf/zamereni-foto.js?v=20261111c'; document.body.appendChild(s);
})();
