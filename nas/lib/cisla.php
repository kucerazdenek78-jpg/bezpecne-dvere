<?php
// Číslování objednávek, nabídek a poptávek: výpočet dalšího čísla + ruční nastavení počátku řady (kartotéka → Číslování, cisla.php).
// Číslo se nikdy nevrací zpět: další číslo = větší z (nejvyšší existující + 1) a (ručně nastavené poslední + 1).
function cisla_typy(): array {
  // klíč => [název, typ v tabulce zadosti, předpona, druh v konceptech, příklad]
  return [
    'objednavka' => ['Objednávky', 'objednavka_interni', '', 'objednavka-vice', '83/2026'],
    'nabidka'    => ['Nabídky', 'nabidka', 'N ', 'nabidka', 'N 12/2026'],
    'poptavka'   => ['Poptávky', 'poptavka', 'P ', '', 'P 25/2026'],
  ];
}
function cisla_init(PDO $pdo): void {
  $pdo->exec('CREATE TABLE IF NOT EXISTS cisla_start (typ TEXT NOT NULL, rok INTEGER NOT NULL, posledni INTEGER NOT NULL, zmeneno TEXT, kdo TEXT, PRIMARY KEY (typ, rok))');
}
// Spočítá další číslo řady za rok $rok. Vrací [pre, max, start, dalsi, zdroj, cisla]; cisla = [[číslo, id konceptu nebo 0], …]
function cisla_spocitej(PDO $pdo, array $cfg, string $typ, int $rok): array {
  $T = cisla_typy(); [, $dbtyp, $pre, $druhKonc] = $T[$typ];
  $cisla = [];
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
  // počátek řady: 1. ručně v kartotéce (Číslování), 2. config.php 'cislo_start', 3. dřívější pevné navázání na papírovou 82/2026
  $start = 0; $zdroj = 'automaticky';
  cisla_init($pdo);
  $q = $pdo->prepare('SELECT posledni FROM cisla_start WHERE typ=? AND rok=?'); $q->execute([$typ, $rok]); $p = $q->fetchColumn();
  if ($p !== false && $p !== null) { $start = (int)$p + 1; $zdroj = 'ručně v kartotéce'; }
  elseif ((int)($cfg['cislo_start'][$typ][$rok] ?? 0) > 0) { $start = (int)$cfg['cislo_start'][$typ][$rok]; $zdroj = 'config.php'; }
  elseif ($typ === 'objednavka' && $rok === 2026) { $start = 83; $zdroj = 'navázání na papírovou 82/2026'; }
  return ['pre' => $pre, 'max' => $max, 'start' => $start, 'dalsi' => max($max + 1, $start ?: 1), 'zdroj' => $zdroj, 'cisla' => $cisla];
}
