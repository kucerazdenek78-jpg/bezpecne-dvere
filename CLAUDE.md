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
2. **Porovnat modely a ceny** na `dvere-do-interieru.html` (34 karet `.int-card`, viz oddíl Karty interiérových dveří) a v `detail-*.html`
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

## Karty interiérových dveří a dekory (od 17. 11. 2026)
- Všech 34 karet `.int-card` na `dvere-do-interieru.html` má stejnou stavbu: štítek SKLADEM / NA OBJEDNÁVKU, materiál, kolečka dekorů + „Názvy dekorů“, Zárubeň v ceně, „Komplet od X Kč“ + doplněk, tlačítka **📷 Ukázat na mé fotce** (`aplikace/#viz?m=interier&d=NÁZEV`) a **Poptávka**, odkazy Detail / Katalog str. N. Štítek „Aplikace Moje dveře v ceně“ na kartách interiérů není (`bonus.js`).
- Karty i odstíny ve vizualizaci generuje `python3 tools/interier/karty.py` (jde pouštět opakovaně; zachová ceny, fotky a texty karet). Dekory podle katalogu jsou v `tools/interier/dekory_data.py`: vzorníky str. 17 (ERKADO), 23 (INVADO), 33 (PORTA) se souřadnicemi vzorků, a `MODELY` = které skupiny dekorů platí pro který model (podle strany modelu). **Při novém katalogu zkontrolovat čísla stran, souřadnice vzorků (`python3 tools/interier/paleta.py` uloží kontrolní arch) a seznamy u modelů.**
- Vizualizace (`aplikace/viz-data.js`, `app.js`): skladové modely mají dekory jako fotky z katalogu `[název, obrázek]`; dveře na objednávku `[název, "#barva", dřevo]` = orientační přebarvení základní fotky v prohlížeči (`tintDoor`), první volba je „Foto z katalogu“. Náhled přebarvení: `python3 tools/interier/tint.py`. Po změně zvýšit `?v=` u `app.js`, `viz-data.js`, `app.css` v `aplikace/index.html`, `test.html` a `VERZE` + `SHELL` v `aplikace/sw.js`.
- Interiérové dveře **nemají servisní knížku** (rozhodnutí Zdeňka 17. 11. 2026): žádná údržba po 6 měsících, připomínky, bonus k záruce ani roční prohlídka; v aplikaci jen záruka, servis a péče o povrch (`intr` v `aplikace/app.js`). NAS je pozná funkcí `udrzba_interier()` v `lib/udrzba.php` (kategorie objednávky `interier` nebo slovo interiér v modelu), používá ji i `lib/bonus.php`. Štítek z `bonus.js` je na detailech jen u `detail-extreme-rc3.html`.
- Vizualizace na telefonu (17. 11. 2026): pod fotkou je rychlý výběr `#qbar` (pás dveří/odstínů + pás dekorů, `quickBar()` v `app.js`); po označení 4 rohů se fotka zmenší na 50 % výšky a jde přes ni posouvat stránku (`touch-action:pan-y`, při označování `.stage.mark` = `none`).
- Konfigurátor ERKADO (17. 11. 2026): obrázky madel (`konfigurator-erkado/img/madla/`, katalog ERKADO str. 69; inox `<id>-inox.jpg` = orientační přebarvení z `tools/erkado_madla_inox.py`) a kování (`img/kovani/`: způsoby otevírání A–F str. 65, klika HAGA str. 68, vložky str. 66) jsou výřezy z `katalog-erkado-2025.pdf` při 300 dpi; při novém katalogu vyříznout znovu. Na telefonu je tlačítko pomocníka AI v konfigurátoru vpravo nahoře (`.pw-top` v `pomocnik.js`).

## Konvence
- Statický HTML + `style.css`, inline CSS v některých stránkách; formuláře přes FormSubmit.
- Hover efekty (zvětšení/posun) jen v `@media (hover:hover)` kvůli iPadu.
- Commity česky; připojit řádky Co-Authored-By a Claude-Session podle instrukce session.
- Při každé změně `style.css` zvýšit číslo verze `style.css?v=…` ve všech HTML (jinak prohlížeče drží starou kopii):
  `sed -i 's/style\.css?v=[0-9a-z]*/style.css?v=RRRRMMDDx/g' *.html`.

