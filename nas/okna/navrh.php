<?php
// Návrhy oprav bytů ke schválení (odeslání e-mailem plátci + veřejné potvrzení v okna-schvaleni.php).
// Sdílené mezi okna/lib.php (přihlášená aplikace) a app/okna-schvaleni.php (veřejná stránka). PDO se vždy předává.
// Snapshot položky: {jed_id, jmeno, byt, ulice, telefon, zavada, oprava, cena, max(bool), plati}

const OK_NAVRH_STAVY = ['odeslano' => 'Odesláno ke schválení', 'schvaleno' => 'Schváleno', 'zavolat' => 'Žádá o telefon'];

function ok_navrh_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_navrh (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER, komu TEXT, komu_typ TEXT DEFAULT 'svj', nazev TEXT, token TEXT UNIQUE,
    polozky TEXT, cena REAL DEFAULT 0, stav TEXT DEFAULT 'odeslano', odeslal TEXT, odeslano TEXT, reakce_dne TEXT, reakce_tel TEXT, reakce_pozn TEXT)");
}
function ok_navrh_token(): string { return bin2hex(random_bytes(16)); }
function ok_navrh(PDO $pdo, string $token): ?array {
  if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
  $st = $pdo->prepare('SELECT * FROM ok_navrh WHERE token=?'); $st->execute([$token]); return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function ok_navrh_url(array $cfg, string $token): string {
  return rtrim((string)($cfg['kartoteka_url'] ?? 'https://kartoteka.dvere.app'), '/') . '/okna-schvaleni.php?t=' . $token;
}
function ok_navrh_kc($n): string { return ($n === null || $n === '' || !is_numeric($n)) ? '' : number_format((float)$n, 0, ',', ' ') . ' Kč'; }

// Z řádku so_jed udělá snapshot položku návrhu (návrh opravy = pole oprava, jinak seznam položek; cena = max_cena, jinak cena).
function ok_navrh_radek(array $x): array {
  $opr = trim((string)($x['oprava'] ?? ''));
  if ($opr === '' && !empty($x['polozky'])) {
    $p = json_decode((string)$x['polozky'], true);
    if (is_array($p)) $opr = implode(', ', array_filter(array_map(fn($r) => trim(rtrim(rtrim((string)($r['mn'] ?? 1), '0'), '.') . '× ' . (string)($r['nazev'] ?? '')), $p)));
  }
  $max = $x['max_cena'] !== null && $x['max_cena'] !== '';
  return ['jed_id' => (int)$x['id'], 'jmeno' => (string)$x['jmeno'], 'byt' => (string)($x['byt'] ?? ''), 'ulice' => (string)($x['ulice'] ?? ''),
    'telefon' => (string)($x['telefon'] ?? ''), 'zavada' => (string)($x['zavada'] ?? ''), 'oprava' => $opr,
    'cena' => $max ? (float)$x['max_cena'] : (float)($x['cena'] ?? 0), 'max' => $max, 'plati' => (string)($x['plati'] ?? '')];
}
function ok_navrh_cena_txt(array $r): string { return $r['cena'] > 0 ? ($r['max'] ? 'do ' : '') . ok_navrh_kc($r['cena']) : ''; }

// HTML tabulka oprav (inline styly kvůli e-mailu). Použito v e-mailu i na veřejné stránce.
function ok_navrh_html(array $pol): string {
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $td = 'padding:6px 9px;border:1px solid #cbd5e1;font-size:14px;vertical-align:top';
  $h = '<table style="border-collapse:collapse;width:100%;margin:10px 0"><tr style="background:#eef2f7">'
    . '<th style="' . $td . ';text-align:left">Byt / nájemník</th><th style="' . $td . ';text-align:left">Zjištěná závada</th>'
    . '<th style="' . $td . ';text-align:left">Návrh opravy</th><th style="' . $td . ';text-align:right">Cena s DPH</th></tr>';
  $celk = 0; $vse = true;
  foreach ($pol as $r) {
    $celk += (float)$r['cena']; if (empty($r['max'])) $vse = false;
    $kdo = $e($r['jmeno']) . ($r['byt'] !== '' ? '<br><span style="color:#64748b">byt ' . $e($r['byt']) . '</span>' : '')
      . ($r['ulice'] !== '' ? '<br><span style="color:#64748b">' . $e($r['ulice']) . '</span>' : '');
    $h .= '<tr><td style="' . $td . '">' . $kdo . '</td><td style="' . $td . '">' . nl2br($e($r['zavada'])) . '</td>'
      . '<td style="' . $td . '">' . nl2br($e($r['oprava'])) . '</td><td style="' . $td . ';text-align:right;white-space:nowrap">' . $e(ok_navrh_cena_txt($r)) . '</td></tr>';
  }
  $h .= '<tr style="background:#eef2f7"><th colspan="3" style="' . $td . ';text-align:left">Cena všech oprav s DPH</th>'
    . '<th style="' . $td . ';text-align:right;white-space:nowrap">' . ($celk > 0 ? ($vse ? 'do ' : '') . ok_navrh_kc($celk) : '') . '</th></tr></table>';
  return $h;
}
function ok_navrh_celkem(array $pol): float { $s = 0; foreach ($pol as $r) $s += (float)$r['cena']; return $s; }
