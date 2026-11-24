/* erkado-tral-uloz.js (24. 11. 2026): pri kliknuti na "Vytvorit nabidku / Prenest do rozpracovane nabidky" v pruvodci ERKADO ulozi na NAS
   presny vyber (kody voleb TRALu) pod stejnym otiskem, jaky dostane nabidka (erkado-nabidka.js, "#hash" v kf_stav.dekor).
   Diky tomu jde objednavka pozdeji prenest do TRALu (erkado-do-tralu.php). Nacita se v erkado-kart.php za erkado-nabidka.js. */
(function () {
  async function sha1(t) { const b = await crypto.subtle.digest('SHA-1', new TextEncoder().encode(t)); return Array.from(new Uint8Array(b)).map(x => ('0' + x.toString(16)).slice(-2)).join(''); }
  async function uloz() {
    try {
      if (!S || !Object.keys(sel).length) return;
      const hash = (await sha1(JSON.stringify(Object.keys(sel).sort().map(k => k + '=' + sel[k])))).slice(0, 8), opts = [];
      S.forEach(g => { if (!aktivni(g)) return; (g.attributeDefinitions || []).forEach(a => { const k = g.extId + '_' + a.extId, v = sel[k]; if (!v) return;
        const o = (a.productAttributes || []).find(x => String(x.extId) === String(v));
        opts.push({ groupId: g.extId, attrId: a.extId, extId: String(v), name: o ? o.name : (names[k] || ''), group: g.name || '', attr: a.name || '', price: o ? o.price : 0, gOrder: g.order || 0, aOrder: a.order || 0 }); }); });
      const d = dims();
      await fetch('erkado-konfig-uloz.php?key=' + encodeURIComponent(KEY), { method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true,
        body: JSON.stringify({ h: hash, opts: opts, rozmer: d ? { w: d.totalW, h: d.totalH } : null, model: ((G[sel['1_3']] || '') + ' ' + (G[sel['1_4']] || '')).trim() }) });
    } catch (e) {}
  }
  document.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('#bNab')) uloz(); }, true);
})();
