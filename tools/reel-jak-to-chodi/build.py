#!/usr/bin/env python3
"""Reel „Jak to u nás chodí“ (1080x1920, 30 fps): od poptávky z webu po aplikaci Moje dveře, s maskotem Falkem a pohledem „do kuchyně“ (panel Mezitím u nás v systému).
Vrstvy (pozadí, nadpis, popisek, karta) kreslí Playwright z HTML, pohyb a videa skládá ffmpeg; každá scéna je jeden soubor.
Spuštění: python3 tools/reel-jak-to-chodi/build.py [--nahled] [--hudba cesta.mp3]   (videa Falka: userFiles/email/*.mp4 nebo --zdroje složka s *-src.mp4)"""
import json, os, subprocess, sys, shutil
R = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
OUT = os.environ.get('REEL_OUT', os.path.join(os.path.dirname(__file__), 'out')); os.makedirs(OUT, exist_ok=True)
SRC = None
if '--zdroje' in sys.argv: SRC = sys.argv[sys.argv.index('--zdroje') + 1]
def vid(n, alt=None):
    if SRC:
        for c in [alt, n]:
            if c and os.path.exists(os.path.join(SRC, c + '-src.mp4')): return os.path.join(SRC, c + '-src.mp4')
    return os.path.join(R, 'userFiles/email', n + '.mp4')
