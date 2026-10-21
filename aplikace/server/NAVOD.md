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

## Počítadlo využití aplikace (17. 10. 2026)
- `aplikace-api.php` umí akci `udalost`: aplikace anonymně posílá události `navsteva_nova` (první otevření v telefonu), `navsteva`
  (otevření v další den), `foto`, `vizualizace` (první dveře na nové fotce), `vyber` (další model/odstín), `odeslano` (poptávka/servis/prohlídka).
  Ukládají se jen denní součty v tabulce `app_statistika` (vytvoří se sama, SQLite i MySQL). Žádná IP, jméno, fotka ani ID telefonu;
  proti zahlcení max. 300 událostí z jedné IP za hodinu (tabulka `app_stat_limit`, jen hash IP pro danou hodinu, maže se po 2 h).
- Přehled: `statistika-aplikace.php` – otevírat přes https://kartoteka.dvere.app/statistika-aplikace.php (Cloudflare Access)
  nebo z místní sítě. Přes formulare.dvere.app vrací 404. `?prodejce` = předvádění prodejcem (režim `#viz?p=1`) zvlášť.
- Nasazení: zálohovat původní `aplikace-api.php` (např. `aplikace-api.php.bak-pred-statistikou`) a nahrát oba soubory do `/docker/formulare/app/`.
  Dokud nejsou na NAS, aplikace události posílá naprázdno (server vrátí „akce“) a nic se nerozbije.

## Pravidelná údržba dveří – připomínky po 6 měsících (21. 10. 2026, platí od 1. 11. 2026)
- Soubory: `udrzba.php` → `/docker/formulare/app/lib/udrzba.php`; `udrzba-pripominky.php` → `/docker/formulare/app/`;
  upravené `aplikace-api.php` (→ app/) a `pozadavky.php` (→ app/lib/). Před nahráním zálohovat s příponou `.bak-pred-udrzbou`.
- Tabulky se vytvoří samy: sloupce `zadosti.udrzba_vypnuto_dne`, `zadosti.udrzba_mail_pro`, tabulka `udrzba_zaznamy` (provedeno / vypnuto / zapnuto / připomínka).
- Aplikace (akce `udrzba_akce`, `co` = hotovo | vypnout | zapnout): zákazník potvrdí provedení údržby, vypne/zapne e-mailové připomínky.
  Vše se zapíše s datem do servisní knížky (rodný list) – v kartotéce je tak doklad, kdy zákazník údržbu potvrdil nebo připomínky vypnul.
- Termín: 6 měsíců od předání, po potvrzení údržby 6 měsíců od potvrzení. Interiérové dveře se nepočítají.
- Plánovač (jednou denně, např. 8:00): `docker exec formulare-web-1 php /var/www/html/udrzba-pripominky.php`
  Zkouška bez odeslání: `... udrzba-pripominky.php --suchy` (případně `--suchy --dnes=2027-05-10`). Do 1. 11. 2026 skript nic nedělá.
- Návod pro zákazníky: https://bezpecne-dvere.cz/udrzba-dveri.html
