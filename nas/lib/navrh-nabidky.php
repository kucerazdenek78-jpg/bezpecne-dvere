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
    // řádky s popiskem kontaktu vynechat celé, v ostatních zamaskovat e-maily, telefony a odkazy (zbytek textu – co zákazník chce – zůstane)
    if (preg_match('/^\s*(jm[ée]no|příjmení|tel|mobil|e-?mail|adres|ulice|psč|psc|obec|město|objednatel|číslo op|op\b|souřadn|gps)[^:]{0,25}:/iu', $r)) continue;
    $r = preg_replace(['/\S+@\S+/u', '/https?:\/\/\S+/u', '/\+?\d[\d \/-]{7,}\d/u'], ['[e-mail skryt]', '[odkaz]', '[číslo skryto]'], $r);
    $radky[] = $r;
  }
  $telo = trim(mb_substr(implode("\n", $radky), 0, 5000));
  $data = pom_ocisti($d);
  unset($data['jmeno'], $data['prijmeni'], $data['fak_jmeno'], $data['fak_adresa'], $data['ic'], $data['dic']);
  if (isset($data['poznamka'])) $data['poznamka'] = preg_replace(['/\S+@\S+/u', '/\+?\d[\d \/-]{7,}\d/u'], ['[e-mail skryt]', '[číslo skryto]'], (string)$data['poznamka']);
  return "PŘEDMĚT: " . preg_replace('/\S+@\S+/', '', (string)($z['predmet'] ?? '')) . "\n\nÚDAJE Z FORMULÁŘE (JSON):\n" . json_encode($data, JSON_UNESCAPED_UNICODE)
    . "\n\nTEXT ZPRÁVY (kontakty odstraněny):\n" . ($telo !== '' ? $telo : '(prázdné)');
}

// Kontakt zákazníka, když ho poptávka nemá v samostatných polích (zákazník ho napsal do textu / poznámky).
// Jen tehdy jde Claudovi i původní text s kontakty – aby z něj vyčetl jméno, telefon, e-mail a adresu do formuláře nabídky.
function nav_kontakt_chybi(array $z, array $d): bool {
  return trim((string)($d['prijmeni'] ?? $d['jmeno'] ?? '')) === '' || (trim((string)($d['telefon'] ?? '')) === '' && trim((string)($d['email'] ?? $z['email'] ?? '')) === '')
    || trim((string)($d['ulice'] ?? '')) === '' || trim((string)($d['mesto'] ?? '')) === '';
}
function nav_kontakt_zdroj(array $z, array $d): string {
  $t = trim((string)($d['poznamka'] ?? '')) . "\n" . preg_replace('~\ndata_json[^\n]*~u', '', (string)($z['telo'] ?? ''));
  return trim(mb_substr($t, 0, 4000));
}
// Kontakt od Claude se převezme jen tehdy, když je opravdu napsaný v textu poptávky (ochrana proti vymyšleným údajům).
function nav_kontakt_over(array $k, string $zdroj): array {
  $norm = fn($x) => mb_strtolower(preg_replace('/[\s.,;:\/-]+/u', '', (string)$x));
  $nz = $norm($zdroj); $cz = preg_replace('/\D+/', '', $zdroj); $out = [];
  foreach (['jmeno', 'prijmeni', 'ulice', 'mesto'] as $f) { $v = trim((string)($k[$f] ?? '')); if ($v !== '' && mb_strlen($v) <= 80 && str_contains($nz, $norm($v))) $out[$f] = $v; }
  $tel = preg_replace('/\D+/', '', (string)($k['telefon'] ?? '')); $tel9 = substr($tel, -9);
  if (strlen($tel9) === 9 && str_contains($cz, $tel9)) $out['telefon'] = trim((string)$k['telefon']);
  $em = trim((string)($k['email'] ?? '')); if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && str_contains(mb_strtolower($zdroj), mb_strtolower($em))) $out['email'] = $em;
  $psc = preg_replace('/\D+/', '', (string)($k['psc'] ?? '')); if (strlen($psc) === 5 && str_contains($cz, $psc)) $out['psc'] = substr($psc, 0, 3) . ' ' . substr($psc, 3);
  return $out;
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
        'bourani' => ['type' => 'boolean', 'description' => 'Interiér: true, když zákazník chce vybourat stávající zárubně / připravit otvor'],
        'popis' => ['type' => 'string', 'description' => 'Krátký doplňující popis položky do nabídky (volitelné)'],
        'nejiste' => ['type' => 'array', 'items' => $str, 'description' => 'Co u této položky chybí nebo je potřeba ověřit'],
        'vychozi' => ['type' => 'boolean', 'description' => 'Interiér: true, když zákazník konkrétní model neuvedl – model pak vybere systém z ceníku (model nech prázdný)'],
        'odstiny_podobne' => ['type' => 'array', 'items' => $str, 'description' => 'Při vychozi=true: přesné názvy dekorů z katalogu interiérů, které jsou nejpodobnější požadované barvě (nejpodobnější první)']]]],
      'interier_dalsi' => ['type' => 'array', 'description' => 'Jen u kategorie byt nebo dum: interiérové dveře, které zákazník chce navíc (samostatná nabídka). Stejná pole jako polozky.', 'items' => ['type' => 'object']],
      'poznamka_zakaznik' => ['type' => 'string', 'description' => 'Krátká poznámka pro zákazníka do PDF (nejvýš 2 věty), nebo prázdné'],
      'otazky' => ['type' => 'array', 'items' => $str, 'description' => 'Na co se zeptat zákazníka, než se nabídka pošle'],
      'pro_prodejce' => ['type' => 'array', 'items' => $str, 'description' => 'Další poznámky pro prodavače (např. co je vidět na fotce)'],
      'kontakt' => ['type' => 'object', 'description' => 'Jen když je v zadání oddíl KONTAKT ZÁKAZNÍKA: údaje přesně tak, jak je zákazník napsal (nic nedomýšlej, chybějící nech prázdné).',
        'properties' => ['jmeno' => $str, 'prijmeni' => $str, 'telefon' => $str, 'email' => $str, 'ulice' => ['type' => 'string', 'description' => 'ulice a číslo domu'], 'mesto' => $str, 'psc' => $str]]]]];
}