## Podpis e-mailů zákazníkům (od 30. 10. 2026)
- Každý e-mail zákazníkovi má podpis: jméno obchodníka, malé logo (`userFiles/email/logo-podpis.png`) a firemní údaje (BEZPEČNÉ DVEŘE CZ, KKSYSTEM, pobočka/sklady Most Obchodní 36, e-shop, infolinka +420 725 559 235 / +420 471 008 994, info@kksystem.cz, chci@bezpecne-dvere.cz).
- Od 13. 11. 2026 je u infolinky pracovní doba **Po–Čt 9:00–16:30** a věta „Volejte prosím v pracovní době, mimo ni nám napište e-mail.“ (konstanta `PODPIS_PRAC_DOBA` v `lib/podpis.php`).
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
Od 4. 11. 2026 **není na webu**: leží na NAS `cenik.php` (za přihlášením do kartotéky, odkaz z `nastroje.php`) jako statický výtah z webu (`dvere-do-bytu.html`, `MODELS` v `poptavka-dvere.js`).
Panel prodejce `panel.html` jen přesměruje na `https://kartoteka.dvere.app/nastroje.php`; zámek 🔒 v patičkách vede rovnou do kartotéky (kartoteka.dvere.app, Úvod; Nástroje jsou v jejím menu). PDF předlohy (`prodejce-soubor.php`) se berou z NAS `data/prodejce-soubory` (pod stejným názvem se dají nahradit).
Při změně cen, příplatků nebo akcí upravit `cenik.php` na NAS a PDF ceníku znovu vygenerovat (Playwright `page.pdf`, A4, okraje 12 mm) a nahrát do `data/prodejce-soubory`; datum „stav k" aktualizovat.

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

## Připomínky nabídek, hodnocení a poptávky z e-mailu (od 5. 11. 2026)
- NAS `lib/terminy.php` + CLI `terminy-pripominky.php` (Plánovač každou hodinu 7–20 h): nabídka ve stavu Odeslána → připomínka po 3 dnech, 2 dny před koncem platnosti, poslední zpráva 1–7 dní po konci; duplicitní záznamy (stejný e-mail + číslo) = jeden e-mail; 5–30 dní po předání (od 1. 11. 2026) dotaz na spokojenost s montáží.
- Box na úvodu kartotéky: stav připomínek, tlačítka „📨 Připomenout teď“ / „Nepřipomínat“, úkoly „📞 Zákazníci čekají na naši reakci“ (✓ Vyřízeno), hodnocení nabídek i montáží.
- `hodnoceni.php?t=` (nabídka: rozmýšlím / upravit / zavolat / nemám zájem + důvod → Storno) a `?m=` (montáž 1–5, 4–5 → recenze Google).
- Poptávky z e-mailu: přeposlat na `poptavky-ai@bezpecne-dvere.cz` (alias do schránky kartoteka-dvere@) → CLI `poptavky-z-mailu.php` (Plánovač každých 15 min) + `lib/poptavky-mail.php` → poptávka P …/RRRR (zdroj email-ai), potvrzení zákazníkovi, odpověď prodejci. Povolení odesílatelé: kucerazd@kksystem.cz, chci@bezpecne-dvere.cz (přepsat jde v config.php `poptavky_ai.povoleni`).

## Termín montáže zákazníkovi e-mailem (od 5. 11. 2026)
- NAS `predani.php` (box „✉️ Termín zákazníkovi e-mailem“ pod termínem montáže) + `lib/montaz-mail.php`: potvrzení, změna a zrušení termínu. **Odesílá obchodník ručně** až po zaškrtnutí „domluveno telefonicky“ (nic nechodí samo při uložení termínu).
- Okno příjezdu: **do 40 km od pobočky 8:30–9:15, nad 40 km 9:00–9:45** (rozhodnutí Zdeňka). Vzdálenost = `doprava_km` v objednávce (Mapy.com, od Obchodní 36, Most); když chybí, ruční volba `montaz_vzd` (do/nad). Hranice: přesně 40 km = kratší okno. Při změně limitu upravit `MONTAZ_LIMIT_KM` a texty v `montaz_okno`, `priprava-na-montaz.html`.
- E-mail: odhad 4–6 h, tlačítka Google Kalendář / Outlook.com, příloha `.ics` (UID `montaz-zakaznik-<id>`, SEQUENCE roste → změna přepíše událost, zrušení = METHOD:CANCEL), příprava, sousedé, odkaz na web `priprava-na-montaz.html` (cedulka k tisku), podpis obchodníka. Jméno montéra se neuvádí.
- Co a komu se odeslalo: `data_json.montaz_odeslano` (poslední) + `montaz_odeslano_historie` (20 posledních). Odlišný uložený termín = žlutý pruh „Po telefonické domluvě pošlete změnu“.
- Text přípravy je ve dvou místech: `montaz_priprava()` v `lib/montaz-mail.php` a `priprava-na-montaz.html` – při změně upravit obojí.

