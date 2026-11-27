<?php
// Návrhy oprav bytů ke schválení (odeslání e-mailem plátci + veřejné potvrzení v okna-schvaleni.php).
// Sdílené mezi okna/lib.php (přihlášená aplikace) a app/okna-schvaleni.php (veřejná stránka). PDO se vždy předává.
// Snapshot položky: {jed_id, jmeno, byt, ulice, telefon, zavada, oprava, cena, max(bool), plati}

const OK_NAVRH_STAVY = ['odeslano' => 'Odesláno ke schválení', 'schvaleno' => 'Schváleno', 'zavolat' => 'Žádá o telefon'];
// druh návrhu: oprava = odsouhlasení opravy; zjisteni = souhlas se zjištěním/zmapováním závady (účtuje se i bez opravy);
// vyuctovani = vyúčtování provedeného servisu; zmapovani = objednávka zmapování závady (2. tlačítko ve vyúčtování)
const OK_NAVRH_DRUHY = ['oprava' => 'oprava', 'zjisteni' => 'zjisteni', 'vyuctovani' => 'vyuctovani', 'zmapovani' => 'zmapovani'];

function ok_navrh_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS ok_navrh (id INTEGER PRIMARY KEY AUTOINCREMENT, svj_id INTEGER, komu TEXT, komu_typ TEXT DEFAULT 'svj', nazev TEXT, token TEXT UNIQUE,
    polozky TEXT, cena REAL DEFAULT 0, stav TEXT DEFAULT 'odeslano', odeslal TEXT, odeslano TEXT, reakce_dne TEXT, reakce_tel TEXT, reakce_pozn TEXT)");
  $sl = array_column($pdo->query('PRAGMA table_info(ok_navrh)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  if (!in_array('druh', $sl, true)) $pdo->exec("ALTER TABLE ok_navrh ADD COLUMN druh TEXT DEFAULT 'oprava'");
  if (!in_array('vyrizeno', $sl, true)) $pdo->exec("ALTER TABLE ok_navrh ADD COLUMN vyrizeno TEXT");   // úkol na úvodu kartotéky vyřízen (kdy + kdo)
}

// Úkoly pro úvodní box kartotéky (box „📞 Zákazníci čekají na reakci“): objednané zmapování závady
// a žádosti o telefonát, dokud je někdo neoznačí jako vyřízené. Volá lib/terminy.php (jiná DB, proto samostatně).
function ok_navrh_ukoly(PDO $pdo): array {
  ok_navrh_tabulka($pdo);
  return $pdo->query("SELECT * FROM ok_navrh WHERE (vyrizeno IS NULL OR vyrizeno='')
    AND ((druh='zmapovani' AND stav='schvaleno') OR stav='zavolat') ORDER BY reakce_dne DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
function ok_navrh_ukol_vyrizeno(PDO $pdo, int $id, string $kdo = ''): void {
  $pdo->prepare("UPDATE ok_navrh SET vyrizeno=? WHERE id=?")->execute([date('Y-m-d H:i:s') . ($kdo !== '' ? ' ' . $kdo : ''), $id]);
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

// HTML tabulka (inline styly kvůli e-mailu). Použito v e-mailu i na veřejné stránce.
// $druh: 'oprava' = sloupec Návrh opravy; 'zjisteni' = sloupec Zjištění / zmapování závady.
function ok_navrh_html(array $pol, string $druh = 'oprava'): string {
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $td = 'padding:6px 9px;border:1px solid #cbd5e1;font-size:14px;vertical-align:top';
  $zj = $druh === 'zjisteni';
  $sl3 = $zj ? 'Zjištění / zmapování závady' : 'Návrh opravy';
  $celkTxt = $zj ? 'Cena za zjištění celkem s DPH' : 'Cena všech oprav s DPH';
  $h = '<table style="border-collapse:collapse;width:100%;margin:10px 0"><tr style="background:#eef2f7">'
    . '<th style="' . $td . ';text-align:left">Byt / nájemník</th><th style="' . $td . ';text-align:left">Zjištěná závada</th>'
    . '<th style="' . $td . ';text-align:left">' . $sl3 . '</th><th style="' . $td . ';text-align:right">Cena s DPH</th></tr>';
  $celk = 0; $vse = true;
  foreach ($pol as $r) {
    $celk += (float)$r['cena']; if (empty($r['max'])) $vse = false;
    $kdo = $e($r['jmeno']) . ($r['byt'] !== '' ? '<br><span style="color:#64748b">byt ' . $e($r['byt']) . '</span>' : '')
      . ($r['ulice'] !== '' ? '<br><span style="color:#64748b">' . $e($r['ulice']) . '</span>' : '');
    $h .= '<tr><td style="' . $td . '">' . $kdo . '</td><td style="' . $td . '">' . nl2br($e($r['zavada'])) . '</td>'
      . '<td style="' . $td . '">' . nl2br($e($r['oprava'])) . '</td><td style="' . $td . ';text-align:right;white-space:nowrap">' . $e(ok_navrh_cena_txt($r)) . '</td></tr>';
  }
  $h .= '<tr style="background:#eef2f7"><th colspan="3" style="' . $td . ';text-align:left">' . $celkTxt . '</th>'
    . '<th style="' . $td . ';text-align:right;white-space:nowrap">' . ($celk > 0 ? ($vse ? 'do ' : '') . ok_navrh_kc($celk) : '') . '</th></tr></table>';
  return $h;
}
function ok_navrh_celkem(array $pol): float { $s = 0; foreach ($pol as $r) $s += (float)$r['cena']; return $s; }

// Tabulka JEN zjištěných závad (byt/okno + popis závady) – bez návrhu opravy a bez cen.
// Pro objednávku zmapování: při servisu víme jen, ŽE je závada, rozsah a cenu opravy určíme až po zmapování.
function ok_zavady_html(array $pol): string {
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $td = 'padding:6px 9px;border:1px solid #cbd5e1;font-size:14px;vertical-align:top';
  $h = '<table style="border-collapse:collapse;width:100%;margin:10px 0"><tr style="background:#eef2f7">'
    . '<th style="' . $td . ';text-align:left;width:34%">Byt / okno</th><th style="' . $td . ';text-align:left">Zjištěná závada</th></tr>';
  foreach ($pol as $r) {
    $kdo = $e($r['jmeno']) . ($r['byt'] !== '' ? '<br><span style="color:#64748b">byt ' . $e($r['byt']) . '</span>' : '')
      . ($r['ulice'] !== '' ? '<br><span style="color:#64748b">' . $e($r['ulice']) . '</span>' : '');
    $h .= '<tr><td style="' . $td . '">' . $kdo . '</td><td style="' . $td . '">' . nl2br($e($r['zavada'] !== '' ? $r['zavada'] : '—')) . '</td></tr>';
  }
  $h .= '</table>';
  return $h;
}
