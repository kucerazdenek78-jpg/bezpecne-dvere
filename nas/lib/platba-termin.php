<?php
// Kartotéka – seznam objednávek: zvýraznění objednávek s nezaplacenou zálohou (24. 11. 2026, přání Zdeňka).
//  • objednávka má termín montáže a záloha ještě nedorazila → řádek oranžově + štítek „Záloha nezaplacena“
//  • záloha nedorazila do termínu → celý řádek červeně + štítek „Záloha po termínu“
// Termín pro zálohu = den objednání + lhůta z nastavení (Správa → E-maily a příjemci, „Hlídání nezaplacené zálohy“, výchozí 4 dny),
// nejpozději ale den před montáží (v den montáže je řádek červený vždy).
// Používá kartoteka.php: platba_termin($r, $d, $cfg) u každého řádku a platba_termin_css() jednou před tabulkou.

function platba_termin(array $r, array $d, array $cfg, ?int $ted = null): ?array {
  if (($r['typ'] ?? '') !== 'objednavka_interni') return null;
  if ((string)($r['zaloha_prijata_dne'] ?? '') !== '') return null;                              // záloha už dorazila
  if (in_array((string)($r['stav'] ?? ''), ['storno', 'vyrizeno', 'namontovano'], true)) return null;
  if ((float)($d['zaloha'] ?? 0) <= 0) return null;                                              // objednávka bez požadované zálohy
  $t = strtotime((string)($r['vytvoreno'] ?? '')); if (!$t) return null;
  $ted = $ted ?? time(); $dnes = date('Y-m-d', $ted);
  $lhuta = 4;
  if (function_exists('nast_zaloha')) $lhuta = (int)nast_zaloha($cfg)['lhuta'];
  elseif (isset($cfg['zaloha']['lhuta'])) $lhuta = max(1, min(60, (int)$cfg['zaloha']['lhuta']));
  $do = date('Y-m-d', strtotime(date('Y-m-d', $t) . ' +' . $lhuta . ' days'));                   // do kdy měla záloha dorazit
  $m = (string)(($d['montaz_dne'] ?? '') ?: ($d['termin_datum'] ?? ''));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $m)) $m = '';
  if ($m !== '') { $pred = date('Y-m-d', strtotime($m . ' -1 day')); if ($pred < $do) $do = max($pred, date('Y-m-d', $t)); }
  $po = $dnes > $do || ($m !== '' && $dnes >= $m);
  if (!$po && $m === '') return null;                                                            // bez termínu montáže a ještě v lhůtě: nic
  $cz = fn($x) => date('j. n.', strtotime($x));
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $kc = number_format((float)$d['zaloha'], 0, ',', ' ') . ' Kč';
  if ($po) { $trida = 'pt-pozde'; $text = '⛔ Záloha po termínu'; $pod = 'měla dorazit do ' . $cz($do) . ($m !== '' ? ' · montáž ' . $cz($m) : ''); }
  else { $trida = 'pt-ceka'; $text = '💰 Záloha nezaplacena'; $pod = 'má dorazit do ' . $cz($do) . ' · montáž ' . $cz($m); }
  $html = '<div class="pt-info"><span class="chip pt-chip">' . $e($text) . '</span><div class="pt-pod">' . $e($kc . ' · ' . $pod) . '</div></div>';
  return ['po' => $po, 'trida' => $trida, 'do' => $do, 'montaz' => $m, 'html' => $html];
}

function platba_termin_css(): string {
  return '<style>'
    . '.pt-info{margin-top:5px}.pt-pod{font-size:12px;margin-top:2px;white-space:normal}'
    . 'tr.pt-ceka td{background:#fff4e0!important}tr.pt-ceka td:first-child{box-shadow:inset 5px 0 0 #e8650a}'
    . 'tr.pt-ceka .pt-chip{background:#e8650a;color:#fff}tr.pt-ceka .pt-pod{color:#9a3b00}'
    . 'tr.pt-pozde td{background:#f6b5b5!important;color:#7a0c0c}tr.pt-pozde td:first-child{box-shadow:inset 6px 0 0 #c81e1e}'
    . 'tr.pt-pozde a{color:#7a0c0c}tr.pt-pozde .mut{color:#8a2a2a}'
    . 'tr.pt-pozde .pt-chip,.pt-info.pt-pozde .pt-chip{background:#c81e1e;color:#fff}tr.pt-pozde .pt-pod{color:#7a0c0c;font-weight:600}'
    . '.pt-info.pt-ceka .pt-chip{background:#e8650a;color:#fff}'
    . '</style>';
}

// Seznam objednávek v kartotéce: styl + malý skript, který podle čísla záznamu (odkaz ?id=N v řádku) obarví řádky
// a pod štítek stavu doplní „Záloha nezaplacena / po termínu“. Volá se z dodavatel_box_html() v lib/terminy-dodavatel.php
// (kartoteka.php je velký soubor a účet MCP ho neumí bezpečně přepsat, proto se řádky značí až v prohlížeči).
function platba_termin_seznam_html(PDO $pdo, ?int $ted = null): string {
  global $cfg;
  if (isset($_GET['id'])) return '';
  try {
    $st = $pdo->query("SELECT id, typ, stav, vytvoreno, zaloha_prijata_dne, data_json FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','vyrizeno','namontovano') AND (zaloha_prijata_dne IS NULL OR zaloha_prijata_dne='')");
    $m = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $d = json_decode((string)$r['data_json'], true) ?: [];
      $p = platba_termin($r, $d, is_array($cfg ?? null) ? $cfg : [], $ted);
      if ($p) $m[(int)$r['id']] = [$p['trida'], $p['html']];
    }
  } catch (Throwable $e) { return ''; }
  if (!$m) return '';
  $js = json_encode($m, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  return platba_termin_css() . '<script>document.addEventListener("DOMContentLoaded",function(){var M=' . $js . ';'
    . 'document.querySelectorAll("main table tr").forEach(function(tr){var a=tr.querySelector("td a[href^=\"?id=\"]");if(!a)return;'
    . 'var p=M[a.getAttribute("href").replace("?id=","")];if(!p)return;tr.classList.add(p[0]);'
    . 'var ch=tr.querySelector("td > span.chip");if(ch&&!tr.querySelector(".pt-info"))ch.insertAdjacentHTML("afterend",p[1]);});});</script>';
}
