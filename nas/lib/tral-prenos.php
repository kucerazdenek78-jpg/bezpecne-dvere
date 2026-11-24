<?php
// Přenos objednávky ERKADO do TRALu (24. 11. 2026). Průvodce ERKADO ukládá přesný výběr (kódy voleb TRALu) do
// /data/erkado-tral/konfig/<hash>.json (erkado-konfig-uloz.php); nabídka i objednávka nesou jen otisk „#hash“ (cfg.erkado.stav.dekor).
// Tady: dohledání konfigurací k záznamu, kód pro TRAL (čte ho https://bezpecne-dvere.cz/tral/nacti.js v prohlížeči na tral.cz)
// a tlačítko „Do TRALu“ v seznamu objednávek. Do TRALu se nic neposílá ze serveru – konfiguraci načte jejich vlastní konfigurátor.

function tral_hashe(string $dataJson): array {
  if (!preg_match_all('/#([0-9a-f]{8})(?![0-9a-f])/', $dataJson, $m)) return [];
  return array_values(array_unique($m[1]));
}
function tral_konfig(string $h): ?array {
  global $DATA;
  if (!preg_match('/^[0-9a-f]{8}$/', $h)) return null;
  $f = rtrim((string)$DATA, '/') . '/erkado-tral/konfig/' . $h . '.json';
  if (!is_file($f)) return null;
  $k = json_decode((string)@file_get_contents($f), true);
  return (is_array($k) && !empty($k['opts']) && is_array($k['opts'])) ? $k : null;
}
// konfigurace patřící k záznamu (jen ty, ke kterým existuje uložený výběr)
function tral_konfigy(string $dataJson): array {
  $o = [];
  foreach (tral_hashe($dataJson) as $h) { $k = tral_konfig($h); if ($k) $o[$h] = $k; }
  return $o;
}
// kód pro načtení v TRALu: base64url(JSON {c: číslo objednávky, m: model, o: [[skupina, atribut, ExtId, název volby, název atributu], …]})
function tral_kod(array $k, string $cislo = ''): string {
  $o = [];
  foreach ($k['opts'] as $x) $o[] = [(int)$x['groupId'], (int)$x['attrId'], (string)$x['extId'], (string)($x['name'] ?? ''), (string)($x['attr'] ?? '')];
  $j = json_encode(['c' => $cislo, 'm' => (string)($k['model'] ?? ''), 'o' => $o], JSON_UNESCAPED_UNICODE);
  return rtrim(strtr(base64_encode($j), '+/', '-_'), '=');
}
// seznam objednávek: do sloupce Akce přidá tlačítko „🚚 Do TRALu“ u objednávek, které mají uloženou konfiguraci ERKADO
function tral_seznam_html(PDO $pdo): string {
  if (isset($_GET['id'])) return '';
  try {
    $st = $pdo->query("SELECT id, data_json FROM zadosti WHERE typ='objednavka_interni' AND stav NOT IN ('storno','vyrizeno','namontovano') AND data_json LIKE '%#%' ORDER BY id DESC LIMIT 400");
    $ids = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) if (tral_konfigy((string)$r['data_json'])) $ids[] = (int)$r['id'];
  } catch (Throwable $e) { return ''; }
  if (!$ids) return '';
  return '<script>document.addEventListener("DOMContentLoaded",function(){var I=' . json_encode($ids) . ';'
    . 'document.querySelectorAll("main table tr").forEach(function(tr){var a=tr.querySelector("td a[href^=\"?id=\"]");if(!a)return;'
    . 'var id=parseInt(a.getAttribute("href").replace("?id=",""),10);if(I.indexOf(id)<0||tr.querySelector(".tral-btn"))return;'
    . 'var td=tr.lastElementChild;if(td)td.insertAdjacentHTML("beforeend","<a class=\"btn sec tral-btn\" style=\"padding:4px 8px;font-size:13px\" href=\"erkado-do-tralu.php?id="+id+"\" title=\"Přenést konfiguraci dveří do TRALu (ERKADO)\">🚚 Do TRALu</a>");});});</script>';
}
