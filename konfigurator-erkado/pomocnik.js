/* Plovoucí pomocník AI pro výběr vchodových dveří (nasazeno 5. 11. 2026).
   Ikona dveří vpravo dole → po vyplnění telefonu, e-mailu a souhlasu se zpracováním se otevře chat. Pomocník se ptá, nabízí barvy, dekory, rozměry
   a dveře za zákazníka nastaví v konfigurátoru (window.KF). Na stránkách bez konfigurátoru ikona odkáže na konfigurátor (#pomocnik).
   Server: https://formulare.dvere.app/pomocnik-web.php (NAS, Claude API; telefon ani e-mail se do Claude neposílají). */
(function () {
  var URL = 'https://formulare.dvere.app/pomocnik-web.php';
  var KEY = 'bd-pom-web', HIST = 'bd-pom-web-hist';
  var hasKF = !!window.KF;
  if (document.getElementById('pomWebBtn')) return;
  function $(s, r) { return (r || document).querySelector(s); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) {} return null; }
  function zamQ() { try { var z = (location.search.match(/[?&]zam=([^&]+)/) || [])[1] || (JSON.parse(localStorage.getItem('bd-zam') || 'null') || {}).t; return z ? '?zam=' + encodeURIComponent(z) : ''; } catch (e) { return ''; } }

  var css = document.createElement('style');
  css.textContent = '#pomWebBtn{position:fixed;right:max(16px,env(safe-area-inset-right,0px));bottom:max(16px,env(safe-area-inset-bottom,0px));z-index:70;display:flex;align-items:center;gap:8px;border:0;border-radius:999px;background:#123d27;color:#fff;font:600 14px/1 system-ui,Arial,sans-serif;padding:10px 16px 10px 10px;box-shadow:0 6px 20px rgba(0,0,0,.28);cursor:pointer}'
    + '#pomWebBtn svg{width:34px;height:34px;background:#fff;border-radius:50%;padding:5px;box-sizing:border-box}#pomWebBtn small{display:block;font-weight:400;font-size:11px;opacity:.85;margin-top:2px}'
    + '@media (max-width:980px){#pomWebBtn{bottom:calc(76px + env(safe-area-inset-bottom,0px))}#pomWebBtn small{display:none}}'
    + '@media (hover:hover){#pomWebBtn:hover{background:#1b5a39}}'
    + '#pomWeb{position:fixed;right:max(16px,env(safe-area-inset-right,0px));bottom:max(16px,env(safe-area-inset-bottom,0px));z-index:80;width:min(400px,calc(100vw - 32px));height:min(600px,calc(100vh - 32px));display:flex;flex-direction:column;background:#fff;color:#1b2430;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.35);overflow:hidden;font:15px/1.45 system-ui,Arial,sans-serif}'
    + '#pomWeb[hidden]{display:none}'
    + '@media (max-width:980px){#pomWeb{right:8px;left:8px;width:auto;bottom:8px;height:min(78vh,620px)}}'
    + '.pw-h{display:flex;align-items:center;gap:10px;background:#123d27;color:#fff;padding:12px 14px}.pw-h b{flex:1;font-size:15px}.pw-h small{display:block;font-weight:400;font-size:12px;opacity:.85}'
    + '.pw-x{background:none;border:0;color:#fff;font-size:24px;line-height:1;cursor:pointer;padding:0 4px}'
    + '.pw-b{flex:1;overflow:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#f4f7f5}'
    + '.pw-m{max-width:88%;padding:9px 12px;border-radius:12px;white-space:pre-wrap;word-wrap:break-word}.pw-m.a{background:#fff;border:1px solid #dbe5df;align-self:flex-start}.pw-m.u{background:#123d27;color:#fff;align-self:flex-end}'
    + '.pw-m.s{align-self:center;background:#e8f3ec;color:#123d27;font-size:13px;max-width:96%}'
    + '.pw-chips{display:flex;flex-wrap:wrap;gap:6px}.pw-chips button{border:1px solid #123d27;background:#fff;color:#123d27;border-radius:999px;padding:6px 11px;font:inherit;font-size:13px;cursor:pointer}'
    + '.pw-f{display:flex;gap:8px;padding:10px;border-top:1px solid #dbe5df;background:#fff}.pw-f textarea{flex:1;resize:none;font:inherit;padding:9px;border:1px solid #c9d1dc;border-radius:10px;height:42px;max-height:100px}'
    + '.pw-f button{border:0;border-radius:10px;background:#123d27;color:#fff;font:inherit;font-weight:600;padding:0 16px;cursor:pointer}.pw-f button:disabled{opacity:.5}'
    + '.pw-g{flex:1;overflow:auto;padding:16px}.pw-g p{margin:0 0 12px;color:#475569}.pw-g label{display:block;font-size:13px;color:#475569;margin:0 0 10px}'
    + '.pw-g input[type=text],.pw-g input[type=tel],.pw-g input[type=email]{display:block;width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #c9d1dc;border-radius:8px;margin-top:3px}'
    + '.pw-g .pw-chk{display:flex;gap:8px;align-items:flex-start;color:#1b2430}.pw-g .pw-chk input{margin-top:4px}.pw-err{color:#b42318;font-size:13px;min-height:1.2em;margin:0 0 8px}'
    + '.pw-g .pw-go{border:0;border-radius:10px;background:#123d27;color:#fff;font:inherit;font-weight:600;padding:11px 18px;cursor:pointer}'
    + '.pw-dots{align-self:flex-start;color:#64748b;font-size:13px}';
  document.head.appendChild(css);

  var btn = document.createElement('button');
  btn.id = 'pomWebBtn'; btn.type = 'button'; btn.setAttribute('aria-label', 'Otevřít pomocníka AI pro výběr dveří');
  btn.innerHTML = '<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="9" y="4" width="22" height="32" rx="2" fill="#123d27"/><rect x="12" y="7" width="16" height="26" rx="1" fill="#2f7d52"/><circle cx="25" cy="21" r="1.8" fill="#fff"/><text x="20" y="19" text-anchor="middle" font-size="9" font-weight="700" font-family="system-ui,Arial,sans-serif" fill="#fff">AI</text></svg><span>Poradí s výběrem<small>Pomocník AI</small></span>';
  document.body.appendChild(btn);

  var box = null, token = ss(KEY), hist = [];
  try { hist = JSON.parse(ss(HIST) || '[]') || []; } catch (e) { hist = []; }
  var lastNote = '';

  function ulozHist() { ss(HIST, JSON.stringify(hist.slice(-24))); }
  function build() {
    box = document.createElement('div'); box.id = 'pomWeb'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Pomocník AI pro výběr dveří'); box.hidden = true;
    box.innerHTML = '<div class="pw-h"><b>Pomocník AI<small>Poradí a dveře za vás nastaví</small></b><button type="button" class="pw-x" aria-label="Zavřít">×</button></div><div id="pwBody" style="display:flex;flex-direction:column;flex:1;min-height:0"></div>';
    document.body.appendChild(box);
    $('.pw-x', box).addEventListener('click', function () { box.hidden = true; btn.hidden = false; });
    return box;
  }
  function open() {
    if (!hasKF) { location.href = 'konfigurator-erkado.html' + zamQ() + (zamQ() ? '&' : '?') + 'pomocnik=1'; return; }
    if (!box) build();
    box.hidden = false; btn.hidden = true;
    if (token) chat(); else gate();
  }
  btn.addEventListener('click', open);

  /* ── brána: telefon + e-mail + souhlas ── */
  function gate() {
    var b = $('#pwBody', box);
    b.innerHTML = '<div class="pw-g"><p>Pomocník vám poradí s barvou, dekorem, rozměry i kováním a dveře za vás rovnou nastaví v konfigurátoru. Než začneme, nechte nám prosím na sebe kontakt, ať se vám můžeme ozvat s nabídkou.</p>'
      + '<label>Telefon *<input type="tel" id="pwTel" inputmode="tel" autocomplete="tel" placeholder="777 123 456"></label>'
      + '<label>E-mail *<input type="email" id="pwEm" autocomplete="email"></label>'
      + '<label>Jméno (nepovinné)<input type="text" id="pwJm" autocomplete="name"></label>'
      + '<label class="pw-chk"><input type="checkbox" id="pwGd"><span>Souhlasím se zpracováním osobních údajů k vyřízení poptávky. Moje zprávy v rozhovoru zpracovává umělá inteligence (Claude od společnosti Anthropic). <a href="zasady-osobnich-udaju.html" target="_blank" rel="noopener">Zásady</a></span></label>'
      + '<div class="pw-err" id="pwErr" role="alert"></div><button type="button" class="pw-go" id="pwGo">Začít s pomocníkem</button></div>';
    var tel = $('#pwTel', b); tel.focus();
    $('#pwGo', b).addEventListener('click', function () {
      var t = tel.value.trim(), m = $('#pwEm', b).value.trim(), j = $('#pwJm', b).value.trim(), err = $('#pwErr', b);
      var tOk = /^\+?\d{9,14}$/.test(t.replace(/[\s\/-]/g, '')), mOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m);
      if (!tOk) { err.textContent = 'Vyplňte platný telefon.'; return; }
      if (!mOk) { err.textContent = 'Vyplňte platný e-mail.'; return; }
      if (!$('#pwGd', b).checked) { err.textContent = 'Potvrďte prosím souhlas se zpracováním údajů.'; return; }
      var go = $('#pwGo', b); go.disabled = true; err.textContent = '';
      post({ akce: 'start', tel: t, email: m, souhlas: true }).then(function (r) {
        if (!r.ok) { err.textContent = r.chyba || 'Nepodařilo se spustit pomocníka.'; go.disabled = false; return; }
        token = r.token; ss(KEY, token); fillLead({ tel: t, email: m, jmeno: j }); chat();
      }).catch(function () { err.textContent = 'Spojení se nepovedlo. Zkuste to prosím znovu.'; go.disabled = false; });
    });
  }
  function setVal(id, v) { var el = document.getElementById(id); if (el && v) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); } }
  function fillLead(c) {
    setVal('kfTel', c.tel); setVal('kfEmail', c.email);
    if (c.jmeno) { var p = c.jmeno.split(/\s+/); setVal('kfJmeno', p[0]); setVal('kfPrijmeni', p.slice(1).join(' ')); }
    var g = document.getElementById('kfGdpr'); if (g) g.checked = true;
    try { sessionStorage.setItem('bd-kf-gate', '1'); } catch (e) {}
    try {
      if (window.BDForm && window.BDForm.send) {
        var r = ['Telefon: ' + c.tel, 'E-mail: ' + c.email, c.jmeno && 'Jméno: ' + c.jmeno].filter(Boolean).join('\n');
        window.BDForm.send({ typ: 'poptavka', predmet: 'Konfigurátor ERKADO – pomocník AI (kontakt před odesláním poptávky)', reply_to: c.email,
          telo: r + '\n\nZákazník spustil v konfigurátoru pomocníka AI a nechal kontakt. Zavolat / napsat a nabídnout cenu a termín.\nSouhlas se zpracováním údajů: ano (včetně zpracování rozhovoru umělou inteligencí).' }).catch(function () {});
      }
    } catch (e) {}
  }

  /* ── chat ── */
  function post(obj) {
    return fetch(URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(obj) }).then(function (r) { return r.json(); });
  }
  function bubble(role, text) {
    var l = $('#pwList', box); var d = document.createElement('div'); d.className = 'pw-m ' + role; d.textContent = text; l.appendChild(d); l.scrollTop = l.scrollHeight; return d;
  }
  function chat() {
    var b = $('#pwBody', box);
    b.innerHTML = '<div class="pw-b" id="pwList" aria-live="polite"></div><form class="pw-f" id="pwForm"><textarea id="pwIn" rows="1" maxlength="1000" placeholder="Napište, co hledáte…" aria-label="Vaše zpráva"></textarea><button type="submit" id="pwSend">Odeslat</button></form>';
    if (!hist.length) {
      bubble('a', 'Dobrý den, jsem pomocník AI. Pomohu vám vybrat vchodové dveře a rovnou je nastavím v konfigurátoru. Řekněte mi, jaký máte dům a jaké dveře si představujete (barva, styl, zasklení, rozpočet).');
      var ch = document.createElement('div'); ch.className = 'pw-chips';
      ['Moderní dům, tmavé dveře', 'Chci dřevodekor a co nejvíc světla', 'Nevím, poraďte mi'].forEach(function (t) {
        var x = document.createElement('button'); x.type = 'button'; x.textContent = t; x.addEventListener('click', function () { ch.remove(); poslat(t); }); ch.appendChild(x);
      });
      $('#pwList', box).appendChild(ch);
    } else hist.forEach(function (h) { bubble(h.role === 'user' ? 'u' : 'a', h.text); });
    var inp = $('#pwIn', box);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#pwForm', box).requestSubmit(); } });
    $('#pwForm', box).addEventListener('submit', function (e) { e.preventDefault(); var t = inp.value.trim(); if (t) { inp.value = ''; poslat(t); } });
    inp.focus();
  }

  /* ── stav konfigurátoru ↔ návrh pomocníka ── */
  function stavTxt() {
    var KF = window.KF, s = KF.state, r = KF.calc(), o = {
      rada: s.model, vzor: s.typ, provedeni: s.prov, konstrukce: s.kon, zarubne: s.zar, otevirani: (s.strana === 'P' ? 'levé' : 'pravé') + ', ' + (s.smer === 'ven' ? 'ven' : 'dovnitř') + ' (pohled zevnitř)',
      sirka: s.sirka, dekor: s.dekor, dvoubarevne: s.dvou, dekor_zevnitr: s.dvou === 'ne' ? undefined : s.dekor2, sklo: s.sklo, rc2: s.rc2, kovani: s.kov, kovani_povrch: s.kovB, madlo: s.madlo, madlo_delka: s.madloD, madlo_povrch: s.madloB,
      montaz: s.montaz, odber: s.odber, cena_s_dph: r && r.sDph, cena_celkem_s_dopravou_a_montazi: r && r.total
    };
    if (lastNote) o.upraveno_konfiguratorem = lastNote;
    if (r && r.warn && r.warn.length) o.upozorneni = r.warn.map(function (w) { return String(w).replace(/<[^>]+>/g, ''); }).slice(0, 4);
    return JSON.stringify(o);
  }
  function pouzit(n) {
    var KF = window.KF, s = KF.state, chtene = {}, mapa = { model: 'model', typ: 'typ', prov: 'prov', kon: 'kon', zar: 'zar', smer: 'smer', sirka: 'sirka', dekor: 'dekor', dvou: 'dvou', dekor2: 'dekor2', sklo: 'sklo', rc2: 'rc2', kov: 'kov', kovB: 'kovB', madlo: 'madlo', madloD: 'madloD', madloB: 'madloB', montaz: 'montaz', odber: 'odber' };
    if (!n || typeof n !== 'object') return;
    var klice = Object.keys(n); if (!klice.length) return;
    if (n.strana) n.strana = n.strana === 'leve' ? 'P' : 'L';   // v konfigurátoru je 'P' = „levé“ (pohled zevnitř)
    ['model', 'typ', 'prov', 'kon', 'zar', 'smer', 'strana', 'sirka', 'dekor', 'dvou', 'dekor2', 'sklo', 'rc2', 'kov', 'kovB', 'madlo', 'madloD', 'madloB', 'montaz', 'odber'].forEach(function (k) {
      if (!(k in n)) return;
      if (k === 'model' && n.model !== s.model) { s.typ = 1; s.prov = ''; }   // normalize() doplní platný vzor a provedení
      s[k] = k === 'typ' ? +n[k] : n[k]; chtene[k] = s[k];
    });
    if (n.dvou && n.dvou !== 'ne' && !n.dekor2) { s.dekor2 = s.dekor2 === s.dekor ? 'bily-mat' : s.dekor2; }
    s.step = Math.min(s.step, 2);
    KF.render();
    var zm = [];
    Object.keys(chtene).forEach(function (k) { if (String(s[k]) !== String(chtene[k])) zm.push(k + ': chtěl jsem ' + chtene[k] + ', konfigurátor nastavil ' + s[k]); });
    lastNote = zm.join('; ');
    try { var kf = document.querySelector('.kf-prev, #kfPrev'); if (kf && kf.scrollIntoView && window.innerWidth > 980) kf.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (e) {}
  }

  var busy = false;
  function poslat(text) {
    if (busy) return; busy = true;
    var send = $('#pwSend', box); send.disabled = true;
    hist.push({ role: 'user', text: text }); bubble('u', text); ulozHist();
    var dots = document.createElement('div'); dots.className = 'pw-dots'; dots.textContent = 'Pomocník píše…'; $('#pwList', box).appendChild(dots);
    post({ akce: 'chat', token: token, zpravy: hist.slice(-16), stav: stavTxt() }).then(function (r) {
      dots.remove(); busy = false; send.disabled = false;
      if (!r.ok) {
        if (r.znovu) { token = null; ss(KEY, null); bubble('s', r.chyba); gate(); return; }
        bubble('s', r.chyba || 'Pomocník se teď nemůže ozvat.'); return;
      }
      var hasN = r.nastaveni && Object.keys(r.nastaveni).length;
      if (hasN) pouzit(r.nastaveni); else lastNote = '';
      hist.push({ role: 'assistant', text: r.text }); bubble('a', r.text); ulozHist();
      if (hasN) bubble('s', 'Dveře v konfigurátoru jsou nastavené. Cenu a náhled vidíte na stránce' + (window.innerWidth <= 980 ? ' (zavřete okno křížkem)' : ' vedle') + '.');
    }).catch(function () { dots.remove(); busy = false; send.disabled = false; bubble('s', 'Spojení se nepovedlo. Zkuste to prosím znovu.'); });
  }

  if (hasKF && /[?&]pomocnik=1/.test(location.search)) setTimeout(open, 400);
})();