## HTML e-maily zákazníkům, videa a cedulka pro sousedy (od 17. 11. 2026)
- NAS `lib/mail-html.php` (šablona se zelenou hlavičkou, video, rámeček, PDF cedulka, `mailh_uprav`) a `lib/mail-formular.php` (`mailh_formular`). Háčky: `lib/posta.php` → `posli_mail` (jen e-maily zákazníkům) a `odeslat.php` (typ objednavka_zakaznik). Když cokoli selže, e-mail odejde ve staré podobě.
- Podle předmětu: objednávka / cenová nabídka z webu (platba s tlačítkem, položky v kartách, u objednávky video „zloději to vzdali“ s příjmením), „Objednávka č. X podepsaná – akceptace“ (video „roboti ve výrobě“; text: podepsali jsme my, zákazník potvrdil zaplacením zálohy, dveře na míru nejdou zrušit), potvrzení / změna termínu montáže (rámeček o prachu, video „buldoček při montáži“ (`buldok-pri-montazi`), příloha `Cedulka-pro-sousedy-DD-MM-RRRR.pdf`), ostatní = prostý text v šabloně.
- Obrázky a videa: web `userFiles/email/` (`*.gif` do e-mailu, `*.mp4` po kliknutí). Videa z Magnific. Podklad cedulky `cedulka-sousede.jpg` se generuje z `tools/email/cedulka-sousede.html` (`node tools/email/cedulka.cjs`); NAS si ho stáhne z webu do `/data/cache/cedulka-sousede-v1.jpg` a dopíše datum a čas. Po změně podkladu zvýšit `v1` v `mailh_cedulka_pdf` a případně souřadnice okének.
- Formuláře objednávky posílají řádek „Příjmení“ (jen pro vtip, v e-mailu se nezobrazuje). Změna předmětu e-mailu v kódu = zkontrolovat, že ho `mailh_uprav` / `mailh_formular` pořád poznají.
- Od 17. 11. 2026 (druhé kolo) NAS `lib/mail-dalsi.php` (`mailh_dalsi`, volá ho `mailh_uprav` jako první): „Děkujeme za poptávku (č. X)“ (video `buldok-poptavka`, rámeček Co bude dál, tabulka „Co jste nám poslali“ z bloku `SHRNUTÍ VAŠÍ POPTÁVKY`, který přidává `odeslat.php`) a připomínky nabídek „Nabídka č. X: dveře se ptají… / platnost ceny končí… / poslední zpráva…“ (odkazy `• text: URL` jako tlačítka, video `buldok-ceka` u prvních dvou). E-mail s cenovou nabídkou z webu má nahoře video `buldok-kontrolor` (šedý francouzský buldoček; `dvere-na-prehlidce` se Zdeňkovi nelíbilo a nepoužívá se) a u dveří do bytu/domu rámeček o aplikaci Moje dveře (řádek „Aplikace Moje dveře v ceně“ z `nabidka.js`): `nabidka.js` posílá obrázky položek jako soubory `nahled-N.jpg` (`nahled-Ni.jpg` = ilustrační), `odeslat.php` je nepřiloží, ale vloží do karet položek (`mailh_formular`, 4. parametr). Změna předmětů v `lib/terminy.php` nebo v poděkování za poptávku = upravit regulární výrazy v `mail-dalsi.php`.
- Zdeněk chce ve videích šedého francouzského buldočka v oranžové přilbě (od 17. 11. 2026 je v nabídce, poptávce, připomínce a u termínu montáže; staré `kocka-pri-montazi`, `poptavka-dorazila`, `nabidka-ceka`, `dvere-na-prehlidce` zůstaly na webu jen kvůli už odeslaným e-mailům). Nové video: Magnific, pak MP4 960×540, GIF 400 px / 8 fps / 64 barev, JPG.
- Buldoček je od 17. 11. 2026 i v připomínkách servisu a záruky a u předání (jen e-maily, **ne PDF**; texty e-mailů beze změny): `mail-dalsi.php` pozná předměty „Prodloužení záruky na Vaše dveře – objednávka X“ (`buldok-zaruka`), „Roční kontrola požárního uzávěru – objednávka X“ a „Čas na údržbu dveří – objednávka X“ (`buldok-servis`), „Předávací a záruční list – objednávka X“ (kopie listu po podpisu na displeji, `buldok-predani`). Změna těchto předmětů v `nabidky.php`, `udrzba-pripominky.php` nebo `posli_predani_kopii` = upravit pole `$bul`.
