# Napojení aplikace na kartotéku (NAS)

Soubor `aplikace-api.php` patří na NAS do `/docker/formulare/app/` vedle `odeslat.php`.
Používá stejné `lib/start.php` (databáze, `povolene_origin` z config.php). Nic v existujících souborech se nemění.

- Co umí: zákazník v aplikaci zadá číslo objednávky + poslední 4 číslice telefonu → dostane model, odstín a prodlouženou záruku
  z objednávky v kartotéce (typ `objednavka_interni`). Adresu, telefon ani ceny nevrací.
- Limit: 10 pokusů za hodinu z jedné IP (`aplikace_limit_hodina` v config.php to může změnit).
- Přes tunel je dostupný na `https://formulare.dvere.app/aplikace-api.php` (jen POST z bezpecne-dvere.cz).
- Dokud soubor na NAS není, aplikace napíše „zadejte dveře ručně“ – nic se nerozbije.

Poptávky, hlášení závad a objednávky prohlídek z aplikace jdou přes stávající `odeslat.php` (typ `poptavka`)
→ jsou v kartotéce a v e-mailu. Poznáte je podle předmětu („Poptávka z aplikace…“, „Servis z aplikace…“, „Roční prohlídka z aplikace…“)
a podle `zdroj` v data_json (`aplikace-vizualizace`, `aplikace-servis`, `aplikace-prohlidka`).
