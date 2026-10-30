/* Bezpečné dveře → Google Kontakty
   Každých 30 minut stáhne zákazníky z kartotéky a založí / aktualizuje je v Google Kontaktech
   (skupina „Zákazníci Bezpečné dveře“). Telefon s tímto Google účtem pak při hovoru ukáže jméno zákazníka.
   - Zákazníka, kterého už v kontaktech máte se stejným číslem, nepřepisuje – jen ho přidá do skupiny.
   - Kontakty založené tímto skriptem aktualizuje, když se v kartotéce změní (jméno, telefon, e-mail, adresa).
   - Ke každému kontaktu přidá odkaz „Kartotéka“ – klik v telefonu otevře kartu zákazníka (historie, poptávky, objednávky, servis).
   - Nic nemaže. Zákazníka smazaného v kartotéce smažte v Kontaktech ručně (pokud chcete).
   Nastavení: Služby (+) → „People API“ přidat, pak spustit funkci nastavit() a povolit přístup. */

const ADRESA = '__FEED__';                  // adresa z kartotéky i s tajným klíčem – nikomu ji neposílejte
const SKUPINA = 'Zákazníci Bezpečné dveře';
const PRIPONA_JMENA = '';                   // např. ' (BD)' – přidá se za příjmení u nově založených kontaktů; prázdné = nic
const MAX_ZAPISU = 120;                     // zápisů za jedno spuštění (Google omezuje rychlost), zbytek se dodělá příště

