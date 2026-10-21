import html
NAVY='#0d1b3e'; MUT='#4a5775'; TXT='#374151'; OR='#e8650a'
els=[]
def box(x,y,w,h,style): els.append(f'<div style="position:absolute;left:{x}pt;top:{y}pt;width:{w}pt;height:{h}pt;{style}"></div>')
DY={21:0.75,10.125:0,9:0.75,11.25:0,9.75:0.75,8.6:0.5,8.25:0.75,7.5:0.5,7:0.5,10.9:1.0,7.13:0.25,9.375:0}
def t(x,y,h,text,size,wt=400,col=TXT,ls=0,right=None,center=None,extra=''):
    y=y+DY.get(size,0)
    # y,h = bbox top/height of the line (pdftotext); line-height = h
    pos=f'left:{x}pt;' if right is None and center is None else (f'right:{595.92-right}pt;text-align:right;' if right is not None else f'left:{center-250}pt;width:500pt;text-align:center;')
    els.append(f'<div style="position:absolute;{pos}top:{y}pt;height:{h}pt;line-height:{h}pt;font-size:{size}pt;font-weight:{wt};color:{col};letter-spacing:{ls}em;white-space:nowrap;{extra}">{text}</div>')
def img(x,y,w,h,src): els.append(f'<img src="{src}" style="position:absolute;left:{x}pt;top:{y}pt;width:{w}pt;height:{h}pt">')
# header
img(36,29.3,78.7,37.6,'logo.png')
t(0,36.3,9.9,'PŘEDÁVACÍ LIST',7.5,700,NAVY,0.20,right=559.5)
t(0,49.8,10.9,'Jsme tu od roku 1996',9,400,'#6b7893',0,right=559.5)
box(36,79.4,523.5,1.2,f'background:{OR}')
t(36,95.2,25.4,'Děkujeme, že jste si vybrali nás',21,700,NAVY)
t(36,128.9,12.3,'Vážíme si Vaší důvěry. Vaše nové dveře jsou namontované a předané. Na tomto listu najdete údaje o',10.125,400,MUT)
t(36,143.9,12.3,'dveřích, pár rad k péči a kontakty pro případ, že budete cokoli potřebovat. Jsme tu i po montáži.',10.125,400,MUT)
# VAŠE DVEŘE
box(36,170.6,523.5,113.8,'background:#e8f0fb;border-radius:9pt')
t(49.5,182.5,10,'VAŠE DVEŘE',7.5,700,NAVY,0.20)
rows=[(208.8,'Model a provedení',128.4,'Číslo objednávky',381.0),(234.3,'Datum předání',111.9,'Základní záruka do',388.8),(259.8,'Počet klíčů',96.2,'Předal (montážník)',388.5)]
for y,l1,e1,l2,e2 in rows:
    t(49.5,y,10.9,l1,9,400,MUT); t(308.2,y,10.9,l2,9,400,MUT)
    box(e1+5.5,y+11.4,286.6-(e1+5.5),0.6,'background:#8aa0cc'); box(e2+5.5,y+11.4,545-(e2+5.5),0.6,'background:#8aa0cc')
# Péče
t(36,298.9,13.6,'Péče o dveře',11.25,700,NAVY)
t(308.2,298.9,13.6,'Servis a kontakt',11.25,700,NAVY)
def chk(y): t(36,y,16,'✓',9.375,700,OR,extra='')
def li(y,lines,boldlead=None):
    chk(y)
    for i,(ln) in enumerate(lines):
        t(50.3,y+i*16.1 if i==0 else y+16.1,11.34 if False else 11.34,ln,9.375,400,TXT)
CARE=[ (316.8,'Povrch čistěte vlhkým hadříkem, bez agresivních a','brusných prostředků.'),
 (348.3,'<b style="color:%s">Každých 6 měsíců</b> ošetřete těsnění dveří a','zárubně (pokud je) silikonovým mazivem.'),
 (379.1,'<b style="color:%s">Každých 6 měsíců</b> promažte zamykací čepy a','krycí misku vložky (pokud je) olejem nebo WD-40.')]
for y,a,b in CARE:
    a=a.replace('%s',NAVY) if '%s' in a else a
    t(36,y,16,'<span style="color:%s;font-weight:700">✓</span>'%OR,9.375)
    t(50.3,y+3,12.9,a,9.375,400,TXT)
    t(50.3,y+16.1+3.4,11.4,b,9.375,400,TXT)
