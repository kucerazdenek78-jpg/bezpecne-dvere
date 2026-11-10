<?php
// Fotky u záznamu v kartotéce (poptávka / objednávka). Ukládá se do $DATA/fotky/<id záznamu>/<16 hex>.jpg
// a náhled <16 hex>_n.jpg. Zmenšení dělá prohlížeč před nahráním (JPEG), server jen kontroluje a ukládá.
// Používá kartoteka.php (už po přihlášení) – tento soubor sám žádné přihlášení neřeší.
// 2. 11. 2026: fotky, které poslal zákazník s poptávkou (přílohy záznamu), se ukazují jako náhledy a otevírají se v prohlížeči.
// 10. 11. 2026: fotka se otevře přes celou obrazovku přímo ve stránce s tlačítkem ✕ Zavřít (v nainstalované aplikaci není tlačítko Zpět);
//               zavře ji i klepnutí na pozadí, Esc a gesto / tlačítko Zpět. Odkaz zůstává i jako záloha (prostřední tlačítko, bez JavaScriptu).
// 10. 11. 2026: totéž pro PDF přílohy záznamu (odkazy ?soubor=….pdf v okně Přílohy): otevřou se v okně s tlačítky ✕ Zavřít a ↗ Otevřít samostatně
//               (v některých telefonech prohlížeč PDF ve stránce neukáže – pak slouží tlačítko Otevřít samostatně).
// 10. 11. 2026: v detailu poptávky nad Fotkami box „📨 Požádat zákazníka o doplnění“ (lib/doplneni.php, veřejná stránka doplnit.php).

const FOTO_MAX_BAJTU = 6 * 1024 * 1024;   // jedna fotka po zmenšení (zmenšená má řádově stovky kB)
const FOTO_MAX_KUSU = 40;                 // na jeden záznam

function foto_dir($id) { global $DATA; return $DATA . '/fotky/' . (int)$id; }

function foto_seznam($id) {
  $o = [];
  foreach (glob(foto_dir($id) . '/*.jpg') ?: [] as $f) {
    $n = basename($f);
    if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) $o[] = ['n' => $n, 't' => filemtime($f)];
  }
  usort($o, fn($a, $b) => $a['t'] <=> $b['t']);
  return $o;
}

function foto_jpeg_ok($tmp) {
  if (!is_uploaded_file($tmp) || filesize($tmp) <= 0 || filesize($tmp) > FOTO_MAX_BAJTU) return false;
  $i = @getimagesize($tmp);
  return $i && $i[2] === IMAGETYPE_JPEG;
}

function foto_json($ok, $zprava = '') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => $ok, 'zprava' => $zprava]); exit; }

// Fotky od zákazníka = obrázkové přílohy záznamu (soubory v $DATA/ulozeno)
function foto_zakaznik($r) {
  $o = [];
  foreach (array_filter(explode("\n", (string)($r['prilohy'] ?? ''))) as $s) {
    if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $s)) $o[] = $s;
  }
  return $o;
}

