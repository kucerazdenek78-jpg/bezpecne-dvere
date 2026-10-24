/* QR platba (český standard SPD) pro zálohu / rezervační poplatek. Bez externích služeb – QR se kreslí přímo v prohlížeči.
   Účet zhotovitele je stejný jako v PDF objednávky (RB 1821576001/5500). */
(function (w) {
  var PREFIX = '', ACC = '1821576001', BANK = '5500', SITE = 'https://bezpecne-dvere.cz/';
  function mod97(s) { var r = 0, i; for (i = 0; i < s.length; i++) r = (r * 10 + (s.charCodeAt(i) - 48)) % 97; return r; }
  function iban() {
    var bban = BANK + ('000000' + PREFIX).slice(-6) + ('0000000000' + ACC).slice(-10);
    var chk = 98 - mod97(bban + '123500'); return 'CZ' + (chk < 10 ? '0' : '') + chk + bban;
  }
  function ascii(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9 .,\-\/]/g, '').trim(); }
  function vs(cislo) { return String(cislo || '').replace(/\D/g, '').replace(/^0+/, '').slice(0, 10); }
  function amount(a) { a = Math.round(Number(a) || 0); return a > 0 ? a : 0; }
  function spd(a, cislo, msg) {
    var s = 'SPD*1.0*ACC:' + iban() + '*AM:' + amount(a).toFixed(2) + '*CC:CZK', v = vs(cislo);
    if (v) s += '*X-VS:' + v;
    var m = ascii(msg || ('Zaloha obj ' + cislo)).slice(0, 60); if (m) s += '*MSG:' + m;
    return s;
  }
  function qr(text) { var q = w.qrcode(0, 'M'); q.addData(text); q.make(); return q; }
  /* nakreslí QR do canvasu (px = velikost strany, quiet zone 4 moduly) */
  function canvas(text, px) {
    var q = qr(text), n = q.getModuleCount(), quiet = 4, cell = Math.max(1, Math.floor(px / (n + 2 * quiet)));
    var size = cell * (n + 2 * quiet), c = document.createElement('canvas'); c.width = c.height = size;
    var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, size, size); g.fillStyle = '#000';
    for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (q.isDark(r, k)) g.fillRect((k + quiet) * cell, (r + quiet) * cell, cell, cell);
    return c;
  }
  function png(text, px) { return canvas(text, px || 360).toDataURL('image/png'); }
  function link(cislo, a) { return SITE + 'platba.html?c=' + encodeURIComponent(cislo || '') + '&a=' + amount(a); }
  function info(cislo, a) { return { ucet: ACC + '/' + BANK, iban: iban(), vs: vs(cislo), castka: amount(a), spd: spd(a, cislo), odkaz: link(cislo, a) }; }
  function mojeUrl(t) { return 'https://bezpecne-dvere.cz/aplikace/#moje?t=' + encodeURIComponent(t || ''); }   // aplikace Moje dveře (automatické spárování)
  function predaniUrl(t) { return 'https://kartoteka.dvere.app/predani.php?t=' + encodeURIComponent(t || ''); }
  w.BDPlatba = { mojeUrl: mojeUrl, predaniUrl: predaniUrl, iban: iban, vs: vs, spd: spd, canvas: canvas, png: png, link: link, info: info, ucet: ACC + '/' + BANK };
})(window);
