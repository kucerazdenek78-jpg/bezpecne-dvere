# -*- coding: utf-8 -*-
"""Dekory interiérových dveří podle akčního katalogu (10/2026).
Vzorníky: str. 17 (ERKADO), 23 (INVADO), 33 (PORTA); které skupiny platí pro který model, je na straně modelu.
Barva kolečka = medián vzorku na straně vzorníku (souřadnice v obrázku katalog-akcni/s-NN.jpg, 1240×1754).
w = 1 dřevodekor, 0 jednobarevný (uni)."""

# klíč: (název česky, strana, x, y, dřevo)
P = {
 # ── ERKADO str. 17 ──
 'g-bila': ('Bílá', 17, 80, 270, 0), 'g-snehobila': ('Sněhobílá', 17, 80, 345, 0), 'g-sonoma': ('Sonoma', 17, 80, 420, 1),
 'g-dub': ('Dub', 17, 80, 497, 1), 'g-dub-stredni': ('Dub střední', 17, 80, 570, 1), 'g-orech': ('Ořech', 17, 80, 648, 1),
 'g-dub-sedy': ('Dub šedý', 17, 80, 722, 1), 'g-dub-zlaty': ('Dub zlatý', 17, 80, 797, 1),
 'p-bila': ('Bílá premium', 17, 310, 270, 0), 'p-kura': ('Kůra bílá', 17, 310, 345, 0), 'p-javor': ('Javor šedý', 17, 310, 422, 1),
 'p-dub': ('Dub premium', 17, 310, 497, 1), 'p-jasan': ('Jasan grafit', 17, 310, 570, 1), 'p-orech': ('Ořech klasik', 17, 310, 648, 1),
 'p-dub-prir': ('Dub přírodní', 17, 310, 724, 1), 'p-dub-pricny': ('Dub příčný přírodní', 17, 310, 797, 1), 'p-beton': ('Beton', 17, 310, 872, 0),
 's-dub-sedy': ('Dub šedý st', 17, 598, 270, 1), 's-dub': ('Dub st', 17, 598, 345, 1), 's-wenge': ('Wenge dark st', 17, 598, 422, 1),
 's-bila': ('Bílá st', 17, 598, 497, 0), 's-kasmir': ('Kašmír st', 17, 598, 570, 0), 's-svetleseda': ('Světlešedá st', 17, 598, 646, 0),
 's-cerna': ('Černá st', 17, 598, 722, 0), 's-kamenna': ('Kamenná šedá st', 17, 598, 797, 0), 's-salvej': ('Šalvěj st', 17, 598, 870, 0),
 's-popelava': ('Popelavá st', 17, 855, 270, 0), 's-antracit': ('Antracit st', 17, 855, 347, 0),
 # ── INVADO str. 23 ──
 'B134': ('Lindo bílá', 23, 100, 213, 0), 'B707': ('Dub zimní', 23, 100, 289, 1), 'B587': ('Dub naturální', 23, 100, 365, 1),
 'B597': ('Ořech klasický', 23, 100, 438, 1), 'B705': ('Dub jarní', 23, 100, 513, 1), 'B637': ('Antracit', 23, 100, 587, 0),
 'B772': ('Dub skandinávský', 23, 100, 667, 1), 'B771': ('Dub karlstad', 23, 100, 741, 1),
 'B598': ('Dub popelavý', 23, 350, 214, 1), 'B708': ('Modřín sibiřský', 23, 350, 289, 1), 'B596': ('Dub sněžný', 23, 350, 363, 1),
 'B541': ('Dub ušlechtilý', 23, 350, 438, 1), 'B639': ('Dub evropský', 23, 350, 513, 1), 'B706': ('Dub podzimní', 23, 350, 585, 1),
 'B731': ('Dub Charleston', 23, 610, 213, 1), 'B734': ('Perleťově šedá', 23, 610, 288, 0), 'B747': ('Borovice polární', 23, 610, 365, 1),
 'B672': ('Diamantová šedá', 23, 610, 437, 0), 'B733': ('Dub Hamilton přírodní', 23, 610, 512, 1), 'B746': ('Dub nebraska šedý', 23, 610, 587, 1),
 'B687': ('Alpská bílá', 23, 610, 664, 0), 'B727': ('Beton světlý', 23, 610, 742, 0),
 'B739': ('Bílá matná', 23, 920, 214, 0), 'B726': ('Černá matná', 23, 920, 288, 0), 'B742': ('Dub Catania', 23, 920, 363, 1),
 'B744': ('Dub Flagstaff', 23, 920, 437, 1), 'B743': ('Dub Narbonne koňakový', 23, 920, 512, 1), 'B741': ('Grafit matný', 23, 920, 586, 0),
 'B770': ('Kašmír matný', 23, 920, 664, 0), 'B753': ('Světle šedá matná', 23, 920, 742, 0), 'B769': ('Zelená matná', 23, 920, 816, 0),
 'B784': ('Dub grandson', 23, 920, 891, 1), 'B786': ('Dub Narbonne sluneční', 23, 920, 965, 1), 'B787': ('Dub Narbonne pískový', 23, 920, 1039, 1),
 # ── PORTA str. 33 ──
 't1-wenge-white': ('Wenge white', 33, 92, 196, 1), 't1-dub-tmavy': ('Dub tmavý', 33, 92, 254, 1), 't1-dub-klasicky': ('Dub klasický', 33, 92, 314, 1),
 't2-dub-matny': ('Dub matný', 33, 94, 409, 1), 't2-dub-naturalni': ('Dub naturální', 33, 94, 469, 1), 't2-dub-matny-tmavy': ('Dub matný tmavý', 33, 94, 530, 1),
 't2-dub-kalifornia': ('Dub kalifornia', 33, 94, 588, 1),
 't3-orech': ('Ořech naturální', 33, 100, 680, 1),
 'r1-craft': ('Dub craft zlatý', 33, 396, 196, 1), 'r1-akat-stribrny': ('Akát stříbrný', 33, 396, 254, 1), 'r1-catania': ('Dub catania', 33, 396, 312, 1),
 'r1-akat-medovy': ('Akát medový', 33, 396, 370, 1), 'r1-sarlatovy': ('Dub šarlatový', 33, 396, 427, 1), 'r1-mauvella': ('Dub Mauvella', 33, 396, 484, 1),
 'r2-hikora-svetly': ('Hikora Jackson světlý', 33, 396, 588, 1), 'r2-hamilton': ('Dub Anglický Hamilton', 33, 396, 644, 1), 'r2-hikora-tmavy': ('Hikora Jackson tmavý', 33, 396, 704, 1),
 'r3-craft': ('Dub craft zlatý', 33, 396, 798, 1),
 'm-salvador-beleny': ('Dub Salvador bělený', 33, 662, 188, 1), 'm-salvador-svetly': ('Dub Salvador světlý', 33, 662, 248, 1), 'm-arles-prir': ('Dub Arles přírodní', 33, 662, 310, 1),
 'm-arles-toffe': ('Dub Arles Toffee', 33, 662, 370, 1), 'm-arles-tmavy': ('Dub Arles tmavý', 33, 662, 428, 1), 'm-hawana': ('Dub Hawana', 28, 150, 843, 1),
 'u-bila': ('Bílá', 33, 944, 427, 0), 'u-seda': ('Šedá', 33, 944, 254, 0), 'u-fjord': ('Fjord', 33, 944, 310, 0), 'u-oliva': ('Oliva', 33, 944, 370, 0), 'u-kasmir': ('Kašmír', 33, 944, 196, 0),
 'u-salvej': ('Šalvěj', 33, 944, 706, 0),
 'c-bila-str': ('Bílá struktura', 33, 944, 806, 0), 'c-antracit-str': ('Antracit struktura', 33, 944, 864, 0),
 # ── SVETDVERI str. 4 (barvy z dosavadních karet) ──
 'sv-dub': ('Dub Halifax', 0, '#C49A6C', 0, 1), 'sv-bila': ('Bílá', 0, '#F5F2EE', 0, 0), 'sv-kasmir': ('Kašmír', 0, '#D9CEBF', 0, 0),
}

