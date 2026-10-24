/* Konfigurátor vchodových ocelových dveří ERKADO – testovací verze 24. 10. 2026.
   Data: konfigurator-erkado/erkado-data.js (ceny NETTO bez DPH z katalogu 2026).
   Cena = komplet z katalogu (* bez kování / ** se základním kováním) + příplatky; montáž +10 000 Kč bez DPH,
   s montáží DPH 12 % z celé zakázky, bez montáže 21 %. Doprava s montáží podle pásem (do 40 km zdarma, 41–80 km 1 200 Kč s DPH, dál upřesníme);
   bez montáže osobní odběr zdarma, nebo doprava na adresu 18 Kč/km s DPH (jen cesta tam, km z Mapy.com). */
(function () {
  'use strict';
  var E = window.ERKADO;
  var IMG = 'konfigurator-erkado/img/';
  var MAPY_KEY = '2J4qFfalnxab9uD2PnIQrF57PRw2Qnmel9a5Vt4x6-k', MAPY = 'https://api.mapy.com/v1/';
  var $ = function (id) { return document.getElementById(id); };
  var KON = {}; E.konstrukce.forEach(function (k) { KON[k.id] = k; });
  var MOD = {}; E.modely.forEach(function (m) { MOD[m.id] = m; });
  var DEK = {}; E.dekory.forEach(function (d) { DEK[d[0]] = d; });
  var TLC = { h88: '#b91c2c', h78: '#d4600f', t78: '#a8860b', t64: '#13684a' };
  var SKLO = [['std', 'Standardní soustava skel', 'v ceně'], ['benat', 'Benátské zrcadlo (polopropustné)', 1], ['bila', 'Matné bílé / průhledné sklo', 2], ['seda', 'Matné šedé / průhledné sklo', 3]];
  var KOV = {
    B: { n: 'Klika – klika + horní zámek', d: 'Klika a rozeta CERTUS RC2, druhý (horní) zámek, vložky v systému 1 klíče. V ceně kompletu.' },
    A: { n: 'Klika – klika', d: 'Jeden zámek, vložka klíč–knoflík, klika CERTUS RC2.' },
    C: { n: 'Madlo – klika', d: 'Madlo zvenku, klika zevnitř, vložka klíč–knoflík. Madlo vyberte níže.' },
    D: { n: 'Madlo – klika + horní zámek', d: 'Madlo zvenku, klika zevnitř, horní zámek, vložky v systému 1 klíče. Madlo vyberte níže.' },
    E: { n: 'Madlo – madlo', d: 'Madlo z obou stran, otevírání tlačítkem, elektromechanický zámek, samozavírač. Jen s hliníkovou zárubní.' },
    F: { n: 'Madlo – madlo + horní zámek', d: 'Jako madlo – madlo, navíc horní zámek a vložky v systému 1 klíče. Jen s hliníkovou zárubní.' },
    X: { n: 'Bez kování', d: 'Bez kliky, madla a vložek (vlastní kování).' }
  };

  var st = {
    step: 1, model: 'horn', typ: 1, prov: 'PLAIN GLASS', kon: 't64', zar: 'ocel', smer: 'ven', strana: 'P',
    sirka: '90', sc: '', vyska: 'std', hc: '', dekor: 'dub-surovy', dvou: 'ne', dekor2: 'bily-mat', sklo: 'std', rc2: false,
    kov: 'B', kovB: 'nikl', madlo: 'none', madloD: '160', madloB: 'cerna', tridaC: false, odstin: 'Dle vzoru v katalogu', rozs: {}, sv: { h: false, hv: '300', l: false, lw: '400', p: false, pw: '400', sklo: 'std' }, vyb: {}, montaz: false, odber: 'odber', km: '', tripMsg: '', trip: null
  };

  /* ── pomocné ── */
  function kc(n) { return Math.round(n).toLocaleString('cs-CZ').replace(/\s/g, ' ') + ' Kč'; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function M() { return MOD[st.model]; }
  function typName(m, t) { return m.nazev + (t ? ' ' + t : ''); }
  function slug(m, t) { return t ? m.id + '-' + t : m.id; }
  /* provedení: vzorek ze str. 20–21 katalogu, „krátký název|popis z katalogu“ */
  var PROV = {
    'LABEL BLACK STR': ['label-black', 'Černé ozdobné lišty|panel lakovaný načerno 2 mm, nalepený na křídlo'],
    'LABEL INOX': ['label-inox', 'Nerezové ozdobné lišty|panel 2 mm s povrchem INOX, nalepený na křídlo'],
    'LABEL DIAMOND GLASS': ['label-diamond', 'Černé sklo se zkosením|panel z černého skla 5 mm se zkosenou hranou'],
    'PLAIN GLASS': ['plain-glass', 'Černé sklo v rovině|panel z černého skla zarovnaný s povrchem křídla'],
    'PLAIN LACOBEL GLASS': ['plain-lacobel', 'Černé lakované sklo v rovině|Lacobel zarovnaný s povrchem, zevnitř hladké křídlo'],
    'PLAIN LACOBEL DUO GLASS': ['plain-lacobel', 'Černé lakované sklo z obou stran|Lacobel zarovnaný s povrchem zvenku i zevnitř'],
    'STAMP': ['stamp', 'Prolisy|oboustranné prolisy 10 mm, hloubka 2 mm'],
    'STAMP ROLLER': ['stamp-roller', 'Široké prolisy|oboustranné prolisy 25 mm, hloubka 2 mm'],
    'STAMP INOX': ['stamp-intarzie', 'Prolisy s nerezovou intarzií|prolis 25 mm, intarzie INOX v rovině křídla'],
    'STAMP BLACK STR': ['', 'Prolisy s černou intarzií|prolis 25 mm, intarzie lakovaná načerno v rovině křídla', 'black'],
    'FRAME': ['frame', 'Lišta v dekoru dveří|profilovaná hliníková lišta 51 × 19 mm v dekoru křídla'],
    'FRAME BLACK STR': ['frame-black', 'Černá lišta|profilovaná hliníková lišta 51 × 19 mm v černé STR'],
    'SOLID': ['', 'Hladké křídlo|bez prolisů a ozdob', 'solid']
  };
  function hot(k) { return k === 'h88' || k === 'h78'; }
  function zarKey() { var k = KON[st.kon]; return st.kon + '-' + (k.zarubenStd === 'hlinik' || st.zar === 'hlinik' ? 'hlinik' : 'ocel'); }
  function isAlu() { return KON[st.kon].zarubenStd === 'hlinik' || st.zar === 'hlinik'; }
  function anySv() { return st.sv.h || st.sv.l || st.sv.p; }
  function madloMadlo() { return st.kov === 'E' || st.kov === 'F'; }
  function needsAlu() { return anySv() || madloMadlo(); }
  function group(m, t, prov) {
    var g = m.skupiny.filter(function (x) { return x.typy.indexOf(t) >= 0 && (!x.jen || String(prov || '').indexOf(x.jen) === 0); });
    return g[0] || null;
  }
  function provOk(m, t, p) { var lim = m.provTypy && m.provTypy[p]; return !lim || lim.indexOf(t) >= 0; }
  function minPrice(m) {
    var lo = Infinity; m.skupiny.forEach(function (g) { lo = Math.min(lo, g.ceny.t64[1]); }); return lo;
  }
  /* černé základní kování: rozdíl sady vložek B černá − nikl (vyšší cena než paušál 399 Kč z katalogu; rozhodl Zdeněk 24. 10.) */
  function cerneB(k) { return Math.max(E.priplatky.cerneKovaniStd, E.sady.B[k][1] - E.sady.B[k][0]); }
  function stdSc(key, w) { return E.rozmery[key].s[w][1]; }
  function curSc() { return st.sirka === 'miru' ? (+st.sc || 0) : stdSc(zarKey(), st.sirka); }
  function curHc() { return st.vyska === 'miru' ? (+st.hc || 0) : E.rozmery[zarKey()].Hc; }

  /* omezení z katalogu: vrátí text důvodu, nebo '' když jde */
  function konBlock(k, smer, prov) {
    var m = M();
    if (hot(k) && smer === 'dovnitr') {
      if (m.id === 'horn') return 'HORN v THERMO HOT nejde otevírat dovnitř (str. 23)';
      if (/PLAIN/.test(prov)) return 'Provedení PLAIN v THERMO HOT nejde otevírat dovnitř (str. 20)';
    }
    if (hot(k) && prov === 'PLAIN LACOBEL DUO GLASS') return 'PLAIN LACOBEL DUO GLASS není pro THERMO HOT (str. 20, 23)';
    return '';
  }
  function minScFor() {
    var m = M(), t = st.typ;
    if ((m.id === 'rotterdam' && (t === 1 || t === 2)) || (m.id === 'ebern' && t >= 1 && t <= 3)) return 1000;
    if (m.id === 'kella' && t === 4) {
      var key = zarKey();
      return { 't64-ocel': 886, 't64-hlinik': 908, 't78-ocel': 890, 't78-hlinik': 912 }[key] || 917;
    }
    return 770;
  }
  function maxScFor() {
    var m = M();
    if ((m.id === 'lutter' || m.id === 'biene') && st.typ === 1) return isAlu() ? 1178 : 1156;
    return 1210;
  }
  function fixedHeight() { var m = M(); return (m.id === 'lutter' || m.id === 'biene') && st.typ === 1; }
  function zasklTyp() { var z = M().zaskleni; return z ? z[String(st.typ)] || null : null; }
  function odkapOk() { var o = M().odkap; return o === true || (Array.isArray(o) && o.indexOf(st.typ) >= 0); }
  function oneLock() { return curHc() > 0 && curHc() < 1984; }

  /* ── srovnání stavu s pravidly (po každé změně) ── */
  function normalize() {
    var m = M();
    if (m.typy.indexOf(st.typ) < 0) st.typ = m.typy[0];
    if (m.provedeni.indexOf(st.prov) < 0 || !provOk(m, st.typ, st.prov)) st.prov = m.provedeni.filter(function (p) { return provOk(m, st.typ, p); })[0];
    if (!group(m, st.typ, st.prov)) st.prov = m.provedeni[0];
    if (konBlock(st.kon, st.smer, st.prov)) {
      var alt = ['t64', 't78', 'h78', 'h88'].filter(function (k) { return !konBlock(k, st.smer, st.prov); })[0];
      st.kon = alt || 't64';
    }
    if (KON[st.kon].zarubenStd === 'hlinik' || needsAlu()) st.zar = 'hlinik';
    else if (st.zar !== 'hlinik' && st.zar !== 'rohova') st.zar = 'ocel';
    if (st.sirka !== 'miru' && (stdSc(zarKey(), st.sirka) < minScFor() || stdSc(zarKey(), st.sirka) > maxScFor())) {
      var ok = E.sirky.filter(function (w) { var s = stdSc(zarKey(), w); return s >= minScFor() && s <= maxScFor(); });
      st.sirka = ok.indexOf('90') >= 0 ? '90' : ok[ok.length - 1] || '90';
    }
    if (fixedHeight()) st.vyska = 'std';
    if (!zasklTyp()) { st.sklo = 'std'; st.rc2 = false; }
    if (oneLock() && (st.kov === 'B' || st.kov === 'D' || st.kov === 'F')) st.kov = { B: 'A', D: 'C', F: 'E' }[st.kov];
    if (madloMadlo()) { st.vyb.samozavirac = true; delete st.vyb.elektro; delete st.vyb.tlacitko; }
    if (!(st.kov === 'C' || st.kov === 'D') || !st.vyb.elektro) delete st.vyb.tlacitko;
    if (st.kov === 'X') st.tridaC = false;
    var md = E.madla.filter(function (x) { return x.id === st.madlo; })[0];
    if (md && !md.d[st.madloD]) st.madloD = Object.keys(md.d).sort(function (a, b) { return b - a; })[0];
    if (!odkapOk() || st.smer !== 'dovnitr') delete st.vyb.odkap;
    if (!isAlu()) { delete st.vyb.elektro; delete st.vyb.samozavirac; }
    if (!(st.kov === 'C' || st.kov === 'D')) delete st.vyb.elektro;
    if (madloMadlo()) st.vyb.samozavirac = true;
    if (!(st.kov === 'A' || st.kov === 'C')) delete st.vyb.smart;
    if (st.vyb.elektro) st.vyb.samozavirac = true;
    if (st.dvou === 'ne') st.dekor2 = st.dekor;
  }

  /* ── přebarvení fotky vzoru na zvolený dekor (maska povrchu img/maska/*.png: šeď = stínování, alfa = povrch křídla) ── */
  var imgCache = {}, paintCache = {};
  function loadImg(src) {
    if (!imgCache[src]) imgCache[src] = new Promise(function (ok, ko) { var i = new Image(); i.onload = function () { ok(i); }; i.onerror = ko; i.src = src; });
    return imgCache[src];
  }
  function paint(sl, dek) {
    var key = sl + '|' + dek;
    if (!paintCache[key]) paintCache[key] = Promise.all([loadImg(IMG + 'typy/' + sl + '.jpg'), loadImg(IMG + 'maska/' + sl + '.png'), loadImg(IMG + 'dekory/' + dek + '.jpg')]).then(function (a) {
      var W = a[0].naturalWidth, H = a[0].naturalHeight, cv = document.createElement('canvas'); cv.width = W; cv.height = H;
      var cx = cv.getContext('2d');
      cx.drawImage(a[1], 0, 0, W, H); var mk = cx.getImageData(0, 0, W, H).data;
      var th = Math.max(1, Math.round(a[2].naturalHeight * W / a[2].naturalWidth));
      for (var y = 0, f = false; y < H; y += th, f = !f) {
        cx.save();
        if (f) { cx.translate(0, y + th); cx.scale(1, -1); cx.drawImage(a[2], 0, 0, W, th); } else cx.drawImage(a[2], 0, y, W, th);
        cx.restore();
      }
      var tx = cx.getImageData(0, 0, W, H).data;
      cx.clearRect(0, 0, W, H); cx.drawImage(a[0], 0, 0, W, H);
      var o = cx.getImageData(0, 0, W, H), d = o.data;
      for (var i = 0; i < d.length; i += 4) {
        var al = mk[i + 3] / 255; if (!al) continue;
        var sh = mk[i] / 255 * 1.5;
        for (var c = 0; c < 3; c++) d[i + c] = d[i + c] * (1 - al) + Math.min(255, tx[i + c] * sh) * al;
      }
      cx.putImageData(o, 0, 0);
      return cv.toDataURL('image/jpeg', 0.9);
    }).catch(function () { delete paintCache[key]; return IMG + 'typy/' + sl + '.jpg'; });
    return paintCache[key];
  }
  function paintAll(root) {
    (root || document).querySelectorAll('img[data-p]').forEach(function (im) {
      var v = im.getAttribute('data-p'), p = v.split('|');
      paint(p[0], p[1]).then(function (u) { if (im.getAttribute('data-p') === v) im.src = u; });
    });
  }

  /* ── světlíky (str. 62–63): cena za nejbližší vyšší rozměr, nestandardní rozměr + 665 Kč ── */
  function pick(list, v) { for (var i = 0; i < list.length; i++) if (list[i] >= v) return i; return -1; }
  function svSize() { return { hc: curHc() || E.rozmery[zarKey()].Hc, sc: curSc() || stdSc(zarKey(), '90'), l: st.sv.l ? +st.sv.lw || 0 : 0, p: st.sv.p ? +st.sv.pw || 0 : 0, hv: +st.sv.hv || 0 }; }
  function svetliky() {
    if (!anySv()) return [];
    var S = E.svetliky, k = st.kon, z = svSize(), out = [], area = 0;
    function side(name, w) {
      var B = S.bocni[k], ci = pick(S.bocniSirky, w), ri = pick(B.h, z.hc);
      if (w < 100 || ci < 0 || ri < 0) { out.push({ label: 'Boční světlík ' + name + ' ' + w + ' × ' + z.hc + ' mm', cena: 0, pozn: 'Boční světlík ' + name + ' ' + w + ' × ' + z.hc + ' mm je mimo ceník (šířka 200–800, výška do 2400 mm) – cenu upřesníme.' }); return; }
      var nstd = S.bocniSirky[ci] !== w || (B.h[ri] !== z.hc && ri > 0) || (ri === 0 && z.hc < B.h[0]);
      out.push({ label: 'Boční světlík ' + name + ' ' + w + ' × ' + z.hc + ' mm' + (nstd ? ' (cena za ' + S.bocniSirky[ci] + ' × ' + B.h[ri] + ')' : ''), cena: B.c[ri][ci] });
      if (nstd) out.push({ label: 'Rozměrově nestandardní světlík ' + name, cena: S.nestd });
      area += w * z.hc;
    }
    if (st.sv.l) side('vlevo', z.l);
    if (st.sv.p) side('vpravo', z.p);
    if (st.sv.h) {
      var T = S.horni[k], w = z.sc + z.l + z.p, ci = pick(T.w, w), ri = pick(S.horniVysky, z.hv);
      if (ci < 0 || ri < 0 || z.hv < 100) out.push({ label: 'Horní světlík ' + w + ' × ' + z.hv + ' mm', cena: 0, pozn: 'Horní světlík ' + w + ' × ' + z.hv + ' mm je mimo ceník (šířka do ' + T.w[T.w.length - 1] + ', výška 200–500 mm) – cenu upřesníme.' });
      else {
        var nstd = T.w[ci] !== w || S.horniVysky[ri] !== z.hv;
        out.push({ label: 'Horní světlík ' + w + ' × ' + z.hv + ' mm' + (nstd ? ' (cena za ' + T.w[ci] + ' × ' + S.horniVysky[ri] + ')' : ''), cena: T.c[ri][ci] });
        if (nstd) out.push({ label: 'Rozměrově nestandardní světlík nahoře', cena: S.nestd });
        area += w * z.hv;
      }
    }
    var m2 = area / 1e6, gl = { p4: 'Bezpečnostní sklo P4', zrcadlo: 'Polopropustné zrcadlo', bila: 'Matné bílé / průhledné sklo', seda: 'Matné šedé / průhledné sklo' }[st.sv.sklo];
    if (gl && m2) out.push({ label: gl + ' ve světlících (' + m2.toFixed(2).replace('.', ',') + ' m² × ' + kc(S.m2[st.sv.sklo]) + ')', cena: Math.round(m2 * S.m2[st.sv.sklo]) });
    if (st.dvou !== 'ne') { var n = (st.sv.h ? 1 : 0) + (st.sv.l ? 1 : 0) + (st.sv.p ? 1 : 0); out.push({ label: 'Dvoubarevný světlík' + (n > 1 ? ' (' + n + '×)' : ''), cena: n * S.dvoubarevny }); }
    return out;
  }

  /* ── výpočet ceny (bez DPH) ── */
  function calc() {
    var m = M(), g = group(m, st.typ, st.prov), k = st.kon, P = E.priplatky, L = [], warn = [], info = [];
    var withKov = st.kov === 'B';
    var base = g.ceny[k][withKov ? 1 : 0];
    L.push(['Dveře ' + typName(m, st.typ) + ', ' + KON[k].nazev + (withKov ? ', komplet se základním kováním' : ', komplet bez kování'), base, 'katalog str. ' + m.str]);
    var pp = m.provPriplatek && m.provPriplatek[st.prov];
    if (pp) L.push(['Provedení ' + st.prov, pp]);
    if (KON[k].zarubenStd === 'ocel' && st.zar === 'hlinik') L.push(['Hliníková zárubeň', KON[k].alu]);
    if (st.zar === 'rohova') { L.push(['Rohová ocelová zárubeň', 0]); info.push('Rohová ocelová zárubeň: příplatek katalog neuvádí (str. 61), potřebné kování pro objímky pantů – ověří prodejce.'); }
    if (st.sirka === '100') L.push(['Křídlo šířky „100“', P.sirka100]);
    if (st.sirka === 'miru') {
      L.push(['Nestandardní šířka Sc ' + (st.sc || '?') + ' mm', P.nestdSirka]);
      /* nad šířku „100“ se přidává i příplatek za křídlo „100“ (rozhodl Zdeněk 24. 10.) */
      if (+st.sc > stdSc(zarKey(), '100')) L.push(['Křídlo širší než „100“ (příplatek za křídlo „100“)', P.sirka100]);
    }
    if (st.vyska === 'miru' && st.hc) {
      var hs = E.rozmery[zarKey()].Hc;
      if (+st.hc > hs) L.push(['Vyšší dveře, Hc ' + st.hc + ' mm', P.vyssi]);
      else if (+st.hc < hs) L.push(['Nižší dveře, Hc ' + st.hc + ' mm', P.nizsi]);
    }
    if (st.dvou === 'kridlo') L.push(['Dvoubarevné dveře – křídlo', P.dvoubarevneKridlo]);
    if (st.dvou === 'komplet') L.push(['Dvoubarevné dveře – křídlo + rám', P.dvoubarevneKomplet]);
    var z = zasklTyp();
    if (z) {
      if (st.rc2) L.push(['Zasklení RC2', z[0]]);
      SKLO.forEach(function (s) { if (s[0] === st.sklo && typeof s[2] === 'number') L.push([s[1], z[s[2]]]); });
    }
    /* kování */
    var c = st.kovB === 'cerna' ? 1 : 0, cn = st.kovB === 'cerna' ? 'černá' : 'nikl / mosaz';
    if (st.kov === 'B') { if (c) L.push(['Černé základní kování (černé vložky)', cerneB(k)]); }
    else if (st.kov === 'A') {
      L.push(['Sada vložek pro otevírání A (' + cn + ')', E.sady.A[k][c]]);
      L.push(['Klika Erkado CERTUS RC2 (' + (c ? 'černá' : 'inox') + ')', E.certus.klika]);
    } else if (st.kov === 'C' || st.kov === 'D') {
      L.push(['Sada vložek pro otevírání ' + st.kov + ' (' + cn + ')', E.sady[st.kov][k][c]]);
      L.push(['Erkado CERTUS set madlo + štíhlá deska (' + (c ? 'černá' : 'inox') + ')', E.certus.setMadlo]);
      if (st.kov === 'D') L.push(['Erkado CERTUS sada horní desky (' + (c ? 'černá' : 'inox') + ')', E.certus.horniDeska]);
    } else if (madloMadlo()) {
      var MM = E.madloMadlo, sada = st.kov === 'E' ? 'A' : 'B';
      L.push(['Sada vložek pro otevírání ' + st.kov + ' (' + cn + ')', E.sady[sada][k][c]]);
      L.push(['Erkado CERTUS slim znak (dveře s madlem)', MM.slimZnak]);
      if (st.kov === 'F') L.push(['Tenké kování CERTUS pro horní zámek', MM.horniStitek]);
      L.push(['2× tlačítko k madlu + relé / stykač + elektroinstalace', MM.tlacitka2]);
      L.push(['Elektromechanický zámek', MM.elZamek]);
      L.push(['Napájení elektromechanického zámku', MM.napajeni]);
    }
    if (st.tridaC && st.kov !== 'X') {
      var V = E.vlozky, one = st.kov === 'A' || st.kov === 'C' || st.kov === 'E';
      L.push(['Vložky třídy C místo B' + (one ? ' (klíč–knoflík)' : ' (sada v systému 1 klíče)'), one ? V.knoflikC[k][c] - V.knoflikB[k][c] : V.sadaC[k][c] - V.sadaB[k][c]]);
    }
    var md = E.madla.filter(function (x) { return x.id === st.madlo; })[0];
    if (md) { var nM = madloMadlo() ? 2 : 1; L.push([(nM > 1 ? '2× ' : '') + md.nazev + ' ' + st.madloD + ' cm, ' + (st.madloB === 'cerna' ? 'černé' : 'inox'), nM * md.d[st.madloD][st.madloB === 'cerna' ? 1 : 0]]); }
    ['h', 'l', 'p'].forEach(function (s) { if (st.rozs[s]) L.push(['Rozšíření rámu ' + { h: 'nahoře', l: 'vlevo', p: 'vpravo' }[s] + ' (' + (k === 'h88' ? 30 : 28) + ' mm)', E.rozsireniRamu]); });
    svetliky().forEach(function (x) { L.push([x.label, x.cena]); if (x.pozn) info.push(x.pozn); });
    E.vybava.forEach(function (v) { if (st.vyb[v.id]) L.push([v.nazev, v.cena]); });
    var dvere = L.reduce(function (a, l) { return a + l[1]; }, 0);
    var lines = L.slice(), bez = dvere;
    if (st.montaz) { lines.push(['Montáž na klíč', E.montaz.cena]); bez += E.montaz.cena; }
    var rate = st.montaz ? E.montaz.dphMontaz : E.montaz.dph;
    var dph = Math.round(bez * rate / 100), sDph = bez + dph;
    var dop = null;
    if (doruc() && st.km !== '' && !isNaN(+st.km)) {
      var km = +st.km, fee = null;
      if (st.montaz) E.doprava.pasma.forEach(function (p) { if (fee === null && km <= p[0]) fee = p[1]; });
      else fee = Math.round(km * E.doprava.zaKm);
      dop = { km: km, fee: fee };
    }
    var total = sDph + (dop && dop.fee ? dop.fee : 0);
    /* upozornění */
    if (curHc() && curHc() >= E.ctvrtyPant[zarKey()]) info.push('Výška Hc ' + curHc() + ' mm: dveře mají 4 panty (od ' + E.ctvrtyPant[zarKey()] + ' mm, str. 60).');
    if (anySv()) info.push('Dveře se světlíky nemají zkoušku odolnosti proti vloupání RC2; boční a horní světlíky vyžadují při montáži odstranění skel (str. 62).');
    if (st.vyb.smart) info.push('S vložkou ErkadoSmart dveře nesplňují třídu RC2 (str. 71).');
    return { lines: lines, dvere: dvere, bez: bez, rate: rate, dph: dph, sDph: sDph, dop: dop, total: total, info: info, warn: warn, group: g };
  }

  /* ── vykreslení ── */
  function radio(name, val, checked, dis) { return '<input type="radio" name="' + name + '" value="' + esc(val) + '"' + (checked ? ' checked' : '') + (dis ? ' disabled' : '') + '>'; }
  function renderModels() {
    var q = ($('kfFind').value || '').trim().toUpperCase();
    $('kfModels').innerHTML = E.modely.filter(function (m) { return !q || m.nazev.indexOf(q) >= 0; }).map(function (m) {
      return '<label class="pq-opt kf-model">' + radio('model', m.id, m.id === st.model) +
        '<img src="' + IMG + 'typy/' + slug(m, m.typy[0]) + '.jpg" data-p="' + slug(m, m.typy[0]) + '|' + st.dekor + '" alt="" width="107" height="212">' +
        '<b>' + m.nazev + '</b><small>' + m.typy.length + (m.typy.length === 1 ? ' vzor' : m.typy.length < 5 ? ' vzory' : ' vzorů') + '</small>' +
        '<em>od ' + kc(minPrice(m)) + '</em></label>';
    }).join('') || '<p class="pq-help">Žádná řada tohoto názvu.</p>';
  }
  function renderTypes() {
    var m = M();
    $('kfTypeHint').textContent = 'ceny: THERMO 64 se základním kováním, bez DPH';
    $('kfTypes').innerHTML = m.typy.map(function (t) {
      var g = group(m, t, st.prov) || group(m, t, m.provedeni.filter(function (p) { return group(m, t, p); })[0]);
      return '<label class="pq-opt kf-type">' + radio('typ', t, t === st.typ) +
        '<img src="' + IMG + 'typy/' + slug(m, t) + '.jpg" data-p="' + slug(m, t) + '|' + st.dekor + '" alt="" width="107" height="212">' +
        '<span>' + typName(m, t) + '</span><em>' + (g ? kc(g.ceny.t64[1]) : '') + '</em></label>';
    }).join('');
    /* cena dveří v daném provedení (skupina ceníku + příplatek), rozdíl proti nejlevnějšímu */
    var provCena = function (p) { var g = provOk(m, st.typ, p) && group(m, st.typ, p); return g ? g.ceny[st.kon][1] + ((m.provPriplatek && m.provPriplatek[p]) || 0) : null; };
    var base = Math.min.apply(null, m.provedeni.map(provCena).filter(function (x) { return x != null; }));
    $('kfProv').innerHTML = m.provedeni.map(function (p) {
      var c = provCena(p), ok = c != null, pp = ok ? c - base : 0, d = PROV[p] || ['', '', ''];
      return '<label class="pq-opt kf-prov">' + radio('prov', p, p === st.prov, !ok) +
        (d[0] ? '<img src="' + IMG + 'provedeni/' + d[0] + '.jpg" alt="" width="120" height="96">' : '<i class="kf-prov-chip ' + (d[2] || '') + '"></i>') +
        '<span><b>' + esc(d[1] ? d[1].split('|')[0] : p) + '</b><small>' + esc(p) + (d[1] ? ' · ' + d[1].split('|')[1] : '') + '</small>' +
        (pp ? '<em>+ ' + kc(pp) + '</em>' : '<em class="z">bez příplatku</em>') + (!ok ? '<small class="no">ne pro tento vzor</small>' : '') + '</span></label>';
    }).join('');
    $('kfProvNote').hidden = m.provedeni.length < 2;
    $('kfModelNotes').innerHTML = m.pozn.length ? '<div class="pq-note"><ul>' + m.pozn.map(function (p) { return '<li>' + esc(p) + '</li>'; }).join('') + '</ul></div>' : '';
  }
  function renderStep2() {
    var m = M(), g = group(m, st.typ, st.prov), kv = st.kov === 'B' ? 1 : 0;
    $('kfKons').innerHTML = ['h88', 'h78', 't78', 't64'].map(function (k) {
      var K = KON[k], why = konBlock(k, st.smer, st.prov);
      return '<label class="pq-opt kf-kon"' + (why ? ' title="' + esc(why) + '"' : '') + '>' + radio('kon', k, k === st.kon, !!why) +
        '<span class="kf-tl" style="background:' + TLC[k] + '">' + K.tl + '</span><b>' + K.nazev + '</b>' +
        '<small>' + K.popis + '<br>Ud ' + K.ud + (K.udAlu ? ' (s hliníkovou zárubní ' + K.udAlu + ')' : '') + ' W/(m²K)</small>' +
        (why ? '<small style="color:#a12a1d">' + esc(why) + '</small>' : '<em>' + kc(g.ceny[k][kv]) + '</em>') + '</label>';
    }).join('');
    var K = KON[st.kon];
    $('kfZar').innerHTML = K.zarubenStd === 'hlinik'
      ? '<label class="pq-opt pq-chip">' + radio('zar', 'hlinik', true) + 'Hliníková s tepelnou přepážkou <small>ve standardu</small></label>'
      : '<label class="pq-opt pq-chip">' + radio('zar', 'ocel', st.zar === 'ocel') + 'Ocelová pozinkovaná <small>ve standardu</small></label>' +
        '<label class="pq-opt pq-chip">' + radio('zar', 'hlinik', st.zar === 'hlinik') + 'Hliníková s tepelnou přepážkou <em>+ ' + kc(K.alu) + '</em></label>' +
        '<label class="pq-opt pq-chip">' + radio('zar', 'rohova', st.zar === 'rohova', needsAlu()) + 'Rohová ocelová <small>příplatek upřesníme</small></label>' +
        (needsAlu() ? '<p class="pq-help" style="margin:.5rem 0 0;width:100%">Světlíky a madlo – madlo jdou jen s hliníkovou zárubní.</p>' : '');
    var opts = [['ven', 'P', 'Pravé, otevírané ven'], ['ven', 'L', 'Levé, otevírané ven'], ['dovnitr', 'P', 'Pravé, otevírané dovnitř'], ['dovnitr', 'L', 'Levé, otevírané dovnitř']];
    $('kfOpen').innerHTML = opts.map(function (o) {
      var on = st.smer === o[0] && st.strana === o[1];
      var anyKon = ['h88', 'h78', 't78', 't64'].some(function (k) { return !konBlock(k, o[0], st.prov); });
      return '<label class="pq-opt pq-tile">' + radio('open', o[0] + '|' + o[1], on, !anyKon) + doorSvg(o[0], o[1]) + '<span>' + o[2] + '</span></label>';
    }).join('');
    var on = [];
    if (st.smer === 'dovnitr' && (m.id === 'horn' || /PLAIN/.test(st.prov))) on.push('Při otevírání dovnitř nejde konstrukce THERMO HOT 88 a HOT 78.');
    if (st.smer === 'dovnitr' && odkapOk()) on.push('U dveří otevíraných dovnitř doporučujeme odkapávací kryt (krok 4).');
    $('kfOpenNote').innerHTML = on.length ? '<div class="pq-note">' + on.join('<br>') + '</div>' : '';
    var key = zarKey(), mn = minScFor(), mx = maxScFor();
    $('kfSirka').innerHTML = E.sirky.map(function (w) {
      var sc = stdSc(key, w), ok = sc >= mn && sc <= mx;
      return '<label class="pq-opt pq-chip">' + radio('sirka', w, st.sirka === w, !ok) + '„' + w + '“ <small>Sc ' + sc + ' mm</small>' + (w === '100' ? ' <em>+ ' + kc(E.priplatky.sirka100) + '</em>' : '') + '</label>';
    }).join('') + '<label class="pq-opt pq-chip">' + radio('sirka', 'miru', st.sirka === 'miru') + 'Na míru <em>+ ' + kc(E.priplatky.nestdSirka) + '</em></label>';
    $('kfScRow').hidden = st.sirka !== 'miru';
    $('kfSc').min = mn; $('kfSc').max = mx;
    var hs = E.rozmery[key].Hc;
    $('kfVyska').innerHTML = '<label class="pq-opt pq-chip">' + radio('vyska', 'std', st.vyska === 'std') + 'Standardní <small>Hc ' + hs + ' mm</small></label>' +
      '<label class="pq-opt pq-chip">' + radio('vyska', 'miru', st.vyska === 'miru', fixedHeight()) + 'Na míru <small>vyšší + ' + kc(E.priplatky.vyssi) + ', nižší + ' + kc(E.priplatky.nizsi) + '</small></label>';
    $('kfHcRow').hidden = st.vyska !== 'miru';
    var R = st.zar === 'rohova' ? E.rohova[st.kon] : E.rozmery[key], d = st.sirka !== 'miru' ? R.s[st.sirka] : null;
    $('kfDim').innerHTML = d
      ? 'Otvor ve stěně <b>' + d[0] + ' × ' + R.Ho + ' mm</b> · zárubeň Sc × Hc <b>' + d[1] + ' × ' + (st.vyska === 'miru' && st.hc ? st.hc : R.Hc) + ' mm</b> · průchod <b>' + d[2] + ' mm</b> <small>(str. 60–61)</small>'
      : 'U rozměru na míru se zadává celková šířka a výška zárubně (Sc, Hc). Výchozí rozměr se zmenšuje nebo zvětšuje po 10 mm (str. 60).';
    var dn = [];
    if (mn > 770) dn.push('Tento vzor je jen od šířky zárubně Sc ' + mn + ' mm.');
    if (mx < 1210) dn.push('Maximální šířka zárubně pro tento vzor je ' + mx + ' mm; výšku nelze měnit.');
    if (st.sirka === 'miru' && st.sc && (+st.sc < mn || +st.sc > mx || +st.sc % 10)) dn.push('<b>Šířka ' + esc(st.sc) + ' mm nejde: zadejte ' + mn + '–' + mx + ' mm po 10 mm.</b>');
    if (st.vyska === 'miru' && st.hc && (+st.hc < 1800 || +st.hc > 2480 || +st.hc % 10)) dn.push('<b>Výška ' + esc(st.hc) + ' mm nejde: zadejte 1800–2480 mm po 10 mm.</b>');
    if (oneLock()) dn.push('Dveře s výškou Hc pod 1984 mm mají jen jeden zámek (bez horního zámku).');
    $('kfDimNote').innerHTML = dn.length ? '<div class="pq-note warn">' + dn.join('<br>') + '</div>' : '';
  }
  function swatch(name, id, on) {
    var d = DEK[id];
    return '<label class="pq-opt pq-sw">' + radio(name, id, on) + '<img src="' + IMG + 'dekory/' + id + '.jpg" alt="" loading="lazy" width="96" height="96"><span>' + d[1] + '</span></label>';
  }
  function renderStep3() {
    var P = E.priplatky;
    $('kfDvou').innerHTML = [['ne', 'Jednobarevné', ''], ['kridlo', 'Jiný dekor zevnitř křídla', P.dvoubarevneKridlo], ['komplet', 'Jiný dekor zevnitř křídla i rámu', P.dvoubarevneKomplet]].map(function (o) {
      return '<label class="pq-opt pq-chip">' + radio('dvou', o[0], st.dvou === o[0]) + o[1] + (o[2] ? ' <em>+ ' + kc(o[2]) + '</em>' : '') + '</label>';
    }).join('');
    $('kfDekor2Wrap').hidden = st.dvou === 'ne';
    if (st.dvou !== 'ne') $('kfDekor2').innerHTML = E.dekory.map(function (x) { return swatch('dekor2', x[0], x[0] === st.dekor2); }).join('');
    var z = zasklTyp();
    $('kfSkloBlock').hidden = !z;
    if (z) {
      $('kfSklo').innerHTML = SKLO.map(function (s) {
        return '<label class="pq-opt pq-chip">' + radio('sklo', s[0], st.sklo === s[0]) + s[1] + (typeof s[2] === 'number' ? ' <em>+ ' + kc(z[s[2]]) + '</em>' : ' <small>' + s[2] + '</small>') + '</label>';
      }).join('');
      $('kfRc2').innerHTML = '<label class="pq-check"><input type="checkbox" id="kfRc2c"' + (st.rc2 ? ' checked' : '') + '><span><b>Zasklení RC2</b><small>Prosklené dveře musí mít soustavu skel RC2, aby byly klasifikovány jako RC2 (str. 12).</small></span><em>+ ' + kc(z[0]) + '</em></label>' +
        '<label class="pq-f" style="margin:1rem 0 0;max-width:320px">Odstín skla (bez příplatku)<select id="kfOdstin">' + E.skla.map(function (o) { return '<option' + (o === st.odstin ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select><small>Dostupnost odstínu pro vzor ověříme (str. 4, 62).</small></label>';
    }
    renderSv();
  }
  function renderSv() {
    var S = E.svetliky, z = svSize(), ok = KON[st.kon].zarubenStd === 'hlinik' || true;
    var chk = function (id, on, t, sub) { return '<label class="pq-check"><input type="checkbox" data-sv="' + id + '"' + (on ? ' checked' : '') + '><span><b>' + t + '</b><small>' + sub + '</small></span></label>'; };
    var h = chk('h', st.sv.h, 'Horní světlík', 'Šířka = zárubeň + boční světlíky (' + (z.sc + z.l + z.p) + ' mm), výška 200–500 mm.') +
      (st.sv.h ? '<div class="kf-row"><label class="pq-f" style="max-width:220px">Výška horního světlíku (mm)<input type="number" id="kfSvHv" min="200" max="500" step="10" value="' + esc(st.sv.hv) + '"><small>Ceník: 200, 300, 400, 500 mm</small></label></div>' : '') +
      chk('l', st.sv.l, 'Boční světlík vlevo', 'Výška podle dveří (Hc ' + z.hc + ' mm), šířka 200–800 mm.') +
      (st.sv.l ? '<div class="kf-row"><label class="pq-f" style="max-width:220px">Šířka levého světlíku (mm)<input type="number" id="kfSvLw" min="200" max="800" step="10" value="' + esc(st.sv.lw) + '"><small>Ceník po 100 mm</small></label></div>' : '') +
      chk('p', st.sv.p, 'Boční světlík vpravo', 'Výška podle dveří (Hc ' + z.hc + ' mm), šířka 200–800 mm.') +
      (st.sv.p ? '<div class="kf-row"><label class="pq-f" style="max-width:220px">Šířka pravého světlíku (mm)<input type="number" id="kfSvPw" min="200" max="800" step="10" value="' + esc(st.sv.pw) + '"><small>Ceník po 100 mm</small></label></div>' : '');
    if (anySv()) h += '<div class="kf-sub">Sklo ve světlících</div><div class="pq-chips">' + [['std', 'Standardní', ''], ['p4', 'Bezpečnostní P4', S.m2.p4], ['zrcadlo', 'Polopropustné zrcadlo', S.m2.zrcadlo], ['bila', 'Matné bílé / průhledné', S.m2.bila], ['seda', 'Matné šedé / průhledné', S.m2.seda]].map(function (o) {
      return '<label class="pq-opt pq-chip">' + radio('svSklo', o[0], st.sv.sklo === o[0]) + o[1] + (o[2] ? ' <em>+ ' + kc(o[2]) + '/m²</em>' : '') + '</label>';
    }).join('') + '</div><div class="pq-note">Světlíky jen s hliníkovou zárubní (zapne se sama). Cena je za nejbližší vyšší rozměr z ceníku, u nestandardního rozměru + 665 Kč. Dveře se světlíky nemají zkoušku RC2. Rozměry jsou vně rámu (str. 62–63).</div>';
    $('kfSv').innerHTML = h;
  }
  function renderStep4() {
    var k = st.kon, c = st.kovB === 'cerna' ? 1 : 0, ol = oneLock();
    $('kfKov').innerHTML = ['B', 'A', 'C', 'D', 'E', 'F', 'X'].map(function (id) {
      var dis = ol && (id === 'B' || id === 'D' || id === 'F'), price, MM = E.madloMadlo;
      if (id === 'B') price = '<em class="free">v ceně kompletu</em>';
      else if (id === 'A') price = '<em>bez kování + ' + kc(E.sady.A[k][c] + E.certus.klika) + '</em>';
      else if (id === 'C') price = '<em>bez kování + ' + kc(E.sady.C[k][c] + E.certus.setMadlo) + ' + madlo</em>';
      else if (id === 'D') price = '<em>bez kování + ' + kc(E.sady.D[k][c] + E.certus.setMadlo + E.certus.horniDeska) + ' + madlo</em>';
      else if (id === 'E') price = '<em>bez kování + ' + kc(E.sady.A[k][c] + MM.slimZnak + MM.tlacitka2 + MM.elZamek + MM.napajeni) + ' + 2 madla + samozavírač</em>';
      else if (id === 'F') price = '<em>bez kování + ' + kc(E.sady.B[k][c] + MM.slimZnak + MM.horniStitek + MM.tlacitka2 + MM.elZamek + MM.napajeni) + ' + 2 madla + samozavírač</em>';
      else price = '<em class="free">komplet bez kování</em>';
      return '<label class="pq-opt pq-tile">' + radio('kov', id, st.kov === id, dis) + kovSvg(id) + '<span>' + KOV[id].n + '</span>' + price + '<small>' + KOV[id].d + '</small></label>';
    }).join('');
    $('kfKovBarva').innerHTML = st.kov === 'X' ? '<p class="pq-help" style="margin:0">Bez kování.</p>' :
      '<label class="pq-opt pq-chip">' + radio('kovB', 'nikl', st.kovB === 'nikl') + 'Inox, vložky nikl / mosaz</label>' +
      '<label class="pq-opt pq-chip">' + radio('kovB', 'cerna', st.kovB === 'cerna') + 'Černé' + (st.kov === 'B' ? ' <em>+ ' + kc(cerneB(st.kon)) + '</em>' : '') + '</label>';
    var V = E.vlozky, one = st.kov === 'A' || st.kov === 'C' || st.kov === 'E';
    var dC = one ? V.knoflikC[k][c] - V.knoflikB[k][c] : V.sadaC[k][c] - V.sadaB[k][c];
    $('kfTrida').innerHTML = st.kov === 'X' ? '' : '<label class="pq-opt pq-chip">' + radio('trida', 'B', !st.tridaC) + 'Třída B <small>ve standardu, splňuje RC2</small></label>' +
      '<label class="pq-opt pq-chip">' + radio('trida', 'C', st.tridaC) + 'Třída C <em>+ ' + kc(dC) + '</em> <small>vyšší ochrana</small></label>';
    $('kfTrida').previousElementSibling.hidden = st.kov === 'X';
    var kn = [];
    if (st.kov !== 'B' && st.kov !== 'X') kn.push('Komplet bez kování (cena *) + díly podle ceníku příslušenství str. 65–69.');
    if (st.kov === 'X') kn.push('Cena kompletu nezahrnuje kování (kliky, madla, vložky).');
    if (ol) kn.push('U výšky Hc pod 1984 mm jen jeden zámek, varianty s horním zámkem nejdou.');
    var m = M();
    if ((st.kov === 'C' || st.kov === 'D') && /^(lutter|biene|kella)$/.test(m.id) && st.typ <= (m.id === 'kella' ? 3 : 2)) kn.push('U ' + typName(m, st.typ) + ' platí omezení při výběru madla (entablace), ověří prodejce.');
    if (madloMadlo()) kn.push('Madlo – madlo: madla jsou z obou stran (2 ks), samozavírač je nutný (přidá se ve výbavě). Hliníková zárubeň se zapne sama.');
    if ((st.kov === 'C' || st.kov === 'D' || madloMadlo()) && st.madlo === 'none') kn.push('<b>U kování s madlem vyberte madlo níže.</b>');
    $('kfKovNote').innerHTML = kn.length ? '<div class="pq-note' + (st.madlo === 'none' && (st.kov === 'C' || st.kov === 'D' || madloMadlo()) ? ' warn' : '') + '">' + kn.join('<br>') + '</div>' : '';
    var md = E.madla.filter(function (x) { return x.id === st.madlo; })[0], bi = st.madloB === 'cerna' ? 1 : 0;
    $('kfMadloD').previousElementSibling.hidden = $('kfMadloB').previousElementSibling.hidden = !md;
    $('kfMadloD').hidden = $('kfMadloB').hidden = !md;
    {
      $('kfMadlo').innerHTML = '<label class="pq-opt pq-chip">' + radio('madlo', 'none', !md) + 'Bez madla</label>' + E.madla.map(function (x) {
        var dd = x.d[st.madloD] ? st.madloD : Object.keys(x.d).sort(function (a, b) { return b - a; })[0];
        return '<label class="pq-opt pq-chip">' + radio('madlo', x.id, x.id === st.madlo) + x.nazev + ' <em>od ' + kc(Math.min.apply(null, Object.keys(x.d).map(function (l) { return x.d[l][bi]; }))) + '</em></label>';
      }).join('');
      if (md) $('kfMadloD').innerHTML = Object.keys(md.d).sort(function (a, b) { return b - a; }).map(function (l) {
        return '<label class="pq-opt pq-chip">' + radio('madloD', l, l === st.madloD) + l + ' cm <em>' + kc(md.d[l][bi]) + '</em></label>';
      }).join('');
      $('kfMadloB').innerHTML = '<label class="pq-opt pq-chip">' + radio('madloB', 'inox', st.madloB === 'inox') + 'Inox (nerez)</label><label class="pq-opt pq-chip">' + radio('madloB', 'cerna', st.madloB === 'cerna') + 'Černé STR</label>';
    }
    $('kfVyb').innerHTML = E.vybava.map(function (v) {
      var why = '';
      if (v.jenDovnitr && st.smer !== 'dovnitr') why = 'jen u dveří otevíraných dovnitř';
      else if (v.id === 'odkap' && !odkapOk()) why = 'pro tento vzor není';
      else if (v.jenAlu && !isAlu()) why = 'jen s hliníkovou zárubní';
      else if (v.zpusoby && v.zpusoby.indexOf(st.kov) < 0) why = 'jen pro ' + v.zpusoby.map(function (z) { return KOV[z].n.toLowerCase(); }).join(' / ');
      if (madloMadlo() && v.id === 'elektro') why = 'u madla – madla je už v kování';
      var lock = v.id === 'samozavirac' && (st.vyb.elektro || madloMadlo());
      return '<label class="pq-check' + (why ? ' off' : '') + '"><input type="checkbox" data-vyb="' + v.id + '"' + (st.vyb[v.id] ? ' checked' : '') + (why || lock ? ' disabled' : '') + '><span><b>' + v.nazev + '</b><small>' + (why ? 'Nejde: ' + why + '. ' : '') + (lock ? 'Nutný k elektromechanickému zámku. ' : '') + (v.info ? esc(v.info) + ' ' : '') + 'Str. ' + v.str + '.</small></span><em>+ ' + kc(v.cena) + '</em></label>';
    }).join('') + '<div class="kf-sub" style="margin-top:1rem">Rozšíření rámu <small style="font-weight:500;color:var(--text-muted)">' + (st.kon === 'h88' ? '30' : '28') + ' mm, ' + kc(E.rozsireniRamu) + ' za kus (str. 70)</small></div>' +
      [['h', 'Nahoře'], ['l', 'Vlevo'], ['p', 'Vpravo']].map(function (o) { return '<label class="pq-check"><input type="checkbox" data-rozs="' + o[0] + '"' + (st.rozs[o[0]] ? ' checked' : '') + '><span><b>Rozšíření rámu ' + o[1].toLowerCase() + '</b></span><em>+ ' + kc(E.rozsireniRamu) + '</em></label>'; }).join('');
  }
  /* doprava se počítá s montáží, nebo bez montáže při volbě „doprava na adresu“ */
  function doruc() { return st.montaz || st.odber === 'doprava'; }
  function dopVal(r) {
    if (!r.dop) return st.montaz ? 'do 40 km zdarma' : 'spočítáme z adresy';
    return r.dop.fee === null ? 'upřesníme' : r.dop.fee ? kc(r.dop.fee) : 'zdarma';
  }
  function dopLabel(r) { return 'Doprava' + (r.dop ? ' ' + String(r.dop.km).replace('.', ',') + ' km' : '') + (st.montaz ? '' : ' (' + E.doprava.zaKm + ' Kč/km s DPH)'); }
  function renderStep5(r) {
    $('kfMontaz').innerHTML = '<label class="pq-opt pq-chip">' + radio('montaz', '0', !st.montaz) + 'Bez montáže <small>DPH 21 %</small></label>' +
      '<label class="pq-opt pq-chip">' + radio('montaz', '1', st.montaz) + 'S montáží na klíč <em>+ ' + kc(E.montaz.cena) + ' bez DPH</em> <small>DPH 12 %</small></label>';
    $('kfOdber').hidden = st.montaz;
    $('kfOdber').innerHTML = '<label class="pq-opt pq-chip">' + radio('odber', 'odber', st.odber === 'odber') + 'Osobní odběr <small>vzorkovna Most, zdarma</small></label>' +
      '<label class="pq-opt pq-chip">' + radio('odber', 'doprava', st.odber === 'doprava') + 'Doprava na adresu <em>' + E.doprava.zaKm + ' Kč/km s DPH</em> <small>z Mostu, jen cesta tam</small></label>';
    $('kfAddrWrap').hidden = !doruc();
    $('kfAdrL').firstChild.nodeValue = st.montaz ? 'Adresa montáže' : 'Adresa doručení';
    var tm = $('kfTripMsg');
    if (doruc() && r.dop) {
      tm.hidden = false; tm.className = 'pq-trip ok';
      tm.innerHTML = '🚚 ' + String(r.dop.km).replace('.', ',') + ' km od vzorkovny – doprava ' + (r.dop.fee === null ? '<b>upřesníme</b> (nad 80 km)' : r.dop.fee ? '<b>' + kc(r.dop.fee) + '</b>' : '<b>zdarma</b>') +
        (!st.montaz ? ' <small>(' + String(r.dop.km).replace('.', ',') + ' km × ' + E.doprava.zaKm + ' Kč s DPH)</small>' : '') + (st.tripMsg ? ' <small>' + esc(st.tripMsg) + '</small>' : '');
    } else if (doruc() && st.tripMsg) { tm.hidden = false; tm.className = 'pq-trip'; tm.textContent = st.tripMsg; }
    else tm.hidden = true;
    var m = M(), rows = [
      ['Dveře', 'ERKADO ' + typName(m, st.typ) + ' · ' + st.prov],
      ['Konstrukce', KON[st.kon].nazev + ', ' + zarText() + ' zárubeň'],
      ['Otevírání', (st.strana === 'P' ? 'pravé' : 'levé') + ', ' + (st.smer === 'ven' ? 'ven' : 'dovnitř')],
      ['Rozměr', sizeText()],
      ['Dekor', DEK[st.dekor][1] + (st.dvou !== 'ne' ? ', zevnitř ' + DEK[st.dekor2][1] + (st.dvou === 'komplet' ? ' (křídlo i rám)' : ' (křídlo)') : '')],
      ['Zasklení', zasklTyp() ? SKLO.filter(function (s) { return s[0] === st.sklo; })[0][1] + (st.rc2 ? ', RC2' : '') : 'bez zasklení / dle vzoru'],
      ['Kování', kovText()],
      ['Madlo', madloText()],
      ['Světlíky', svText()],
      ['Výbava', E.vybava.filter(function (v) { return st.vyb[v.id]; }).map(function (v) { return v.nazev; }).join(', ') || '—'],
      ['Montáž', st.montaz ? 'ano, na klíč' + (r.dop ? ' · ' + String(r.dop.km).replace('.', ',') + ' km' : '') : 'ne, ' + (st.odber === 'doprava' ? 'doprava na adresu' + (r.dop ? ' · ' + String(r.dop.km).replace('.', ',') + ' km' : '') : 'osobní odběr ve vzorkovně')]
    ];
    $('kfReview').innerHTML = rows.map(function (x) { return '<dt>' + x[0] + '</dt><dd>' + esc(x[1]) + '</dd>'; }).join('');
  }
  function zarText() { return st.zar === 'rohova' ? 'rohová ocelová' : isAlu() ? 'hliníková' : 'ocelová'; }
  function svText() {
    if (!anySv()) return 'bez světlíků';
    var z = svSize(), a = [];
    if (st.sv.l) a.push('vlevo ' + z.l + ' mm'); if (st.sv.p) a.push('vpravo ' + z.p + ' mm'); if (st.sv.h) a.push('nahoře výška ' + z.hv + ' mm');
    return a.join(', ') + (st.sv.sklo !== 'std' ? ', sklo ' + { p4: 'P4', zrcadlo: 'polopropustné zrcadlo', bila: 'matné bílé', seda: 'matné šedé' }[st.sv.sklo] : '');
  }
  function sizeText() {
    var key = zarKey(), R = st.zar === 'rohova' ? E.rohova[st.kon] : E.rozmery[key];
    var w = st.sirka === 'miru' ? 'na míru, Sc ' + (st.sc || '?') + ' mm' : '„' + st.sirka + '“ (Sc ' + R.s[st.sirka][1] + ' mm, otvor ' + R.s[st.sirka][0] + ' mm)';
    var h = st.vyska === 'miru' ? 'Hc ' + (st.hc || '?') + ' mm' : 'standardní výška Hc ' + R.Hc + ' mm';
    return w + ', ' + h;
  }
  function kovText() {
    if (st.kov === 'X') return 'bez kování';
    var t = KOV[st.kov].n + ', ' + (st.kovB === 'cerna' ? 'černé' : 'inox / nikl') + ', vložky třídy ' + (st.tridaC ? 'C' : 'B');
    return t;
  }
  function madloText() {
    var md = E.madla.filter(function (x) { return x.id === st.madlo; })[0];
    return md ? (madloMadlo() ? '2× ' : '') + md.nazev + ' ' + st.madloD + ' cm, ' + (st.madloB === 'cerna' ? 'černé' : 'inox') : 'bez madla';
  }
  function finalLights() {
    var z = svSize(), wrap = $('kfFinSv'); if (!wrap) return;
    var H = 340, sc = H / z.hc, mir = st.strana === 'L';
    var L = st.sv[mir ? 'p' : 'l'] ? (mir ? z.p : z.l) : 0, P = st.sv[mir ? 'l' : 'p'] ? (mir ? z.l : z.p) : 0; // pohled zvenku: zrcadlí se jen dveře
    L = st.sv.l ? z.l : 0; P = st.sv.p ? z.p : 0;
    wrap.style.setProperty('--svl', Math.round(L * sc) + 'px'); wrap.style.setProperty('--svp', Math.round(P * sc) + 'px');
    wrap.style.setProperty('--svh', st.sv.h ? Math.round(z.hv * sc) + 'px' : '0px');
    wrap.className = 'kf-svwrap' + (st.sv.h ? ' h' : '') + (L ? ' l' : '') + (P ? ' p' : '');
  }
  function renderSummary(r) {
    var m = M(), sl = slug(m, st.typ), mir = st.strana === 'L', d2 = st.dvou !== 'ne' ? st.dekor2 : st.dekor;
    $('kfPImg').setAttribute('data-p', sl + '|' + st.dekor); $('kfPImg').className = mir ? 'mir' : ''; $('kfPImg').alt = typName(m, st.typ);
    $('kfMImg').setAttribute('data-p', sl + '|' + st.dekor); $('kfMImg').className = mir ? 'mir' : '';
    $('kfFinOut').setAttribute('data-p', sl + '|' + st.dekor); $('kfFinOut').className = mir ? 'mir' : '';
    $('kfFinIn').setAttribute('data-p', sl + '|' + d2); $('kfFinIn').className = mir ? '' : 'mir';
    finalLights();
    $('kfFinOutT').textContent = DEK[st.dekor][1]; $('kfFinInT').textContent = DEK[d2][1] + (st.dvou === 'ne' ? '' : ' (dvoubarevné)');
    $('kfPDek').src = IMG + 'dekory/' + st.dekor + '.jpg'; $('kfPDekName').textContent = DEK[st.dekor][1];
    $('kfPCap').textContent = 'Orientační náhled zvenku: dekor přenesený na fotku vzoru z katalogu' + (mir ? ', zrcadlově pro levé dveře.' : '.') + ' Skutečný odstín ověřte na vzorníku ve vzorkovně.';
    $('kfSName').textContent = 'ERKADO ' + typName(m, st.typ);
    $('kfMName').textContent = 'ERKADO ' + typName(m, st.typ);
    var sub = KON[st.kon].nazev + ' · ' + st.prov + ' · ' + DEK[st.dekor][1];
    $('kfSSub').textContent = sub; $('kfMSub').textContent = sub;
    var li = r.lines.map(function (l) { return '<li><span>' + esc(l[0]) + '</span><b>' + (l[1] ? kc(l[1]) : 'upřesníme') + '</b></li>'; });
    li.push('<li class="sub"><span>Cena bez DPH</span><b>' + kc(r.bez) + '</b></li>');
    li.push('<li><span>DPH ' + r.rate + ' %</span><b>' + kc(r.dph) + '</b></li>');
    if (doruc()) li.push('<li><span>' + esc(dopLabel(r)) + '</span><b>' + dopVal(r) + '</b></li>');
    $('kfSLines').innerHTML = li.join('');
    $('kfSTotal').textContent = kc(r.total);
    $('kfSLabel').textContent = 'Celkem s ' + r.rate + ' % DPH';
    $('kfSNote').innerHTML = (st.montaz ? 'S montáží na klíč: DPH 12 % z celé zakázky (bydlení). ' : (st.odber === 'doprava' ? 'Bez montáže: DPH 21 %, doprava z Mostu ' + E.doprava.zaKm + ' Kč/km s DPH (jen cesta tam). ' : 'Bez montáže: DPH 21 %, odběr ve vzorkovně KK SYSTEM, Obchodní 36, Most. ')) +
      'Ceny dveří podle katalogu ERKADO 2026 (bez DPH). Orientační cena, závazná je nabídka po zaměření.' +
      (r.info.length ? '<br>' + r.info.map(esc).join('<br>') : '');
  }
  function render() {
    normalize();
    var r = calc();
    if (st.step === 1) {
      renderModels(); renderTypes();
      $('kfDekor').innerHTML = E.dekory.map(function (d) { return swatch('dekor', d[0], d[0] === st.dekor); }).join('');
      $('kfDekorNote').innerHTML = DEK[st.dekor][2] ? '<div class="pq-note">' + esc(DEK[st.dekor][2]) + '</div>' : '';
    }
    if (st.step === 2) renderStep2();
    if (st.step === 3) renderStep3();
    if (st.step === 4) renderStep4();
    if (st.step === 5) renderStep5(r);
    renderSummary(r);
    paintAll();
    document.querySelectorAll('[data-step]').forEach(function (el) { el.hidden = +el.getAttribute('data-step') !== st.step; });
    document.querySelectorAll('#kfSteps li').forEach(function (li) { var s = +li.getAttribute('data-s'); li.className = s === st.step ? 'on' : s < st.step ? 'done' : ''; });
    $('kfPrev').style.visibility = st.step === 1 ? 'hidden' : 'visible';
    $('kfNext').style.visibility = st.step === 5 ? 'hidden' : 'visible';
    return r;
  }

  /* ── obrázky (kreslené) ── */
  function doorSvg(smer, strana) {
    var L = strana === 'L', x0 = 46, w = 58, hx = L ? x0 : x0 + w, sw = L ? 1 : -1;
    var arc = smer === 'ven'
      ? '<path d="M' + hx + ' 70 Q' + (hx + sw * -6) + ' 90 ' + (hx + sw * -26) + ' 96" fill="none" stroke="#c2410c" stroke-width="2" marker-end="url(#ah' + smer + strana + ')"/>'
      : '<path d="M' + hx + ' 70 Q' + (hx + sw * -10) + ' 60 ' + (hx + sw * -30) + ' 62" fill="none" stroke="#c2410c" stroke-width="2" marker-end="url(#ah' + smer + strana + ')"/>';
    var leaf = smer === 'ven'
      ? '<polygon points="' + hx + ',10 ' + (hx + sw * -w * 0.82) + ',16 ' + (hx + sw * -w * 0.82) + ',64 ' + hx + ',70" fill="#3f5a4b"/>'
      : '<polygon points="' + hx + ',10 ' + (hx + sw * -w * 0.6) + ',6 ' + (hx + sw * -w * 0.6) + ',74 ' + hx + ',70" fill="#3f5a4b" opacity=".85"/>';
    return '<svg viewBox="0 0 150 100" aria-hidden="true"><defs><marker id="ah' + smer + strana + '" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto"><path d="M0 0L6 3L0 6z" fill="#c2410c"/></marker></defs>' +
      '<rect x="' + (x0 - 4) + '" y="6" width="' + (w + 8) + '" height="68" fill="none" stroke="#123d27" stroke-width="3"/>' + leaf +
      '<circle cx="' + hx + '" cy="22" r="2.2" fill="#c2410c"/><circle cx="' + hx + '" cy="58" r="2.2" fill="#c2410c"/>' + arc + '</svg>';
  }
  function kovSvg(id) {
    var klika = function (x, dir) { return '<rect x="' + (x - 3) + '" y="40" width="6" height="16" rx="2" fill="#123d27"/><rect x="' + (dir > 0 ? x : x - 18) + '" y="42" width="18" height="5" rx="2.5" fill="#123d27"/>'; };
    var madlo = '<rect x="96" y="12" width="5" height="62" rx="2" fill="#123d27"/><rect x="88" y="16" width="9" height="3" fill="#123d27"/><rect x="88" y="67" width="9" height="3" fill="#123d27"/>';
    var zamek = '<rect x="80" y="24" width="7" height="10" rx="2" fill="#5f7568"/><circle cx="83.5" cy="29" r="1.6" fill="#fff"/>';
    var vl = '<rect x="80" y="58" width="7" height="10" rx="2" fill="#5f7568"/><circle cx="83.5" cy="63" r="1.6" fill="#fff"/>';
    var body = '<rect x="48" y="6" width="56" height="74" rx="2" fill="#eef6f1" stroke="#123d27" stroke-width="2"/>';
    var inner = id === 'B' ? klika(84, 1) + zamek + vl : id === 'A' ? klika(84, 1) + vl : id === 'C' ? madlo + vl : id === 'D' ? madlo + zamek + vl : '<line x1="62" y1="30" x2="92" y2="60" stroke="#a12a1d" stroke-width="3"/><line x1="92" y1="30" x2="62" y2="60" stroke="#a12a1d" stroke-width="3"/>';
    return '<svg viewBox="0 0 150 86" aria-hidden="true">' + body + inner + '</svg>';
  }

  /* ── doprava (Mapy.com – funguje na doménách webu) ── */
  function mapy(path, params) {
    var q = Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(MAPY + path + '?' + q + '&apikey=' + MAPY_KEY).then(function (r) { if (!r.ok) throw new Error('mapy ' + r.status); return r.json(); });
  }
  function trip() {
    var a = $('kfAdr').value.trim();
    if (!a) { st.tripMsg = 'Napište adresu, nebo zadejte vzdálenost ručně.'; render(); return; }
    $('kfTrip').disabled = true; $('kfTrip').textContent = 'Počítám…';
    Promise.all([
      mapy('geocode', { query: E.doprava.odkud, lang: 'cs', limit: 1, type: 'regional.address' }),
      mapy('geocode', { query: a, lang: 'cs', limit: 1, type: 'regional.address,regional.municipality', locality: 'cz' })
    ]).then(function (g) {
      var s = g[0].items[0].position, e = g[1].items[0].position;
      return mapy('routing/route', { start: s.lon + ',' + s.lat, end: e.lon + ',' + e.lat, routeType: 'car_fast', format: 'polyline' });
    }).then(function (j) {
      st.km = String(Math.round(j.length / 100) / 10); $('kfKm').value = st.km; st.tripMsg = '';
    }).catch(function () {
      st.tripMsg = 'Vzdálenost se nepodařilo spočítat (mapy fungují až na webu bezpecne-dvere.cz). Zadejte km ručně.';
    }).then(function () { $('kfTrip').disabled = false; $('kfTrip').textContent = 'Spočítat dopravu'; render(); });
  }

  /* ── data pro kartotéku (stejná pole jako poptávka z webu) ── */
  function ref() { var d = new Date(), p = function (n) { return ('0' + n).slice(-2); }; return 'KONF-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes()); }
  function collect() {
    var r = calc(), m = M(), jm = $('kfJmeno').value.trim().split(/\s+/);
    return {
      verze: 1, zdroj: 'web-konfigurator-erkado', typ: 'poptavka', kategorie: 'dum', kategorie_nazev: 'Dveře do domu', cislo: st.ref || (st.ref = ref()), odeslano: new Date().toISOString(),
      model: 'ERKADO ' + typName(m, st.typ), model_kod: 'erkado-' + slug(m, st.typ), odstin: DEK[st.dekor][1], provedeni: st.prov,
      konfigurace: {
        rada: m.nazev, vzor: st.typ, provedeni: st.prov, konstrukce: KON[st.kon].nazev, zarubne: zarText(),
        otevirani: (st.strana === 'P' ? 'pravé' : 'levé') + ', ' + (st.smer === 'ven' ? 'ven' : 'dovnitř'),
        sirka: st.sirka, sc_mm: curSc() || null, hc_mm: curHc() || null, rozmer: sizeText(),
        dekor: DEK[st.dekor][1], dekor_zevnitr: st.dvou !== 'ne' ? DEK[st.dekor2][1] : '', dvoubarevne: st.dvou,
        zaskleni: zasklTyp() ? st.sklo : '', zaskleni_rc2: st.rc2, kovani: kovText(), madlo: madloText(), vlozky_trida: st.tridaC ? 'C' : 'B', odstin_skla: st.odstin, svetliky: svText(), rozsireni_ramu: Object.keys(st.rozs), vybava: Object.keys(st.vyb), katalog: E.katalog, strana_katalogu: m.str
      },
      polozky: r.lines.map(function (l) { return { co: l[0], volba: l[2] || '', priplatek: l[1] }; }),
      cena_bez_dph: r.bez, dph_sazba: r.rate, dph: r.dph, cena_s_dph: r.sDph, montaz: st.montaz, doprava: st.montaz ? 'montaz' : st.odber, vizualizace: !!vizRes,
      doprava_km: r.dop ? r.dop.km : null, doprava_cena: r.dop ? r.dop.fee : null, cena_celkem: r.total,
      jmeno: jm[0] || '', prijmeni: jm.slice(1).join(' '), telefon: $('kfTel').value.trim(), email: $('kfEmail').value.trim(),
      ulice: $('kfAdr').value.trim(), mesto: $('kfObec').value.trim(), poznamka: $('kfPozn').value.trim()
    };
  }

  /* ── PDF (stejný vzhled jako poptavka-pdf.js) ── */
  var GREEN = [18, 61, 39], ORANGE = [194, 65, 12], MUTED = [79, 106, 91], LINE = [220, 233, 225], PALE = [246, 250, 247];
  var pdfReady = null;
  function loadScript(src) { return new Promise(function (ok, ko) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); }); }
  function b64(buf) { var s = '', u = new Uint8Array(buf), i; for (i = 0; i < u.length; i += 0x8000) s += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000)); return btoa(s); }
  function preparePdf() {
    if (!pdfReady) pdfReady = (window.jspdf ? Promise.resolve() : loadScript('poptavka-pdf/jspdf.umd.min.js')).then(function () {
      return Promise.all(['Inter-Regular.ttf', 'Inter-Bold.ttf'].map(function (f) { return fetch('poptavka-pdf/' + f).then(function (r) { if (!r.ok) throw new Error(f); return r.arrayBuffer(); }).then(b64); }));
    });
    pdfReady.catch(function () { pdfReady = null; });
    return pdfReady;
  }
  function toJpeg(src, maxPx, mirror) {
    return new Promise(function (ok) {
      var img = new Image();
      img.onload = function () {
        var s = Math.min(1, maxPx / Math.max(img.naturalWidth, img.naturalHeight)), cv = document.createElement('canvas');
        cv.width = Math.round(img.naturalWidth * s); cv.height = Math.round(img.naturalHeight * s);
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
        if (mirror) { cx.translate(cv.width, 0); cx.scale(-1, 1); }
        cx.drawImage(img, 0, 0, cv.width, cv.height);
        try { ok({ data: cv.toDataURL('image/jpeg', 0.88), w: cv.width, h: cv.height }); } catch (e) { ok(null); }
      };
      img.onerror = function () { ok(null); };
      img.src = src;
    });
  }
  function buildPdf(d) {
    var r = calc(), m = M();
    return preparePdf().then(function (fonts) {
      return Promise.all([toJpeg('logo.jpg', 600), paint(slug(m, st.typ), st.dekor).then(function (u) { return toJpeg(u, 700, st.strana === 'L'); }), toJpeg(IMG + 'dekory/' + st.dekor + '.jpg', 300),
        st.dvou !== 'ne' ? toJpeg(IMG + 'dekory/' + st.dekor2 + '.jpg', 300) : Promise.resolve(null)]).then(function (im) {
        var doc = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        doc.addFileToVFS('Inter-Regular.ttf', fonts[0]); doc.addFont('Inter-Regular.ttf', 'Inter', 'normal');
        doc.addFileToVFS('Inter-Bold.ttf', fonts[1]); doc.addFont('Inter-Bold.ttf', 'Inter', 'bold');
        var W = 210, Mg = 14, y, page = 1, cw = W - 2 * Mg;
        var font = function (s, z, c) { doc.setFont('Inter', s); doc.setFontSize(z); doc.setTextColor.apply(doc, c || [15, 42, 28]); };
        doc.setFillColor.apply(doc, GREEN); doc.rect(0, 0, W, 30, 'F');
        if (im[0]) { var lh = 16, lw = lh * im[0].w / im[0].h; doc.addImage(im[0].data, 'JPEG', Mg, 7, lw, lh); }
        font('bold', 15, [255, 255, 255]); doc.text('Poptávka vchodových dveří ERKADO', W - Mg, 13, { align: 'right' });
        font('normal', 9.5, [210, 230, 218]); doc.text('č. ' + d.cislo + '  ·  ' + new Date(d.odeslano).toLocaleString('cs-CZ'), W - Mg, 19.5, { align: 'right' });
        doc.text('Konfigurátor dveří ERKADO · bezpecne-dvere.cz', W - Mg, 24.5, { align: 'right' });
        y = 40;
        function footer() { font('normal', 7.5, MUTED); doc.text('Bezpečné dveře · Zdeněk Kučera, IČ 64682676 · vzorkovna Obchodní 36, Most · +420 725 559 235 · chci@bezpecne-dvere.cz', W / 2, 289, { align: 'center' }); }
        function ensure(h) { if (y + h > 280) { footer(); doc.addPage(); page++; y = 18; } }
        function section(t) { ensure(16); font('bold', 9, ORANGE); doc.text(t.toUpperCase(), Mg, y); y += 2; doc.setDrawColor.apply(doc, LINE); doc.setLineWidth(0.3); doc.line(Mg, y, W - Mg, y); y += 5.5; }
        function pairs(list, x0, wl, wv) {
          var yy = y; list.forEach(function (p) { if (!p[1]) return; font('normal', 9, MUTED); doc.text(p[0], x0, yy); font('bold', 9.5); var ls = doc.splitTextToSize(String(p[1]), wv); doc.text(ls, x0 + wl, yy); yy += Math.max(1, ls.length) * 4.4 + 1.5; }); return yy;
        }
        section('Zákazník');
        var yl = pairs([['Jméno', (d.jmeno + ' ' + d.prijmeni).trim() || '—'], ['Telefon', d.telefon || '—'], ['E-mail', d.email || '—']], Mg, 22, 66);
        var yr = pairs([['Montáž', d.montaz ? ([d.ulice, d.mesto].filter(Boolean).join(', ') || 'ano, adresa neuvedena') : st.odber === 'doprava' ? 'bez montáže, doprava na adresu' : 'bez montáže (osobní odběr)'], ['Adresa', !d.montaz && st.odber === 'doprava' ? ([d.ulice, d.mesto].filter(Boolean).join(', ') || 'neuvedena') : '']], 108, 22, 66);
        y = Math.max(yl, yr) + 3;
        section('Vybrané dveře');
        var top = y - 3, iw0 = 40, ih0 = 76;
        doc.setFillColor(255, 255, 255); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(Mg, top, iw0, ih0, 2, 2, 'FD');
        if (im[1]) { var rr = Math.min((iw0 - 4) / im[1].w, (ih0 - 4) / im[1].h), w1 = im[1].w * rr, h1 = im[1].h * rr; doc.addImage(im[1].data, 'JPEG', Mg + (iw0 - w1) / 2, top + (ih0 - h1) / 2, w1, h1); }
        var sy = top + ih0 + 3;
        if (im[2]) doc.addImage(im[2].data, 'JPEG', Mg, sy, 12, 12);
        font('normal', 7.5, MUTED); doc.text('Dekor zvenku', Mg + 14, sy + 4.5); font('bold', 8.5); doc.text(doc.splitTextToSize(DEK[st.dekor][1], iw0 - 14), Mg + 14, sy + 8.8);
        if (im[3]) { doc.addImage(im[3].data, 'JPEG', Mg, sy + 14, 12, 12); font('normal', 7.5, MUTED); doc.text('Zevnitř', Mg + 14, sy + 18.5); font('bold', 8.5); doc.text(doc.splitTextToSize(DEK[st.dekor2][1], iw0 - 14), Mg + 14, sy + 22.8); }
        var x1 = Mg + iw0 + 8;
        font('bold', 16, GREEN); doc.text(d.model, x1, top + 5);
        y = top + 12;
        var c = d.konfigurace;
        var yd = pairs([['Provedení', c.provedeni], ['Konstrukce', c.konstrukce + ', ' + c.zarubne + ' zárubeň'], ['Otevírání', c.otevirani], ['Rozměr', c.rozmer],
          ['Dekor', c.dekor + (c.dekor_zevnitr ? ', zevnitř ' + c.dekor_zevnitr : '')], ['Zasklení', zasklTyp() ? SKLO.filter(function (s) { return s[0] === st.sklo; })[0][1] + (st.rc2 ? ', zasklení RC2' : '') : 'dle vzoru'],
          ['Kování', c.kovani], ['Madlo', c.madlo], ['Světlíky', anySv() ? c.svetliky : ''], ['Odstín skla', zasklTyp() && st.odstin !== E.skla[0] ? st.odstin : ''], ['Výbava', E.vybava.filter(function (v) { return st.vyb[v.id]; }).map(function (v) { return v.nazev; }).join(', ')], ['Katalog', E.katalog + ', str. ' + c.strana_katalogu]], x1, 24, W - Mg - x1 - 24);
        y = Math.max(yd, sy + (im[3] ? 30 : 16)) + 3;
        section('Orientační cena');
        var priceRow = function (l, v, b) { ensure(6); font(b ? 'bold' : 'normal', b ? 10 : 9, b ? [15, 42, 28] : MUTED); var ls = doc.splitTextToSize(l, cw - 34); doc.text(ls, Mg, y); doc.text(v, W - Mg, y, { align: 'right' }); y += ls.length * 4.3 + 1.1; };
        r.lines.forEach(function (l) { priceRow(l[0], (l[1] ? kc(l[1]) : 'upřesníme')); });
        doc.setDrawColor.apply(doc, LINE); doc.line(Mg, y - 2.6, W - Mg, y - 2.6); y += 1;
        priceRow('Cena bez DPH', kc(r.bez), true);
        priceRow('DPH ' + r.rate + ' %', kc(r.dph));
        if (doruc()) priceRow(dopLabel(r), dopVal(r));
        else priceRow('Osobní odběr ve vzorkovně (Obchodní 36, Most)', 'zdarma');
        ensure(16); y += 2;
        doc.setFillColor.apply(doc, PALE); doc.setDrawColor.apply(doc, LINE); doc.roundedRect(Mg, y - 4.5, cw, 12, 2, 2, 'FD');
        font('bold', 11, GREEN); doc.text('Celkem vč. ' + r.rate + ' % DPH', Mg + 4, y + 3);
        font('bold', 15, ORANGE); doc.text(kc(r.total), W - Mg - 4, y + 3.4, { align: 'right' });
        y += 12;
        font('normal', 8, MUTED);
        var nt = doc.splitTextToSize((d.montaz ? 'Cena s montáží na klíč (DPH 12 % z celé zakázky). ' : st.odber === 'doprava' ? 'Cena bez montáže, DPH 21 %, doprava z Mostu ' + E.doprava.zaKm + ' Kč/km s DPH (jen cesta tam). ' : 'Cena bez montáže, DPH 21 %, odběr ve vzorkovně KK SYSTEM, Obchodní 36, Most. ') +
          'Ceny dveří a příslušenství podle katalogu ERKADO Ocelové dveře 2026 (NETTO bez DPH). Orientační cena – závaznou nabídku pošleme po zaměření. ' + r.info.join(' '), cw);
        doc.text(nt, Mg, y); y += nt.length * 3.4 + 4;
        if (d.poznamka) { section('Poznámka'); font('normal', 9.5); var pl = doc.splitTextToSize(d.poznamka, cw); ensure(pl.length * 4.4); doc.text(pl, Mg, y); y += pl.length * 4.4 + 3; }
        if (vizRes) {
          footer(); doc.addPage(); page++; y = 18; section('Vizualizace na fotce zákazníka');
          var vp = doc.getImageProperties(vizRes.img), vr = Math.min(cw / vp.width, 235 / vp.height);
          doc.addImage(vizRes.img, 'JPEG', Mg + (cw - vp.width * vr) / 2, y, vp.width * vr, vp.height * vr); y += vp.height * vr + 5;
          font('normal', 8, MUTED); doc.text(doc.splitTextToSize('Ilustrativní náhled z aplikace Bezpečné dveře – dveře vložené do fotky zákazníka. Odlesky, stíny a přesné kování se neupravují.', cw), Mg, y);
        }
        footer();
        return doc.output('blob');
      });
    });
  }

  /* ── události ── */
  var form = $('kfForm');
  form.addEventListener('change', function (e) {
    var t = e.target, n = t.name, v = t.value;
    if (t.matches('[data-sv]')) { st.sv[t.getAttribute('data-sv')] = t.checked; }
    else if (t.matches('[data-rozs]')) { if (t.checked) st.rozs[t.getAttribute('data-rozs')] = true; else delete st.rozs[t.getAttribute('data-rozs')]; }
    else if (t.id === 'kfOdstin') st.odstin = t.value;
    else if (n === 'svSklo') st.sv.sklo = v;
    else if (n === 'trida') st.tridaC = v === 'C';
    else if (t.matches('[data-vyb]')) { if (t.checked) st.vyb[t.getAttribute('data-vyb')] = true; else delete st.vyb[t.getAttribute('data-vyb')]; }
    else if (t.id === 'kfRc2c') st.rc2 = t.checked;
    else if (n === 'model') { st.model = v; st.typ = MOD[v].typy[0]; st.prov = MOD[v].provedeni[0]; }
    else if (n === 'typ') st.typ = +v;
    else if (n === 'open') { st.smer = v.split('|')[0]; st.strana = v.split('|')[1]; }
    else if (n === 'montaz') st.montaz = v === '1';
    else if (n && n in st) st[n] = v;
    else return;
    render();
  });
  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t.id === 'kfSc') { st.sc = t.value; render(); }
    else if (t.id === 'kfHc') { st.hc = t.value; render(); }
    else if (t.id === 'kfSvHv' || t.id === 'kfSvLw' || t.id === 'kfSvPw') { st.sv[{ kfSvHv: 'hv', kfSvLw: 'lw', kfSvPw: 'pw' }[t.id]] = t.value; liveRender(t); }
    else if (t.id === 'kfKm') { st.km = t.value; st.tripMsg = ''; render(); }
    else if (t.id === 'kfFind') { renderModels(); paintAll(); }
  });
  /* překreslení bez ztráty kurzoru v poli */
  function liveRender(inp) { var id = inp.id, pos = inp.selectionStart; render(); var n = $(id); if (n && n !== inp) { n.focus(); try { n.setSelectionRange(pos, pos); } catch (e) {} } }
  form.addEventListener('submit', function (e) { e.preventDefault(); });
  function go(s) { st.step = Math.max(1, Math.min(5, s)); render(); var top = document.querySelector('.kf-wrap'); if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  $('kfNext').addEventListener('click', function () { go(st.step + 1); });
  $('kfPrev').addEventListener('click', function () { go(st.step - 1); });
  document.querySelectorAll('#kfSteps button').forEach(function (b) { b.addEventListener('click', function () { go(+b.parentNode.getAttribute('data-s')); }); });
  $('kfTrip').addEventListener('click', trip);
  $('kfAdr').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); trip(); } });
  var lastUrl = null;
  var LIVE = !!(window.BDForm && window.BDForm.send);
  if (!LIVE) { $('kfMode').textContent = 'Testovací verze · neodesílá se'; $('kfSendBtn').hidden = true; $('kfJsonBtn').hidden = false;
    $('kfMsg').textContent = 'Testovací verze: nic se neodesílá. PDF má stejný vzhled jako PDF poptávky dveří do bytu.'; }
  function showPdf(blob) {
    if (lastUrl) URL.revokeObjectURL(lastUrl);
    lastUrl = URL.createObjectURL(blob);
    $('kfPdfFrame').src = lastUrl; $('kfPdf').hidden = false; window.KF_LAST_PDF = blob;
  }
  $('kfPdfBtn').addEventListener('click', function () {
    var b = this, t0 = b.textContent; b.disabled = true; b.textContent = 'Připravuji PDF…';
    buildPdf(collect()).then(function (blob) { showPdf(blob); $('kfMsg').textContent = 'Náhled PDF je níže. Po odeslání ho dostaneme i s poptávkou.'; })
      .catch(function () { $('kfMsg').textContent = 'PDF se nepodařilo připravit.'; })
      .then(function () { b.disabled = false; b.textContent = t0; });
  });
  $('kfJsonBtn').addEventListener('click', function () { $('kfJsonPre').textContent = JSON.stringify(collect(), null, 2); $('kfJson').hidden = !$('kfJson').hidden; });

  /* ── odeslání do kartotéky (formulare.dvere.app → kartoteka.dvere.app) ── */
  var CC = 'chci@bezpecne-dvere.cz';
  function makeRef() {
    var t = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    return 'W' + t.getFullYear() + p(t.getMonth() + 1) + p(t.getDate()) + '-' + p(t.getHours()) + p(t.getMinutes()) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase();
  }
  function kc(n) { return n == null ? '' : Math.round(n).toLocaleString('cs-CZ') + ' Kč'; }
  function mailRows(d) {
    var k = d.konfigurace, rows = [
      ['Číslo poptávky', d.cislo], ['Zdroj', 'Konfigurátor ERKADO (web)'], ['Model', d.model], ['Řada / vzor', k.rada + ' ' + k.vzor + (k.provedeni ? ' – ' + k.provedeni : '')],
      ['Konstrukce', k.konstrukce], ['Zárubeň', k.zarubne], ['Otevírání', k.otevirani], ['Rozměr', k.rozmer], ['Dekor', k.dekor + (k.dekor_zevnitr ? ' / zevnitř ' + k.dekor_zevnitr : '')]
    ];
    if (k.zaskleni) rows.push(['Zasklení', k.zaskleni + (k.zaskleni_rc2 ? ' (RC2)' : '')]);
    if (k.svetliky) rows.push(['Světlíky', k.svetliky]);
    rows.push(['Kování', k.kovani], ['Madlo', k.madlo || '–'], ['Vložky', 'třída ' + k.vlozky_trida]);
    d.polozky.forEach(function (p) { rows.push(['· ' + p.co, (p.volba ? p.volba + ' – ' : '') + (p.priplatek ? kc(p.priplatek) : 'upřesníme')]); });
    rows.push(['Cena bez DPH', kc(d.cena_bez_dph)], ['DPH ' + d.dph_sazba + ' %', kc(d.dph)], ['Montáž', d.montaz ? 'ano' : 'ne'],
      ['Doprava', d.doprava === 'odber' ? 'osobní odběr ve vzorkovně' : (d.montaz ? 's montáží' : 'bez montáže, ' + E.doprava.zaKm + ' Kč/km s DPH') + ' – ' + (d.doprava_km == null ? 'km nespočítány' : d.doprava_km + ' km – ' + (d.doprava_cena == null ? 'upřesníme' : kc(d.doprava_cena)))],
      ['Cena celkem s DPH', kc(d.cena_celkem)], ['Jméno', (d.jmeno + ' ' + d.prijmeni).trim()], ['Telefon', d.telefon], ['E-mail', d.email],
      ['Adresa montáže', d.ulice], ['Obec', d.mesto], ['Poznámka', d.poznamka || '–'], ['Odesláno', new Date(d.odeslano).toLocaleString('cs-CZ')]);
    return rows;
  }
  function invalid() {
    var bad = [];
    function chk(el, ok) { el.classList.toggle('bad', !ok); if (!ok) bad.push(el); }
    chk($('kfJmeno'), $('kfJmeno').value.trim().length >= 2);
    var tel = $('kfTel').value.replace(/\s/g, ''), em = $('kfEmail').value.trim();
    var telOk = /^\+?\d{9,15}$/.test(tel), emOk = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em);
    chk($('kfTel'), telOk || (!tel && emOk)); chk($('kfEmail'), emOk || (!em && telOk));
    chk($('kfObec'), $('kfObec').value.trim().length >= 2);
    chk($('kfGdpr').closest('.pq-check'), $('kfGdpr').checked);
    if (!bad.length) return '';
    bad[0].focus && bad[0].focus();
    if (!$('kfGdpr').checked && bad.length === 1) return 'Pro odeslání prosím potvrďte souhlas se zpracováním osobních údajů.';
    return 'Doplňte prosím jméno, obec a telefon nebo e-mail (označená pole).';
  }
  $('kfSendBtn').addEventListener('click', function () {
    var b = this, msg = $('kfMsg'), done = $('kfDone');
    if ($('kfHoney').value) return;
    var err = invalid(); if (err) { msg.textContent = err; return; }
    if ((st.sirka === 'miru' && !curSc()) || (st.vyska === 'miru' && !curHc())) { msg.textContent = 'Doplňte prosím rozměr na míru (krok 2 – Konstrukce a rozměr).'; return; }
    b.disabled = true; b.textContent = 'Připravuji…'; done.hidden = true;
    var refP = st.cislo ? Promise.resolve(st.cislo) : window.BDForm.nextNumber('poptavka').then(function (c) { return c || makeRef(); });
    var d, pdf = null;
    refP.then(function (c) {
      st.cislo = c; st.ref = c; d = collect(); d.cislo = c;
      return buildPdf(d).catch(function () { return null; });
    }).then(function (blob) {
      pdf = blob; d.pdf = !!pdf; b.textContent = 'Odesílám…';
      var rows = mailRows(d), files = pdf ? [{ name: 'poptavka_pdf', file: 'Poptavka-' + d.cislo.replace(/[\s\/]+/g, '-') + '.pdf', blob: pdf }] : [];
      if (vizRes) { rows.push(['Vizualizace', 'fotka zákazníka s dveřmi v příloze']); files.push({ name: 'vizualizace', file: 'vizualizace-' + d.cislo.replace(/[\s\/]+/g, '-') + '.jpg', blob: dataBlob(vizRes.img) }); }
      var o = { typ: 'poptavka', predmet: 'Poptávka dveří ' + d.cislo + ' – ' + d.model + ' – ' + (d.prijmeni || d.jmeno) + ', ' + d.mesto,
        telo: window.BDForm.bodyFromFields(rows), reply_to: d.email, cislo: d.cislo, data_json: JSON.stringify(d), honey: $('kfHoney').value, files: files };
      return window.BDForm.send(o).catch(function () { o.files = []; d.poznamka_system = 'PDF se nepodařilo přiložit'; o.data_json = JSON.stringify(d); return window.BDForm.send(o); });
    }).then(function () {
      if (window.bdTrack) window.bdTrack('Lead', { content_name: 'Konfigurátor ' + d.model, value: d.cena_celkem || 0, currency: 'CZK' });
      done.className = 'kf-done'; done.innerHTML = '<b>Děkujeme, poptávka č. ' + esc(d.cislo) + ' dorazila.</b><br>Ozveme se obvykle do jednoho pracovního dne. Telefon: <a href="tel:+420725559235">+420 725 559 235</a>.' +
        (pdf ? '<br><a class="btn btn-outline" id="kfDonePdf" download="Poptavka-' + esc(d.cislo.replace(/[\s\/]+/g, '-')) + '.pdf">Stáhnout PDF poptávky</a>' : '');
      done.hidden = false; msg.textContent = '';
      if (pdf) { showPdf(pdf); $('kfDonePdf').href = lastUrl; }
      b.textContent = 'Odesláno ✓';
    }).catch(function () {
      done.className = 'kf-done err'; done.innerHTML = 'Odeslání se nepodařilo. Zkuste to prosím znovu, nebo nám zavolejte na <a href="tel:+420725559235">+420 725 559 235</a> či napište na <a href="mailto:' + CC + '">' + CC + '</a>.';
      done.hidden = false; b.disabled = false; b.textContent = 'Odeslat nezávaznou poptávku';
    });
  });

  /* ── vyzkoušení na fotce zákazníka: aplikace/#viz?m=konf (obojí na bezpecne-dvere.cz, předává se přes localStorage) ── */
  var VIZ_KEY = 'bd-konf-viz', VIZ_RES = 'bd-konf-viz-vysledek', VIZ_ST = 'bd-konf-stav', vizRes = null;
  function lsGet(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function lsSet(k, v) { try { if (v == null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); return true; } catch (e) { return false; } }
  function dataBlob(u) { var p = u.split(','), b = atob(p[1]), a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return new Blob([a], { type: 'image/jpeg' }); }
  /* obrázek přesně jako „Konečný vzhled zvenku“: dveře v dekoru (zrcadlově pro levé) + světlíky */
  function vizImage() {
    var m = M();
    return paint(slug(m, st.typ), st.dekor).then(function (u) {
      return new Promise(function (ok, ko) {
        var im = new Image();
        im.onload = function () {
          var z = svSize(), H = 1100, sc = H / (z.hc || 2075), dw = Math.round(im.naturalWidth * H / im.naturalHeight);
          var L = st.sv.l ? Math.round(z.l * sc) : 0, P = st.sv.p ? Math.round(z.p * sc) : 0, T = st.sv.h ? Math.round(z.hv * sc) : 0;
          var cv = document.createElement('canvas'); cv.width = L + dw + P; cv.height = T + H; var cx = cv.getContext('2d');
          var light = function (x, y, w, h) {
            var b = Math.max(4, Math.round(H * 4 / 340)), g = cx.createLinearGradient(x, y, x + w * 0.6, y + h);
            g.addColorStop(0, '#d3dee3'); g.addColorStop(0.55, '#93a7b1'); g.addColorStop(1, '#c2d0d6');
            cx.fillStyle = '#33383b'; cx.fillRect(x, y, w, h); cx.fillStyle = g; cx.fillRect(x + b, y + b, w - 2 * b, h - 2 * b);
          };
          if (T) light(0, 0, cv.width, T); if (L) light(0, T, L, H); if (P) light(L + dw, T, P, H);
          cx.save(); if (st.strana === 'L') { cx.translate(L + dw, 0); cx.scale(-1, 1); cx.drawImage(im, 0, T, dw, H); } else cx.drawImage(im, L, T, dw, H); cx.restore();
          ok(cv.toDataURL('image/jpeg', 0.88));
        };
        im.onerror = ko; im.src = u;
      });
    });
  }
  function formVals() { var o = {}; ['kfJmeno', 'kfTel', 'kfEmail', 'kfObec', 'kfPozn', 'kfAdr'].forEach(function (id) { o[id] = $(id).value; }); o.gdpr = $('kfGdpr').checked; return o; }
  function tryOnPhoto(b) {
    var t0 = b.textContent; b.disabled = true; b.textContent = 'Připravuji…';
    vizImage().then(function (img) {
      var m = M(), r = calc();
      var ok = lsSet(VIZ_KEY, { img: img, nazev: 'ERKADO ' + typName(m, st.typ), popis: [PROV[st.prov] ? PROV[st.prov][1].split('|')[0] : st.prov, DEK[st.dekor][1], KON[st.kon].nazev, (st.strana === 'P' ? 'pravé' : 'levé') + ' ' + (st.smer === 'ven' ? 'ven' : 'dovnitř')].join(' · '),
        cena: kc(r.total) + ' s DPH', svetliky: anySv(), zpet: new URL('konfigurator-erkado.html#viz-zpet', location.href).href, ts: Date.now() }) &&
        lsSet(VIZ_ST, { st: st, form: formVals(), ts: Date.now() });
      if (!ok) throw new Error('storage');
      location.href = 'aplikace/#viz?m=konf';
    }).catch(function () { b.disabled = false; b.textContent = t0; alertMsg('Náhled se nepodařilo předat do vizualizace (prohlížeč nepovolil uložení). Zkuste jiný prohlížeč.'); });
  }
  function alertMsg(s) { $('kfMsg').textContent = s; }
  document.querySelectorAll('[data-viz]').forEach(function (b) { b.addEventListener('click', function () { tryOnPhoto(b); }); });
  function showVizRes() {
    var w = $('kfVizRes'); if (!w) return;
    w.hidden = !vizRes; if (vizRes) $('kfVizImg').src = vizRes.img;
  }
  $('kfVizDel').addEventListener('click', function () { vizRes = null; lsSet(VIZ_RES, null); showVizRes(); });
  /* návrat z vizualizace: obnovit konfiguraci a převzít výsledek */
  (function () {
    var res = lsGet(VIZ_RES); if (res && res.img && Date.now() - res.ts < 864e5) vizRes = res; else if (res) lsSet(VIZ_RES, null);
    if (location.hash !== '#viz-zpet') return;
    var s = lsGet(VIZ_ST);
    if (s && s.st && Date.now() - s.ts < 864e5) {
      Object.keys(s.st).forEach(function (k) { if (k in st) st[k] = s.st[k]; });
      Object.keys(s.form || {}).forEach(function (k) { if (k === 'gdpr') $('kfGdpr').checked = !!s.form.gdpr; else if ($(k)) $(k).value = s.form[k]; });
      st.step = 5;
    }
    try { history.replaceState(null, '', location.pathname); } catch (e) {}
  })();

  /* předvolba z odkazu: #horn-4 */
  var h = (location.hash || '').replace('#', '').toLowerCase().split('-');
  if (h[0] && MOD[h[0]]) { st.model = h[0]; st.typ = +h[1] || MOD[h[0]].typy[0]; st.prov = MOD[h[0]].provedeni[0]; }
  window.KF = { state: st, calc: calc, render: render, collect: collect, buildPdf: buildPdf, vizImage: vizImage };
  render(); showVizRes();
  if (st.step === 5 && vizRes) setTimeout(function () { var e = $('kfVizRes'); if (e) e.scrollIntoView({ block: 'center' }); }, 300);
})();
