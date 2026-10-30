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
$mapa = ['objednavka' => ['objednavka_interni', '', 'objednavka-vice'], 'nabidka' => ['nabidka', 'N ', 'nabidka'], 'poptavka' => ['poptavka', 'P ', '']];
if (!isset($mapa[$typ])) { http_response_code(400); echo json_encode(['cislo' => '']); exit; }
[$dbtyp, $pre, $druhKonc] = $mapa[$typ];
$rok = (int)date('Y');
$norm = fn($c) => preg_replace('/\s+/', '', mb_strtolower(trim((string)$c)));   // „N 1/2026“ = „N1 / 2026“
$pdo = db();
$cisla = [];   // [cislo, id konceptu nebo 0]
$st = $pdo->prepare('SELECT cislo FROM zadosti WHERE typ=?'); $st->execute([$dbtyp]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) $cisla[] = [(string)$c, 0];
if ($druhKonc !== '') {
  try { $st = $pdo->prepare("SELECT id, cislo FROM koncepty WHERE stav='rozpracovano' AND druh=?"); $st->execute([$druhKonc]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $cisla[] = [(string)$r['cislo'], (int)$r['id']]; } catch (Throwable $e) { /* tabulka koncepty ještě nemusí existovat */ }
}
$max = 0;
foreach ($cisla as [$c]) {
  if (preg_match('/^' . preg_quote($pre, '/') . '(\d{1,6})\s*\/\s*' . $rok . '$/', trim($c), $m)) $max = max($max, (int)$m[1]);
}
$start = (int)(($cfg['cislo_start'][$typ][$rok] ?? 0));   // volitelně v config.php: 'cislo_start' => ['objednavka' => [2026 => 83]]
if ($typ === 'objednavka' && $rok === 2026 && $start === 0) $start = 83;   // poslední papírová objednávka 82/2026
$dalsi = max($max + 1, $start ?: 1);
$out = ['cislo' => $pre . $dalsi . '/' . $rok];
if (isset($_GET['over'])) {
  $over = $norm($_GET['over']); $konc = (int)($_GET['konc'] ?? 0); $volne = $over !== '';
  foreach ($cisla as [$c, $kid]) if ($norm($c) === $over && !($kid && $kid === $konc)) { $volne = false; break; }
  $out['volne'] = $volne;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
