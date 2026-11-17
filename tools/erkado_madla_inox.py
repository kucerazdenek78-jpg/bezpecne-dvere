# -*- coding: utf-8 -*-
"""Madla v konfigurátoru ERKADO: z černých výřezů z katalogu (str. 69) udělá orientační nerezové (inox) provedení.
Katalog ukazuje madla jen černá → tmavé body madla se převedou na světlou kovovou šeď se zachovaným stínováním.
Spuštění: python3 tools/erkado_madla_inox.py  (vytvoří konfigurator-erkado/img/madla/<id>-inox.jpg)"""
import os, glob
import numpy as np
from PIL import Image
D = os.path.join(os.path.dirname(__file__), '..', 'konfigurator-erkado', 'img', 'madla')
for f in sorted(glob.glob(os.path.join(D, '*.jpg'))):
    if f.endswith('-inox.jpg'): continue
    a = np.asarray(Image.open(f).convert('RGB')).astype(np.float32)
    L = a @ np.array([.299, .587, .114], np.float32)
    w = np.clip((150 - L) / 45, 0, 1)                      # 1 = madlo (tmavé), 0 = pozadí a stín
    k = np.clip(112 + (L - 22) * 1.7, 96, 205)            # kovová šeď, hrany a plochy si nechají rozdíl jasu
    inox = np.stack([k * .985, k * .995, k * 1.01], axis=2) # lehce studený tón
    out = np.clip(inox * w[..., None] + a * (1 - w[..., None]), 0, 255).astype(np.uint8)
    Image.fromarray(out).save(f[:-4] + '-inox.jpg', quality=88); print(os.path.basename(f), '→ inox')
