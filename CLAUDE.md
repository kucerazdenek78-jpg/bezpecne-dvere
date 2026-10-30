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

## Podpis e-mailů zákazníkům (od 30. 10. 2026)
- Každý e-mail zákazníkovi má podpis: jméno obchodníka, malé logo (`userFiles/email/logo-podpis.png`) a firemní údaje (BEZPEČNÉ DVEŘE CZ, KKSYSTEM, pobočka/sklady Most Obchodní 36, e-shop, infolinka +420 725 559 235 / +420 471 008 994, info@kksystem.cz, chci@bezpecne-dvere.cz).
- Kód: NAS `lib/podpis.php`; používá `odeslat.php` (typ objednavka_zakaznik, jméno z pole `prodavac`) a `lib/posta.php` → `posli_mail` (jméno z parametru, jinak přihlášený uživatel kartotéky). Interní e-maily firmě jsou bez podpisu.
- Nový formulář, který posílá e-mail zákazníkovi, musí posílat `prodavac` (formulare-klient.js ho přenáší). Změna firemních údajů = upravit `lib/podpis.php`.

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

## Ceník pro prodejce (pokaždé, když se změní ceny/akce vchodových dveří)
`cenik-prodejce.html` (+ PDF `prodejce/cenik-bezpecnostni-dvere.pdf`, odkazy v `panel.html`) je statický výtah z webu (`dvere-do-bytu.html`, `MODELS` v `poptavka-dvere.js`).
Při změně cen, příplatků nebo akcí upravit ceník a znovu vygenerovat PDF (Playwright `page.pdf`, A4, okraje 12 mm); datum „stav k" aktualizovat.

## Uvítací antré k novému webu (dočasné)
- Na `index.html` je dole blok mezi komentáři `VITEJ-ANTRE:start` a `VITEJ-ANTRE:end` (okno „Vítejte na našem novém webu“ s jemnou animací, zavíratelné, jednou za návštěvníka přes localStorage `bd-vitej-2026`, respektuje `prefers-reduced-motion`).
- Samo se přestane zobrazovat **26. 10. 2026** (14 dní od spuštění domény bezpecne-dvere.cz dne 12. 10. 2026). Po tomto datu lze celý blok smazat.

## Dárkové poukazy (od 29. 10. 2026)
- Web: `darkovy-poukaz.html` (objednávka; od 30. 10. 2026 veřejná – v menu „🎁 Poukaz“ za Kontaktem na všech stránkách, v patičce pod „Doporuč nás“, v sitemap), `poukaz-stav.html?t=…` (stav, QR platba, stažení PDF a potvrzení o platbě),
  `poukaz/poukaz.js` (grafika a PDF poukazu + potvrzení, jsPDF), `poukaz/poukaz-web.js`, pruh na úvodu `poukaz/poukaz-banner.js` (ZAPNUTO=true od 30. 10. 2026, vložen před sekci PREMIUM 60; vypnout = ZAPNUTO=false).
- Písma Playfair Display a Caveat (OFL, z npm @fontsource, sloučeno latin + latin-ext) v `poukaz/`.
- `VZOR_CENA` v `poukaz/poukaz.js` = akční cena PREMIUM 60 (38 890 Kč) pro ukazatel „kolik dveří je zaplaceno“ – při změně ceny upravit.
- NAS: `poukaz-api.php` (veřejné), `poukazy.php` (kartotéka), `lib/poukazy.php`, párování plateb v `platby.php` (VS 9RRNNNNN).
- Reklamní obrázky: `tools/poukaz/reklamy.html` (Playwright snímek prvků #feed, #ctverec, #story po `go('vanoce')`).
- Motivy: vanoce / narozeniny / darkovy / **vlastni** (zákazník nahraje fotku → `poukaz-api.php` akce=foto, uloží se `/data/poukazy/<id>_<token>_foto.jpg`; kartotéka ji načte přes `poukazy.php?foto=id`).
- Prodej ve vzorkovně: hotově nebo **QR platbou** (kartou ne – kvůli provizím); QR se ukáže v detailu poukazu, platba se spáruje z banky.
- Reklamní materiály (reel + 3 obrázky) jsou na NAS v `/data/reklama/05-darkove-poukazy-vanoce/` (kartotéka → Reklamní materiály).
- Reel k poukazům: AI klip (Magnific, Seedance 2.0) + titulky v Magnific Move; záložní vlastní druhá část `tools/poukaz/reel.html` + `reel.py`.

## Podpis na displeji (od 30. 10. 2026)
- Sdílený modul `podpis/podpis.js` (`BDPodpis.volba` = volba „Na papíře / Na displeji“ + pole pro podpis prstem; `BDPodpis.pole`).
- Objednávky (`objednavka-nova.html` → `poptavka-dvere-nova.js`, `objednavka-vice.html` → `nabidka.js`): při „Na displeji“ se podpis zákazníka (a nepovinně zhotovitele) vloží nad čáru v PDF objednávky (`BDPodpisPng` v `poptavka-pdf-nova.js`), do dat jen `podpis_rezim` / `podpis_cas`. Tisk na papír zůstává.
- Předání: NAS `predani-podpis.php?t=…&rezim=podpis` – podpis na displeji HNED zapíše předání (záruka, sklad, poděkování), uloží podepsaný list do `/data/predani` a pošle ho zákazníkovi. Tisk předem + potvrzení QR (`predani.php`) zůstává.
- Změna PDF/podpisu = zvýšit `?v=` u `podpis.js`, `poptavka-pdf-nova.js` (i v `predani-podpis.php` na NAS).