function nastavit() {
  ScriptApp.getProjectTriggers().forEach(function (t) { if (t.getHandlerFunction() === 'synchronizovat') ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('synchronizovat').timeBased().everyMinutes(30).create();
  synchronizovat();
}

function synchronizovat() {
  const r = UrlFetchApp.fetch(ADRESA, { muteHttpExceptions: true, headers: { 'Accept': 'application/json' } });
  if (r.getResponseCode() !== 200) throw new Error('Kartotéka odpověděla ' + r.getResponseCode() + ': ' + r.getContentText().slice(0, 200));
  const data = JSON.parse(r.getContentText());
  if (!data.ok || !Array.isArray(data.zakaznici)) throw new Error('Neplatná data z kartotéky');
  const skupina = najdiSkupinu_();

  const vsechny = []; let pt = null;
  do {
    const res = People.People.Connections.list('people/me', { personFields: 'names,phoneNumbers,emailAddresses,userDefined,memberships,urls', pageSize: 1000, pageToken: pt });
    (res.connections || []).forEach(function (p) { vsechny.push(p); });
    pt = res.nextPageToken;
  } while (pt);
  const podleKlice = {}, podleTel = {};
  vsechny.forEach(function (p) {
    (p.userDefined || []).forEach(function (u) { if (u.key === 'bd-klic') podleKlice[u.value] = p; });
    (p.phoneNumbers || []).forEach(function (t) { const d = String(t.value || '').replace(/\D/g, '').slice(-9); if (d.length === 9 && !podleTel[d]) podleTel[d] = p; });
  });

  let zapisu = 0, nove = 0, upr = 0, oznac = 0; const doSkupiny = [];
  for (let i = 0; i < data.zakaznici.length && zapisu < MAX_ZAPISU; i++) {
    const z = data.zakaznici[i], h = otisk_(z);
    let p = podleKlice[z.klic];
    const tel9 = String(z.telefon || '').replace(/\D/g, '').slice(-9);
    if (!p && tel9.length === 9 && podleTel[tel9]) {          // už ho máte v kontaktech → jen označit a dát do skupiny, nic nepřepisovat
      p = podleTel[tel9];
      const ud = (p.userDefined || []).filter(function (u) { return !/^bd-/.test(u.key); }).concat([{ key: 'bd-klic', value: z.klic }, { key: 'bd-hash', value: h }, { key: 'bd-vlastni', value: '1' }]);
      const zm = { etag: p.etag, userDefined: ud }, uu = urly_(p, z), pole = ['userDefined'];
      if (uu) { zm.urls = uu; pole.push('urls'); }
      People.People.updateContact(zm, p.resourceName, { updatePersonFields: pole.join(',') });
      doSkupiny.push(p.resourceName); podleKlice[z.klic] = p; zapisu++; oznac++; continue;
    }
    if (!p) {                                                  // nový kontakt
      const o = osoba_(z, h); o.memberships = [{ contactGroupMembership: { contactGroupResourceName: skupina } }];
      People.People.createContact(o); zapisu++; nove++; continue;
    }
    const ud0 = p.userDefined || [];
    const stary = ud0.filter(function (u) { return u.key === 'bd-hash'; })[0];
    const vlastni = ud0.some(function (u) { return u.key === 'bd-vlastni'; });
    if (!(p.memberships || []).some(function (m) { return m.contactGroupMembership && m.contactGroupMembership.contactGroupResourceName === skupina; })) doSkupiny.push(p.resourceName);
    if (vlastni) {                                             // váš vlastní kontakt: nic nepřepisovat, jen doplnit odkaz do kartotéky
      const uu = urly_(p, z);
      if (uu) { People.People.updateContact({ etag: p.etag, urls: uu }, p.resourceName, { updatePersonFields: 'urls' }); zapisu++; upr++; }
      continue;
    }
    if (stary && stary.value === h) continue;                  // beze změny
    const o = osoba_(z, h); o.etag = p.etag;
    People.People.updateContact(o, p.resourceName, { updatePersonFields: 'names,phoneNumbers,emailAddresses,addresses,organizations,biographies,userDefined,urls' });
    zapisu++; upr++;
  }
  for (let j = 0; j < doSkupiny.length; j += 500) People.ContactGroups.Members.modify({ resourceNamesToAdd: doSkupiny.slice(j, j + 500) }, skupina);
  console.log('Hotovo: ' + data.zakaznici.length + ' zákazníků v kartotéce, nových ' + nove + ', upravených ' + upr + ', označených existujících ' + oznac + (zapisu >= MAX_ZAPISU ? ' (zbytek příště)' : ''));
}

function osoba_(z, h) {
  const o = {
    names: [{ givenName: z.jmeno || '', familyName: (z.prijmeni || (z.jmeno ? '' : z.firma) || '') + PRIPONA_JMENA }],
    organizations: [{ name: z.firma || 'Bezpečné dveře – zákazník', title: z.firma ? 'zákazník Bezpečné dveře' : '' }],
    biographies: [{ value: z.poznamka || '', contentType: 'TEXT_PLAIN' }],
    userDefined: [{ key: 'bd-klic', value: z.klic }, { key: 'bd-hash', value: h }]
  };
  if (z.telefon) o.phoneNumbers = [{ value: z.telefon, type: 'mobile' }];
  if (z.odkaz) o.urls = [{ value: z.odkaz, type: 'Kartotéka' }];
  if (z.email) o.emailAddresses = [{ value: z.email, type: 'home' }];
  if (z.ulice || z.mesto || z.psc) o.addresses = [{ streetAddress: z.ulice || '', city: z.mesto || '', postalCode: z.psc || '', countryCode: 'CZ', type: 'home' }];
  return o;
}
// odkaz do kartotéky u kontaktu: null = už tam je správně, jinak nový seznam odkazů (vaše vlastní odkazy zůstanou)
function urly_(p, z) {
  if (!z.odkaz) return null;
  const ur = p.urls || [];
  if (ur.some(function (u) { return u.value === z.odkaz; })) return null;
  return ur.filter(function (u) { return !/\/zakaznici\.php\?k=/.test(String(u.value || '')); }).concat([{ value: z.odkaz, type: 'Kartotéka' }]);
}
function otisk_(z) {
  const b = Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, JSON.stringify([z.jmeno, z.prijmeni, z.firma, z.telefon, z.email, z.ulice, z.mesto, z.psc, z.poznamka, PRIPONA_JMENA, z.odkaz || '']), Utilities.Charset.UTF_8);
  return b.map(function (x) { return ('0' + (x & 255).toString(16)).slice(-2); }).join('');
}
function najdiSkupinu_() {
  const res = People.ContactGroups.list({ pageSize: 1000 });
  const g = (res.contactGroups || []).filter(function (x) { return x.name === SKUPINA; })[0];
  return g ? g.resourceName : People.ContactGroups.create({ contactGroup: { name: SKUPINA } }).resourceName;
}
