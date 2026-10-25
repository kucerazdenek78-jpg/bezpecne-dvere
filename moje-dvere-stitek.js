/* Štítek „Moje dveře · aplikace v ceně“ v rohu hlavní fotky dveří.
   Zobrazí se od 1. 11. 2026 (náhled dřív: přidej ?nahled=1 do adresy).
   Použití:
   - na <img data-md-stitek>  → fotka se obalí a štítek je v jejím pravém dolním rohu
   - na kontejneru <div data-md-stitek="kontejner"> (pozice relative) → štítek je v rohu kontejneru */
(function () {
  var START = new Date('2026-11-01T00:00:00+01:00').getTime();
  var preview = /[?&]nahled=1/.test(location.search);
  if (Date.now() < START && !preview) return;
  var END = null; /* konec zobrazení štítku u loga: null = do odvolání, jinak např. new Date('2027-02-01T00:00:00+01:00').getTime() */
  var els = document.querySelectorAll('[data-md-stitek]');
  var css = '' +
    '.md-st-wrap{position:relative;display:block}' +
    '.md-stitek{position:absolute;right:.6rem;bottom:.6rem;z-index:3;display:flex;align-items:center;gap:.5rem;text-decoration:none;color:#fff;background:linear-gradient(135deg,var(--navy-mid,#1f6b45),var(--navy,#123d27));border:2px solid #fff;border-radius:14px;padding:.4rem .75rem .4rem .45rem;box-shadow:0 8px 20px rgba(0,0,0,.28);line-height:1.1;transform:rotate(-3deg)}' +
    '.md-stitek svg{flex:none;width:30px;height:30px}' +
    '.md-stitek b{display:block;font-size:.88rem;font-weight:800;letter-spacing:.01em}' +
    '.md-stitek small{display:block;font-size:.68rem;font-weight:700;color:#ffbe96;text-transform:uppercase;letter-spacing:.05em;margin-top:.15rem}' +
    '.md-logo-st{position:absolute;top:100%;left:6px;margin-top:6px;z-index:5;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;color:#fff;background:linear-gradient(135deg,var(--navy-mid,#1f6b45),var(--navy,#123d27));border:2px solid #fff;border-radius:12px;padding:.3rem .65rem .3rem .4rem;box-shadow:0 6px 16px rgba(0,0,0,.25);line-height:1.05;white-space:nowrap;transform:rotate(-2deg)}' +
    '.md-logo-st svg{flex:none;width:22px;height:22px}.md-logo-st b{display:block;font-size:.76rem;font-weight:800}.md-logo-st small{display:block;font-size:.6rem;font-weight:700;color:#ffbe96;text-transform:uppercase;letter-spacing:.05em}' +
    '@media (hover:hover){.md-stitek,.md-logo-st{transition:transform .2s}.md-logo-st:hover{transform:rotate(0) scale(1.05)}}' +
    '@media (hover:hover){.md-stitek{transition:transform .2s}.md-stitek:hover{transform:rotate(0) scale(1.05)}}' +
    '@media(max-width:480px){.md-stitek{right:.4rem;bottom:.4rem;padding:.3rem .6rem .3rem .35rem}.md-stitek svg{width:24px;height:24px}.md-stitek b{font-size:.78rem}.md-stitek small{font-size:.6rem}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  var ico = '<svg viewBox="0 0 32 32" aria-hidden="true"><rect x="9" y="2" width="14" height="28" rx="3.5" fill="#fff"/><rect x="11.5" y="6" width="9" height="17" rx="1" fill="#123d27"/><path d="M14 10h4v10h-4z" fill="#ffbe96"/><circle cx="17" cy="15.5" r=".8" fill="#123d27"/><circle cx="16" cy="26.5" r="1.2" fill="#123d27"/></svg>';
  function badge() {
    var a = document.createElement('a');
    a.className = 'md-stitek'; a.href = 'moje-dvere.html';
    a.setAttribute('aria-label', 'Aplikace Moje dveře je ke dveřím v ceně – zjistit víc');
    a.innerHTML = ico + '<span><b>Moje dveře</b><small>aplikace v ceně</small></span>';
    return a;
  }
  var logo = document.querySelector('.nav-logo');
  if (logo && (END === null || Date.now() < END)) {
    var l = document.createElement('a');
    l.className = 'md-logo-st'; l.href = 'moje-dvere.html';
    l.setAttribute('aria-label', 'Aplikace Moje dveře je ke dveřím v ceně – zjistit víc');
    l.innerHTML = ico + '<span><b>Moje dveře</b><small>aplikace v ceně</small></span>';
    logo.appendChild(l);
  }
  els.forEach(function (el) {
    if (el.tagName === 'IMG') {
      var w = document.createElement('span'); w.className = 'md-st-wrap';
      el.parentNode.insertBefore(w, el); w.appendChild(el);
      var mb = parseFloat(getComputedStyle(el).marginBottom) || 0;
      var b = badge(); if (mb) b.style.bottom = (mb + 9) + 'px'; w.appendChild(b);
    } else {
      if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
      var bd = badge(); if (el.getAttribute('data-md-stitek') === 'kontejner-vys') bd.style.bottom = '5.2rem'; el.appendChild(bd);
    }
  });
})();
