# Pravidla pro práci na webu bezpecne-dvere

Komunikace: česky, neformálně, důsledně, nic si nevymýšlet (ověřovat, co nevím, říct to).

## Aktualizace akčního katalogu (pokaždé, když Zdenek pošle nové PDF)

Spouštěcí věta od Zdenka např.: „Tady je nový katalog, zkontroluj a doplň podle pravidel“.
**Katalog je prvořadý** – při rozporu vždy vyhrává katalog (ceny, modely, skladovost, čísla stran).
Web svetdveri.cz/.sk není z prostředí dostupný (proxy 403) → vždy pracuji jen s PDF přiloženým v chatu.

1. **PDF → repo**
   - Nahradit `akciovy-katalog.pdf` (originál, ~32 MB), v `dokumenty.html` upravit velikost u odkazu na PDF.
   - `pdftoppm` → `katalog-akcni/s-NN.jpg` (1240×1754), upravit `const TOTAL` v `katalog-akcni.html`,
     aktualizovat `katalog-akcni/source.json` (label např. „10/2026", sha256, pages).
   - Ceny číst z textové vrstvy: `pdftotext -layout`. V katalogu jsou € s DPH / Kč bez DPH; **web uvádí Kč bez DPH**.
2. **Porovnat modely a ceny** na `dvere-do-interieru.html` (31 karet `.int-card`) a v `detail-*.html`
   (ceny komplet = křídlo + zárubeň; „pouze křídlo"; falcové vs. bezfalcové).
   - Modely skladem (SKLADEM) mají třídu `int-card-sklad` (zelený rámeček) + `badge-skladem`; na objednávku ne.
   - Všechny skladové dveře z katalogu musí být na webu (kromě vědomě vynechaných: Extreme RC3 jako karta, HIDE zárubně – zeptat se).
   - U modelů, kde je zárubeň v ceně: zelený `.zaruben-badge` „Zárubeň v ceně" na každé takové kartě.
3. **Fotky** jsou výřezy ze stran katalogu v `interiery-imgs/katalog/<slug>.jpg` (celé dveře na výšku, pozadí
   (236,238,235), poměr ~1:2, `object-fit:contain`). Po změně stran zkontrolovat, že fotka odpovídá modelu
   (správný model a barva, žádný text/maskot v ořezu).
4. **Kování** – sekce `#kovani` odpovídá straně kování v katalogu (dříve str. 30); ověřit číslo strany a ceny.
5. **Detaily** `detail-*.html`: barevné varianty jen jako dekory (kolečko + název + SKLADEM), žádné hotlinky
   na eshop.svetdveri.sk. Kontrola, že ceny sedí s kartami v přehledu.
6. **Formulář** poptávky: `<optgroup label="Skladem">` musí obsahovat všechny skladové modely.
7. **Kontrola**: Playwright screenshot přehledu (desktop + mobil 390 px): řádky karet zarovnané, ceny se
   nezalamují, žádný chybějící obrázek. Pak commit + push do `main` (GitHub Pages).
8. **Shrnutí pro Zdenka**: co se změnilo (nové/odebrané modely, změny cen, co nejsou v katalogu ověřeno).

## Konvence
- Statický HTML + `style.css`, inline CSS v některých stránkách; formuláře přes FormSubmit.
- Hover efekty (zvětšení/posun) jen v `@media (hover:hover)` kvůli iPadu.
- Commity česky; připojit řádky Co-Authored-By a Claude-Session podle instrukce session.
- Při každé změně `style.css` zvýšit číslo verze `style.css?v=…` ve všech HTML (jinak prohlížeče drží starou kopii):
  `sed -i 's/style\.css?v=[0-9a-z]*/style.css?v=RRRRMMDDx/g' *.html`.

## Předlohy prodejce a formuláře poptávky/objednávky (pokaždé, když se změní dokument)

Web generuje PDF poptávky, objednávky a předávacího listu v prohlížeči (`poptavka-dvere.js`, `poptavka-pdf/poptavka-pdf.js`,
stránky `poptavka-dvere.html` a `objednavka-dvere.html`). **Když se upraví kterýkoli z těchto dokumentů, musí se hned doplnit i na web,
ať je všechno aktuální** (pravidlo od Zdenka 11. 10. 2026):
- **Papírové objednávky** `prodejce/objednavka-*.pdf` (právní text, zhotovitel, příplatky): po nahrazení spustit
  `python3 tools/aktualizace_predloh.py` → propíše smluvní ujednání a řádek zhotovitele do `poptavka-pdf.js`.
  Změněné ceny/příplatky/zálohy ručně přenést do `MODELS` a `MODELS.*.order` v `poptavka-dvere.js`.
- **Předávací list** `prodejce/predavaci-list.pdf`: web ho vyplňuje přímo z tohoto souboru. Po změně spustit
  `python3 tools/aktualizace_predloh.py --check`; když hlásí posunutá políčka, upravit souřadnice ve funkci `buildHandover`.
- **Voucher Doporuč a získej** `prodejce/voucher-doporuc-a-ziskej.pdf`: objednávka do něj dopíše jméno zákazníka („Doporučuje“)
  a přiloží ho jen zákazníkovi (e-mail s objednávkou), do `VOUCHER_PLATI_DO` (2026-12-31) v `poptavka-pdf.js`. Při změně akce/voucheru upravit
  datum a spustit `--check` (hlídá polohu políček).
- **Ceny a provedení na webu** (`dvere-do-bytu.html`) → stejné změny do `MODELS` v `poptavka-dvere.js` (ceny, odstíny, příplatky).
- Opačně: změna textů/cen ve formuláři → upravit i papírové předlohy v `prodejce/` (nebo na rozdíl upozornit Zdenka).
- Po každé změně JS zvýšit `?v=` u `poptavka-dvere.js` / `poptavka-pdf.js` v obou HTML, otestovat (Playwright, mock Mapy.com
  a FormSubmit) a commit + push do `main`.
