<?php
// Nastavení kartotéky měnitelné ze Správy (bez zásahu do config.php): příjemci e-mailů (komu co chodí ve firmě),
// účetní a číslo účtu pro zálohy. Ukládá se do /data/nastaveni.json a v lib/start.php se přepíše přes $cfg (nast_merge).
// Co není vyplněné, zůstává z config.php. 10. 11. 2026 (přání Zdeňka).

const NAST_TYPY = [
  'poptavka' => ['Poptávky', 'Komu ve firmě chodí nové poptávky (z webu i z kartotéky) a hlídání termínů.'],
  'nabidka' => ['Nabídky', 'Interní kopie odeslaných nabídek.'],
  'objednavka_interni' => ['Objednávky (firmě)', 'Interní kopie nových objednávek a upozornění (např. nezaplacená záloha).'],
  'objednavka_zakaznik' => ['Objednávky – skrytá kopie', 'Skrytá kopie (BCC) e-mailů, které chodí zákazníkovi (potvrzení objednávky, zálohy, montáž).'],
  'poukaz' => ['Dárkové poukazy', 'Komu chodí objednávky a platby dárkových poukazů.'],
];

function nast_soubor(): string { global $DATA; return ($DATA ?: '/data') . '/nastaveni.json'; }
function nast_nacti(): array { $f = nast_soubor(); if (!is_file($f)) return []; $d = json_decode((string)file_get_contents($f), true); return is_array($d) ? $d : []; }
function nast_uloz(array $data): bool { return @file_put_contents(nast_soubor(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false; }

// seznam e-mailů z textu (čárky / středníky / mezery / nové řádky), jen platné adresy, bez duplicit
function nast_emaily($v): array {
  $o = []; foreach (preg_split('/[\s,;]+/', is_array($v) ? implode(' ', $v) : (string)$v) as $e) { $e = trim($e); if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) $o[mb_strtolower($e)] = $e; }
  return array_values($o);
}
// účet „1821576001/5500" (volitelně s předčíslím „19-123/0800") → ořez; prázdné = nevyplněno
function nast_ucet_ocisti(string $v): string { $v = trim($v); return preg_match('~^\d{1,6}-?\d{2,10}/\d{4}$~', $v) ? $v : ''; }

// přepíše $cfg hodnotami z nastaveni.json (jen co je vyplněné). Volá se v start.php, obalené v try/catch.
function nast_merge(array &$cfg): void {
  $n = nast_nacti(); if (!$n) return;
  if (!empty($n['prijemci']) && is_array($n['prijemci'])) {
    foreach ($n['prijemci'] as $typ => $pp) {
      if (!isset(NAST_TYPY[$typ]) || !is_array($pp)) continue;
      foreach (['to', 'cc'] as $k) if (isset($pp[$k]) && is_array($pp[$k])) $cfg['prijemci'][$typ][$k] = $pp[$k];
    }
  }
  if (!empty($n['ucetni']) && is_array($n['ucetni'])) $cfg['ucetnictvi']['email'] = $n['ucetni'];
  if (!empty($n['from']) && filter_var($n['from'], FILTER_VALIDATE_EMAIL)) $cfg['from'] = $n['from'];
  if (!empty($n['ucet']) && is_string($n['ucet'])) $cfg['ucet'] = $n['ucet'];
}

// účetní e-maily (z nastavení / configu, jinak výchozí Dana)
function nast_ucetni(array $cfg): array { $e = nast_emaily($cfg['ucetnictvi']['email'] ?? []); return $e ?: ['kucerovadn@kksystem.cz']; }
// číslo účtu pro zálohy (z nastavení / configu, jinak výchozí RB – stejný jako na webu v platba/platba.js)
function nast_ucet(array $cfg): string { $u = nast_ucet_ocisti((string)($cfg['ucet'] ?? '')); return $u !== '' ? $u : '1821576001/5500'; }
