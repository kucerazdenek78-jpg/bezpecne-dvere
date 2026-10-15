/* Odesílání formulářů na vlastní server (formulare.dvere.app) – náhrada FormSubmit.
   Použití: BDForm.send({ typ, predmet, telo, reply_to, email_zakaznik, cislo, data_json, honey, files:[{name,file,blob}] }) → Promise
   typ: poptavka | objednavka_interni | objednavka_zakaznik (adresáti jsou pevně na serveru, ne v prohlížeči). */
(function (w) {
  'use strict';
  var ENDPOINT = 'https://formulare.dvere.app/odeslat.php';
  /* Záloha: když vlastní server neodpoví (výpadek NAS/internetu) nebo selže (HTTP 5xx), poptávka a interní
     objednávka se odešle přes FormSubmit na tuto adresu, ať o ni nepřijdeme (e-mail pak bude anglický). */
  var FALLBACK_TO = 'kucerazd@kksystem.cz';

  function humanize(n) {
    n = String(n).replace(/_/g, ' ').trim();
    return n.charAt(0).toUpperCase() + n.slice(1);
  }

  /* [['Popisek','hodnota'], …] → text e-mailu (pole začínající _ a data_json se nepřidávají) */
  function bodyFromFields(list) {
    return list.filter(function (x) { return x[0] && x[0].charAt(0) !== '_' && x[0] !== 'data_json'; })
      .map(function (x) { return x[0] + ': ' + x[1]; }).join('\n');
  }

  function send(o) {
    var fd = new FormData();
    fd.append('typ', o.typ || 'poptavka');
    fd.append('predmet', o.predmet || '');
    fd.append('telo', o.telo || '');
    if (o.reply_to) fd.append('reply_to', o.reply_to);
    if (o.email_zakaznik) fd.append('email_zakaznik', o.email_zakaznik);
    if (o.cislo) fd.append('cislo', o.cislo);
    if (o.data_json) fd.append('data_json', o.data_json);
    fd.append('_honey', o.honey || '');
    (o.files || []).forEach(function (f) { fd.append(f.name, f.blob, f.file); });
    var ctl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, 90000) : null;
    var typ = o.typ || 'poptavka';
    return fetch(ENDPOINT, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd, signal: ctl ? ctl.signal : undefined })
      .then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }, function () { return { ok: false, status: r.status, j: null }; });
      })
      .then(function (x) {
        if (!(x.ok && x.j && (x.j.success === true || x.j.success === 'true'))) {
          var err = new Error((x.j && x.j.message) || 'fail');
          err.retryable = x.status >= 500; /* 4xx (limit, neplatná data) se nezkouší znovu */
          throw err;
        }
      })
      .then(function (v) { if (timer) clearTimeout(timer); return v; }, function (e) {
        if (timer) clearTimeout(timer);
        var network = !('retryable' in e); /* fetch selhal: server nedostupný / timeout */
        if ((network || e.retryable) && typ !== 'objednavka_zakaznik') return fallback(o);
        throw e;
      });
  }

  /* záložní odeslání přes FormSubmit (stejná data, anglický rámec e-mailu) */
  function fallback(o) {
    var fd = new FormData();
    fd.append('_subject', (o.predmet || 'Formulář z webu') + ' [záložní odeslání]');
    fd.append('_captcha', 'false');
    fd.append('_template', 'table');
    if (o.reply_to) fd.append('email', o.reply_to);
    fd.append('zprava', o.telo || '');
    if (o.cislo) fd.append('cislo', o.cislo);
    if (o.data_json) fd.append('data_json', o.data_json);
    (o.files || []).forEach(function (f) { fd.append(f.name, f.blob, f.file); });
    return fetch('https://formsubmit.co/ajax/' + FALLBACK_TO, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!(d && (d.success === true || d.success === 'true'))) throw new Error('fallback'); });
  }

  /* odeslání běžného HTML formuláře (form-submit.js, registrace záruky): pole → text, soubory → přílohy */
  function sendForm(form, typ) {
    var lines = [], files = [], replyTo = '', subject = '', honey = '';
    new FormData(form).forEach(function (v, k) {
      if (k === '_subject') { subject = v; return; }
      if (k === '_honey') { honey = v; return; }
      if (k.charAt(0) === '_') return;
      if (typeof v === 'object' && v && 'size' in v) { if (v.size > 0) files.push({ name: k, file: v.name || k, blob: v }); return; }
      if (String(v).trim() === '') return;
      lines.push(humanize(k) + ': ' + v);
    });
    var em = form.querySelector('input[type="email"]'); if (em) replyTo = em.value;
    lines.push('Odesláno: ' + new Date().toLocaleString('cs-CZ'));
    return send({ typ: typ || form.getAttribute('data-typ') || 'poptavka', predmet: subject || 'Formulář z webu', telo: lines.join('\n'), reply_to: replyTo, honey: honey, files: files });
  }

  w.BDForm = { ENDPOINT: ENDPOINT, send: send, sendForm: sendForm, bodyFromFields: bodyFromFields };
})(window);
