<?php
// Minimalistický PDF generátor pro servis oken (bez externích knihoven; prostředí nemá GD ani wkhtmltopdf).
// Embeduje subset fontu Liberation Sans (reg + bold) jako Identity-H CIDFontType2 → plná česká diakritika.
// Fonty + metrika jsou v pdf-fonty/ (reg.ttf, bold.ttf, metrics.json). Používá se pro přílohu „seznam zjištěných závad".

class OknaPdf {
  private array $obj = [];        // číslo → obsah (string) nebo ['stream'=>..., 'dict'=>...]
  private array $pages = [];      // čísla objektů stránek
  private string $buf = '';       // obsah aktuální stránky
  private array $fonts;           // 'reg'/'bold' → metrika
  private array $fontObj = [];    // 'reg'/'bold' → číslo Type0 objektu
  public float $w = 595.28, $h = 841.89;  // A4 v bodech
  public float $ml = 48, $mr = 48, $mt = 54, $mb = 54;
  public float $y;                // kurzor odshora (body od horního okraje)

  function __construct() {
    $m = json_decode((string)@file_get_contents(__DIR__ . '/pdf-fonty/metrics.json'), true);
    if (!$m) throw new RuntimeException('pdf: chybí metrics.json');
    $this->fonts = $m;
    $this->y = $this->mt;
  }

  // šířka textu v bodech
  function sirka(string $s, string $font, float $size): float {
    $f = $this->fonts[$font]; $upm = $f['upm']; $w = 0;
    foreach ($this->codepoints($s) as $u) {
      $g = $f['uni2gid'][$u] ?? ($f['uni2gid'][63] ?? 0);
      $w += ($f['widths'][$g] ?? 0);
    }
    return $w / $upm * $size;
  }

  private function codepoints(string $s): array {
    $out = []; $len = strlen($s); $i = 0;
    while ($i < $len) {
      $c = ord($s[$i]);
      if ($c < 0x80) { $out[] = $c; $i++; }
      elseif ($c < 0xE0) { $out[] = (($c & 0x1f) << 6) | (ord($s[$i+1]) & 0x3f); $i += 2; }
      elseif ($c < 0xF0) { $out[] = (($c & 0x0f) << 12) | ((ord($s[$i+1]) & 0x3f) << 6) | (ord($s[$i+2]) & 0x3f); $i += 3; }
      else { $out[] = (($c & 0x07) << 18) | ((ord($s[$i+1]) & 0x3f) << 12) | ((ord($s[$i+2]) & 0x3f) << 6) | (ord($s[$i+3]) & 0x3f); $i += 4; }
    }
    return $out;
  }

  // text zapsaný jako Identity-H (2 bajty GID na znak)
  private function hexGid(string $s, string $font): string {
    $f = $this->fonts[$font]; $h = '';
    foreach ($this->codepoints($s) as $u) {
      $g = $f['uni2gid'][$u] ?? ($f['uni2gid'][63] ?? 0);
      $h .= sprintf('%04X', $g);
    }
    return $h;
  }

  function novaStranka(): void {
    if ($this->buf !== '') $this->ulozStranku();
    $this->buf = ''; $this->y = $this->mt;
  }
  private function ulozStranku(): void {
    $this->pages[] = '@PAGE@' . count($this->pages);   // placeholder, rozvine se v render()
    $this->obj['page_' . (count($this->pages) - 1)] = $this->buf;
  }

  // vytiskne text na aktuální pozici kurzoru (nebo na zadané x), posune kurzor dolů o výšku řádku
  function text(string $s, string $font = 'reg', float $size = 10, ?float $x = null, string $barva = '0 0 0', float $radek = 1.35): void {
    $x = $x ?? $this->ml;
    $ty = $this->h - $this->y - $size;           // baseline
    $this->buf .= "BT /F" . ($font === 'bold' ? '2' : '1') . " " . $this->n($size) . " Tf " . $barva . " rg "
      . $this->n($x) . " " . $this->n($ty) . " Td <" . $this->hexGid($s, $font) . "> Tj ET\n";
    $this->y += $size * $radek;
  }

