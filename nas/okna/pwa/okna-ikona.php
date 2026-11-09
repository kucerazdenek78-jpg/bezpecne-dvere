<?php
// Ikona aplikace Servis oken 512×512 (soubor je v datech kontejneru, $DATA/okna-pwa/okna-512.png). Veřejná – jen obrázek.
$f = (getenv('FORMULARE_DATA') ?: '/data') . '/okna-pwa/okna-512.png';
if (!is_file($f)) { http_response_code(404); exit; }
header('Content-Type: image/png'); header('Cache-Control: public, max-age=604800'); header('Content-Length: ' . filesize($f));
readfile($f);
