# -*- coding: utf-8 -*-
"""Sjednocení karet interiérových dveří (dvere-do-interieru.html) a odstíny do vizualizace (aplikace/viz-data.js).
Spuštění: python3 tools/interier/karty.py   (z kořene repozitáře; dá se pouštět opakovaně)
Dekory a jejich přiřazení k modelům: tools/interier/dekory_data.py (podle akčního katalogu)."""
import re, os, sys, json, html
sys.path.insert(0, os.path.dirname(__file__))
from dekory_data import P, MODELY
from paleta import hexy
ROOT = os.path.join(os.path.dirname(__file__), '..', '..')
H = hexy()
MAXCH = 8   # kolik koleček se ukáže na kartě, zbytek „+ N“

# modely, které ve vizualizaci chyběly: obrázek (těsný ořez křídla), popis, cena, skladem
NOVE = {'VERTE HOME G.4': ['img/interier/verte-home-g4.jpg', 'Soft CPL · falcové', 'od 6 300 Kč', True]}

def sklon(n): return '1 dekor' if n == 1 else ('%d dekory' % n if n < 5 else '%d dekorů' % n)
def dekory(nazev):
    seen, out = set(), []
    for k in MODELY[nazev][1]:
        n = P[k][0]
        if n.lower() in seen: continue
        seen.add(n.lower()); out.append((n, H[k], P[k][4]))
    return out
def svetla(hx): return sum(int(hx[i:i+2], 16) for i in (1, 3, 5)) > 660
def chips(nazev):
    d = dekory(nazev); o = '<div class="color-chips">'
    for n, hx, w in d[:MAXCH]:
        o += '<span class="chip" style="background:%s;%s" title="%s"></span>' % (hx, 'border-color:#ccc;' if svetla(hx) else '', html.escape(n))
    if len(d) > MAXCH: o += '<span class="chip-more">+%d</span>' % (len(d) - MAXCH)
    o += '<span class="chip-label">%s</span></div>' % (html.escape(d[0][0]) if len(d) == 1 else sklon(len(d)))
    if len(d) > 1:
        o += '\n            <details class="dekory-vse"><summary>Názvy dekorů</summary><p>%s</p></details>' % html.escape(', '.join(n for n, _, _ in d))
    return o

STAR = '<svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1l2.39 4.84L18 6.82l-4 3.9.94 5.49L10 13.77l-4.94 2.44L6 10.72 2 6.82l5.61-.98z"/></svg>'
def text(x): return re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', x)).strip()

def karta(p):
    sklad = 'int-card-sklad' in p[:60]
    nm = text(re.search(r'int-card-name">(.*?)</div>', p, re.S).group(1)).replace('SKLADEM', '').replace('NA OBJEDNÁVKU', '').strip()
    img = re.search(r'<img [^>]*class="int-card-img"[^>]*>', p).group(0)
    det = re.search(r'href="(detail-[^"]+)"', p); det = det.group(1) if det else ''
    pick = re.search(r"pick\('([^']*)'\)", p).group(1)
    pg = re.search(r'strana-(\d+)', p).group(1)
    zb = 'zaruben-badge' in p
    sub = re.search(r'int-card-sub">(.*?)</div>', p, re.S).group(1).strip()
    casti = [c.strip() for c in sub.split('·')]
    casti = [c for c in casti if not re.search(r'dekor|^bílá$|^biela$|\d+ skupin|\d+ barev', text(c), re.I)]
    sub = ' · '.join(casti)
    # cena: hlavní řádek „Komplet od X Kč“, pod ním doplňky
    m = re.search(r'<strong>\s*(?:od\s*)?([\d ]+Kč)\s*</strong>', p); cena = m.group(1).strip()
    dopl = [text(y) for x in re.findall(r'<div class="int-card-price-wing">(.*?)</div>', p, re.S) for y in x.split('<br>')]
    dopl = [html.unescape(x) for x in dopl if x and x != '&nbsp;']
    stara = re.search(r'<div class="int-card-price">(.*?)</div>', p, re.S)
    if stara:
        zb2 = stara.group(1).split('</strong>', 1)[1]
        zav = re.match(r'\s*\(([^)]*)\)', zb2); kus = []
        if zav: kus.append(zav.group(1)); zb2 = zb2[zav.end():]
        t = text(zb2.replace('<br>', ' '))
        if t: kus.append(t)
        if kus: dopl = [' · '.join(kus)]
    d = dekory(nm)
    viz = 'aplikace/#viz?m=interier&amp;d=' + html.escape(re.sub(r'%20', '+', __import__('urllib.parse').parse.quote(nm, safe='')))
    o = '<div class="int-card%s">\n' % (' int-card-sklad' if sklad else '')
    o += '          <a href="%s" class="int-card-img-wrap">\n            %s\n          </a>\n' % (det or 'katalog-akcni.html#strana-' + pg, img)
    o += '          <div class="int-card-body">\n'
    o += '            <div class="int-card-name">%s <span class="%s">%s</span></div>\n' % (html.escape(nm), 'badge-skladem' if sklad else 'badge-skladem badge-objednavka', 'SKLADEM' if sklad else 'NA OBJEDNÁVKU')
    o += '            <div class="int-card-sub">%s</div>\n' % sub
    o += '            %s\n' % chips(nm)
    if zb: o += '            <div class="zaruben-badge">%s Zárubeň v ceně</div>\n' % STAR
    o += '            <div class="int-card-price-line">Komplet od <strong>%s</strong></div>\n' % cena
    o += '            <div class="int-card-price-wing">%s</div>\n' % ('<br>'.join(html.escape(x) for x in dopl) if dopl else '&nbsp;')
    o += '            <div class="int-card-actions">\n'
    o += '              <a class="int-card-btn-foto" href="%s">📷 Ukázat na mé fotce</a>\n' % viz
    o += '              <a class="int-card-btn" href="#poptavka" onclick="setTimeout(function(){PoptavkaKat.pick(\'%s\')},80)">Poptávka →</a>\n' % pick
    o += '            </div>\n'
    o += '            <div class="int-card-links">%s<a href="katalog-akcni.html#strana-%s">📖 Katalog str. %s</a></div>\n' % ('<a href="%s">Detail →</a>' % det if det else '', pg, pg)
    o += '          </div>\n        </div>'
    return nm, o, (text(sub), 'od ' + cena, sklad)