  // zalomí text na šířku a vytiskne víceřádkově; vrátí počet řádků
  function odstavec(string $s, string $font, float $size, float $x, float $sirkaMax, float $radek = 1.3): int {
    $slova = preg_split('/\s+/u', trim($s)); $radky = []; $akt = '';
    foreach ($slova as $sl) {
      if ($sl === '') continue;
      $zkus = $akt === '' ? $sl : $akt . ' ' . $sl;
      if ($this->sirka($zkus, $font, $size) > $sirkaMax && $akt !== '') { $radky[] = $akt; $akt = $sl; }
      else $akt = $zkus;
    }
    if ($akt !== '') $radky[] = $akt;
    if (!$radky) $radky = [''];
    foreach ($radky as $r) $this->text($r, $font, $size, $x, '0 0 0', $radek);
    return count($radky);
  }

  // obdélník (výplň) – souřadnice odshora
  function obdelnik(float $x, float $yTop, float $w, float $vyska, string $barva): void {
    $this->buf .= $barva . " rg " . $this->n($x) . " " . $this->n($this->h - $yTop - $vyska) . " "
      . $this->n($w) . " " . $this->n($vyska) . " re f\n";
  }
  function cara(float $x1, float $y1, float $x2, float $y2, string $barva = '0.8 0.84 0.88', float $tl = 0.6): void {
    $this->buf .= $barva . " RG " . $this->n($tl) . " w " . $this->n($x1) . " " . $this->n($this->h - $y1) . " m "
      . $this->n($x2) . " " . $this->n($this->h - $y2) . " l S\n";
  }