M = 'file://' + R + '/userFiles/maskot/'
W, H, FPS = 1080, 1920, 30
KROKU = 7
# (id, délka s, číslo kroku, nadpis, popisek, karta: ('video', soubor) | ('html', kód), štítky)
SC = [
 ('s0', 3.6, 0, None, None, None, None),
 ('s1', 6.0, 1, 'Pošlete poptávku z webu', 'Vyberete dveře do bytu a odešlete. Potvrzení Vám přijde e-mailem hned.', ('video', vid('buldok-poptavka')),
  [('P 31/2026 · PREMIUM 60 · Litvínov', 'Nová', '#e8650a'), ('Odpovědět do 2 pracovních dnů', 'hlídá systém', '#2e9e5b')]),
 ('s2', 6.0, 2, 'Připravíme nabídku', 'Dostanete ji e-mailem: přehledně, s cenou a fotkou Vašich dveří.', ('video', vid('buldok-kontrolor', 'buldok')),
  [('Nabídka N 8/2026', 'Odeslána', '#1d5fb8'), ('Když se neozvete, sama se připomene', 'po 3 dnech', '#2e9e5b')]),
 ('s3', 6.0, 3, 'Něco chybí? Doplníte to mobilem', 'Pošleme odkaz. Vyfotíte dveře, připíšete rozměry a odešlete.', ('html', 'DOPLNENI'),
  [('Požádat o doplnění: fotka, rozměry', 'Odesláno', '#1d5fb8'), ('Zákazník doplnil poptávku', 'hotovo', '#2e9e5b')]),
 ('s4', 6.0, 4, 'Objednávka a záloha', 'Zálohu zaplatíte QR kódem. Podepsanou objednávku pošleme e-mailem.', ('video', vid('roboti-ve-vyrobe')),
  [('Záloha k objednávce 83/2026', 'Spárováno z banky', '#2e9e5b'), ('Objednávka podepsána a odeslána', 'hotovo', '#2e9e5b')]),
 ('s5', 6.0, 5, 'Termín montáže do 14 dnů', 'Přijde e-mailem, i s událostí do kalendáře a cedulkou pro sousedy.', ('video', vid('buldok-pri-montazi')),
  [('Kalendář montáží · út 8. 12.', 'příjezd 8:30–9:15', '#e8650a'), ('Termín odeslán zákazníkovi', 'hotovo', '#2e9e5b')]),
 ('s6', 6.0, 6, 'Předání s podpisem na displeji', 'Podepíšete prstem na tabletu nebo mobilu. Předávací list dorazí e-mailem.', ('video', vid('buldok-predani')),
  [('Předání zapsáno', 'záruka běží', '#2e9e5b'), ('Dveře odepsány ze skladu', 'automaticky', '#2e9e5b')]),
 ('s7', 6.0, 7, 'Aplikace Moje dveře', 'Záruka, historie dveří a registrace na jednom místě. Kdykoli, v mobilu.', ('video', vid('buldok-zaruka')),
  [('Dveře spárované s objednávkou', 'v ceně dveří', '#2e9e5b'), ('Připomínky údržby a kontroly', 'hlídá systém', '#2e9e5b')]),
 ('s8', 6.0, 7, 'Servis na pár kliknutí', 'Záruční i placený servis objednáte přímo v aplikaci. Ozveme se s termínem.', ('video', vid('buldok-servis')),
  [('Servisní požadavek S-2026-012', 'Přijato', '#e8650a'), ('Termín návštěvy potvrzen e-mailem', 'Naplánováno', '#1d5fb8')]),
 ('s9', 5.0, 0, None, None, None, None),
]
CSS = '''
*{box-sizing:border-box;margin:0;padding:0}html,body{width:1080px;height:1920px;background:transparent;font-family:Inter,Arial,sans-serif;color:#1d2b22;overflow:hidden}
.abs{position:absolute;left:0;top:0;width:1080px;height:1920px}
.bg{background:radial-gradient(1200px 900px at 85% 8%,#fff7ea 0,#fbf1e2 45%,#f4e6d2 100%)}
.pruh{position:absolute;left:0;top:0;width:1080px;height:236px;background:#123d27}
.brand{position:absolute;left:60px;top:106px;display:flex;align-items:center;gap:22px;color:#fff}
.brand img{width:96px;height:96px;border-radius:50%;background:#e8f0ea}
.brand b{font-size:40px;font-weight:800;letter-spacing:-.01em}.brand span{display:block;font-size:26px;color:#b9d3c2;font-weight:500;margin-top:2px}
.tecky{position:absolute;right:60px;top:138px;display:flex;gap:12px}.tecky i{width:26px;height:26px;border-radius:50%;background:#2c5a41;display:block}.tecky i.a{background:#e8650a}.tecky i.h{background:#b9d3c2}
.pata{position:absolute;left:0;bottom:0;width:1080px;height:150px;display:flex;align-items:center;justify-content:center;gap:16px;font-size:34px;font-weight:700;color:#123d27;opacity:.85}
.cislo{position:absolute;left:60px;top:318px;width:124px;height:124px;border-radius:50%;background:#e8650a;color:#fff;font-size:70px;font-weight:800;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(232,101,10,.35)}
.nadpis{position:absolute;left:214px;top:306px;width:806px;height:150px;display:flex;align-items:center;font-size:66px;line-height:1.08;font-weight:800;letter-spacing:-.02em;color:#123d27}
.popis{position:absolute;left:60px;top:1092px;width:960px;height:190px;display:flex;align-items:center;font-size:45px;line-height:1.28;font-weight:500;color:#2b3a31}
.kuch{position:absolute;left:48px;top:1300px;width:984px;border-radius:32px;background:#123d27;box-shadow:0 18px 44px rgba(18,61,39,.28);padding:22px 26px 26px}
.kuch h5{font-size:27px;letter-spacing:.09em;text-transform:uppercase;color:#b9d3c2;font-weight:700;display:flex;align-items:center;gap:12px;margin:0 6px 16px}
.kuch h5:before{content:"";width:16px;height:16px;border-radius:50%;background:#e8650a;display:block}
.kuch .r{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fff;border-radius:20px;padding:18px 22px;margin-top:12px;font-size:33px;font-weight:600;color:#1d2b22}
.kuch .r b{flex:none;color:#fff;font-size:27px;font-weight:700;border-radius:40px;padding:8px 20px;white-space:nowrap}
.karta{position:absolute;left:48px;top:496px;width:984px;height:554px;border-radius:40px;background:#fff;box-shadow:0 24px 60px rgba(40,25,10,.22)}
.ram{position:absolute;left:48px;top:496px;width:984px;height:554px;border-radius:40px;border:8px solid #fff;box-shadow:0 24px 60px rgba(40,25,10,.25)}
.maska{position:absolute;left:0;top:0;width:984px;height:554px;border-radius:40px;background:#fff}
'''
def page(body, extra=''):
    return '<!doctype html><html><head><meta charset="utf-8"><style>' + CSS + extra + '</style></head><body>' + body + '</body></html>'
