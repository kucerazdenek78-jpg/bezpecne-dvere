# Servis oken – samostatná aplikace (kartoteka.dvere.app/okna/)

PHP v kontejneru nesmí zapisovat do složky aplikace, proto kód aplikace leží v datech kontejneru:
`$DATA/okna-app/*.php` (na NAS /docker/formulare/data/okna-app, nahrává ho jednorázový instalátor z GitHubu).
Ve složce `app/okna/` jsou jen malé „stuby“ (index.php, svj.php, zakaznici.php, poptavky.php), které definují OK_APP
a načtou stejnojmenný soubor z `$DATA/okna-app/`. Databáze: `$DATA/okna.sqlite`, fotky a protokoly `$DATA/okna/`.
