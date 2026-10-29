# Snímky druhé části reelu (tools/poukaz/reel.html) → PNG po 1/30 s. Spuštění: python3 tools/poukaz/reel.py <url> <výstupní složka> [sekundy]
import asyncio, sys, os
from playwright.async_api import async_playwright

async def main(url, out, dur):
    os.makedirs(out, exist_ok=True)
    async with async_playwright() as p:
        b = await p.chromium.launch()
        pg = await b.new_page(viewport={'width': 1080, 'height': 1920})
        await pg.goto(url); await pg.evaluate('ready()')
        n = int(dur * 30)
        for i in range(n):
            await pg.evaluate(f'setT({i / 30})')
            await pg.locator('#s').screenshot(path=f'{out}/f{i:04d}.png')
        await b.close()

asyncio.run(main(sys.argv[1], sys.argv[2], float(sys.argv[3]) if len(sys.argv) > 3 else 10))
