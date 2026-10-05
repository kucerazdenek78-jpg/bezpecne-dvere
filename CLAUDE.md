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