  private function n(float $v): string { return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.'); }

  private function pridej(string $obsah): int { $this->obj[] = $obsah; return array_key_last($this->obj) + 1; }

  function uloz(string $cesta): bool {
    if ($this->buf !== '') $this->ulozStranku();
    // sestavení objektů
    $objekty = [];   // 1-based
    $add = function (string $s) use (&$objekty): int { $objekty[] = $s; return count($objekty); };

    // fonty (reg=1/F1, bold=2/F2) – každý potřebuje: FontFile2, FontDescriptor, CIDFont, Type0, ToUnicode
    $fontRef = [];
    foreach (['reg' => 'F1', 'bold' => 'F2'] as $klic => $jm) {
      $ttf = (string)@file_get_contents(__DIR__ . '/pdf-fonty/' . ($klic === 'bold' ? 'bold.ttf' : 'reg.ttf'));
      $f = $this->fonts[$klic]; $upm = $f['upm'];
      $comp = gzcompress($ttf, 9);
      $ff = $add("<< /Length " . strlen($comp) . " /Length1 " . strlen($ttf) . " /Filter /FlateDecode >>\nstream\n" . $comp . "\nendstream");
      // W pole (šířky v 1000/em)
      $W = '';
      ksort($f['widths']);
      foreach ($f['widths'] as $g => $w) $W .= $g . ' [' . round($w / $upm * 1000) . '] ';
      // ToUnicode
      $tu = $this->toUnicode($f);
      $tuc = gzcompress($tu, 9);
      $tuObj = $add("<< /Length " . strlen($tuc) . " /Filter /FlateDecode >>\nstream\n" . $tuc . "\nendstream");
      $asc = round($f['ascent'] / $upm * 1000); $desc = round($f['descent'] / $upm * 1000);
      $fd = $add("<< /Type /FontDescriptor /FontName /" . $f['name'] . " /Flags 32 /FontBBox [-200 " . $desc . " 1100 " . $asc . "]"
        . " /ItalicAngle 0 /Ascent " . $asc . " /Descent " . $desc . " /CapHeight " . $asc . " /StemV 80 /FontFile2 " . $ff . " 0 R >>");
      $cid = $add("<< /Type /Font /Subtype /CIDFontType2 /BaseFont /" . $f['name'] . " /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>"
        . " /FontDescriptor " . $fd . " 0 R /CIDToGIDMap /Identity /DW 500 /W [ " . $W . "] >>");
      $t0 = $add("<< /Type /Font /Subtype /Type0 /BaseFont /" . $f['name'] . " /Encoding /Identity-H /DescendantFonts [" . $cid . " 0 R] /ToUnicode " . $tuObj . " 0 R >>");
      $fontRef[$jm] = $t0;
    }

    $resObj = $add("<< /Font << /F1 " . $fontRef['F1'] . " 0 R /F2 " . $fontRef['F2'] . " 0 R >> >>");

    // stránky
    $pagesObjNum = count($objekty) + 1; // vyhradíme číslo pro Pages
    $objekty[] = '@PAGES@';             // placeholder
    $kids = [];
    foreach ($this->pages as $i => $_) {
      $content = $this->obj['page_' . $i] ?? '';
      $cc = gzcompress($content, 9);
      $cObj = $add("<< /Length " . strlen($cc) . " /Filter /FlateDecode >>\nstream\n" . $cc . "\nendstream");
      $pObj = $add("<< /Type /Page /Parent " . $pagesObjNum . " 0 R /MediaBox [0 0 " . $this->n($this->w) . " " . $this->n($this->h) . "]"
        . " /Resources " . $resObj . " 0 R /Contents " . $cObj . " 0 R >>");
      $kids[] = $pObj . ' 0 R';
    }
    $objekty[$pagesObjNum - 1] = "<< /Type /Pages /Count " . count($kids) . " /Kids [" . implode(' ', $kids) . "] >>";
    $catalog = $add("<< /Type /Catalog /Pages " . $pagesObjNum . " 0 R >>");

    // zápis souboru + xref
    $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
    $offsety = [];
    foreach ($objekty as $i => $o) { $offsety[$i + 1] = strlen($out); $out .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n"; }
    $xref = strlen($out);
    $n = count($objekty) + 1;
    $out .= "xref\n0 " . $n . "\n0000000000 65535 f \n";
    for ($i = 1; $i < $n; $i++) $out .= sprintf("%010d 00000 n \n", $offsety[$i]);
    $out .= "trailer\n<< /Size " . $n . " /Root " . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return file_put_contents($cesta, $out) !== false;
  }

  private function toUnicode(array $f): string {
    $map = [];
    foreach ($f['uni2gid'] as $u => $g) $map[$g] = $u;
    ksort($map);
    $lines = '';
    foreach ($map as $g => $u) $lines .= sprintf("<%04X> <%04X>\n", $g, $u);
    return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
      . "/CMapName /Adobe-Identity-UCS def /CMapType 2 def\n1 begincodespacerange <0000> <FFFF> endcodespacerange\n"
      . count($map) . " beginbfchar\n" . $lines . "endbfchar\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
  }
}

// Vytvoří PDF se seznamem zjištěných závad. $hlav = ['svj'=>..,'servis'=>..,'smlouva'=>..]; $zavady = [['byt'=>,'zavada'=>,'oprava'=>,'cena'=>], ...]
function okna_pdf_zavady(string $cesta, array $hlav, array $zavady): bool {
  $p = new OknaPdf();
  $p->novaStranka();
  // hlavička
  $p->text('Zjištěné závady při servisu oken', 'bold', 15);
  $p->y += 2;
  $p->text('KK SYSTEM · BEZPEČNÉ DVEŘE CZ · IČ 64682676 · info@kksystem.cz', 'reg', 8.5, null, '0.4 0.47 0.55');
  $p->y += 6;
  foreach (['SVJ / objednatel' => $hlav['svj'] ?? '', 'Provedený servis' => $hlav['servis'] ?? '', 'Smlouva' => $hlav['smlouva'] ?? ''] as $k => $v) {
    if ($v === '') continue;
    $p->text($k . ': ', 'bold', 9.5, $p->ml); $p->y -= 9.5 * 1.35;  // zpět nahoru, dopíšeme hodnotu za popisek
    $p->text($v, 'reg', 9.5, $p->ml + $p->sirka($k . ': ', 'bold', 9.5));
  }
  $p->y += 6;
  // tabulka
  $x0 = $p->ml; $prava = $p->w - $p->mr; $celk = $prava - $x0;
  $cBy = $x0; $cZa = $x0 + $celk * 0.16; $cOp = $x0 + $celk * 0.52; $cCe = $x0 + $celk * 0.86;
  $wZa = $cOp - $cZa - 8; $wOp = $cCe - $cOp - 8; $wCe = $prava - $cCe;
  $hlavicka = function () use ($p, $x0, $cBy, $cZa, $cOp, $cCe, $celk, $prava) {
    $p->obdelnik($x0, $p->y, $celk, 16, '0.93 0.95 0.97');
    $yT = $p->y; $p->y += 3.5;
    $p->text('Byt / vchod', 'bold', 9, $cBy); $p->y -= 9 * 1.35;
    $p->text('Zjištěná závada', 'bold', 9, $cZa); $p->y -= 9 * 1.35;
    $p->text('Návrh opravy', 'bold', 9, $cOp); $p->y -= 9 * 1.35;
    $p->text('Cena s DPH', 'bold', 9, $cCe);
    $p->y = $yT + 16 + 3;
    $p->cara($x0, $p->y - 2, $prava, $p->y - 2);
  };
  $hlavicka();
  $celkem = 0; $jenMax = true;
  foreach ($zavady as $z) {
    $yStart = $p->y;
    // odhad výšky řádku
    $p->y = $yStart;
    $n1 = $p->odstavec(($z['byt'] ?? ''), 'bold', 9, $cBy, $cZa - $cBy - 6); $y1 = $p->y;
    $p->y = $yStart;
    $n2 = $p->odstavec(($z['zavada'] ?? '—'), 'reg', 9, $cZa, $wZa); $y2 = $p->y;
    $p->y = $yStart;
    $n3 = $p->odstavec(($z['oprava'] ?? ''), 'reg', 9, $cOp, $wOp); $y3 = $p->y;
    $p->y = $yStart;
    $cenaTxt = '';
    if (($z['cena'] ?? 0) > 0) { $cenaTxt = ($z['max'] ?? false ? 'do ' : '') . number_format((float)$z['cena'], 0, ',', ' ') . ' Kč'; $celkem += (float)$z['cena']; if (empty($z['max'])) $jenMax = false; }
    $p->odstavec($cenaTxt, 'reg', 9, $cCe, $wCe);
    $p->y = max($y1, $y2, $y3, $p->y) + 4;
    $p->cara($x0, $p->y - 2, $prava, $p->y - 2, '0.9 0.92 0.94', 0.4);
    if ($p->y > $p->h - $p->mb - 40) { $p->novaStranka(); $hlavicka(); }
  }
  if ($celkem > 0) {
    $p->y += 2;
    $p->obdelnik($x0, $p->y, $celk, 17, '0.93 0.95 0.97');
    $p->y += 4;
    $p->text('Celkem k odsouhlasení (orientačně, ceny jsou maximální)', 'bold', 9.5, $cBy); $p->y -= 9.5 * 1.35;
    $p->text(($jenMax ? 'do ' : '') . number_format($celkem, 0, ',', ' ') . ' Kč', 'bold', 9.5, $cCe);
    $p->y += 10;
  }
  $p->y += 6;
  $p->odstavec('Toto je přehled závad zjištěných při pravidelném servisu oken. Uvedené ceny jsou maximální a budou vyúčtovány podle skutečného rozsahu prací. Oprava bude provedena až po odsouhlasení.', 'reg', 8.5, $x0, $celk);
  return $p->uloz($cesta);
}