# red box
box(36,412.6,251,64.8,'background:#fee2e2;border:0.75pt solid #b91c1c;border-radius:7pt;box-sizing:border-box')
RED='#7f1d1d'
t(44.2,421.8,10.9,'<b>Pozor – samotná vložka (cylindr):</b> když jdou klíče',9,400,'#991b1b')
t(44.2,433.8,10.9,'špatně zasouvat, použijte pouze teflonový nebo',9,400,'#991b1b')
t(44.2,446.5,10.9,'grafitový sprej. Nic jiného do vložky nepatří, ani olej,',9,400,'#991b1b')
t(44.2,459.3,10.9,'ani WD-40.',9,400,'#991b1b')
# servis box
box(308.2,319,251.2,82,f'border:1pt solid {NAVY};border-radius:9pt;box-sizing:border-box')
img(319,332.6,54.8,54.8,'servis.svg')
t(384,328.1,11.8,'Servis a reklamace přes servisní',9.75,700,NAVY)
t(384,342.3,11.8,'formulář',9.75,700,NAVY)
for y,s in [(355.8,'Naskenujte kód, uveďte číslo'),(367.8,'objednávky a případně přiložte fotku.'),(380.5,'Ozveme se a domluvíme termín.')]: t(384,y,10.9,s,9,400,MUT)
t(308.2,408.3,10.9,'<b style="color:%s">+420 725 559 235</b> · také WhatsApp · chci@bezpecne-'%NAVY,9,400,MUT)
t(308.2,421.8,10.9,'dvere.cz',9,400,MUT)
t(308.2,436.8,10.9,'Prodejna: Obchodní 36, Most · pracovní dny 8–17 hod.',9,400,MUT)
# karty
box(36,491.8,255.6,148.2,'border:0.75pt solid #e2e8f2;border-radius:10pt;box-sizing:border-box')
box(303.8,491.8,255.6,148.2,'border:0.75pt solid #e2e8f2;border-radius:10pt;box-sizing:border-box')
img(49,528,74,74,'reg.svg')
box(136,517,82,14.4,f'background:{OR};border-radius:8pt')
t(142.5,518.9,9.5,'+1 ROK ZÁRUKY',7,700,'#fff',0.15)
t(135.7,536.2,13.2,'Zaregistrujte své dveře',10.9,700,NAVY)
for y,s in [(553.4,'Pro dveře PREMIUM 60 a'),(566.1,'EXTREME 54: vyplňte do 30 dnů'),(578.1,'od předání krátký dotazník o'),(590.9,'montáži a prodloužíme Vám'),(603.6,'základní záruku o 1 rok.')]: t(135.7,y,10.4,s,8.6,400,MUT)
t(315,503.5,10,'OHODNOŤTE NÁS',7.5,700,NAVY,0.20)
t(315,518.1,10.5,'Vaše upřímné hodnocení pomůže lidem, kteří dveře',8.6,400,MUT)
t(315,530.9,10.4,'teprve vybírají.',8.6,400,MUT)
img(365,553,57.6,57.6,'google.svg'); img(442,553,57.6,57.6,'fb.svg')
t(0,617.1,10.5,'Google',8.6,700,NAVY,center=393.4); t(0,617.1,10.5,'Facebook',8.6,700,NAVY,center=469.8)
# předání
box(36,657.4,523.5,127.6,f'border:1pt solid {NAVY};border-radius:10pt;box-sizing:border-box')
t(50.2,669.3,9.9,'PŘEDÁNÍ A PŘEVZETÍ DÍLA',7.5,700,NAVY,0.20)
t(50.2,685.8,10.9,'Objednatel potvrzuje, že dílo (dveře včetně montáže) převzal kompletní, funkční a bez viditelného poškození a že při',9,400,MUT)
t(50.2,699.3,10.9,'montáži nedošlo k poškození jeho majetku.',9,400,MUT)
t(50.2,721.8,10.9,'Výhrady (pokud jsou)',9,400,MUT)
box(147.6,733.4,397.4,0.6,'background:#8aa0cc')
for x,w in [(50.4,152.6),(221,153),(392.4,152.6)]: box(x,756.4,w,0.7,f'background:{NAVY}')
t(50.2,761.5,10,'Místo a datum předání',8.25,400,MUT); t(221.2,761.5,10,'Za zhotovitele (podpis)',8.25,400,MUT); t(392.2,761.5,10,'Objednatel (podpis)',8.25,400,MUT)
box(36,795,523.5,0.6,'background:#e2e8f2')
t(0,805.3,8.7,'Zdeněk Kučera · IČ 64682676 · DIČ CZ7801242768 · Zd. Štěpánka 2764/1, 434 01 Most · Prodejna: Obchodní 36, 434 01 Most',7.13,400,'#6b7893',center=297.96,extra='')
open(__import__('pathlib').Path(__file__).with_name('pl.html'),'w').write('<!doctype html><meta charset="utf-8"><style>@page{size:595.92pt 841.92pt;margin:0}html,body{margin:0;padding:0;width:595.92pt;height:841.92pt;position:relative;font-family:Inter,sans-serif;overflow:hidden;background:#fff}</style><body>'+'\n'.join(els)+'</body>')