def bg(krok):
    t = ''.join('<i class="%s"></i>' % ('a' if i == krok else ('h' if i < krok else '')) for i in range(1, KROKU + 1))
    return page('<div class="abs bg"></div><div class="pruh"></div><div class="brand"><img src="%sbuldok-hlava.png"><div><b>Jak to u nás chodí</b><span>bezpečnostní dveře do bytu</span></div></div>' % M
                + ('<div class="tecky">%s</div>' % t if krok else '') + '<div class="pata">bezpecne-dvere.cz</div>')
DOPLNENI = '''<div class="karta" style="display:flex;gap:34px;padding:34px 40px;align-items:center">
 <div style="flex:none;width:250px;height:486px;border-radius:38px;background:#16241c;padding:12px"><div style="width:100%;height:100%;border-radius:28px;background:#f6faf7;padding:18px 16px;font-size:17px;line-height:1.3">
  <div style="font-weight:800;color:#123d27;font-size:19px">Doplnění poptávky</div><div style="color:#4f6a5b;margin:4px 0 12px">Poptávka č. P 31/2026</div>
  <div style="background:#fff;border:2px solid #d0e0d5;border-radius:12px;padding:9px 10px;margin-bottom:8px"><b style="color:#c2410c">1</b> Fotka dveří zvenku</div>
  <div style="background:#fff;border:2px solid #d0e0d5;border-radius:12px;padding:9px 10px;margin-bottom:8px"><b style="color:#c2410c">2</b> Rozměry zárubně</div>
  <div style="height:112px;border-radius:12px;background:linear-gradient(135deg,#cfd8cf,#9fb1a5);display:flex;align-items:flex-end;justify-content:center;overflow:hidden"><img src="{M}buldok-hotovo.png" style="height:104px"></div>
  <div style="background:#fff;border:2px solid #d0e0d5;border-radius:12px;padding:9px 10px;margin:8px 0;color:#4f6a5b">š. 90 cm, v. 197 cm</div>
  <div style="background:#e8650a;color:#fff;font-weight:800;text-align:center;border-radius:30px;padding:11px 0;font-size:19px">Odeslat</div></div></div>
 <div style="flex:1;font-size:40px;line-height:1.25;font-weight:700;color:#123d27">Chybí nám fotka nebo rozměr?<div style="font-size:32px;font-weight:500;color:#4f6a5b;margin-top:18px">Stačí kliknout na odkaz v e-mailu, SMS nebo WhatsAppu.</div></div></div>'''.replace('{M}', M)
INTRO = '''<div class="abs" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:300px 70px 220px">
 <img src="%sbuldok-ahoj.png" style="height:560px;filter:drop-shadow(0 26px 30px rgba(40,25,10,.25))">
 <div style="font-size:104px;line-height:1.02;font-weight:800;letter-spacing:-.03em;color:#123d27;margin-top:40px">Jak to u nás chodí</div>
 <div style="font-size:50px;line-height:1.25;font-weight:500;color:#2b3a31;margin-top:28px">Od poptávky po servis.<br><b style="color:#c2410c">Jednoduše a bez papírů.</b></div></div>''' % M
