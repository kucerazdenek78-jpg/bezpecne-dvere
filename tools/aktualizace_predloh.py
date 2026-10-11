#!/usr/bin/env python3
"""Synchronizace předloh prodejce s webovým generátorem PDF.

Spouštět vždy, když se změní některá předloha v prodejce/:
  - objednavka-*.pdf   → právní text (smluvní ujednání) a řádek zhotovitele se propíše
                         do poptavka-pdf/poptavka-pdf.js (konstanty LEGAL a ZHOTOVITEL),
                         takže PDF objednávky z webu má vždy stejný text jako papírová objednávka
  - predavaci-list.pdf → zkontroluje, že popisky políček jsou na stejném místě, kam web
                         dopisuje údaje (jinak vypíše, co se posunulo)

Použití:  python3 tools/aktualizace_predloh.py          (změní soubor, vypíše shrnutí)
          python3 tools/aktualizace_predloh.py --check  (jen kontrola, nic nemění; exit 1 při rozdílu)
Potřebuje: pdftotext (poppler-utils).
"""
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
GEN = ROOT / 'poptavka-pdf' / 'poptavka-pdf.js'
MODELY = ['premium-60', 'extreme-54', 'magnum-56', 'securido-65']
ZACATEK, KONEC = 'Zhotovitel se zavazuje', 'naleznete na www.bezpecne-dvere.cz.'
ODSTAVCE = ['Práva z vadného plnění', 'Nedílnou součástí', 'Byla-li tato objednávka', 'K mimosoudnímu řešení']

# kde web dopisuje údaje do předávacího listu: popisek → (xMin, yMin) v pt, tolerance 3 pt
PL_POLICKA = {
    'Model': (49.5, 208.8), 'objednávky': (331.9, 208.8), 'Počet': (49.5, 259.8),
    'Místo': (50.2, 761.5), 'Objednatel': (392.2, 761.5),
}


def text(pdf, *args):
    return subprocess.run(['pdftotext', *args, str(pdf), '-'], capture_output=True, text=True, check=True).stdout


def pravni_text(pdf):
    t = text(pdf, '-raw')
    a, b = t.index(ZACATEK), t.index(KONEC) + len(KONEC)
    s = re.sub(r'-\n(?=[a-zá-ž])', '', t[a:b])
    s = re.sub(r'\s*\n\s*', ' ', s)
    for z in ODSTAVCE:
        s = s.replace(' ' + z, '\n' + z)
    return s.strip().split('\n')


def zhotovitel(pdf):
    t = text(pdf, '-raw')
    radky = t[:t.index('Objednávka')].split('\n')
    s = ''
    for r in (r.strip() for r in radky if r.strip()):
        s += (r if not s else ((' ' if s[-1] in '.,;:' else '. ') + r))
    s = re.sub(r'\s+', ' ', s).replace('KK SYSTEM.', 'KK SYSTEM,')
    return s.rstrip(',')


def kontrola_predavaci_list():
    out = text(ROOT / 'prodejce' / 'predavaci-list.pdf', '-bbox')
    slova = re.findall(r'xMin="([\d.]+)" yMin="([\d.]+)"[^>]*>([^<]+)</word>', out)
    chyby = []
    for popisek, (x, y) in PL_POLICKA.items():
        nalez = [(float(a), float(b)) for a, b, w in slova if w == popisek and float(b) > 150]
        if not any(abs(a - x) <= 3 and abs(b - y) <= 3 for a, b in nalez):
            chyby.append('%s: čekám (%.1f, %.1f), v PDF %s' % (popisek, x, y, nalez or 'nenalezeno'))
    return chyby


def main():
    jen_kontrola = '--check' in sys.argv
    texty = {m: pravni_text(ROOT / 'prodejce' / f'objednavka-{m}.pdf') for m in MODELY}
    zaklad = texty[MODELY[0]]
    ruzne = [m for m in MODELY if texty[m] != zaklad]
    if ruzne:
        print('POZOR: právní text se liší u modelů:', ', '.join(ruzne), '– web používá text z', MODELY[0])
    zh = zhotovitel(ROOT / 'prodejce' / f'objednavka-{MODELY[0]}.pdf')

    js = GEN.read_text(encoding='utf8')
    novy_legal = 'var LEGAL = ' + json.dumps(zaklad, ensure_ascii=False, indent=4).replace('\n', '\n  ') + ';'
    novy_zh = 'var ZHOTOVITEL = ' + json.dumps(zh, ensure_ascii=False).replace('"', "'") + ';'
    js2 = re.sub(r'var LEGAL = \[.*?\];', lambda _: novy_legal, js, count=1, flags=re.S)
    js2 = re.sub(r"var ZHOTOVITEL = '.*?';", lambda _: novy_zh, js2, count=1)

    chyby = kontrola_predavaci_list()
    for c in chyby:
        print('Předávací list – posunuté políčko:', c)

    if js2 == js:
        print('Právní text a zhotovitel: web je aktuální.')
    elif jen_kontrola:
        print('Právní text nebo zhotovitel se liší od předloh – spusťte bez --check.')
    else:
        GEN.write_text(js2, encoding='utf8')
        print('Aktualizováno: poptavka-pdf/poptavka-pdf.js (LEGAL, ZHOTOVITEL). Zvyšte ?v= u poptavka-pdf.js v HTML.')
    if not chyby:
        print('Předávací list: políčka sedí.')
    sys.exit(1 if (jen_kontrola and (js2 != js or chyby or ruzne)) else 0)


if __name__ == '__main__':
    main()
