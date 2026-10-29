/* Katalog pro pomocníka „Připravit nabídku“ (kartotéka na NAS, lib/navrh-nabidky.php).
   Vytahuje z webu názvy modelů, odstíny a tloušťky zárubní (BEZ cen – ceny počítá formulář nabídky sám).
   Spuštění: node tools/navrh_katalog.js > nas/lib/navrh-katalog.json  → nahrát na NAS do /docker/formulare/app/lib/navrh-katalog.json
   Pouštět znovu při každé změně modelů / odstínů (nabidka-nova.html NB_KATALOG, aplikace/viz-data.js, INT_CENIK v nabidka.js). */
const fs = require('fs'), path = require('path'), R = (f) => fs.readFileSync(path.join(__dirname, '..', f), 'utf8');
const nb = JSON.parse(R('nabidka-nova.html').match(/window\.NB_KATALOG=(\[.*?\]);<\/script>/s)[1]);
global.window = {}; eval(R('aplikace/viz-data.js')); const V = window.VIZ_DATA;
const js = R('nabidka.js'); const blk = js.slice(js.indexOf('var R1 ='), js.indexOf('var INT_ZAHRNUJE'));
const INT_CENIK = new Function(blk.replace('var LF', 'var LF') + '; return INT_CENIK;')();
const out = {
  vytvoreno: new Date().toISOString().slice(0, 10),
  byt: nb.map(m => ({ model_kod: m.k, nazev: m.name, odstiny: m.shades.map(s => s.n), skladem: m.shades.filter(s => s.sklad).map(s => s.n), priplatek_kc: Object.fromEntries(m.shades.filter(s => s.pl).map(s => [s.n, s.pl])) })),
  dum: V.domovni.map(c => c[0]),
  interier: V.interier.map(c => {
    const e = INT_CENIK[c[0]];
    return { model: c[0], popis: c[2], skladem: !!c[4], odstiny: (c[5] || []).map(d => d[0]).concat(e && e.v ? Object.keys(e.v).filter(k => !(c[5] || []).some(d => d[0] === k)) : []),
      tl_zarubne: e ? e.r : [], cena_z_ceniku: !!e,
      // nejnižší ceníková cena (dveře + zárubeň, Kč bez DPH, bez montáže) – jen pro výběr „nejlevnějšího“ modelu, cenu do nabídky počítá formulář
      cena_od_bez_dph: !e ? null : (e.v ? Object.fromEntries(Object.entries(e.v).map(([k, a]) => [k, Math.min(...a)])) : Math.min(...e.c)) };
  })
};
process.stdout.write(JSON.stringify(out, null, 1));
