<?php
// erkado-konfig-uloz.php (24. 11. 2026): ulozi presnou konfiguraci dveri z pruvodce ERKADO (kody voleb TRALu) do /data/erkado-tral/konfig/<h>.json,
// aby sla objednavka pozdeji prenest do TRALu (erkado-do-tralu.php). <h> = 8 znaku otisku vyberu, stejny, ktery pruvodce dava do nabidky
// (polozky[].cfg.erkado.stav.dekor = "… #<h>"). INTERNI ?key=.  POST (JSON): {h, opts:[{groupId,attrId,extId,name,group,attr,price}], rozmer, model}
require __DIR__ . '/lib/start.php';
$KEY = @require __DIR__ . '/openclaw-key.php';
if (!is_string($KEY) || !hash_equals($KEY, (string)($_GET['key'] ?? ''))) { http_response_code(404); exit; }
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
$konec = function (int $kod, array $o) { http_response_code($kod); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $konec(405, ['ok' => false]);
$raw = (string)file_get_contents('php://input', false, null, 0, 200001);
if (strlen($raw) > 200000) $konec(413, ['ok' => false, 'chyba' => 'velke']);
$in = json_decode($raw, true); if (!is_array($in)) $konec(400, ['ok' => false, 'chyba' => 'data']);
$h = (string)($in['h'] ?? ''); if (!preg_match('/^[0-9a-f]{8}$/', $h)) $konec(400, ['ok' => false, 'chyba' => 'klic']);
$opts = [];
foreach ((array)($in['opts'] ?? []) as $o) {
  if (!is_array($o) || count($opts) >= 200) continue;
  $g = (int)($o['groupId'] ?? 0); $a = (int)($o['attrId'] ?? 0); $x = (string)($o['extId'] ?? '');
  if ($g <= 0 || $a <= 0 || !preg_match('/^\d{1,9}$/', $x)) continue;
  $t = fn($k) => mb_substr(trim((string)($o[$k] ?? '')), 0, 200);
  $opts[] = ['groupId' => $g, 'attrId' => $a, 'extId' => $x, 'name' => $t('name'), 'group' => $t('group'), 'attr' => $t('attr'),
    'price' => is_numeric($o['price'] ?? null) ? (float)$o['price'] : 0, 'gOrder' => (int)($o['gOrder'] ?? 0), 'aOrder' => (int)($o['aOrder'] ?? 0)];
}
if (!$opts) $konec(400, ['ok' => false, 'chyba' => 'prazdne']);
$dir = $DATA . '/erkado-tral/konfig';
if (!is_dir($dir) && !@mkdir($dir, 0770, true)) $konec(500, ['ok' => false, 'chyba' => 'adresar']);
$r = is_array($in['rozmer'] ?? null) ? ['w' => (int)($in['rozmer']['w'] ?? 0), 'h' => (int)($in['rozmer']['h'] ?? 0)] : null;
$zaznam = ['h' => $h, 'kdy' => date('Y-m-d H:i:s'), 'model' => mb_substr(trim((string)($in['model'] ?? '')), 0, 120), 'rozmer' => $r, 'opts' => $opts];
if (file_put_contents("$dir/$h.json", json_encode($zaznam, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) $konec(500, ['ok' => false, 'chyba' => 'zapis']);
$konec(200, ['ok' => true, 'pocet' => count($opts)]);