PREM7 = ['p-orech', 'p-bila', 'p-javor', 'p-dub-prir', 'p-dub', 'p-jasan', 'p-kura']
PREM9 = PREM7 + ['p-dub-pricny', 'p-beton']
STCPL = ['s-cerna', 's-antracit', 's-dub-sedy', 's-bila', 's-dub', 's-kasmir', 's-popelava', 's-salvej', 's-kamenna', 's-wenge', 's-svetleseda']
GREKO7 = ['g-orech', 'g-dub', 'g-dub-sedy', 'g-dub-stredni', 'g-sonoma', 'g-snehobila', 'g-bila']
GREKO8 = GREKO7 + ['g-dub-zlaty']
END10 = ['B707', 'B587', 'B596', 'B134', 'B771', 'B705', 'B708', 'B706', 'B639', 'B772']
END14 = END10 + ['B541', 'B597', 'B598', 'B637']
LAM = ['B739', 'B726', 'B742', 'B744', 'B743', 'B741', 'B770', 'B753', 'B769', 'B784', 'B786', 'B787']
ICPL = ['B687', 'B672', 'B731', 'B733', 'B746', 'B734', 'B747', 'B727']
T1 = ['t1-wenge-white', 't1-dub-klasicky', 't1-dub-tmavy']
T2_3 = ['t2-dub-matny', 't2-dub-naturalni', 't2-dub-matny-tmavy']
T2_4 = T2_3 + ['t2-dub-kalifornia']
R1 = ['r1-craft', 'r1-akat-stribrny', 'r1-catania', 'r1-akat-medovy', 'r1-sarlatovy', 'r1-mauvella']
R2 = ['r2-hikora-svetly', 'r2-hamilton', 'r2-hikora-tmavy']
MOD5 = ['m-salvador-beleny', 'm-salvador-svetly', 'm-arles-prir', 'm-arles-toffe', 'm-arles-tmavy']
SV = ['sv-dub', 'sv-bila', 'sv-kasmir']
ST3 = ['s-dub', 's-bila', 's-kasmir']
INV5 = ['B639', 'B134', 'B598', 'B770', 'B753']