function nav_system(array $n, array $priklady): string {
  $s = "Jsi pomocník firmy Bezpečné dveře (Zdeněk Kučera, vzorkovna KK SYSTEM, Obchodní 36, Most). Z poptávky zákazníka připravíš NÁVRH cenové nabídky, který prodavač zkontroluje, doplní a teprve pak sám odešle.\n\n"
  . "PRAVIDLA:\n- Nic si nevymýšlej. Používej jen modely a odstíny z KATALOGU níže, s přesně stejným zápisem. Když zákazník napíše něco, co v katalogu není nebo to nejde jednoznačně určit, nech pole prázdné a napiš to do nejiste.\n"
  . "- Ceny nezadáváš vůbec – počítá je formulář z ceníku.\n- Rozměry, otevírání, tloušťku zdi a kování vyplň jen když je zákazník uvedl. Z fotky rozměry neodhaduj; co je na fotce vidět, napiš jen do pro_prodejce jako opatrný postřeh.\n"
  . "- Dveře do bytu a do domu: na jednu adresu jedny dveře (1 položka, 1 kus). Interiérové dveře: libovolně položek a kusů; stejný model a odstín se stejnými parametry = jedna položka s počtem kusů.\n"
  . "- Interiéry: montáž (zárubeň, osazení křídla, kování do připraveného otvoru) se počítá ke každým dveřím, pokud zákazník výslovně neuvede, že montáž nechce. Když chce vybourat stávající zárubně a připravit otvor (nebo to je zaškrtnuté v poptávce – pole bourani), dej bourani=true: počítá se 2 000 Kč bez DPH za kus vč. odvozu materiálu (cenu přičte formulář). Jiné stavební úpravy až po zaměření – to patří do otazky.\n"
  . "- Tloušťka zárubně u interiérů = tloušťka zdi; vyber rozsah z tl_zarubne jen když zákazník uvedl tloušťku zdi v mm/cm.\n"
  . "- Interiérové dveře, u kterých zákazník neuvedl konkrétní model (např. jen počet kusů a barvu): dej vychozi=true, model nech prázdný a do odstiny_podobne vypiš dekory z katalogu interiérů nejpodobnější barvě, kterou chce – když barvu interiérů neuvedl, vezmi odstín jeho bezpečnostních / vchodových dveří. Model vybere systém (druhý nejlevnější z ceníku).\n"
  . "- Když zákazník chce dveře do bytu nebo domu A K TOMU interiérové dveře (počet kusů), interiérové dej do interier_dalsi – budou v samostatné nabídce.\n"
  . "- Když poptávka vůbec není na dveře (např. servis oken), dej kategorii nejasne a vysvětli v otazky.\n- Piš česky, stručně. Kontaktní údaje patří jen do pole kontakt (a jen když je v zadání oddíl KONTAKT ZÁKAZNÍKA), nikam jinam.\n\n";
  $s .= "KATALOG (JSON):\n" . json_encode(nav_katalog(), JSON_UNESCAPED_UNICODE) . "\n";
  if ($priklady) $s .= "\nPŘÍKLADY Z MINULA – co jsi navrhl a co prodavač nakonec odeslal. Uč se z rozdílů (jak prodavač doplňuje a opravuje):\n" . json_encode($priklady, JSON_UNESCAPED_UNICODE) . "\n";
  if (trim((string)($n['pokyny'] ?? '')) !== '') $s .= "\nDOPLŇUJÍCÍ POKYNY OD ZDEŇKA (mají přednost):\n" . mb_substr(trim((string)$n['pokyny']), 0, 6000) . "\n";
  return $s;
}

