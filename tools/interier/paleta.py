# -*- coding: utf-8 -*-
import os, statistics
from PIL import Image
from dekory_data import P
KAT = os.path.join(os.path.dirname(__file__), '..', '..', 'katalog-akcni')
_c = {}
def hexy():
    out = {}
    for k, (n, pg, x, y, w) in P.items():
        if pg == 0: out[k] = x.upper(); continue
        im = _c.get(pg) or _c.setdefault(pg, Image.open(os.path.join(KAT, 's-%02d.jpg' % pg)).convert('RGB'))
        px = [im.getpixel((xx, yy)) for xx in range(x - 16, x + 17, 2) for yy in range(y - 9, y + 10, 2)]
        out[k] = '#%02X%02X%02X' % tuple(int(statistics.median(p[i] for p in px)) for i in range(3))
    return out
if __name__ == '__main__':
    h = hexy()
    # kontrolní arch: vzorek + název
    from PIL import ImageDraw
    ks = list(P); W = 6; im = Image.new('RGB', (W * 200, (len(ks) + W - 1) // W * 44), 'white'); d = ImageDraw.Draw(im)
    for i, k in enumerate(ks):
        x, y = i % W * 200, i // W * 44; d.rectangle((x + 4, y + 4, x + 60, y + 38), fill=h[k], outline='#999'); d.text((x + 66, y + 8), k, fill='black'); d.text((x + 66, y + 22), h[k], fill='black')
    im.save('/tmp/claude-0/-home-claude/9186fc79-8ad8-53fa-b5c2-675a3a6e04ad/scratchpad/kat/paleta.png'); print(len(ks))
