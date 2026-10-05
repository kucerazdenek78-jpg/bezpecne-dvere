/* Odeslání poptávkových formulářů na e-mail chci@bezpecne-dvere.cz (služba FormSubmit.co, bez přesměrování) */
(function () {
  var TO = 'chci@bezpecne-dvere.cz';
  document.querySelectorAll('form[data-email-form]').forEach(function (form) {
    var btn = form.querySelector('button[type="submit"]');
    var msg = document.createElement('div');
    msg.setAttribute('role', 'status');
    msg.style.cssText = 'display:none;margin-top:1rem;padding:1rem 1.1rem;border-radius:10px;font-size:.95rem;line-height:1.5';
    form.appendChild(msg);
    function show(ok, html) {
      msg.style.display = 'block';
      msg.style.background = ok ? '#e8f5ee' : '#fdecea';
      msg.style.color = ok ? '#123d27' : '#7a1c12';
      msg.innerHTML = html;
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Odesílám…'; }
      fetch('https://formsubmit.co/ajax/' + TO, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: new FormData(form)
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && (d.success === true || d.success === 'true')) {
          form.reset();
          show(true, '<strong>Děkujeme, poptávka byla odeslána.</strong> Ozveme se Vám co nejdříve.');
        } else { throw new Error('fail'); }
      }).catch(function () {
        show(false, 'Odeslání se nepodařilo. Napište nám prosím na <a href="mailto:' + TO + '">' + TO + '</a> nebo zavolejte na <a href="tel:+420725559235">+420 725 559 235</a>.');
      }).then(function () {
        if (btn) { btn.disabled = false; btn.textContent = label; }
      });
    });
  });
})();
