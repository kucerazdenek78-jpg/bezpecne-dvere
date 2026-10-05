#!/usr/bin/env python3
"""Zkontroluje aktuální akční katalog na svetdveri.cz a při změně aktualizuje web.

- najde nejnovější PDF (odkazy na stránkách z discover_pages, jinak pdf_url)
- porovná otisk (sha256) s uloženým v katalog-akcni/source.json
- při změně: uloží PDF jako akciovy-katalog.pdf, vyrenderuje strany do katalog-akcni/s-NN.jpg,
  přepíše počet stran a označení (např. 9/2026) v HTML
- vypíše do $GITHUB_OUTPUT: changed=true/false
Použití: python3 tools/update_katalog.py [--file lokalni.pdf]
"""
import hashlib, json, os, re, subprocess, sys, glob, urllib.request, urllib.parse, tempfile, shutil

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'katalog-akcni', 'source.json')
UA = {'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'}

def get(url, binary=False):
    with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=90) as r:
        d = r.read()
    return d if binary else d.decode('utf-8', 'replace')

def label_from(url):
    m = re.search(r'(\d{1,2})[_-](\d{2})(?!\d)', urllib.parse.unquote(url).split('/')[-1])
    return f'{int(m.group(1))}/20{m.group(2)}' if m else None

def discover(cfg):
    found = []
    for pg in cfg.get('discover_pages', []):
        try:
            html = get(pg)
        except Exception as e:
            print('! stránku', pg, 'se nepodařilo načíst:', e); continue
        for h in re.findall(r'href=["\']([^"\']+\.pdf)["\']', html, re.I):
            h = urllib.parse.urljoin(pg, h)
            if re.search(r'katalog', h, re.I) and re.search(r'ak[cč]', h, re.I):
                found.append(h)
    def key(u):
        m = re.search(r'/uploads/(\d{4})/(\d{2})/', u)
        return (int(m.group(1)), int(m.group(2))) if m else (0, 0), u
    return sorted(set(found), key=key)

def main():
    cfg = json.load(open(SRC))
    if '--file' in sys.argv:
        path = sys.argv[sys.argv.index('--file') + 1]; data = open(path, 'rb').read(); url = cfg['pdf_url']
    else:
        cands = discover(cfg)
        url = cands[-1] if cands else cfg['pdf_url']
        print('Zdroj katalogu:', url, '(nalezeno odkazů:', len(cands), ')')
        data = get(url, binary=True)
    if not data.startswith(b'%PDF'):
        sys.exit('Stažený soubor není PDF')
    sha = hashlib.sha256(data).hexdigest()
    if sha == cfg.get('sha256'):
        print('Katalog se nezměnil.'); out('changed=false'); return
    print('Katalog je nový/změněný, aktualizuji web…')
    tmp = tempfile.mkdtemp(); pdf = os.path.join(tmp, 'k.pdf'); open(pdf, 'wb').write(data)
    subprocess.check_call(['pdftoppm', '-jpeg', '-jpegopt', 'quality=85', '-scale-to-x', '1240', '-scale-to-y', '-1', pdf, os.path.join(tmp, 's')])
    pages = sorted(glob.glob(os.path.join(tmp, 's-*.jpg')))
    if not pages: sys.exit('Nepodařilo se vyrenderovat strany')
    kd = os.path.join(ROOT, 'katalog-akcni')
    for f in glob.glob(os.path.join(kd, 's-*.jpg')): os.remove(f)
    for i, f in enumerate(pages, 1): shutil.copy(f, os.path.join(kd, f's-{i:02d}.jpg'))
    shutil.copy(pdf, os.path.join(ROOT, 'akciovy-katalog.pdf'))
    n = len(pages)
    from PIL import Image
    w, h = Image.open(pages[0]).size
    old_label = cfg.get('label') or ''
    new_label = label_from(url) or old_label
    page = os.path.join(ROOT, 'katalog-akcni.html'); t = open(page, encoding='utf-8').read()
    t = re.sub(r'const TOTAL = \d+;', f'const TOTAL = {n};', t)
    t = re.sub(r'const PAGE_W = \d+, PAGE_H = \d+;', f'const PAGE_W = {w}, PAGE_H = {h};', t)
    t = re.sub(r'(id="pageInput" min="1" max=")\d+', rf'\g<1>{n}', t)
    t = re.sub(r'(id="pageTotal">)\d+', rf'\g<1>{n}', t)
    open(page, 'w', encoding='utf-8').write(t)
    if old_label and new_label != old_label:
        for f in ['katalog-akcni.html', 'dvere-do-interieru.html', 'dokumenty.html']:
            p = os.path.join(ROOT, f); s = open(p, encoding='utf-8').read()
            open(p, 'w', encoding='utf-8').write(s.replace(old_label, new_label))
    size = os.path.getsize(os.path.join(ROOT, 'akciovy-katalog.pdf')) / 1e6
    p = os.path.join(ROOT, 'dokumenty.html'); s = open(p, encoding='utf-8').read()
    open(p, 'w', encoding='utf-8').write(re.sub(r'↓ PDF \([\d.,]+ MB\)', f'↓ PDF ({size:.0f} MB)', s))
    cfg.update(pdf_url=url, sha256=sha, pages=n, label=new_label)
    json.dump(cfg, open(SRC, 'w'), indent=2, ensure_ascii=False)
    out('changed=true'); out(f'pages={n}'); out(f'label={new_label}'); out(f'url={url}')
    print(f'Hotovo: {n} stran, označení {new_label}')

def out(line):
    print(line)
    if os.environ.get('GITHUB_OUTPUT'):
        open(os.environ['GITHUB_OUTPUT'], 'a').write(line + '\n')

main()
