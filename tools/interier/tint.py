# -*- coding: utf-8 -*-
"""Orientační přebarvení dveří do barvy dekoru – stejný postup jako tintDoor() v aplikace/app.js (jen pro náhled a kontrolu)."""
from PIL import Image
import numpy as np
def tint(im, hexc, wood):
    a = np.asarray(im.convert('RGB')).astype(np.float32); h, w, _ = a.shape
    base = np.median(a[int(h*.05):int(h*.95), int(w*.1):int(w*.9)].reshape(-1, 3), axis=0)
    L = a @ np.array([.299, .587, .114], np.float32); lb = float(base @ np.array([.299, .587, .114])) or 1.
    k = L / lb
    d = np.abs(a - base[None, None, :] * k[..., None]).max(axis=2)
    wgt = np.clip(1 - (d - 22) / 26, 0, 1) * np.clip((k - .35) / .2, 0, 1) * np.clip((1.9 - k) / .25, 0, 1)
    kk = np.clip(k if wood else 1 + (k - 1) * .3, .6, 1.35)
    t = np.array([int(hexc[i:i+2], 16) for i in (1, 3, 5)], np.float32)
    out = np.clip(t[None, None, :] * kk[..., None], 0, 255) * wgt[..., None] + a * (1 - wgt[..., None])
    return Image.fromarray(out.astype(np.uint8))
if __name__ == '__main__':
    import os
    from paleta import hexy
    from dekory_data import P
    H = hexy(); src = '../../aplikace/img/interier/'
    ms = ['miskant', 'laurencia', 'daglezia', 'frezia', 'altamura', 'nemezia', 'verte-premium-e', 'harmony', 'decorato', 'vector']
    ks = ['p-bila', 's-dub', 's-antracit', 'g-orech', 's-salvej', 'B707']
    sheet = Image.new('RGB', (len(ms) * 125, 7 * 285), 'white')
    for i, m in enumerate(ms):
        im = Image.open(src + m + '.jpg').resize((120, 280)); sheet.paste(im, (i * 125, 0))
        for j, k in enumerate(ks): sheet.paste(tint(im, H[k], P[k][4]), (i * 125, (j + 1) * 285))
    sheet.save('/tmp/claude-0/-home-claude/9186fc79-8ad8-53fa-b5c2-675a3a6e04ad/scratchpad/kat/tint.jpg', quality=88)
