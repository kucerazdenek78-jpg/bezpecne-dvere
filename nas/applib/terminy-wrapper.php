<?php
// Tenký wrapper: plná logika je v data/okna-app/terminy-plny.php (app/lib nejde přepsat instalátorem).
// Předáme TERMINY_LIB = tento adresář (app/lib), aby sousední require (posta.php, zamek.php, okna-ukoly.php, navaznost.php) seděly.
define('TERMINY_LIB', __DIR__);
$f = (getenv('FORMULARE_DATA') ?: '/data') . '/okna-app/terminy-plny.php';
if (is_file($f)) require $f;