INFO = {}   # název → (popis, cena, skladem) z karet na webu, přebírá je vizualizace
def web():
    f = os.path.join(ROOT, 'dvere-do-interieru.html'); s = open(f, encoding='utf-8').read()
    rx = re.compile(r'<div class="int-card(?: int-card-sklad)?">.*?class="int-card-(?:catalog"[^>]*>[^<]*</a>|links">.*?</div>)\s*</div>\s*</div>', re.S)
    jmena = []
    def rep(m):
        nm, o, inf = karta(m.group(0)); jmena.append(nm); INFO[nm] = inf; return o
    s2 = rx.sub(rep, s)
    chybi = [n for n in jmena if n not in MODELY]; assert not chybi, chybi
    open(f, 'w', encoding='utf-8').write(s2); print('karet:', len(jmena)); return jmena

def viz(jmena):
    f = os.path.join(ROOT, 'aplikace', 'viz-data.js'); s = open(f, encoding='utf-8').read()
    a = s.index('"interier": ['); b = s.index('\n]', a)
    rad = [l for l in s[a:b].split('\n')[1:] if l.strip()]
    out = []; vidim = set()
    for l in rad:
        x = json.loads(l.rstrip().rstrip(','))
        nm = x[0]; vidim.add(nm)
        if nm not in MODELY: print('  viz: model mimo web', nm); out.append(x); continue
        stare = {d[0]: d[1] for d in (x[5] if len(x) > 5 else []) if isinstance(d[1], str) and not d[1].startswith('#')}
        stare = {{'Cappuccino': 'Kašmír st', 'Dub přírodní': 'Dub st', 'Bílá': 'Bílá st'}.get(k, k) if MODELY[nm][0] in (8, 9) else k: v for k, v in stare.items()}
        stare = {{'Bílá': 'Lindo bílá'}.get(k, k) if MODELY[nm][0] == 7 else k: v for k, v in stare.items()}
        dk = []
        for n, hx, w in dekory(nm):
            dk.append([n, stare[n]] if n in stare else [n, hx, w])
        dk.sort(key=lambda d: 0 if not d[1].startswith('#') else 1)   # nejdřív skutečné fotky z katalogu
        if len(dk) == 1 and dk[0][1].startswith('#'): dk = [[dk[0][0], x[1]]]   # jediný dekor = fotka z katalogu
        elif dk and all(d[1].startswith('#') for d in dk): dk.insert(0, ['Foto z katalogu', x[1]])   # výchozí = původní fotka, dekory jen orientačně
        x = [x[0], x[1], INFO[nm][0], INFO[nm][1], INFO[nm][2], dk]
        out.append(x)
    for n in jmena:
        if n in vidim: continue
        if n in NOVE:   # model je na webu, ve vizualizaci ještě ne → doplnit (za ostatní skladové)
            x = list(NOVE[n]); x = [n] + x + [[[a, x[0]] for a, b, c in dekory(n)]]
            i = max([k for k, y in enumerate(out) if y[4]] + [-1]) + 1; out.insert(i, x)
        else: print('  viz: chybí model z webu', n)
    body = ',\n'.join(json.dumps(x, ensure_ascii=False) for x in out)
    s = s[:a] + '"interier": [\n' + body + s[b:]
    s = re.sub(r'^/\*.*?\*/', '/* data vizualizace: názvy a orientační ceny z webu (dvere-do-domu.html, dvere-do-interieru.html) – interiérové: 5. pole = skladem, 6. pole = dekory z akčního katalogu: [název, obrázek] = fotka dveří z katalogu, [název, "#barva", dřevo 0/1] = orientační přebarvení podle vzorníku (tools/interier/karty.py) */', s, count=1, flags=re.S)
    open(f, 'w', encoding='utf-8').write(s); print('viz modelů:', len(out))

if __name__ == '__main__':
    viz(web())
