/* Odeslání z aplikace na vlastní server (formulare.dvere.app → kartotéka na NAS).
   Stejné rozhraní jako formulare-klient.js na webu (větev vlastni-formulare): typ poptavka se uloží do kartotéky
   a pošle e-mailem. Když server neodpoví nebo vrátí 5xx, odešle se záložně přes FormSubmit (bez příloh, anglický rámec). */
(function (w) {
  'use strict';
  var BASE = 'https://formulare.dvere.app/';
  var ENDPOINT = BASE + 'odeslat.php';
  var FALLBACK_TO = 'kucerazd@kksystem.cz';

  function send(o) {
    var fd = new FormData();
    fd.append('typ', o.typ || 'poptavka');
    fd.append('predmet', o.predmet || '');
    fd.append('telo', o.telo || '');
    if (o.reply_to) fd.append('reply_to', o.reply_to);
    if (o.cislo) fd.append('cislo', o.cislo);
    if (o.data_json) fd.append('data_json', o.data_json);
    fd.append('_honey', o.honey || '');
    (o.files || []).forEach(function (f) { fd.append(f.name, f.blob, f.file); });
    var ctl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, 90000) : null;
    return fetch(ENDPOINT, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd, signal: ctl ? ctl.signal : undefined })
      .then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }, function () { return { ok: false, status: r.status, j: null }; });
      })
      .then(function (x) {
        if (!(x.ok && x.j && (x.j.success === true || x.j.success === 'true'))) {
          var err = new Error((x.j && x.j.message) || 'fail'); err.retryable = x.status >= 500; throw err;
        }
        return { via: 'server' };
      })
      .then(function (v) { if (timer) clearTimeout(timer); return v; }, function (e) {
        if (timer) clearTimeout(timer);
        var network = !('retryable' in e);
        if (network || e.retryable) return fallback(o);
        throw e;
      });
  }

  function fallback(o) {
    var fd = new FormData();
    fd.append('_subject', (o.predmet || 'Aplikace') + ' [záložní odeslání]');
    fd.append('_captcha', 'false'); fd.append('_template', 'table');
    if (o.reply_to) fd.append('email', o.reply_to);
    fd.append('zprava', (o.telo || '') + ((o.files || []).length ? '\n\n(Fotky se při záložním odeslání nepřenesly – vyžádejte si je od zákazníka.)' : ''));
    if (o.cislo) fd.append('cislo', o.cislo);
    if (o.data_json) fd.append('data_json', o.data_json);
    return fetch('https://formsubmit.co/ajax/' + FALLBACK_TO, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!(d && (d.success === true || d.success === 'true'))) throw new Error('fallback'); return { via: 'zaloha' }; });
  }

  /* dotaz na API aplikace (spárování dveří s objednávkou v kartotéce) */
  function api(akce, data, files) {
    var fd = new FormData(); fd.append('akce', akce);
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    (files || []).forEach(function (f) { fd.append(f.name, f.blob, f.file); });
    return fetch(BASE + 'aplikace-api.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) {
        return r.json().catch(function () { return null; }).then(function (j) {
          if (!j) { var e0 = new Error('nedostupne'); e0.code = r.status === 404 ? 'nedostupne' : 'chyba'; throw e0; } // API ještě není nasazené
          if (!r.ok || !j.ok) { var e = new Error(j.zprava || 'chyba'); e.code = j.kod || 'chyba'; throw e; }
          return j;
        });
      });
  }

  w.BDApp = { send: send, api: api, BASE: BASE };
})(window);
