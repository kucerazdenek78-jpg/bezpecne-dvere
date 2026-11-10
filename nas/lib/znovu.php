<?php
// Znovu odeslat objednávku zákazníkovi (10. 11. 2026, přání Zdeňka):
// když prodavač zadal špatný e-mail a objednávka se vrátila, v detailu objednávky se opraví e-mail
// a pošle se zákazníkovi znovu potvrzení objednávky (rekapitulace + platba zálohy + PDF objednávky v příloze).
// Volá se z lib/foto.php: zn_box($r) vykreslí okno v detailu, zn_poslat($pdo) zpracuje odeslání (POST akce=objednavka_znovu).
// Opravený e-mail se uloží i do záznamu (data_json.email), ať dál chodí na správnou adresu.

// najde PDF přílohu objednávky (soubor uložený v $DATA/ulozeno); vrací [cesta, hezký název] nebo null
function zn_pdf_priloha($r): ?array {
  global $DATA;
  foreach (array_filter(explode("\n", (string)($r['prilohy'] ?? ''))) as $s) {
    if (preg_match('/\.pdf$/i', $s) && is_file($DATA . '/ulozeno/' . $s)) {
      return [$DATA . '/ulozeno/' . $s, preg_replace('/^\d+_[0-9a-f]{8}_/', '', $s)];
    }
  }
  return null;
}

// text potvrzení objednávky zákazníkovi (rekapitulace + platba zálohy)
function zn_text(array $d, string $cislo, bool $maPdf): array {
  $ucet = function_exists('nast_ucet') ? nast_ucet($GLOBALS['cfg']) : '1821576001/5500';
  $vs = ltrim(preg_replace('/\D+/', '', $cislo), '0');
  $kc = fn($v) => ($v === null || $v === '' || !is_numeric($v)) ? '' : number_format((float)$v, 0, ',', ' ') . ' Kč';
  // rekapitulace dveří z dostupných polí
  $popis = array_filter([
    'Model' => (string)($d['model'] ?? ''), 'Barva' => (string)($d['odstin'] ?? ''), 'Zárubeň' => (string)($d['zarubne'] ?? ''),
    'Kování' => (string)($d['kovani'] ?? ''), 'Otevírání' => (string)($d['otevirani'] ?? ''), 'Šířka' => (string)($d['sire'] ?? ''),
  ], fn($v) => trim($v) !== '');
  $radky = '';
  foreach ($popis as $k => $v) $radky .= "• $k: $v\n";
  $zaloha = $d['zaloha'] ?? null;
  $cena = $kc($d['cena_celkem'] ?? null);

  $t = "Dobrý den,\n\nposíláme Vám potvrzení Vaší objednávky bezpečnostních dveří č. $cislo.\n\n";
  if ($radky !== '') $t .= $radky . "\n";
  if ($cena !== '') $t .= "Cena celkem: $cena\n";
  if ($zaloha !== null && is_numeric($zaloha) && (float)$zaloha > 0) {
    $t .= "Záloha (rezervační poplatek): " . $kc($zaloha) . "\n\n"
      . "Zálohu prosím uhraďte na účet $ucet" . ($vs !== '' ? ", variabilní symbol $vs" : '') . ". "
      . ($maPdf ? "Nejrychleji přes QR kód, který najdete v přiloženém PDF objednávky. " : '')
      . "Jakmile platba dorazí, pustíme se do přípravy Vašich dveří.\n\n";
  } else {
    $t .= "\n";
  }
  if ($maPdf) $t .= "V příloze je rekapitulace objednávky v PDF.\n\n";
  $t .= "Kdybyste cokoli potřebovali – upřesnit objednávku, poradit s platbou nebo se jen na něco zeptat – stačí odpovědět na tento e-mail nebo zavolat na +420 725 559 235. Rádi pomůžeme.\n\n"
    . "Děkujeme za důvěru.";
  return ["Potvrzení objednávky č. $cislo – Bezpečné dveře", $t];
}