OUTRO = '''<div class="abs" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:290px 70px 200px">
 <img src="%sbuldok-hotovo.png" style="height:500px;filter:drop-shadow(0 26px 30px rgba(40,25,10,.25))">
 <div style="font-size:92px;line-height:1.04;font-weight:800;letter-spacing:-.03em;color:#123d27;margin-top:36px">Jsme plně digitální.</div>
 <div style="font-size:58px;line-height:1.15;font-weight:700;color:#c2410c;margin-top:14px">A pořád lidští.</div>
 <div style="font-size:42px;line-height:1.3;font-weight:500;color:#2b3a31;margin-top:34px">Vy vyberete dveře,<br>o zbytek se postaráme my a Falko.</div>
 <div style="margin-top:44px;background:#e8650a;color:#fff;font-size:50px;font-weight:800;border-radius:70px;padding:24px 56px">bezpecne-dvere.cz</div></div>''' % M
def layers(sc):
    sid, d, krok, nad, pop, karta, st = sc
    L = {'bg': bg(krok)}
    if sid == 's0': L['karta'] = page(INTRO); return L
    if sid == 's9': L['karta'] = page(OUTRO); return L
    L['nadpis'] = page('<div class="cislo">%d</div><div class="nadpis">%s</div>' % (krok, nad))
    L['popis'] = page('<div class="popis">%s</div>' % pop)
    L['kuch'] = page('<div class="kuch"><h5>Mezitím u nás v systému</h5>%s</div>' % ''.join('<div class="r"><span>%s</span><b style="background:%s">%s</b></div>' % (t, c, b) for t, b, c in st))
    if karta[0] == 'html': L['karta'] = page(DOPLNENI)
    else: L['ram'] = page('<div class="ram"></div>'); L['maska'] = page('<div class="maska"></div>', 'html,body{width:984px;height:554px;background:#000}')
    return L
def shots():
    job = []
    for sc in SC:
        for k, h in layers(sc).items():
            f = os.path.join(OUT, '%s-%s' % (sc[0], k)); open(f + '.html', 'w', encoding='utf-8').write(h)
            job.append({'html': f + '.html', 'png': f + '.png', 'w': 984 if k == 'maska' else W, 'h': 554 if k == 'maska' else H, 'opaque': k in ('bg', 'maska')})
    js = os.path.join(OUT, 'shots.cjs'); open(os.path.join(OUT, 'job.json'), 'w').write(json.dumps(job))
    open(js, 'w').write("""const { chromium } = require('playwright'); const job = require('./job.json');
(async () => { const b = await chromium.launch(); for (const j of job) { const p = await b.newPage({ viewport: { width: j.w, height: j.h } }); await p.goto('file://' + j.html); await p.evaluate(() => document.fonts.ready); await p.screenshot({ path: j.png, omitBackground: !j.opaque }); await p.close(); } await b.close(); })();""")
    subprocess.run(['node', js], check=True)
