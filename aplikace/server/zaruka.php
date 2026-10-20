<?php
// Dodatečné placené prodloužení záruky (do 6 měsíců od předání) a roční kontrola požárního uzávěru.
// Od 20. 10. 2026: základní záruka u všech dveří 2 roky; prodloužení (kupuje zákazník v aplikaci Moje dveře / na stránce Moje dveře): 5 let 1 500 Kč, 10 let 3 000 Kč.
// Netýká se interiérových dveří. Starší objednávky se zárukou delší než 2 roky (např. EXTREME 5 let) dostanou jen delší variantu.
function zaruka_let_text(string $t): int { return preg_match('/(\d+)\s*(?:rok|let)/u', $t, $m) ? (int)$m[1] : 0; }
function zaruka_nabidka(array $d): array {
  if (preg_match('/interi|vnit[rř]n/iu', (string)($d['model_kod'] ?? '') . ' ' . (string)($d['model'] ?? ''))) return [];   // interiérové dveře: prodloužení záruky se nenabízí
  $t = (string)($d['zaruka'] ?? ''); if (preg_match('/Ano,\s*na/u', $t)) return [];   // záruka už byla zvolena při objednávce
  $akt = $t === '' ? 2 : zaruka_let_text($t); $o = [];
  foreach ([5 => 1500, 10 => 3000] as $let => $kc) if ($let > $akt) $o[$let] = $kc;
  return $o;
}
function zaruka_vs(string $cislo): string { $d = ltrim(preg_replace('/\D+/', '', $cislo), '0'); return substr('8' . $d, 0, 10); }   // VS prodloužení = 8 + číslice objednávky (VS zálohy začíná rokem, nekoliduje)
function zaruka_spd(string $iban, string $cislo, int $kc): string {
  $msg = substr(preg_replace('/[^A-Za-z0-9 .,\-\/]/', '', 'Prodlouzeni zaruky obj ' . $cislo), 0, 60);
  return 'SPD*1.0*ACC:' . $iban . '*AM:' . number_format($kc, 2, '.', '') . '*CC:CZK*X-VS:' . zaruka_vs($cislo) . '*MSG:' . $msg;
}
function zaruka_lhuta(array $z): string { return date('Y-m-d', strtotime($z['predano_dne'] . ' +6 months')); }
function zaruka_nove_do(array $z, int $let): string { return date('Y-m-d', strtotime($z['zaruka_od'] . ' +' . $let . ' years -1 day')); }
function zaruka_url(array $cfg, string $tok): string { return rtrim($cfg['moje_url'] ?? 'https://formulare.dvere.app/moje.php', '?') . '?t=' . $tok; }
function zaruka_iban(array $cfg): string { return $cfg['iban'] ?? 'CZ4455000000001821576001'; }
function zaruka_ucet(array $cfg): string { return $cfg['ucet_prijem'] ?? '1821576001/5500'; }

// Zákaznický token pro odkaz „Moje dveře“ (u starších objednávek se dogeneruje).
function zaruka_token(PDO $pdo, array $z): string {
  $d = json_decode((string)$z['data_json'], true) ?: [];
  if (!empty($d['zakaznik_token']) && preg_match('/^[a-f0-9]{24}$/', $d['zakaznik_token'])) return $d['zakaznik_token'];
  $d['zakaznik_token'] = bin2hex(random_bytes(12));
  $pdo->prepare('UPDATE zadosti SET data_json=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $z['id']]);
  return $d['zakaznik_token'];
}

// Zpracování příchozí platby s VS prodloužení. Vrací [stav, zadost_id, poznámka].
function zaruka_zpracuj_platbu(PDO $pdo, array $cfg, string $DATA, array $p, bool $suchy): array {
  $kl = ltrim(substr($p['vs'], 1), '0'); $kand = [];
  foreach ($pdo->query("SELECT * FROM zadosti WHERE typ='objednavka_interni'") as $z) {
    $d = json_decode((string)$z['data_json'], true) ?: []; $cislo = (string)($d['obj_cislo'] ?? $z['cislo']);
    if ($kl !== '' && ltrim(preg_replace('/\D+/', '', $cislo), '0') === $kl) $kand[] = [$z, $d, $cislo];
  }
  if (count($kand) !== 1) return ['nesparovano', null, count($kand) ? 'VS odpovídá více objednávkám' : 'VS prodloužení nenalezeno'];
  [$z, $d, $cislo] = $kand[0]; $zid = (int)$z['id'];
  if (empty($z['predano_dne']) || empty($z['zaruka_od'])) return ['kontrola', $zid, 'dveře ještě nejsou předány'];
  if (!empty($z['zaruka_prodl_let'])) return ['kontrola', $zid, 'záruka už byla prodloužena na ' . $z['zaruka_prodl_let'] . ' let – další platba'];
  $let = 0; foreach (zaruka_nabidka($d) as $l => $kc) if (abs($p['castka'] - $kc) < 0.5) $let = $l;
  if (!$let) return ['kontrola', $zid, 'částka ' . $p['castka'] . ' Kč neodpovídá žádné nabídce prodloužení'];
  if (substr($p['datum'], 0, 10) > zaruka_lhuta($z)) return ['kontrola', $zid, 'platba po lhůtě (' . zaruka_lhuta($z) . ')'];
  $pozn = '';
  if (!$suchy) {
    $nove = zaruka_nove_do($z, $let); if (!empty($z['zaruka_do']) && $z['zaruka_do'] > $nove) $nove = $z['zaruka_do'];
    $pdo->prepare('UPDATE zadosti SET zaruka_do=?, zaruka_prodl_let=?, zaruka_prodl_dne=?, zaruka_prodl_kc=? WHERE id=?')->execute([$nove, $let, $p['datum'], $p['castka'], $zid]);
    $em = filter_var(trim((string)($d['email'] ?? $z['email'])), FILTER_VALIDATE_EMAIL);
    if ($em) { require_once __DIR__ . '/posta.php'; if (!posli_potvrzeni_prodlouzeni($cfg, $DATA, $em, $cislo, $let, $nove)) $pozn = 'záruka prodloužena, potvrzení zákazníkovi se nepodařilo odeslat'; }
    else $pozn = 'záruka prodloužena, zákazník nemá e-mail';
  }
  return ['sparovano', $zid, $pozn ?: "prodloužení záruky na $let let"];
}