// Výběr interiérových dveří, když zákazník model neuvedl: ze všech ceníkových modelů a dekorů z $podobne
// se vezme druhá nejnižší cenová hladina (bez DPH, bez montáže); když je jen jedna hladina, ta nejlevnější. Vrací [model, dekor] nebo null.
function nav_vyber_levny(array $podobne): ?array {
  $k = nav_katalog(); $kand = [];
  foreach ($podobne as $poradi => $dek) foreach ($k['interier'] as $m) {
    if (empty($m['cena_z_ceniku']) || !in_array($dek, (array)$m['odstiny'], true)) continue;
    $c = is_array($m['cena_od_bez_dph']) ? ($m['cena_od_bez_dph'][$dek] ?? null) : $m['cena_od_bez_dph']; if ($c === null) continue;
    $kand[] = ['m' => $m['model'], 'd' => $dek, 'c' => (int)$c, 'p' => $poradi];
  }
  if (!$kand) return null;
  usort($kand, fn($a, $b) => [$a['c'], $a['p']] <=> [$b['c'], $b['p']]);
  $hladiny = array_values(array_unique(array_column($kand, 'c')));
  $cil = $hladiny[count($hladiny) > 1 ? 1 : 0];
  foreach ($kand as $x) if ($x['c'] === $cil) return [$x['m'], $x['d']];
  return null;
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
      if (!empty($p['vychozi'])) {
        $vyb = nav_vyber_levny(array_values(array_filter(array_map('strval', (array)($p['odstiny_podobne'] ?? [])))));
        if ($vyb) { [$mod, $p['odstin']] = $vyb; $nej[] = 'Typ dveří vybrán orientačně (2. nejlevnější z ceníku v podobném odstínu) – zákazník si musí vybrat typ, cena se podle toho změní.'; }
        else $nej[] = 'Podobný odstín v ceníku nenalezen – vyberte model a odstín.';
        $p['vychozi'] = true;
      } else $p['vychozi'] = false;
      $e = null; foreach ($k['interier'] as $x) if ($x['model'] === $mod) $e = $x;
      if ($mod !== '' && !$e) { $nej[] = 'Model „' . $mod . '“ v katalogu není.'; $p['nazev'] = $p['nazev'] ?? $mod; $mod = ''; }
      if ($e && ($p['odstin'] ?? '') !== '' && $e['odstiny'] && !in_array($p['odstin'], $e['odstiny'], true)) { $nej[] = 'Odstín „' . $p['odstin'] . '“ u tohoto modelu není – vyberte.'; $p['odstin'] = ''; }
      if (($p['tl'] ?? '') !== '' && (!$e || !in_array($p['tl'], $e['tl_zarubne'], true))) { $nej[] = 'Tloušťka zárubně „' . $p['tl'] . '“ – vyberte z nabídky.'; $p['tl'] = ''; }
      if (($p['tl'] ?? '') === '' && $e && $e['tl_zarubne'] && !empty($p['vychozi'])) { $p['tl'] = $e['tl_zarubne'][0]; $nej[] = 'Cena je spočítaná pro zárubeň ' . $p['tl'] . ' – po zaměření upravte.'; }
      elseif (($p['tl'] ?? '') === '' && $e && $e['tl_zarubne']) $nej[] = 'Chybí tloušťka zdi (zárubně).';
      if (($p['sirka'] ?? '') === '') $nej[] = !empty($p['vychozi']) ? 'Rozměry dveří je nutné specifikovat (šířka, tloušťka zdi).' : 'Chybí šířka dveří.';
    } else {
      if ($mod !== '' && !in_array($mod, $k['dum'], true)) { $nej[] = 'Model „' . $mod . '“ v katalogu ERKADO není.'; $p['nazev'] = $p['nazev'] ?? $mod; $mod = ''; }
      $p['ks'] = 1;
    }
    $p['model'] = $mod; $p['ks'] = max(1, (int)($p['ks'] ?? 1)); $p['nejiste'] = array_values(array_unique($nej));
    unset($p['odstiny_podobne']); if ($kat === 'interier') $p['bourani'] = !empty($p['bourani']);
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
  $kchybi = nav_kontakt_chybi($z, $d); $kzdroj = $kchybi ? nav_kontakt_zdroj($z, $d) : '';
  $text = nav_vstup_text($z, $d) . ($kchybi && $kzdroj !== '' ? "\n\nKONTAKT ZÁKAZNÍKA (zákazník ho napsal do textu; původní znění jen pro vyplnění pole kontakt – do jiných polí ho nepiš):\n" . $kzdroj : '');
  $obsah = array_merge(nav_fotky($z, $DATA), [['type' => 'text', 'text' => $text . "\n\nPřiprav návrh nabídky nástrojem navrh_nabidky."]]);
  $model = (string)$n['model'];
  $r = pom_api($n, ['model' => $model, 'max_tokens' => 2500, 'system' => nav_system($n, nav_priklady($pdo)), 'tools' => [nav_nastroj()],
    'tool_choice' => ['type' => 'tool', 'name' => 'navrh_nabidky'], 'messages' => [['role' => 'user', 'content' => $obsah]]], 90);
  if (!$r['ok']) { pom_zapis($pdo, 'navrh', $model, 0, 0, $r['chyba'] . ' ' . ($r['detail'] ?? '')); return $r; }
  $in = (int)($r['data']['usage']['input_tokens'] ?? 0); $out = (int)($r['data']['usage']['output_tokens'] ?? 0);
  $v = null; foreach ((array)($r['data']['content'] ?? []) as $b) if (($b['type'] ?? '') === 'tool_use') $v = (array)$b['input'];
  if (!$v) { pom_zapis($pdo, 'navrh', $model, $in, $out, 'bez návrhu'); return ['ok' => false, 'chyba' => 'Pomocník návrh nevrátil. Zkuste to znovu.']; }
  pom_zapis($pdo, 'navrh', $model, $in, $out);
  $dalsi = (in_array($v['kategorie'] ?? '', ['byt', 'dum'], true) && !empty($v['interier_dalsi']) && is_array($v['interier_dalsi'])) ? $v['interier_dalsi'] : [];
  $v = nav_kontrola($v); unset($v['interier_dalsi']);
  if ($dalsi) $v['interier_dalsi'] = nav_kontrola(['kategorie' => 'interier', 'polozky' => $dalsi])['polozky'];
  $v['kontakt'] = ($kchybi && $kzdroj !== '' && is_array($v['kontakt'] ?? null)) ? nav_kontakt_over($v['kontakt'], $kzdroj) : [];
  $cislo = pom_cislo($z, $d);
  $pdo->prepare('INSERT INTO pomocnik_navrhy (cas, zadost_id, poptavka_cislo, uzivatel, model, navrh_json, vstup, vystup) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([date('Y-m-d H:i:s'), $id, $cislo, $uzivatel, $model, json_encode($v, JSON_UNESCAPED_UNICODE), $in, $out]);
  $nid = (int)$pdo->lastInsertId();
  $url2 = !empty($v['interier_dalsi']) ? nav_url($cfg, $z, $d, ['kategorie' => 'interier', 'polozky' => $v['interier_dalsi'], 'shrnuti' => $v['shrnuti'] ?? '', 'otazky' => $v['otazky'] ?? [], 'kontakt' => $v['kontakt']], $nid) : '';
  return ['ok' => true, 'id' => $nid, 'navrh' => $v, 'url' => nav_url($cfg, $z, $d, $v, $nid), 'url2' => $url2];
}