ENC = ['-c:v', 'libx264', '-crf', '18', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-r', str(FPS)]
def fade(lbl, out, t0, d, tin=0.45, tout=0.3):   # vrstva s alfou: náběh od t0, zmizení na konci scény
    return "[%s]format=rgba,fade=t=in:st=%.2f:d=%.2f:alpha=1,fade=t=out:st=%.2f:d=%.2f:alpha=1[%s]" % (lbl, t0, tin, d - tout, tout, out)
def slide(t0, y0=40, dur=0.5):   # posun vrstvy zdola na místo
    return "y='if(lt(t,%.2f),%d,if(lt(t,%.2f),%d*(1-(t-%.2f)/%.2f)*(1-(t-%.2f)/%.2f),0))'" % (t0, y0, t0 + dur, y0, t0, dur, t0, dur)
def scene(sc):
    sid, d, krok, nad, pop, karta, st = sc; P = lambda k: os.path.join(OUT, '%s-%s.png' % (sid, k)); out = os.path.join(OUT, sid + '.mp4'); tmp = out[:-4] + '.tmp.mp4'
    inp = ['-framerate', str(FPS), '-loop', '1', '-t', str(d), '-i', P('bg')]; f = []
    def still(k): inp.extend(['-framerate', str(FPS), '-loop', '1', '-t', str(d), '-i', P(k)]); return len(inp) // 8 - 1 if False else None
    idx = [1]
    def add_still(k):
        inp.extend(['-framerate', str(FPS), '-loop', '1', '-t', str(d), '-i', P(k)]); i = idx[0]; idx[0] += 1; return '%d:v' % i
    last = '0:v'
    if sid in ('s0', 's9'):
        k = add_still('karta'); f.append(fade(k, 'k', 0.15, d, 0.6, 0.35 if sid == 's0' else 0.01)); f.append("[%s][k]overlay=0:%s:format=auto[v1]" % (last, slide(0.15, 50, 0.7))); last = 'v1'
    else:
        if karta[0] == 'video':
            inp.extend(['-stream_loop', '-1', '-t', str(d), '-i', karta[1]]); vi = '%d:v' % idx[0]; idx[0] += 1
            mk = add_still('maska'); rm = add_still('ram')
            f.append("[%s]fps=%d,scale=984:554:force_original_aspect_ratio=increase,crop=984:554,format=rgba[vv];[%s]format=gray[mm];[vv][mm]alphamerge[vm]" % (vi, FPS, mk))
            f.append(fade('vm', 'vf', 0.25, d)); f.append(fade(rm, 'rf', 0.25, d))
            f.append("[%s][rf]overlay=0:%s:format=auto[a1];[a1][vf]overlay=48:%s:format=auto[a2]" % (last, slide(0.25), slide(0.25).replace("y='", "y='496+")))
            last = 'a2'
        else:
            k = add_still('karta'); f.append(fade(k, 'kf', 0.25, d)); f.append("[%s][kf]overlay=0:%s:format=auto[a2]" % (last, slide(0.25))); last = 'a2'
        n = add_still('nadpis'); p = add_still('popis')
        f.append(fade(n, 'nf', 0.05, d)); f.append("[%s][nf]overlay=0:%s:format=auto[a3]" % (last, slide(0.05, 30)))
        f.append(fade(p, 'pf', 0.7, d)); f.append("[a3][pf]overlay=0:%s:format=auto[a4]" % slide(0.7, 30))
        ku = add_still('kuch'); f.append(fade(ku, 'uf', 2.2, d)); f.append("[a4][uf]overlay=0:%s:format=auto[a5]" % slide(2.2, 60)); last = 'a5'
    f.append("[%s]format=yuv420p,trim=duration=%s[o]" % (last, d))
    subprocess.run(['ffmpeg', '-v', 'error', '-y'] + inp + ['-filter_complex', ';'.join(f), '-map', '[o]', '-an'] + ENC + [tmp], check=True)
    os.replace(tmp, out); return out
def nahled():
    for sc in SC:
        sid, d = sc[0], sc[1]
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-ss', str(min(d - 0.6, 3.0)), '-i', os.path.join(OUT, sid + '.mp4'), '-frames:v', '1', '-vf', 'scale=360:-1', os.path.join(OUT, sid + '-nahled.jpg')], check=True)
if __name__ == '__main__':
    shots()
    jen = [a for a in sys.argv[1:] if a.startswith('s') and a[1:].isdigit()]
    for sc in SC:
        if not jen or sc[0] in jen: scene(sc); print('scéna', sc[0])
    nahled()
    lst = os.path.join(OUT, 'seznam.txt'); open(lst, 'w').write(''.join("file '%s.mp4'\n" % sc[0] for sc in SC))
    bez = os.path.join(OUT, 'reel-bez-zvuku.mp4'); subprocess.run(['ffmpeg', '-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', lst, '-c', 'copy', bez], check=True)
    total = sum(sc[1] for sc in SC); print('celkem', total, 's')
    if '--hudba' in sys.argv:
        hud = sys.argv[sys.argv.index('--hudba') + 1]; fin = os.path.join(OUT, 'jak-to-u-nas-chodi.mp4')
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', bez, '-i', hud, '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-af', 'volume=0.8,afade=t=in:st=0:d=0.6,afade=t=out:st=%.2f:d=1.6' % (total - 1.6), '-c:a', 'aac', '-b:a', '160k', '-t', str(total), '-movflags', '+faststart', fin], check=True)
        print(fin)
