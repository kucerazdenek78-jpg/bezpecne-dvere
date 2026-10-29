<?php
// Rozpracované nabídky / objednávky (uložené bez odeslání) – tlačítko „💾 Uložit rozpracovanou“ v nabidka-nova.html a objednavka-vice.html.
// Zápis a načtení přes veřejný koncept.php (jen s tajným klíčem konceptu), seznam v kartotéce koncepty.php (Rozpracované).
// Patří na NAS do /docker/formulare/app/lib/koncepty.php.

function konc_tabulka(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS koncepty (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL, vytvoreno TEXT, upraveno TEXT, stav TEXT NOT NULL DEFAULT 'rozpracovano',
    druh TEXT, cislo TEXT, zakaznik TEXT, kategorie TEXT, cena REAL, prodavac TEXT, data_json TEXT)");
  $pdo->exec('CREATE INDEX IF NOT EXISTS koncepty_stav ON koncepty(stav)');
}
function konc_pocet(PDO $pdo): int {
  try { konc_tabulka($pdo); return (int)$pdo->query("SELECT COUNT(*) FROM koncepty WHERE stav='rozpracovano'")->fetchColumn(); } catch (Throwable $e) { return 0; }
}
// Odkaz na web, který koncept otevře k dokončení (#k=id.token).
function konc_url(array $cfg, array $k): string {
  $wu = rtrim((string)($cfg['web_url'] ?? 'https://bezpecne-dvere.cz'), '/');
  return $wu . '/' . (($k['druh'] ?? '') === 'objednavka-vice' ? 'objednavka-vice.html' : 'nabidka-nova.html') . '?sys=1#k=' . (int)$k['id'] . '.' . $k['token'];
}
