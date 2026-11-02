<?php
// Navrhne další číslo v řadě: objednavka -> 83/2026, nabidka -> N 12/2026, poptavka -> P 25/2026.
// Jen návrh (formulář číslo nezamyká, prodavač ho může přepsat podle papírové objednávky).
// Počítají se odeslané záznamy (zadosti) i rozpracované nabídky/objednávky (koncepty) – rozpracovaná si číslo „drží“.
// Kontrola před odesláním: ?typ=nabidka&over=N 1/2026&konc=ID → {cislo: další volné, volne: true/false}
//   (volne = číslo zatím nemá žádný odeslaný záznam ani jiná rozpracovaná nabídka než ta s ID konc).
require __DIR__ . '/lib/start.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $cfg['povolene_origin'], true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); header('Access-Control-Allow-Methods: GET, OPTIONS'); }
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
$typ = $_GET['typ'] ?? '';
require_once __DIR__ . '/lib/cisla.php';
if (!isset(cisla_typy()[$typ])) { http_response_code(400); echo json_encode(['cislo' => '']); exit; }
$rok = (int)date('Y');
$norm = fn($c) => preg_replace('/\s+/', '', mb_strtolower(trim((string)$c)));   // „N 1/2026“ = „N1 / 2026“
$v = cisla_spocitej(db(), $cfg, $typ, $rok);   // počátek řady se nastavuje v kartotéce (Číslování)
$pre = $v['pre']; $dalsi = $v['dalsi']; $cisla = $v['cisla'];
$out = ['cislo' => $pre . $dalsi . '/' . $rok];
if (isset($_GET['over'])) {
  $over = $norm($_GET['over']); $konc = (int)($_GET['konc'] ?? 0); $volne = $over !== '';
  foreach ($cisla as [$c, $kid]) if ($norm($c) === $over && !($kid && $kid === $konc)) { $volne = false; break; }
  $out['volne'] = $volne;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
