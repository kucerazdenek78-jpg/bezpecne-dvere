<?php
// Pomocník: návrh cenové nabídky z poptávky (tlačítko „🤖 Připravit nabídku“ v detailu poptávky v kartotéce).
// Claude přečte poptávku (bez telefonu, e-mailu a adresy) a vyplní položky nabídky jen z našeho katalogu (lib/navrh-katalog.json).
// Ceny NEVYMÝŠLÍ: u interiérů je spočítá formulář nabídky z akčního ceníku, u bytu z ceníku formuláře, u domu se zadávají ručně.
// Výsledek se uloží do tabulky pomocnik_navrhy a otevře se formulář nabidka-nova.html s předvyplněnými údaji (nic se neodesílá).
// „Učení“: když z návrhu vznikne odeslaná nabídka (data_json ai_navrh = id návrhu), dostane pomocník příště
// jako příklad, co navrhl a co prodavač nakonec poslal. Doplňující pokyny od Zdeňka (Nastavení pomocníka) mají přednost.
// Patří na NAS do /docker/formulare/app/lib/navrh-nabidky.php. Potřebuje lib/start.php a lib/pomocnik.php.

function nav_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS pomocnik_navrhy (id INTEGER PRIMARY KEY AUTOINCREMENT, cas TEXT, zadost_id INTEGER, poptavka_cislo TEXT, uzivatel TEXT, model TEXT, navrh_json TEXT, vstup INTEGER DEFAULT 0, vystup INTEGER DEFAULT 0)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS pomocnik_navrhy_zadost ON pomocnik_navrhy(zadost_id)');
}

function nav_katalog(): array {
  static $k = null; if ($k !== null) return $k;
  $j = json_decode((string)@file_get_contents(__DIR__ . '/navrh-katalog.json'), true);
  return $k = is_array($j) ? $j : ['byt' => [], 'dum' => [], 'interier' => []];
}

// Text poptávky pro Claude – bez kontaktů (telefon, e-mail, ulice, PSČ, OP) a bez souborů.
function nav_vstup_text(array $z, array $d): string {
  $telo = preg_replace('~\ndata_json[^\n]*~u', '', (string)($z['telo'] ?? ''));
  $radky = [];
  foreach (preg_split('/\R/u', $telo) as $r) {
    if (preg_match('/tel|mobil|e-?mail|@|adres|ulice|psč|psc|obč|op\b|souřadn|gps|jm[ée]no|příjmení|objednatel|https?:\/\//iu', $r)) continue;
    if (preg_match('/(\+?\d[\d ]{8,})/', $r)) $r = preg_replace('/\+?\d[\d ]{8,}\d/', '[číslo skryto]', $r);
    $radky[] = $r;
  }
  $telo = trim(mb_substr(implode("\n", $radky), 0, 5000));
  $data = pom_ocisti($d);
  unset($data['jmeno'], $data['prijmeni'], $data['fak_jmeno'], $data['fak_adresa'], $data['ic'], $data['dic']);
  return "PŘEDMĚT: " . preg_replace('/\S+@\S+/', '', (string)($z['predmet'] ?? '')) . "\n\nÚDAJE Z FORMULÁŘE (JSON):\n" . json_encode($data, JSON_UNESCAPED_UNICODE)
    . "\n\nTEXT ZPRÁVY (kontakty odstraněny):\n" . ($telo !== '' ? $telo : '(prázdné)');
}

// Fotky z poptávky (jen JPG/PNG, max 3, do 3,5 MB) – Claude je smí použít jen orientačně.
function nav_fotky(array $z, string $DATA): array {
  $out = [];
  foreach (array_filter(explode("\n", (string)($z['prilohy'] ?? ''))) as $s) {
    $f = $DATA . '/ulozeno/' . basename($s); $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true) || !is_file($f) || filesize($f) > 3.5 * 1048576) continue;
    $out[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $ext === 'png' ? 'image/png' : 'image/jpeg', 'data' => base64_encode((string)file_get_contents($f))]];
    if (count($out) >= 3) break;
  }
  return $out;
}

