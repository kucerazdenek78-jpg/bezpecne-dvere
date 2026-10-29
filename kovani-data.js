/* Kování (kliky) k interiérovým dveřím – stejné jako na dvere-do-interieru.html#kovani (MP Kovania, akční katalog 10/2026 str. 34).
   Ceny v Kč BEZ DPH za kus (klika s rozetou zvoleného typu). Používá: poptavka-kat.js (poptávka), nabidka.js (nabídka / objednávka).
   Při změně cen v katalogu upravit tady i v sekci #kovani na dvere-do-interieru.html. */
window.BD_KOVANI = [
  { id: 'favorit', nazev: 'Favorit', povrch: 'nerez matný', img: 'interiery-imgs/kovani/favorit.jpg', ceny: { 'BB / PZ': 330, 'WC': 430 } },
  { id: 'hit-cerna', nazev: 'Hit', povrch: 'černá matná', img: 'interiery-imgs/kovani/hit-cerna.jpg', ceny: { 'BB / PZ': 490, 'WC': 550 } },
  { id: 'hit-nikl', nazev: 'Hit', povrch: 'nikl matný', img: 'interiery-imgs/kovani/hit-nikel.jpg', ceny: { 'BB / PZ': 490, 'WC': 550 } },
  { id: 'cynthia', nazev: 'Cynthia', povrch: 'nikl matný / chróm lesklý', img: 'interiery-imgs/kovani/cynthia.jpg', ceny: { 'BB / PZ': 490, 'WC': 550 } },
  { id: 'topaz', nazev: 'Topaz', povrch: 'nerez matný', img: 'interiery-imgs/kovani/topaz.jpg', ceny: { 'BB / PZ': 370, 'WC': 420 } },
  { id: 'rumba', nazev: 'Rumba', povrch: 'černá matná', img: 'interiery-imgs/kovani/rumba.jpg', ceny: { 'BB / PZ': 490, 'WC': 570 } },
  { id: 'perfecta', nazev: 'Perfecta', povrch: 'nikl matný', img: 'interiery-imgs/kovani/perfecta.jpg', ceny: { 's uzamykáním': 1913 }, pozn: 'k bezfalcovým dveřím ALTAMURA 1 (bez spodní rozety)' },
  { id: 'impressa', nazev: 'Impressa', povrch: 'černá matná', img: 'interiery-imgs/kovani/impressa.jpg', ceny: { 's uzamykáním': 1913 }, pozn: 'k bezfalcovým dveřím ALTAMURA 1 (bez spodní rozety)' }
];
window.BD_ROZETY = { 'BB / PZ': 'BB = obyčejný klíč, PZ = cylindrická vložka', 'WC': 'WC – koupelna / toaleta (otočný knoflík)', 's uzamykáním': 'klika s uzamykáním' };
