/* ZKUŠEBNÍ: kontakt v průběhu konfigurace (2. 11. 2026).
   Po dokončení kroku 2 (model, dekor, konstrukce) se jednou nabídne uložení návrhu s cenou: stačí telefon NEBO e-mail.
   Kdo nechce, pokračuje bez uložení (nic se nenutí). Vyplněný kontakt se předvyplní do kroku 5, takže ho nepíše podruhé.
   Tato testovací verze nic neodesílá (stránka nenačítá formulare-klient.js); údaje jen předvyplní formulář a zapíše do konzole. */
(function () {
  var KEY = 'bd-kf-gate-test', shown = false, bypass = false;
  try { shown = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
  function $(id) { return document.getElementById(id); }
  function curStep() { var li = document.querySelector('#kfSteps li.on'); return li ? +li.getAttribute('data-s') : 1; }
  var css = document.createElement('style');
  css.textContent = '.kfg-bg{position:fixed;inset:0;background:rgba(15,39,71,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px}'
    + '.kfg{background:#fff;color:#1b2430;border-radius:14px;max-width:460px;width:100%;padding:22px;box-shadow:0 12px 40px rgba(0,0,0,.35);font:15px/1.5 system-ui,Arial,sans-serif}'
    + '.kfg h2{margin:0 0 6px;font-size:20px}.kfg p{margin:0 0 12px;color:#475569}.kfg label{display:block;font-size:13px;color:#475569;margin:0 0 10px}'
    + '.kfg input[type=text],.kfg input[type=tel],.kfg input[type=email]{display:block;width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:8px;margin-top:3px}'
    + '.kfg .kfg-chk{display:flex;gap:8px;align-items:flex-start;color:#1b2430}.kfg .kfg-chk input{margin-top:4px}'
    + '.kfg .kfg-err{color:#b42318;font-size:13px;min-height:1.2em;margin:0 0 8px}.kfg .kfg-act{display:flex;flex-wrap:wrap;gap:10px;align-items:center}'
    + '.kfg .kfg-skip{background:none;border:0;color:#1f5fbf;text-decoration:underline;font:inherit;cursor:pointer;padding:8px}';
  document.head.appendChild(css);
  function open(done) {
    var bg = document.createElement('div'); bg.className = 'kfg-bg';
    bg.innerHTML = '<div class="kfg" role="dialog" aria-modal="true" aria-labelledby="kfgH"><h2 id="kfgH">Uložíme vám návrh s cenou</h2>'
      + '<p>Pošleme vám ho, ať se k němu můžete vrátit, a poradíme s výběrem. Stačí telefon <b>nebo</b> e-mail.</p>'
      + '<label>Telefon<input type="tel" id="kfgTel" inputmode="tel" autocomplete="tel" placeholder="777 123 456"></label>'
      + '<label>E-mail<input type="email" id="kfgEm" autocomplete="email"></label>'
      + '<label>Jméno (nepovinné)<input type="text" id="kfgJm" autocomplete="name"></label>'
      + '<label class="kfg-chk"><input type="checkbox" id="kfgGd"><span>Souhlasím se zpracováním osobních údajů k vyřízení poptávky. <a href="zasady-osobnich-udaju.html" target="_blank" rel="noopener">Zásady</a></span></label>'
      + '<div class="kfg-err" id="kfgErr" role="alert"></div>'
      + '<div class="kfg-act"><button type="button" class="btn btn-primary" id="kfgOk">Uložit a pokračovat</button><button type="button" class="kfg-skip" id="kfgNo">Pokračovat bez uložení</button></div></div>';
    document.body.appendChild(bg);
    var tel = bg.querySelector('#kfgTel'); tel.focus();
    function close(saved) { document.removeEventListener('keydown', esc); bg.remove(); try { sessionStorage.setItem(KEY, '1'); } catch (e) {} shown = true; if (saved) fill(saved); done(); }
    function esc(e) { if (e.key === 'Escape') close(null); }
    document.addEventListener('keydown', esc);
    bg.querySelector('#kfgNo').addEventListener('click', function () { close(null); });
    bg.querySelector('#kfgOk').addEventListener('click', function () {
      var t = tel.value.trim(), m = bg.querySelector('#kfgEm').value.trim(), err = bg.querySelector('#kfgErr');
      var tOk = t.replace(/[\s\/-]/g, '').replace(/^\+420/, '').length >= 9 && /^\+?\d+$/.test(t.replace(/[\s\/-]/g, ''));
      var mOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m);
      if (!tOk && !mOk) { err.textContent = 'Vyplňte platný telefon nebo e-mail.'; return; }
      if (t && !tOk) { err.textContent = 'Telefon nevypadá správně.'; return; }
      if (m && !mOk) { err.textContent = 'E-mail nevypadá správně.'; return; }
      if (!bg.querySelector('#kfgGd').checked) { err.textContent = 'Potvrďte prosím souhlas se zpracováním údajů.'; return; }
      close({ tel: t, email: m, jmeno: bg.querySelector('#kfgJm').value.trim() });
    });
  }
  function set(id, v) { var el = $(id); if (el && v) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); } }
  function fill(c) {
    set('kfTel', c.tel); set('kfEmail', c.email);
    if (c.jmeno) { var p = c.jmeno.split(/\s+/); set('kfJmeno', p[0]); set('kfPrijmeni', p.slice(1).join(' ')); }
    var g = $('kfGdpr'); if (g) g.checked = true;
    try { console.log('[TEST] uložený kontakt (neodesláno):', c); } catch (e) {}
  }
  /* zachytit přechod z kroku ≤2 dál (tlačítko Pokračovat i záložky kroků), jednou za návštěvu */
  function guard(e, target) {
    if (bypass || shown || curStep() > 2) return;
    var s = target === 'next' ? curStep() + 1 : +e.currentTarget.parentNode.getAttribute('data-s');
    if (!(s >= 3)) return;
    e.stopImmediatePropagation(); e.preventDefault();
    var el = e.currentTarget;
    open(function () { bypass = true; el.click(); bypass = false; });
  }
  var nx = $('kfNext'); if (nx) nx.addEventListener('click', function (e) { guard(e, 'next'); }, true);
  document.querySelectorAll('#kfSteps button').forEach(function (b) { b.addEventListener('click', function (e) { guard(e, 'tab'); }, true); });
})();
