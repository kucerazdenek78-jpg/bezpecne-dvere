/* Digitální servisní knížka ke dveřím – blok na web.
   Zobrazí se od 1. 11. 2026 (náhled dřív: přidej ?nahled=1 do adresy).
   Použití: <div data-servisni-knizka></div>  nebo  <div data-servisni-knizka="kompakt"></div> */
(function () {
  var START = new Date('2026-10-25T00:00:00+01:00').getTime();
  var preview = /[?&]nahled=1/.test(location.search);
  if (Date.now() < START && !preview) return;
  var slots = document.querySelectorAll('[data-servisni-knizka]');
  if (!slots.length) return;
  var css = '' +
    '.sk-band{background:radial-gradient(120% 90% at 85% 10%,#2a7a52 0%,#123d27 45%,#0b2618 100%);color:#fff;padding:3.5rem 0;overflow:hidden}' +
    '.sk-wrap{max-width:1100px;margin:0 auto;padding:0 1rem;display:grid;grid-template-columns:1.2fr .8fr;gap:2.5rem;align-items:center}' +
    '.sk-kick{display:inline-block;background:#c2410c;color:#fff;font-weight:800;font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;border-radius:999px;padding:.4rem .9rem}' +
    '.sk-band h2{color:#fff;font-size:clamp(1.9rem,4.5vw,3rem);font-weight:900;line-height:1.05;letter-spacing:-.02em;margin:1rem 0 .8rem}' +
    '.sk-band h2 em{font-style:normal;color:#ffbe96}' +
    '.sk-sub{color:#cfe9da;font-size:1.1rem;line-height:1.45;margin:0 0 1.2rem}' +
    '.sk-pts{list-style:none;padding:0;margin:0 0 1.6rem;display:grid;gap:.6rem}' +
    '.sk-pts li{display:flex;gap:.6rem;font-weight:600;line-height:1.35}' +
    '.sk-pts li:before{content:"✓";flex:none;width:1.4em;height:1.4em;border-radius:50%;background:#9be7bd;color:#0b2618;display:flex;align-items:center;justify-content:center;font-size:.8em;font-weight:900;margin-top:.05em}' +
    '.sk-btns{display:flex;flex-wrap:wrap;gap:.7rem}' +
    '.sk-btn{display:inline-block;border-radius:999px;padding:.8rem 1.4rem;font-weight:800;text-decoration:none;border:2px solid #fff;color:#fff}' +
    '.sk-btn.pri{background:#c2410c;border-color:#c2410c}' +
    '.sk-vid{justify-self:center;width:100%;max-width:300px;aspect-ratio:9/16;border-radius:28px;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.4);background:#0b2618}' +
    '.sk-vid video{width:100%;height:100%;object-fit:cover;display:block}' +
    '.sk-note{color:#9fc7b1;font-size:.85rem;margin-top:1rem}' +
    '.sk-band.kompakt{padding:2.5rem 0}.sk-band.kompakt .sk-wrap{grid-template-columns:1fr auto}.sk-band.kompakt .sk-vid{max-width:200px}' +
    '@media(max-width:760px){.sk-wrap,.sk-band.kompakt .sk-wrap{grid-template-columns:1fr}.sk-vid{max-width:260px}.sk-band.kompakt .sk-vid{display:none}}';
  var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var video = '<div class="sk-vid"><video src="userFiles/moje-dvere/reel-servisni-knizka.mp4" poster="userFiles/moje-dvere/reel-servisni-knizka-poster.webp" muted loop playsinline preload="none"' + (reduce ? ' controls' : ' autoplay') + ' aria-label="Video: digitální servisní knížka ke dveřím"></video></div>';
  slots.forEach(function (el) {
    var mode = el.getAttribute('data-servisni-knizka'), kompakt = mode === 'kompakt';
    var btn1 = mode === 'stranka' ? '<a class="sk-btn pri" href="poptavka-dvere.html">Chci dveře</a>' : '<a class="sk-btn pri" href="moje-dvere.html">Jak to funguje</a>';
    el.innerHTML =
      '<section class="sk-band' + (kompakt ? ' kompakt' : '') + '" aria-label="Digitální servisní knížka ke dveřím"><div class="sk-wrap"><div>' +
      '<span class="sk-kick">Nový přístup ke dveřím · od 1. 11.</span>' +
      '<h2>Digitální servisní knížka <em>ke dveřím</em></h2>' +
      '<p class="sk-sub">Jako u auta – jen pro vaše dveře. Ke každým bezpečnostním dveřím od nás v ceně, v aplikaci Moje dveře. Jiný přístup ke dveřím i k zákazníkům.</p>' +
      (kompakt ? '' :
      '<ul class="sk-pts">' +
        '<li>Celá historie dveří: montáž, registrace, servis i prohlídky</li>' +
        '<li>Připomínka roční prohlídky a objednání termínu z mobilu</li>' +
        '<li>Návod k údržbě s připomínkou každých 6 měsíců a certifikáty dveří ke stažení</li>' +
        '<li>Až +12 měsíců záruky navíc (6 za registraci, 6 za recenzi) při pravidelné údržbě</li>' +
      '</ul>') +
      '<div class="sk-btns">' + btn1 + '<a class="sk-btn" href="aplikace/#viz">Vyzkoušet dveře na fotce</a></div>' +
      '</div>' + video + '</div></section>';
  });
})();