// Odkaz na formulář nabídky s předvyplněním (#d=base64url JSON). Kontakty bere z kartotéky, ne od Claude.
function nav_url(array $cfg, array $z, array $d, array $v, int $nid): string {
  $o = ['ai' => true, 'ai_navrh' => (string)$nid, 'cislo' => pom_cislo($z, $d), 'kategorie' => $v['kategorie']];
  foreach (['jmeno', 'prijmeni', 'telefon', 'email', 'ulice', 'mesto', 'psc'] as $k) if (!empty($d[$k])) $o[$k] = (string)$d[$k];
  if (empty($o['email']) && !empty($z['email'])) $o['email'] = (string)$z['email'];
  $zText = [];   // kontakt vyčtený z textu poptávky (jen pole, která formulář poptávky nevyplnil)
  foreach ((array)($v['kontakt'] ?? []) as $k => $x) if (empty($o[$k]) && $x !== '') { $o[$k] = (string)$x; $zText[] = $k; }
  if (!empty($v['poznamka_zakaznik'])) $o['poznamka'] = mb_substr((string)$v['poznamka_zakaznik'], 0, 500);
  if ($v['kategorie'] === 'interier' && array_filter((array)($v['polozky'] ?? []), fn($p) => !empty($p['vychozi'])))   // model vybraný orientačně (pravidlo Zdeňka 29. 10.)
    $o['poznamka'] = trim(($o['poznamka'] ?? '') . "\nTyp interiérových dveří v nabídce je zatím orientační – vyberte si prosím konkrétní model, cena nabídky se podle výběru změní. Rozměry dveří (šířku a tloušťku zdi) je nutné upřesnit, nejlépe zaměřením.");
  $pozn = []; $otz = array_values(array_filter(array_map('strval', (array)($v['otazky'] ?? []))));
  foreach ($otz as $t) $pozn[] = 'Zeptat se: ' . $t;
  foreach ((array)($v['pro_prodejce'] ?? []) as $t) if ((string)$t !== '') $pozn[] = (string)$t;
  if ($v['kategorie'] === 'nejasne') $pozn[] = 'Pomocník nepoznal druh dveří – vyberte kategorii a položky ručně.';
  $nazvy = ['jmeno' => 'jméno', 'prijmeni' => 'příjmení', 'telefon' => 'telefon', 'email' => 'e-mail', 'ulice' => 'ulice', 'mesto' => 'obec', 'psc' => 'PSČ'];
  if ($zText) $pozn[] = 'Kontakt (' . implode(', ', array_map(fn($k) => $nazvy[$k], $zText)) . ') je vyčtený z textu poptávky – zkontrolujte ho.';
  $chybi = array_values(array_filter(['jmeno', 'prijmeni', 'telefon', 'ulice', 'mesto'], fn($k) => empty($o[$k])));
  if ($chybi) $pozn[] = 'Chybí: ' . implode(', ', array_map(fn($k) => $nazvy[$k], $chybi)) . ' – doplňte.';
  if ($v['kategorie'] === 'byt') {
    if ($v['byt']['model_kod'] !== '') { $o['model_kod'] = $v['byt']['model_kod']; if ($v['byt']['odstin'] !== '') $o['odstin'] = $v['byt']['odstin']; }
    else $pozn[] = 'Model dveří do bytu vyberte ručně.';
    foreach ($v['byt']['nejiste'] as $t) $pozn[] = $t;
    $o['ai_poznamky'] = $pozn;
  } else { $o['polozky'] = $v['polozky']; $o['ai_poznamky'] = $pozn; }
  $o['interni'] = mb_substr('Návrh pomocníka č. ' . $nid . (!empty($v['shrnuti']) ? ' – ' . $v['shrnuti'] : '') . ($otz ? "\nZeptat se: " . implode('; ', $otz) : ''), 0, 1500);
  $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  return $wu . '/nabidka-nova.html?sys=1#d=' . rtrim(strtr(base64_encode(json_encode($o, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
}
