// Vygeneruje textový katalog voleb konfigurátoru ERKADO pro webového pomocníka AI (nas/lib/pomocnik-web-katalog.json).
// Spuštění: node tools/pomocnik_web_katalog.js   (po každé změně konfigurator-erkado/erkado-data.js; pak nahrát na NAS do /docker/formulare/app/lib/)
global.window = {}; require('../konfigurator-erkado/erkado-data.js'); const E = window.ERKADO;
const mod = E.modely.map(m => `${m.id} | ${m.nazev} | vzory: ${m.typy.join(',')} | provedení: ${m.provedeni.join(' / ')}`);
const kon = E.konstrukce.map(k => `${k.id} = ${k.nazev} (${k.tl} mm, Ud ${k.ud}; ${k.popis})`);
const dek = E.dekory.map(d => `${d[0]} = ${d[1]}`);
const out = {
  modely: E.modely.map(m => ({ id: m.id, typy: m.typy, provedeni: m.provedeni })),
  dekory: E.dekory.map(d => d[0]), konstrukce: E.konstrukce.map(k => k.id), sirky: E.sirky,
  madla: E.madla.map(m => m.id), skla: ['std', 'benat', 'bila', 'seda'],
  text: [
    'ŘADY (id | název | čísla vzorů | provedení):', ...mod, '',
    'KONSTRUKCE (id):', ...kon, '',
    'ZÁRUBNĚ (zarubne): ocel = ocelová (standard u THERMO 78 a 64), hlinik = hliníková (standard u HOT 88 a HOT 78, u THERMO za příplatek; nutná pro světlíky a madlo–madlo), rohova = ocelová rohová (THERMO 78 a 64).', '',
    'ŠÍŘKA A VÝŠKA: sirka = označení z katalogu ' + E.sirky.join(', ') + ' (číslo = zhruba šířka průchodu v cm, N = užší varianta). Nestandardní šířku nastav pomocí sirka_mm (770–1210 mm, po 10 mm, příplatek). Standardní výška je v ceně; jinou výšku dveří nastav pomocí vyska_mm (1800–2480 mm, po 10 mm, příplatek za vyšší i nižší). Zákazník může chtít např. 2400 mm, nastav to.', '',
    'DEKORY (id = název):', ...dek, '',
    'KOVÁNÍ (kovani): A klika–klika (1 zámek), B klika–klika + horní zámek (výchozí), C madlo–klika, D madlo–klika + horní zámek, E madlo–madlo (jen hliníková zárubeň), F madlo–madlo + horní zámek (jen hliníková zárubeň), X bez kování. Povrch kování (kovani_povrch): nikl (inox/nikl, v ceně) nebo cerna (příplatek).', '',
    'MADLA (madlo, jen u C–F): ' + E.madla.map(m => m.id + ' = ' + m.nazev).join('; ') + '. Délka madla (madlo_delka): 60, 120, 160, 180 cm (ne každé madlo má všechny délky, konfigurátor to dorovná). Povrch madla (madlo_povrch): inox nebo cerna.', '',
    'SKLO (sklo, jen u řad se zasklením): std (standardní soustava), benat (benátské zrcadlo), bila (matné bílé), seda (matné šedé).', '',
    'OTEVÍRÁNÍ: smer = ven nebo dovnitr; strana = leve nebo prave, vždy při pohledu ZEVNITŘ z bytu/domu (kde jsou panty). Při otevírání dovnitř nejde HOT 88 a HOT 78.', '',
    'DVOUBARVENÉ DVEŘE: dvoubarevne = ne (výchozí) / kridlo (jiný dekor zevnitř, příplatek) / komplet (křídlo i rám, příplatek); dekor_zevnitr = id dekoru zevnitř.', '',
    'SVĚTLÍKY (nastavuješ je ty): svetlik_horni_mm = výška horního světlíku (nadsvětlíku) 200–500 mm (ceník 200, 300, 400, 500), svetlik_vlevo_mm a svetlik_vpravo_mm = šířka bočního světlíku 200–800 mm (popisky vlevo/vpravo jako v konfigurátoru), hodnota 0 světlík zruší. Světlíky vyžadují hliníkovou zárubeň (konfigurátor ji zapne sám, příplatek), dveře se světlíky nemají zkoušku RC2. Cena je za nejbližší vyšší rozměr z ceníku, u nestandardního rozměru příplatek.', '',
    'MONTÁŽ: montaz true = montáž na klíč, false = bez montáže (osobní odběr nebo doprava).'
  ].join('\n')
};
require('fs').writeFileSync(__dirname + '/../nas/lib/pomocnik-web-katalog.json', JSON.stringify(out, null, 1));
console.log('OK', out.text.length, 'znaků');
