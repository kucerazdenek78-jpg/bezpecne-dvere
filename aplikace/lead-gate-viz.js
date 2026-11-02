/* Kontakt po nasazení dveří na fotku (nasazeno 2. 11. 2026).
   Jednou za návštěvu nabídne zákazníkovi „pošleme vám obrázek i s cenou“ (telefon NEBO e-mail, souhlas GDPR).
   Kontakt se uloží do telefonu (bd-app-kontakt) a pošle se do kartotéky jako předběžný kontakt (BDApp.send, typ poptavka). */
(function () {
  var KEY = 'bd-viz-gate', shown = false, bypass = false;
  try { shown = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
  var css = document.createElement('style');
  css.textContent = '.kfg-bg{position:fixed;inset:0;background:rgba(15,39,71,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px}'
    + '.kfg{background:#fff;color:#1b2430;border-radius:14px;max-width:440px;width:100%;padding:20px;box-shadow:0 12px 40px rgba(0,0,0,.35);font:15px/1.5 system-ui,Arial,sans-serif}'
    + '.kfg h2{margin:0 0 6px;font-size:19px}.kfg p{margin:0 0 12px;color:#475569}.kfg label{display:block;font-size:13px;color:#475569;margin:0 0 10px}'
    + '.kfg input[type=text],.kfg input[type=tel],.kfg input[type=email]{display:block;width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:8px;margin-top:3px}'
    + '.kfg .kfg-chk{display:flex;gap:8px;align-items:flex-start;color:#1b2430}.kfg .kfg-chk input{margin-top:4px}'
    + '.kfg .kfg-err{color:#b42318;font-size:13px;min-height:1.2em;margin:0 0 8px}.kfg .kfg-act{display:flex;flex-wrap:wrap;gap:10px;align-items:center}'
    + '.kfg .kfg-ok{background:#c2410c;color:#fff;border:0;border-radius:10px;padding:12px 18px;font:600 15px system-ui,sans-serif;cursor:pointer}'
    + '.kfg .kfg-skip{background:none;border:0;color:#1f5fbf;text-decoration:underline;font:inherit;cursor:pointer;padding:8px}';
  document.head.appendChild(css);
  function open(done) {
    var bg = document.createElement('div'); bg.className = 'kfg-bg';
    bg.innerHTML = '<div class="kfg" role="dialog" aria-modal="true" aria-labelledby="kfgH"><h2 id="kfgH">Líbí se vám? Pošleme vám obrázek i s cenou</h2>'
      + '<p>Uložíme vám tuto vizualizaci a poradíme s výběrem a termínem. Stačí telefon <b>nebo</b> e-mail.</p>'
      + '<label>Telefon<input type="tel" id="kfgTel" inputmode="tel" autocomplete="tel" placeholder="777 123 456"></label>'
      + '<label>E-mail<input type="email" id="kfgEm" autocomplete="email"></label>'
      + '<label>Jméno (nepovinné)<input type="text" id="kfgJm" autocomplete="name"></label>'
      + '<label class="kfg-chk"><input type="checkbox" id="kfgGd"><span>Souhlasím se zpracováním osobních údajů k vyřízení poptávky. <a href="../zasady-osobnich-udaju.html" target="_blank" rel="noopener">Zásady</a></span></label>'
      + '<div class="kfg-err" id="kfgErr" role="alert"></div>'
      + '<div class="kfg-act"><button type="button" class="kfg-ok" id="kfgOk">Uložit a pokračovat</button><button type="button" class="kfg-skip" id="kfgNo">Pokračovat bez uložení</button></div></div>';
    document.body.appendChild(bg);
    var tel = bg.querySelector('#kfgTel'); tel.focus();
    function close(saved) { document.removeEventListener('keydown', esc); bg.remove(); try { sessionStorage.setItem(KEY, '1'); } catch (e) {} shown = true; if (saved) save(saved); done(); }
    function esc(e) { if (e.key === 'Escape') close(null); }
    document.addEventListener('keydown', esc);
    bg.querySelector('#kfgNo').addEventListener('click', function () { close(null); });
    bg.querySelector('#kfgOk').addEventListener('click', function () {
      var t = tel.value.trim(), m = bg.querySelector('#kfgEm').value.trim(), err = bg.querySelector('#kfgErr'), tn = t.replace(/[\s\/-]/g, '');
      var tOk = /^\+?\d+$/.test(tn) && tn.replace(/^\+420/, '').length >= 9, mOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m);
      if (!tOk && !mOk) { err.textContent = 'Vyplňte platný telefon nebo e-mail.'; return; }
      if (t && !tOk) { err.textContent = 'Telefon nevypadá správně.'; return; }
      if (m && !mOk) { err.textContent = 'E-mail nevypadá správně.'; return; }
      if (!bg.querySelector('#kfgGd').checked) { err.textContent = 'Potvrďte prosím souhlas se zpracováním údajů.'; return; }
      close({ telefon: t, email: m, jmeno: bg.querySelector('#kfgJm').value.trim() });
    });
  }
  function save(c) {
    try {
      var k = JSON.parse(localStorage.getItem('bd-app-kontakt') || '{}') || {}, p = c.jmeno ? c.jmeno.split(/\s+/) : [];
      if (c.telefon) k.telefon = c.telefon; if (c.email) k.email = c.email;
      if (p.length) { k.jmeno = p[0]; k.prijmeni = p.slice(1).join(' '); }
      localStorage.setItem('bd-app-kontakt', JSON.stringify(k));
    } catch (e) {}
    try {
      if (window.BDApp && window.BDApp.send) {
        var r = [c.telefon && 'Telefon: ' + c.telefon, c.email && 'E-mail: ' + c.email, c.jmeno && 'Jméno: ' + c.jmeno].filter(Boolean).join('\n');
        window.BDApp.send({ typ: 'poptavka', predmet: 'Vizualizace – předběžný kontakt (zákazník zatím neposlal poptávku)', reply_to: c.email || '',
          telo: r + '\n\nZákazník si v aplikaci nasadil dveře na fotku a nechal kontakt. Zavolat / napsat a nabídnout cenu a termín.\nSouhlas se zpracováním údajů: ano.' }).catch(function () {});
      }
    } catch (e) {}
  }
  /* hlavní moment: dveře jsou nasazené na fotku (tlačítko Uložit se odemkne) – po 2,5 s, aby si zákazník náhled prohlédl */
  var saveBtn = document.getElementById('save');
  if (saveBtn && window.MutationObserver) {
    new MutationObserver(function () {
      if (saveBtn.disabled || shown || document.querySelector('.kfg-bg')) return;
      setTimeout(function () { if (!shown && !document.querySelector('.kfg-bg') && !saveBtn.disabled) open(function () {}); }, 2500);
    }).observe(saveBtn, { attributes: true, attributeFilter: ['disabled'] });
  }
  /* záloha: při uložení / sdílení, pokud se okno ještě neukázalo */
  ['save', 'share'].forEach(function (id) {
    var b = document.getElementById(id); if (!b) return;
    b.addEventListener('click', function (e) {
      if (bypass || shown) return;
      e.stopImmediatePropagation(); e.preventDefault();
      open(function () { bypass = true; b.click(); bypass = false; });
    }, true);
  });
})();
