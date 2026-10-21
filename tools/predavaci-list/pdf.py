"""Sestaví prodejce/predavaci-list.pdf: python3 gen.py (vytvoří pl.html) && python3 pdf.py.
QR kódy (servis.svg, reg.svg, google.svg, fb.svg) jsou hotové soubory ve složce; text se mění v gen.py (pozice v pt podle původního listu,
pole pro vyplnění musí zůstat na místě – ověřit: python3 ../aktualizace_predloh.py --check). Potřebuje Playwright + písmo Inter."""
from pathlib import Path
from playwright.sync_api import sync_playwright
d = Path(__file__).resolve().parent
with sync_playwright() as p:
    b = p.chromium.launch(); pg = b.new_page(); pg.goto((d / 'pl.html').as_uri()); pg.wait_for_timeout(500)
    pg.pdf(path=str(d.parent.parent / 'prodejce' / 'predavaci-list.pdf'), width='8.2767in', height='11.6933in', print_background=True, page_ranges='1'); b.close()