// Příklady z minula: co pomocník navrhl × co prodavač nakonec v nabídce odeslal (nejvýš 6 posledních).
function nav_priklady(PDO $pdo): array {
  $out = [];
  try { $rows = $pdo->query('SELECT id, navrh_json FROM pomocnik_navrhy ORDER BY id DESC LIMIT 60')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
  $st = $pdo->prepare("SELECT data_json FROM zadosti WHERE typ='nabidka' AND data_json LIKE ? ORDER BY id DESC LIMIT 1");
  foreach ($rows as $n) {
    $st->execute(['%"ai_navrh":"' . (int)$n['id'] . '"%']); $nb = $st->fetchColumn(); if (!$nb) continue;
    $nav = json_decode((string)$n['navrh_json'], true) ?: []; $fin = json_decode((string)$nb, true) ?: [];
    $polF = [];
    foreach ((array)($fin['polozky'] ?? []) as $p) if (isset($p['ks'])) $polF[] = array_filter(['nazev' => $p['nazev'] ?? '', 'odstin' => $p['odstin'] ?? '', 'ks' => $p['ks'] ?? '',
      'parametry' => implode('; ', array_map(fn($x) => ($x[0] ?? '') . ': ' . ($x[1] ?? ''), (array)($p['spec'] ?? [])))]);
    $out[] = ['poptavka' => $nav['shrnuti'] ?? '', 'navrhl_jsi' => ['kategorie' => $nav['kategorie'] ?? '', 'polozky' => $nav['polozky'] ?? [], 'byt' => $nav['byt'] ?? null],
      'prodavac_odeslal' => ['kategorie' => $fin['kategorie'] ?? '', 'polozky' => $polF, 'poznamka_pro_zakaznika' => mb_substr((string)($fin['poznamka'] ?? ''), 0, 300)]];
    if (count($out) >= 6) break;
  }
  return $out;
}

function nav_nastroj(): array {
  $str = ['type' => 'string'];
  return ['name' => 'navrh_nabidky', 'description' => 'Uloží návrh cenové nabídky. Vyplň jen to, co z poptávky opravdu vyplývá; ostatní nech prázdné a napiš do nejiste / otazky.',
    'input_schema' => ['type' => 'object', 'required' => ['kategorie', 'shrnuti', 'polozky', 'otazky'], 'properties' => [
      'kategorie' => ['type' => 'string', 'enum' => ['byt', 'dum', 'interier', 'nejasne'], 'description' => 'byt = bezpečnostní dveře do bytu (PREMIUM 60, EXTREME 54, MAGNUM 56, SECURIDO 65); dum = vchodové dveře do domu ERKADO; interier = interiérové dveře'],
      'shrnuti' => ['type' => 'string', 'description' => 'Jednou dvěma větami, co zákazník chce (bez jména a kontaktů).'],
      'byt' => ['type' => 'object', 'description' => 'Jen pro kategorii byt.', 'properties' => ['model_kod' => $str, 'odstin' => $str, 'nejiste' => ['type' => 'array', 'items' => $str]]],
      'polozky' => ['type' => 'array', 'description' => 'Pro dum a interier. U dum vždy nejvýš 1 položka a 1 kus.', 'items' => ['type' => 'object', 'properties' => [
        'model' => ['type' => 'string', 'description' => 'Přesný název modelu z katalogu, nebo prázdné, když model nejde jednoznačně určit'],
        'nazev' => ['type' => 'string', 'description' => 'Jen když model není v katalogu: co zákazník chce (krátce)'],
        'odstin' => ['type' => 'string', 'description' => 'Přesný název odstínu z katalogu daného modelu; když katalog odstíny nemá, slovy podle zákazníka; jinak prázdné'],
        'ks' => ['type' => 'integer'],
        'sirka' => ['type' => 'string', 'description' => 'Šířka křídla v cm (u interiérů 60, 70, 80 nebo 90), jen když ji zákazník uvedl'],
        'otev' => ['type' => 'string', 'description' => 'Otevírání (levé/pravé, dovnitř/ven), jen když ho zákazník uvedl'],
        'zarub' => ['type' => 'string', 'description' => 'Druh zárubně, jen když vyplývá z poptávky'],
        'tl' => ['type' => 'string', 'description' => 'Interiér: rozsah tloušťky zárubně PŘESNĚ z tl_zarubne daného modelu, jen když zákazník uvedl tloušťku zdi'],
        'kov' => ['type' => 'string', 'description' => 'Kování, jen když ho zákazník uvedl'],
        'montaz' => ['type' => 'boolean', 'description' => 'Interiér: true, pokud zákazník chce montáž nebo to neuvedl; false jen když výslovně montáž nechce'],
        'popis' => ['type' => 'string', 'description' => 'Krátký doplňující popis položky do nabídky (volitelné)'],
        'nejiste' => ['type' => 'array', 'items' => $str, 'description' => 'Co u této položky chybí nebo je potřeba ověřit']]]],
      'poznamka_zakaznik' => ['type' => 'string', 'description' => 'Krátká poznámka pro zákazníka do PDF (nejvýš 2 věty), nebo prázdné'],
      'otazky' => ['type' => 'array', 'items' => $str, 'description' => 'Na co se zeptat zákazníka, než se nabídka pošle'],
      'pro_prodejce' => ['type' => 'array', 'items' => $str, 'description' => 'Další poznámky pro prodavače (např. co je vidět na fotce)']]]];
}

function nav_system(array $n, array $priklady): string {
  $s = "Jsi pomocník firmy Bezpečné dveře (Zdeněk Kučera, vzorkovna KK SYSTEM, Obchodní 36, Most). Z poptávky zákazníka připravíš NÁVRH cenové nabídky, který prodavač zkontroluje, doplní a teprve pak sám odešle.\n\n"
  . "PRAVIDLA:\n- Nic si nevymýšlej. Používej jen modely a odstíny z KATALOGU níže, s přesně stejným zápisem. Když zákazník napíše něco, co v katalogu není nebo to nejde jednoznačně určit, nech pole prázdné a napiš to do nejiste.\n"
  . "- Ceny nezadáváš vůbec – počítá je formulář z ceníku.\n- Rozměry, otevírání, tloušťku zdi a kování vyplň jen když je zákazník uvedl. Z fotky rozměry neodhaduj; co je na fotce vidět, napiš jen do pro_prodejce jako opatrný postřeh.\n"
  . "- Dveře do bytu a do domu: na jednu adresu jedny dveře (1 položka, 1 kus). Interiérové dveře: libovolně položek a kusů; stejný model a odstín se stejnými parametry = jedna položka s počtem kusů.\n"
  . "- Interiéry: montáž (zárubeň, osazení křídla, kování do připraveného otvoru) se počítá ke každým dveřím, pokud zákazník výslovně neuvede, že montáž nechce. Vše ostatní (bourání, úprava otvoru) až po zaměření – to patří do otazky.\n"
  . "- Tloušťka zárubně u interiérů = tloušťka zdi; vyber rozsah z tl_zarubne jen když zákazník uvedl tloušťku zdi v mm/cm.\n"
  . "- Když poptávka vůbec není na dveře (např. servis oken), dej kategorii nejasne a vysvětli v otazky.\n- Piš česky, stručně, bez kontaktních údajů.\n\n";
  $s .= "KATALOG (JSON):\n" . json_encode(nav_katalog(), JSON_UNESCAPED_UNICODE) . "\n";
  if ($priklady) $s .= "\nPŘÍKLADY Z MINULA – co jsi navrhl a co prodavač nakonec odeslal. Uč se z rozdílů (jak prodavač doplňuje a opravuje):\n" . json_encode($priklady, JSON_UNESCAPED_UNICODE) . "\n";
  if (trim((string)($n['pokyny'] ?? '')) !== '') $s .= "\nDOPLŇUJÍCÍ POKYNY OD ZDEŇKA (mají přednost):\n" . mb_substr(trim((string)$n['pokyny']), 0, 6000) . "\n";
  return $s;
}

// Kontrola výstupu proti katalogu (cokoli mimo katalog se přesune do „nejisté“).
function nav_kontrola(array $v): array {
  $k = nav_katalog(); $kat = in_array($v['kategorie'] ?? '', ['byt', 'dum', 'interier'], true) ? $v['kategorie'] : 'nejasne';
  $v['kategorie'] = $kat; $pol = [];
  if ($kat === 'byt') {
    $b = is_array($v['byt'] ?? null) ? $v['byt'] : []; $nej = array_values(array_filter(array_map('strval', (array)($b['nejiste'] ?? []))));
    $m = null; foreach ($k['byt'] as $x) if ($x['model_kod'] === ($b['model_kod'] ?? '')) $m = $x;
    if (!$m) { if (!empty($b['model_kod'])) $nej[] = 'Model „' . $b['model_kod'] . '“ neznám – vyberte ho.'; $b = ['model_kod' => '', 'odstin' => '']; }
    elseif (($b['odstin'] ?? '') !== '' && !in_array($b['odstin'], $m['odstiny'], true)) { $nej[] = 'Zákazník chce odstín „' . $b['odstin'] . '“ – v nabídce ho nemáme, vyberte.'; $b['odstin'] = ''; }
    $v['byt'] = ['model_kod' => (string)($b['model_kod'] ?? ''), 'odstin' => (string)($b['odstin'] ?? ''), 'nejiste' => $nej]; $v['polozky'] = [];
    return $v;
  }
  foreach ((array)($v['polozky'] ?? []) as $p) {
    if (!is_array($p)) continue; $nej = array_values(array_filter(array_map('strval', (array)($p['nejiste'] ?? []))));
    $mod = (string)($p['model'] ?? '');
    if ($kat === 'interier') {
      $e = null; foreach ($k['interier'] as $x) if ($x['model'] === $mod) $e = $x;
      if ($mod !== '' && !$e) { $nej[] = 'Model „' . $mod . '“ v katalogu není.'; $p['nazev'] = $p['nazev'] ?? $mod; $mod = ''; }
      if ($e && ($p['odstin'] ?? '') !== '' && $e['odstiny'] && !in_array($p['odstin'], $e['odstiny'], true)) { $nej[] = 'Odstín „' . $p['odstin'] . '“ u tohoto modelu není – vyberte.'; $p['odstin'] = ''; }
      if (($p['tl'] ?? '') !== '' && (!$e || !in_array($p['tl'], $e['tl_zarubne'], true))) { $nej[] = 'Tloušťka zárubně „' . $p['tl'] . '“ – vyberte z nabídky.'; $p['tl'] = ''; }
      if (($p['tl'] ?? '') === '' && $e && $e['tl_zarubne']) $nej[] = 'Chybí tloušťka zdi (zárubně).';
      if (($p['sirka'] ?? '') === '') $nej[] = 'Chybí šířka dveří.';
    } else {
      if ($mod !== '' && !in_array($mod, $k['dum'], true)) { $nej[] = 'Model „' . $mod . '“ v katalogu ERKADO není.'; $p['nazev'] = $p['nazev'] ?? $mod; $mod = ''; }
      $p['ks'] = 1;
    }
    $p['model'] = $mod; $p['ks'] = max(1, (int)($p['ks'] ?? 1)); $p['nejiste'] = array_values(array_unique($nej));
    foreach (['nazev', 'odstin', 'sirka', 'otev', 'zarub', 'tl', 'kov', 'popis'] as $f) $p[$f] = mb_substr(trim((string)($p[$f] ?? '')), 0, 200);
    if ($p['model'] === '' && $p['nazev'] === '') $p['nazev'] = $kat === 'dum' ? 'Vchodové dveře' : 'Interiérové dveře';
    $pol[] = $p; if ($kat === 'dum') break;
  }
  $v['polozky'] = $pol;
  return $v;
}

// Hlavní funkce: vrátí ['ok'=>true,'id'=>…,'navrh'=>…,'url'=>…] nebo ['ok'=>false,'chyba'=>…]
function nav_vytvor(PDO $pdo, array $cfg, string $DATA, int $id, string $uzivatel = ''): array {
  pom_tabulky($pdo); nav_tabulka($pdo); $n = pom_nastaveni($DATA);
  if (empty($n['zapnuto'])) return ['ok' => false, 'chyba' => 'Pomocník je vypnutý v nastavení.'];
  if (pom_dnes_pocet($pdo) >= max(1, (int)$n['limit_den'])) return ['ok' => false, 'chyba' => 'Pro dnešek je vyčerpaný počet dotazů pomocníka.'];
  $st = $pdo->prepare('SELECT * FROM zadosti WHERE id=?'); $st->execute([$id]); $z = $st->fetch(PDO::FETCH_ASSOC);
  if (!$z || $z['typ'] !== 'poptavka') return ['ok' => false, 'chyba' => 'Poptávka nenalezena.'];
  $d = json_decode((string)$z['data_json'], true) ?: [];
  $obsah = array_merge(nav_fotky($z, $DATA), [['type' => 'text', 'text' => nav_vstup_text($z, $d) . "\n\nPřiprav návrh nabídky nástrojem navrh_nabidky."]]);
  $model = (string)$n['model'];
  $r = pom_api($n, ['model' => $model, 'max_tokens' => 2500, 'system' => nav_system($n, nav_priklady($pdo)), 'tools' => [nav_nastroj()],
    'tool_choice' => ['type' => 'tool', 'name' => 'navrh_nabidky'], 'messages' => [['role' => 'user', 'content' => $obsah]]], 90);
  if (!$r['ok']) { pom_zapis($pdo, 'navrh', $model, 0, 0, $r['chyba'] . ' ' . ($r['detail'] ?? '')); return $r; }
  $in = (int)($r['data']['usage']['input_tokens'] ?? 0); $out = (int)($r['data']['usage']['output_tokens'] ?? 0);
  $v = null; foreach ((array)($r['data']['content'] ?? []) as $b) if (($b['type'] ?? '') === 'tool_use') $v = (array)$b['input'];
  if (!$v) { pom_zapis($pdo, 'navrh', $model, $in, $out, 'bez návrhu'); return ['ok' => false, 'chyba' => 'Pomocník návrh nevrátil. Zkuste to znovu.']; }
  pom_zapis($pdo, 'navrh', $model, $in, $out);
  $v = nav_kontrola($v);
  $cislo = pom_cislo($z, $d);
  $pdo->prepare('INSERT INTO pomocnik_navrhy (cas, zadost_id, poptavka_cislo, uzivatel, model, navrh_json, vstup, vystup) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([date('Y-m-d H:i:s'), $id, $cislo, $uzivatel, $model, json_encode($v, JSON_UNESCAPED_UNICODE), $in, $out]);
  $nid = (int)$pdo->lastInsertId();
  return ['ok' => true, 'id' => $nid, 'navrh' => $v, 'url' => nav_url($cfg, $z, $d, $v, $nid)];
}

// Odkaz na formulář nabídky s předvyplněním (#d=base64url JSON). Kontakty bere z kartotéky, ne od Claude.
function nav_url(array $cfg, array $z, array $d, array $v, int $nid): string {
  $o = ['ai' => true, 'ai_navrh' => (string)$nid, 'cislo' => pom_cislo($z, $d), 'kategorie' => $v['kategorie']];
  foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc'] as $k) if (!empty($d[$k])) $o[$k] = (string)$d[$k];
  if (empty($o['email']) && !empty($z['email'])) $o['email'] = (string)$z['email'];
  if (!empty($v['poznamka_zakaznik'])) $o['poznamka'] = mb_substr((string)$v['poznamka_zakaznik'], 0, 500);
  $pozn = []; $otz = array_values(array_filter(array_map('strval', (array)($v['otazky'] ?? []))));
  foreach ($otz as $t) $pozn[] = 'Zeptat se: ' . $t;
  foreach ((array)($v['pro_prodejce'] ?? []) as $t) if ((string)$t !== '') $pozn[] = (string)$t;
  if ($v['kategorie'] === 'nejasne') $pozn[] = 'Pomocník nepoznal druh dveří – vyberte kategorii a položky ručně.';
  if ($v['kategorie'] === 'byt') {
    if ($v['byt']['model_kod'] !== '') { $o['model_kod'] = $v['byt']['model_kod']; if ($v['byt']['odstin'] !== '') $o['odstin'] = $v['byt']['odstin']; }
    else $pozn[] = 'Model dveří do bytu vyberte ručně.';
    foreach ($v['byt']['nejiste'] as $t) $pozn[] = $t;
    $o['ai_poznamky'] = $pozn;
  } else { $o['polozky'] = $v['polozky']; $o['ai_poznamky'] = $pozn; }
  $o['interni'] = mb_substr('Návrh pomocníka č. ' . $nid . ($v['shrnuti'] ? ' – ' . $v['shrnuti'] : '') . ($otz ? "\nZeptat se: " . implode('; ', $otz) : ''), 0, 1500);
  $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  return $wu . '/nabidka-nova.html?sys=1#d=' . rtrim(strtr(base64_encode(json_encode($o, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
}
