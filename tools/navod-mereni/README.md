# Návod „Jak dveře změřit" (23. 11. 2026)

Obrázek `userFiles/navod/jak-zmerit-dvere.png` – ukazuje se zákazníkovi na `doplnit.php` (NAS), když ho prodejce žádá o rozměry, a odkazuje na něj e-mail „Doplnění poptávky".

- **Dveře** nakreslil Magnific (text-to-image, plán Plus), prompt: čisté ploché infografické dveře v otvoru ve zdi, čelní pohled, bez textu/šipek, světlá omítka (špaleta) kolem rámu, velký prázdný okraj. Vybraná varianta = `dvere-magnific.png`.
- **Kóty dokresleny přesně v SVG** (`navod-mereni.svg`, přes Magnific obrázek jako `<image>`): šířka + výška otvoru (špaleta, červeně) a boční řez s tloušťkou zdi (modře). Špaletu jsem napozicoval podle rámu (tmavě hnědé pixely) + ~70 px omítky.
- Překreslit: nový Magnific obrázek → najít rám (bounding box tmavých pixelů) → upravit souřadnice v SVG → Playwright `rend.cjs` → zmenšit na šířku 1200 px.