// Zpracuje požadavky na fotky; volat po přihlášení. Nic nedělá, když požadavek fotek se netýká.
function foto_obsluz($pdo) {
  // obrázková příloha od zákazníka: ?soubor=<jméno> → zobrazit v prohlížeči (ostatní přílohy řeší kartoteka.php)
  if (isset($_GET['soubor'])) {
    global $DATA;
    $jm = basename((string)$_GET['soubor']);
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][strtolower(pathinfo($jm, PATHINFO_EXTENSION))] ?? null;
    $c = $DATA . '/ulozeno/' . $jm;
    if ($mime && is_file($c)) {
      header('Content-Type: ' . $mime); header('Content-Disposition: inline'); header('X-Content-Type-Options: nosniff');
      header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
      header('Cache-Control: private, max-age=86400'); header('Content-Length: ' . filesize($c));
      readfile($c); exit;
    }
  }
  // zobrazení: ?foto=<id>&f=<jmeno>[&nahled=1]
  if (isset($_GET['foto'])) {
    $id = (int)$_GET['foto']; $n = (string)($_GET['f'] ?? '');
    if (!preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) { http_response_code(404); exit('Nenalezeno'); }
    if (!empty($_GET['nahled'])) $n = substr($n, 0, 16) . '_n.jpg';
    $c = foto_dir($id) . '/' . $n;
    if (!is_file($c)) { http_response_code(404); exit('Nenalezeno'); }
    header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400'); header('Content-Length: ' . filesize($c));
    readfile($c); exit;
  }
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  $akce = $_POST['akce'] ?? '';
  if ($akce === 'doplneni_poslat' && is_file(__DIR__ . '/doplneni.php')) { require_once __DIR__ . '/doplneni.php'; dp_poslat($pdo); }   // žádost o doplnění poptávky
  if ($akce === 'objednavka_znovu' && is_file(__DIR__ . '/znovu.php')) { require_once __DIR__ . '/znovu.php'; zn_poslat($pdo); }   // znovu odeslat objednávku zákazníkovi (opravený e-mail)
  if ($akce !== 'foto_nahraj' && $akce !== 'foto_smaz') return;
  $id = (int)($_POST['id'] ?? 0);
  $st = $pdo->prepare('SELECT id FROM zadosti WHERE id=?'); $st->execute([$id]);
  if (!$st->fetchColumn()) { if ($akce === 'foto_nahraj') foto_json(false, 'Záznam neexistuje.'); http_response_code(404); exit('Nenalezeno'); }
  $dir = foto_dir($id);

  if ($akce === 'foto_smaz') {
    uziv_jen_admin('Fotku smí smazat jen administrátor.');
    $n = (string)($_POST['f'] ?? '');
    if (preg_match('/^[0-9a-f]{16}\.jpg$/', $n)) {
      @unlink($dir . '/' . $n); @unlink($dir . '/' . substr($n, 0, 16) . '_n.jpg');
    }
    header('Location: kartoteka.php?id=' . $id . '&ulozeno=1#fotky'); exit;
  }

  // foto_nahraj (volá JavaScript v prohlížeči, odpověď JSON)
  if (count(foto_seznam($id)) >= FOTO_MAX_KUSU) foto_json(false, 'U záznamu je už maximum ' . FOTO_MAX_KUSU . ' fotek.');
  $f = $_FILES['foto'] ?? null; $nh = $_FILES['nahled'] ?? null;
  if (!$f || $f['error'] !== UPLOAD_ERR_OK || !foto_jpeg_ok($f['tmp_name'])) foto_json(false, 'Soubor není platná fotka JPEG nebo je příliš velký.');
  if (!$nh || $nh['error'] !== UPLOAD_ERR_OK || !foto_jpeg_ok($nh['tmp_name'])) foto_json(false, 'Chybí náhled fotky.');
  if (!is_dir($dir) && !@mkdir($dir, 0775, true)) foto_json(false, 'Nelze vytvořit složku pro fotky.');
  $jm = bin2hex(random_bytes(8));
  if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $jm . '.jpg') || !move_uploaded_file($nh['tmp_name'], $dir . '/' . $jm . '_n.jpg')) foto_json(false, 'Fotku se nepodařilo uložit.');
  foto_json(true);
}