# název karty na webu → (strana katalogu s dekory, seznam dekorů)
MODELY = {
 'PATRAS': (4, SV), 'KAVALA': (4, SV), 'LARISA': (4, SV),
 'FRÉZIA 1': (9, ST3), 'FRÉZIA 9': (9, ['s-bila', 's-kasmir']), 'NEMÉZIA 14': (9, ['s-bila', 's-kasmir']),
 'LAURENCIA 1': (8, ST3), 'ALTAMURA 1': (8, ['s-bila']), 'ALTAMURA 1 – falcové': (8, ST3),
 'LAURENCIA': (11, PREM9 + STCPL), 'DAGLÉZIA': (12, PREM7 + STCPL + GREKO8), 'MAGNÓLIA': (12, PREM7 + STCPL + GREKO8),
 'FRÉZIA': (13, PREM7 + STCPL + GREKO7), 'ALTAMURA': (14, PREM9 + STCPL), 'ALTAMURA LUX': (14, PREM9 + STCPL),
 'NEMÉZIA': (15, PREM7 + STCPL + GREKO7), 'MISKANT': (16, PREM7 + STCPL + GREKO8),
 'LINEA FORTE 5': (7, INV5), 'NORMA DECOR 1': (7, ['B639', 'B134', 'B598', 'B753']), 'AVERSA 1': (7, INV5),
 'DECORATO · BELGATTO': (18, END10 + LAM), 'LINEA FORTE': (19, END14 + LAM), 'NOGARO · TAMPARO': (20, END14 + LAM),
 'LIBRA · NORMA DECOR': (21, END14 + LAM + ICPL),
 'CAPRIO · NIDA · ROMA': (22, ['B707', 'B587', 'B596', 'B134', 'B705', 'B708', 'B706', 'B639', 'B637', 'B541', 'B597', 'B598']),   # B339 je na str. 22, ale ve vzorníku str. 23 chybí
 'VERTE HOME G.4': (10, ['u-bila']), 'CPL 1.1': (10, ['u-bila']), 'DECOR P': (10, ['u-bila']),
 'VECTOR': (24, ['u-bila', 'u-seda', 'u-kasmir', 'u-salvej']),
 'VERTE HOME G / H': (25, T1 + T2_3 + R1 + ['u-seda', 'u-fjord', 'u-bila', 'u-oliva', 'u-kasmir']),
 'VERTE PREMIUM E': (26, T1 + T2_4 + R1 + R2 + ['u-seda', 'u-bila', 'u-kasmir', 'u-oliva', 'u-fjord'] + MOD5),
 'VERTE PREMIUM A': (27, T2_4 + R1 + R2 + ['u-seda', 'u-bila', 'u-kasmir']),
 'HARMONY': (28, T1 + ['u-seda', 'u-bila', 'u-fjord', 'u-oliva', 'u-kasmir'] + MOD5 + ['m-hawana']),
 'CPL': (30, ['c-antracit-str', 'c-bila-str', 't3-orech', 'r3-craft']),
}