// zpracování POST (akce=objednavka_znovu) – volá se z foto_obsluz v kartoteka.php (už po přihlášení)
function zn_poslat(PDO $pdo): void {
  global $cfg, $DATA;
  $id = (int)($_POST['id'] ?? 0);
  $st = $pdo->prepare("SELECT * FROM zadosti WHERE id=? AND typ='objednavka_interni'"); $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) { header('Location: kartoteka.php?id=' . $id); exit; }
  if (function_exists('zamceno') && zamceno($pdo, $id)) { header('Location: kartoteka.php?id=' . $id . '&zn=zamceno#znovu'); exit; }
  $d = json_decode((string)$r['data_json'], true) ?: [];
  $em = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
  if (!$em) { header('Location: kartoteka.php?id=' . $id . '&zn=email#znovu'); exit; }
  // oprava e-mailu v záznamu (ať dál chodí na správnou adresu)
  $puvodni = (string)($d['email'] ?? '');
  if (strcasecmp($puvodni, $em) !== 0) {
    $d['email'] = $em;
    $pdo->prepare('UPDATE zadosti SET data_json=?, email=? WHERE id=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $em, $id]);
    if (function_exists('hist_pis')) hist_pis($pdo, $id, 'opraven e-mail zákazníka', $puvodni . ' → ' . $em);
  }
  require_once __DIR__ . '/posta.php';
  $cislo = (string)($d['obj_cislo'] ?? $r['cislo']);
  $pdf = zn_pdf_priloha($r);
  [$predmet, $telo] = zn_text($d, $cislo, $pdf !== null);
  $cc = $cfg['prijemci']['objednavka_zakaznik']['cc'] ?? [];
  $prodavac = (string)($d['prodavac'] ?? '');
  $ok = posli_mail($cfg, $DATA, [$em], $cc, $cfg['from'], $predmet, $telo, $pdf ? [$pdf] : [], '', $prodavac);
  if (function_exists('hist_pis')) hist_pis($pdo, $id, $ok ? 'objednávka znovu odeslána zákazníkovi' : 'znovuodeslání objednávky SELHALO', $em);
  header('Location: kartoteka.php?id=' . $id . '&zn=' . ($ok ? 'ok' : 'chyba') . '#znovu'); exit;
}

// okno „Znovu odeslat objednávku zákazníkovi“ do detailu objednávky
function zn_box($r): void {
  if (($r['typ'] ?? '') !== 'objednavka_interni') return;
  $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  $d = json_decode((string)($r['data_json'] ?? ''), true) ?: [];
  $em = (string)($d['email'] ?? $r['email'] ?? '');
  $pdf = zn_pdf_priloha($r);
  $zn = $_GET['zn'] ?? '';
  $zpravy = [
    'ok' => ['ok', 'Objednávka byla znovu odeslána zákazníkovi na ' . $e($em) . '.'],
    'chyba' => ['err', 'E-mail se nepodařilo odeslat. Zkuste to prosím znovu, případně zkontrolujte adresu.'],
    'email' => ['err', 'Zadejte prosím platnou e-mailovou adresu.'],
    'zamceno' => ['err', 'Záznam právě upravuje někdo jiný, zkuste to za chvíli.'],
  ];
  ?><div class="box" id="znovu"><h3>✉️ Znovu odeslat objednávku zákazníkovi</h3>
    <?php if (isset($zpravy[$zn])): ?><div class="<?= $zpravy[$zn][0] ?>" style="margin-bottom:10px"><?= $zpravy[$zn][1] ?></div><?php endif; ?>
    <p class="mut" style="margin:.2rem 0 .6rem">Když se objednávka zákazníkovi vrátila (např. překlep v e-mailu), oprav tady adresu a pošli potvrzení objednávky znovu. Pošle se rekapitulace objednávky<?= $pdf ? ', platba zálohy a PDF objednávky v příloze' : ' a platba zálohy' ?>; e-mail dostane podpis obchodníka.</p>
    <form method="post" onsubmit="return confirm('Poslat objednávku zákazníkovi na tuto e-mailovou adresu?')">
      <input type="hidden" name="akce" value="objednavka_znovu"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <p><label>E-mail zákazníka<br><input type="email" name="email" value="<?= $e($em) ?>" required size="34" placeholder="jmeno@email.cz" style="max-width:100%"></label></p>
      <p><?= $pdf ? '📎 V příloze: <b>' . $e($pdf[1]) . '</b>' : '<span class="mut">K objednávce není uložené PDF – pošle se jen text objednávky.</span>' ?></p>
      <button>✉️ Odeslat objednávku znovu</button>
    </form>
  </div><?php
}
