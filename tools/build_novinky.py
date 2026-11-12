#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generuje sekci Novinky: index novinky.html + statické stránky novinky/<slug>.html.
Data článků jsou v tools/novinky/clanky.py (seznam ARTICLES).
Spuštění:  python3 tools/build_novinky.py
Index zobrazuje jen články s datem <= dnes (přes client JS), takže se každý den sám objeví nový.
"""
import os, sys, html, datetime, json, re
BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(BASE, "tools", "novinky"))
from clanky import ARTICLES  # noqa: E402

STYLE_V = "20261025e"

NAV = '''<nav class="navbar" id="navbar">
  <div class="container">
    <div class="nav-inner">
      <a href="{root}index.html" class="nav-logo">
        <img src="{root}logo.jpg" alt="Bezpečné dveře" class="nav-logo-img">
      </a>
      <ul class="nav-menu" id="navMenu">
        <li class="nav-item"><a href="{root}dvere-do-bytu.html" class="nav-link">Do bytu</a></li>
        <li class="nav-item"><a href="{root}dvere-do-domu.html" class="nav-link">Do domu</a></li>
        <li class="nav-item"><a href="{root}dvere-do-interieru.html" class="nav-link">Do interiéru</a></li>
        <li class="nav-item"><a href="{root}servis.html" class="nav-link">Servis</a></li>
        <li class="nav-item"><a href="{root}dokumenty.html" class="nav-link">Dokumenty</a></li>
        <li class="nav-item"><a href="{root}poptavka.html" class="nav-link">Kontakt</a></li>
        <li class="nav-item"><a href="{root}darkovy-poukaz.html" class="nav-link nav-poukaz">🎁 Poukaz</a></li>
      </ul>
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <a href="{root}poptavka-dvere.html" class="btn btn-primary" style="padding:0.6rem 1.2rem;font-size:0.85rem;">Chci dveře</a>
      </div>
      <button class="nav-toggle" id="navToggle" aria-label="Menu"><span></span><span></span><span></span></button>
    </div>
  </div>
</nav>'''

FOOTER = '''<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="{root}index.html"><img src="{root}logo.jpg" alt="Bezpečné dveře" class="footer-logo-img"></a>
        <p>Jsme tu od roku 1996. Rodinná firma z Mostu.</p>
      </div>
      <div class="footer-col">
        <h5>Produkty</h5>
        <div class="footer-links">
          <a href="{root}dvere-do-bytu.html">Dveře do bytu</a>
          <a href="{root}dvere-do-domu.html">Dveře do domu</a>
          <a href="{root}dvere-do-interieru.html">Interiérové dveře</a>
          <a href="{root}dvere-do-bytu.html#premium60">PREMIUM 60</a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Servis</h5>
        <div class="footer-links">
          <a href="{root}servis-dveri.html">Servis dveří a reklamace</a>
          <a href="{root}udrzba-dveri.html">Údržba dveří</a>
          <a href="{root}nouzove-otevirani.html">Nouzové otevírání</a>
          <a href="{root}objednani-klicu.html">Objednání klíčů</a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Kontakt</h5>
        <div class="footer-links">
          <a href="{root}poptavka.html">Poptávka</a>
          <a href="{root}darkovy-poukaz.html">🎁 Dárkový poukaz</a>
          <a href="tel:+420725559235">+420 725 559 235</a>
          <a href="{root}poptavka.html">Obchodní 36, Most</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <span class="cy">2026</span><script>document.currentScript.previousElementSibling.textContent=new Date().getFullYear()</script> Bezpečné dveře · Provozovatel: Zdeněk Kučera, IČ 64682676, DIČ CZ7801242768 · Zapsán v živnostenském rejstříku od 13. 3. 1996 · Prodejna a vzorkovna: Obchodní 36, 434 01 Most</span>
      <span><a href="https://kartoteka.dvere.app/" title="Pro prodejce" rel="nofollow" style="text-decoration:none;opacity:.4">🔒</a></span>
      <span><a href="{root}novinky.html" style="color:inherit;text-decoration:underline">Novinky</a> · <a href="{root}zasady-osobnich-udaju.html" style="color:inherit;text-decoration:underline">Zásady osobních údajů</a> · <a href="{root}cookies.html" style="color:inherit;text-decoration:underline">Cookies</a></span>
    </div>
  </div>
</footer>'''

SCRIPTS = '''<div class="cookie-bar" id="cookieBar">
  Tento web používá cookies.
  <button class="btn btn-primary" style="padding:0.5rem 1.2rem;font-size:0.82rem;" onclick="document.getElementById('cookieBar').classList.add('hidden');try{{localStorage.setItem('cookiesOk','1')}}catch(e){{}}">Rozumím</button>
</div>
<script>try{{if(localStorage.getItem('cookiesOk')==='1'){{var cb=document.getElementById('cookieBar');if(cb)cb.classList.add('hidden');}}}}catch(e){{}}</script>
<script>
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', () => navbar.classList.toggle('scrolled', window.scrollY > 40));
  document.getElementById('navToggle').addEventListener('click', () => navbar.classList.toggle('open'));
</script>
<script src="{root}form-submit.js?v=20261024a"></script>
<script src="{root}cookies.js?v=5" defer></script>
<script src="{root}moje-dvere-stitek.js?v=20261025c" defer></script>'''

CSS = '''  <style>
    .nv-wrap{max-width:1100px;margin:0 auto;padding:0 1rem}
    .nv-head{text-align:center;padding:2.5rem 1rem 1.5rem}
    .nv-head h1{color:var(--head);font-size:2rem;margin-bottom:.5rem}
    .nv-head p{color:var(--gray-600);max-width:640px;margin:0 auto}
    .nv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.4rem;margin:1.5rem 0 3rem}
    .nv-card{display:flex;flex-direction:column;background:#fff;border:2px solid var(--gray-200);border-radius:var(--radius-lg);overflow:hidden;transition:.2s;text-decoration:none}
    @media(hover:hover){.nv-card:hover{transform:translateY(-4px);border-color:var(--green);box-shadow:0 10px 26px rgba(0,0,0,.08)}}
    .nv-card-body{padding:1.2rem 1.3rem 1.4rem;display:flex;flex-direction:column;gap:.5rem;flex:1}
    .nv-kat{font-size:.72rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--green)}
    .nv-card h2{color:var(--head);font-size:1.12rem;line-height:1.3;margin:0}
    .nv-perex{color:var(--gray-600);font-size:.92rem;flex:1}
    .nv-date{color:var(--gray-500);font-size:.8rem}
    .nv-empty{text-align:center;color:var(--gray-500);padding:2rem}
    /* článek */
    .ar-wrap{max-width:760px;margin:0 auto;padding:0 1rem}
    .ar-crumb{font-size:.85rem;color:var(--gray-500);padding:1.4rem 0 .4rem}
    .ar-crumb a{color:var(--gray-600)}
    .ar-kat{font-size:.74rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--green)}
    .ar-wrap h1{color:var(--head);font-size:1.9rem;line-height:1.2;margin:.3rem 0 .5rem}
    .ar-meta{color:var(--gray-500);font-size:.85rem;margin-bottom:1.5rem}
    .ar-body{font-size:1.02rem;line-height:1.7;color:var(--gray-700)}
    .ar-body h2{color:var(--head);font-size:1.3rem;margin:1.8rem 0 .7rem}
    .ar-body p{margin-bottom:1rem}
    .ar-body ul,.ar-body ol{margin:0 0 1rem 1.2rem;display:flex;flex-direction:column;gap:.4rem}
    .ar-body li{list-style:disc}
    .ar-cta{background:var(--green-50,#f0f7f2);border:2px solid var(--green);border-radius:var(--radius-lg);padding:1.3rem 1.4rem;margin:2rem 0;text-align:center}
    .ar-cta h3{color:var(--head);margin-bottom:.4rem;font-size:1.15rem}
    .ar-cta p{color:var(--gray-600);margin-bottom:.9rem;font-size:.95rem}
    .ar-more{border-top:1px solid var(--gray-200);margin-top:2.5rem;padding-top:1.5rem}
    .ar-more h3{color:var(--head);font-size:1.1rem;margin-bottom:.8rem}
    .ar-more a{display:block;color:var(--green);padding:.3rem 0;text-decoration:none;font-weight:600}
    @media(max-width:860px){.nv-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.nv-grid{grid-template-columns:1fr}.nv-head h1{font-size:1.6rem}.ar-wrap h1{font-size:1.5rem}}
  </style>'''

def head(title, desc, canon, extra_css="", root=""):
    return f'''<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{html.escape(title)}</title>
  <meta name="description" content="{html.escape(desc)}">
  <link rel="canonical" href="{canon}">
  <meta property="og:type" content="article">
  <meta property="og:locale" content="cs_CZ">
  <meta property="og:site_name" content="Bezpečné dveře">
  <meta property="og:title" content="{html.escape(title)}">
  <meta property="og:description" content="{html.escape(desc)}">
  <meta property="og:url" content="{canon}">
  <meta property="og:image" content="https://bezpecne-dvere.cz/og-image.png">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="stylesheet" href="{root}style.css?v={STYLE_V}">
  <link rel="icon" href="{root}favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="{root}favicon-32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="{root}apple-touch-icon.png">
  <meta name="theme-color" content="#123d27">
{extra_css}
</head>
<body>'''

CZ_MONTHS = ["", "ledna","února","března","dubna","května","června","července","srpna","září","října","listopadu","prosince"]
def cz_date(d):
    y,m,day = d.split("-")
    return f"{int(day)}. {CZ_MONTHS[int(m)]} {y}"

def build():
    nd = os.path.join(BASE, "novinky")
    os.makedirs(nd, exist_ok=True)
    arts = sorted(ARTICLES, key=lambda a: a["den"])
    # ---- článkové stránky ----
    by_date_desc = sorted(ARTICLES, key=lambda a: a["den"], reverse=True)
    for i, a in enumerate(arts):
        canon = f"https://bezpecne-dvere.cz/novinky/{a['slug']}.html"
        # související: nejbližší 3 jiné
        rel = [x for x in arts if x["slug"] != a["slug"]]
        rel = rel[max(0,i-2):i] + rel[i:i+2]
        seen=set(); rel2=[]
        for x in rel:
            if x["slug"] not in seen and x["slug"]!=a["slug"]:
                seen.add(x["slug"]); rel2.append(x)
        rel2 = rel2[:3]
        more = "".join(f'<a href="{x["slug"]}.html">{html.escape(x["titulek"])}</a>' for x in rel2)
        cta_href = a.get("cta_href", "../poptavka-dvere.html")
        cta_label = a.get("cta_label", "Nezávazná poptávka")
        ld = [
          {"@context":"https://schema.org","@type":"Article","headline":a["titulek"],
           "description":a["perex"],"datePublished":a["den"],"dateModified":a["den"],
           "inLanguage":"cs","mainEntityOfPage":canon,"image":"https://bezpecne-dvere.cz/og-image.png",
           "author":{"@type":"Organization","name":"Bezpečné dveře"},
           "publisher":{"@type":"Organization","name":"Bezpečné dveře","logo":{"@type":"ImageObject","url":"https://bezpecne-dvere.cz/logo.jpg"}}},
          {"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[
            {"@type":"ListItem","position":1,"name":"Úvod","item":"https://bezpecne-dvere.cz/"},
            {"@type":"ListItem","position":2,"name":"Novinky","item":"https://bezpecne-dvere.cz/novinky.html"},
            {"@type":"ListItem","position":3,"name":a["titulek"],"item":canon}]},
        ]
        page = head(f"{a['titulek']} | Bezpečné dveře", a["perex"], canon, CSS, root="../")
        page += '\n<script type="application/ld+json">' + json.dumps(ld, ensure_ascii=False) + '</script>'
        page += NAV.format(root="../", nov_active=" active")
        page += f'''
<main style="margin-top:72px;padding-bottom:2rem">
  <article class="ar-wrap">
    <div class="ar-crumb"><a href="../index.html">Úvod</a> › <a href="../novinky.html">Novinky</a> › {html.escape(a['titulek'])}</div>
    <div class="ar-kat">{html.escape(a['kat'])}</div>
    <h1>{html.escape(a['titulek'])}</h1>
    <div class="ar-meta">{cz_date(a['den'])}</div>
    <div class="ar-body">
{a['obsah']}
    </div>
    <div class="ar-cta">
      <h3>{html.escape(a.get('cta_titulek','Chcete poradit s dveřmi?'))}</h3>
      <p>{html.escape(a.get('cta_text','Rodinná firma z Mostu, od roku 1996. Vzorkovna Obchodní 36.'))}</p>
      <a href="{cta_href}" class="btn btn-primary">{html.escape(cta_label)}</a>
    </div>
    <div class="ar-more">
      <h3>Další novinky</h3>
      {more}
    </div>
  </article>
</main>
'''
        page += FOOTER.format(root="../") + "\n" + SCRIPTS.format(root="../") + "\n</body>\n</html>\n"
        with open(os.path.join(nd, a["slug"]+".html"), "w", encoding="utf-8") as f:
            f.write(page)
    # ---- index ----
    cards = ""
    for a in by_date_desc:
        cards += f'''      <a class="nv-card" data-date="{a['den']}" href="novinky/{a['slug']}.html">
        <div class="nv-card-body">
          <span class="nv-kat">{html.escape(a['kat'])}</span>
          <h2>{html.escape(a['titulek'])}</h2>
          <p class="nv-perex">{html.escape(a['perex'])}</p>
          <span class="nv-date">{cz_date(a['den'])}</span>
        </div>
      </a>
'''
    idx = head("Novinky a rady o dveřích | Bezpečné dveře",
               "Rady, tipy a novinky o bezpečnostních, vchodových i interiérových dveřích. Údržba, bezpečnost, výběr dveří a servis od rodinné firmy z Mostu.",
               "https://bezpecne-dvere.cz/novinky.html", CSS, root="")
    idx += NAV.format(root="", nov_active=" active")
    idx += f'''
<main style="margin-top:72px">
  <div class="nv-head">
    <h1>Novinky a rady</h1>
    <p>Praktické rady o bezpečnostních, vchodových i interiérových dveřích – výběr, údržba, bezpečnost i servis. Každý den něco nového.</p>
  </div>
  <div class="nv-wrap">
    <div class="nv-grid" id="nvGrid">
{cards}    </div>
    <div class="nv-empty" id="nvEmpty" style="display:none">Zatím tu nic není, brzy přidáme první článek.</div>
  </div>
</main>
<script>
(function(){{
  var today=new Date(); var t=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
  var cards=document.querySelectorAll('#nvGrid .nv-card'); var shown=0;
  cards.forEach(function(c){{ if(c.getAttribute('data-date')>t){{c.style.display='none';}} else {{shown++;}} }});
  if(shown===0){{var e=document.getElementById('nvEmpty'); if(e)e.style.display='block';}}
}})();
</script>
'''
    idx += FOOTER.format(root="") + "\n" + SCRIPTS.format(root="") + "\n</body>\n</html>\n"
    with open(os.path.join(BASE, "novinky.html"), "w", encoding="utf-8") as f:
        f.write(idx)
    update_sitemap(arts)
    print(f"Hotovo: {len(arts)} článků + novinky.html + sitemap")

def update_sitemap(arts):
    sp = os.path.join(BASE, "sitemap.xml")
    if not os.path.exists(sp):
        return
    with open(sp, encoding="utf-8") as f:
        xml = f.read()
    # odstranit dřívější novinky záznamy (idempotentní rebuild)
    xml = re.sub(r"\s*<url><loc>https://bezpecne-dvere\.cz/novinky[^<]*</loc>.*?</url>", "", xml)
    lines = ['  <url><loc>https://bezpecne-dvere.cz/novinky.html</loc><lastmod>%s</lastmod></url>' % arts[-1]["den"]]
    for a in arts:
        lines.append('  <url><loc>https://bezpecne-dvere.cz/novinky/%s.html</loc><lastmod>%s</lastmod></url>' % (a["slug"], a["den"]))
    block = "\n".join(lines) + "\n"
    xml = xml.replace("</urlset>", block + "</urlset>")
    with open(sp, "w", encoding="utf-8") as f:
        f.write(xml)

if __name__ == "__main__":
    build()