// Okno „Fotky“ do detailu záznamu.
function foto_box($r) {
  if (($r['typ'] ?? '') === 'poptavka' && is_file(__DIR__ . '/doplneni.php')) { require_once __DIR__ . '/doplneni.php'; dp_box($r); }   // žádost o doplnění poptávky
  if (($r['typ'] ?? '') === 'objednavka_interni' && is_file(__DIR__ . '/znovu.php')) { require_once __DIR__ . '/znovu.php'; zn_box($r); }   // znovu odeslat objednávku zákazníkovi
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $id = (int)$r['id']; $sez = foto_seznam($id); $zak = foto_zakaznik($r);
  ?><div class="box" id="fotky"><h3>Fotky<?= $sez ? ' (' . count($sez) . ')' : '' ?></h3>
    <?php if ($zak): ?><p style="margin:.2rem 0;color:#556"><b>Od zákazníka (<?= count($zak) ?>)</b> – klepnutím se fotka zvětší</p>
      <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin:.4rem 0 .8rem">
      <?php foreach ($zak as $s): ?><a href="?soubor=<?= urlencode($s) ?>" class="fotoLb" style="display:block;width:150px"><img src="?soubor=<?= urlencode($s) ?>" alt="Fotka od zákazníka" loading="lazy" style="width:150px;height:112px;object-fit:cover;border-radius:8px;border:1px solid #d8dde6;display:block"></a><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($sez): ?><div style="display:flex;flex-wrap:wrap;gap:.6rem;margin:.4rem 0 .8rem">
      <?php foreach ($sez as $f): ?><div style="width:150px">
        <a href="?foto=<?= $id ?>&amp;f=<?= $e($f['n']) ?>" class="fotoLb"><img src="?foto=<?= $id ?>&amp;f=<?= $e($f['n']) ?>&amp;nahled=1" alt="Fotka" loading="lazy" style="width:150px;height:112px;object-fit:cover;border-radius:8px;border:1px solid #d8dde6;display:block"></a>
        <form method="post" onsubmit="return confirm('Smazat tuto fotku?')" style="margin:.25rem 0 0<?= uziv_admin() ? '' : ';display:none' ?>"><input type="hidden" name="akce" value="foto_smaz"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="f" value="<?= $e($f['n']) ?>"><button class="sec" style="padding:.2rem .6rem;font-size:.85em">Smazat</button></form>
      </div><?php endforeach; ?></div>
    <?php elseif (!$zak): ?><p style="color:#667;margin:.2em 0 .6em">Zatím žádné fotky.</p><?php endif; ?>
    <input type="file" id="fotoVyber" accept="image/*" multiple style="display:none">
    <button type="button" id="fotoTlacitko">📷 Přidat fotky</button>
    <span id="fotoStav" style="margin-left:.6rem;color:#556"></span>
    <script>
    (function () {
      var id = <?= $id ?>, vyber = document.getElementById('fotoVyber'), tl = document.getElementById('fotoTlacitko'), stav = document.getElementById('fotoStav');
      tl.addEventListener('click', function () { vyber.click(); });
      function zmensi(soubor, max, kvalita) {
        return createImageBitmap(soubor, { imageOrientation: 'from-image' }).then(function (bm) {
          var k = Math.min(1, max / Math.max(bm.width, bm.height)), c = document.createElement('canvas');
          c.width = Math.max(1, Math.round(bm.width * k)); c.height = Math.max(1, Math.round(bm.height * k));
          c.getContext('2d').drawImage(bm, 0, 0, c.width, c.height);
          return new Promise(function (ok, ne) { c.toBlob(function (b) { b ? ok(b) : ne(new Error('převod')); }, 'image/jpeg', kvalita); });
        });
      }
      vyber.addEventListener('change', function () {
        var soubory = Array.prototype.slice.call(vyber.files || []); if (!soubory.length) return;
        tl.disabled = true; var hotovo = 0, chyby = [];
        function dalsi(i) {
          if (i >= soubory.length) {
            stav.textContent = chyby.length ? 'Nahráno ' + hotovo + ', chyby: ' + chyby.join('; ') : 'Nahráno ' + hotovo + '.';
            if (hotovo) setTimeout(function () { location.href = 'kartoteka.php?id=' + id + '&ulozeno=1#fotky'; }, 600); else tl.disabled = false;
            return;
          }
          stav.textContent = 'Nahrávám ' + (i + 1) + ' z ' + soubory.length + '…';
          Promise.all([zmensi(soubory[i], 1600, 0.82), zmensi(soubory[i], 320, 0.75)]).then(function (b) {
            var fd = new FormData(); fd.append('akce', 'foto_nahraj'); fd.append('id', id);
            fd.append('foto', b[0], 'foto.jpg'); fd.append('nahled', b[1], 'nahled.jpg');
            return fetch('kartoteka.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
          }).then(function (j) { if (j.ok) hotovo++; else chyby.push(j.zprava || 'chyba'); })
            .catch(function () { chyby.push(soubory[i].name + ' (nelze zpracovat)'); })
            .then(function () { dalsi(i + 1); });
        }
        dalsi(0);
      });
    })();
    </script>
    <style>
    #fotoLb{position:fixed;inset:0;z-index:10000;background:rgba(8,14,26,.94);display:none;align-items:center;justify-content:center;padding:max(56px,env(safe-area-inset-top,0px)) max(8px,env(safe-area-inset-right,0px)) max(8px,env(safe-area-inset-bottom,0px)) max(8px,env(safe-area-inset-left,0px));overscroll-behavior:contain}
    #fotoLb.on{display:flex}
    #fotoLb img{max-width:100%;max-height:100%;object-fit:contain;border-radius:4px;background:#111}
    #fotoLb iframe{width:100%;height:100%;border:0;border-radius:4px;background:#fff}
    #fotoLb.pdf img,#fotoLb:not(.pdf) iframe{display:none}
    #fotoLbBar{position:absolute;top:max(10px,env(safe-area-inset-top,0px));right:max(10px,env(safe-area-inset-right,0px));z-index:1;display:flex;gap:8px}
    #fotoLbBar button,#fotoLbBar a{background:#fff;color:#16233a;border:0;border-radius:999px;padding:11px 20px;font:700 16px system-ui,Arial,sans-serif;cursor:pointer;text-decoration:none;box-shadow:0 2px 10px rgba(0,0,0,.4)}
    #fotoLb:not(.pdf) #fotoLbOtev{display:none}
    @media (hover:hover){#fotoLbBar button:hover,#fotoLbBar a:hover{background:#e6ebf3}}
    </style>
    <script>
    (function () {
      var lb = document.getElementById('fotoLb');
      if (!lb) {
        lb = document.createElement('div'); lb.id = 'fotoLb'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-label', 'Náhled přílohy');
        lb.innerHTML = '<div id="fotoLbBar"><a id="fotoLbOtev" target="_blank" rel="noopener">↗ Otevřít samostatně</a><button type="button" id="fotoLbX">✕ Zavřít</button></div><img alt="Fotka"><iframe title="PDF příloha"></iframe>';
        document.body.appendChild(lb);
      }
      var im = lb.querySelector('img'), fr = lb.querySelector('iframe'), x = lb.querySelector('#fotoLbX'), ot = lb.querySelector('#fotoLbOtev'), vHistorii = false;
      function schovej() { lb.classList.remove('on', 'pdf'); im.removeAttribute('src'); fr.removeAttribute('src'); }
      function otevri(url, pdf) {
        if (pdf) { fr.src = url; ot.href = url; lb.classList.add('pdf'); } else { im.src = url; lb.classList.remove('pdf'); }
        lb.classList.add('on');
        try { history.pushState({ fotoLb: 1 }, ''); vHistorii = true; } catch (e) { vHistorii = false; }
        x.focus();
      }
      function zavri() {
        if (!lb.classList.contains('on')) return;
        schovej();
        if (vHistorii) { vHistorii = false; try { history.back(); } catch (e) {} }
      }
      document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a');
        if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button) return;
        var h = a.getAttribute('href') || '', foto = a.classList.contains('fotoLb'), pdf = /^\?soubor=.*\.pdf$/i.test(h);   // foto, nebo PDF příloha záznamu
        if (!foto && !pdf) return;
        ev.preventDefault(); otevri(h, pdf);
      });
      x.addEventListener('click', function (ev) { ev.stopPropagation(); zavri(); });
      ot.addEventListener('click', function (ev) { ev.stopPropagation(); });
      lb.addEventListener('click', function (ev) { if (ev.target === lb || ev.target === im) zavri(); });
      document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') zavri(); });
      window.addEventListener('popstate', function () { if (lb.classList.contains('on')) { vHistorii = false; schovej(); } });
    })();
    </script>
  </div><?php
}
