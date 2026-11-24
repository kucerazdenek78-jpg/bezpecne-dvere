<?php
// erkado-kart.php: pruvodce ERKADO pro kartoteku = erkado-dvere.php + tlacitko "Vytvorit nabidku" (erkado-nabidka.js). INTERNI ?key=.
// Vklada ho do ramu stranka erkado-test.php (Sprava -> Konfigurator ERKADO (zkouska)). 15. 11. 2026.
// 24. 11. 2026: + erkado-tral-uloz.js (ulozi presny vyber s kody TRALu, aby sla objednavka prenest do TRALu).
ob_start();
include __DIR__ . '/erkado-dvere.php';
$h = ob_get_clean();
$v = (string)@filemtime(__DIR__ . '/erkado-nabidka.js');
$v2 = (string)@filemtime(__DIR__ . '/erkado-tral-uloz.js');
echo str_replace('</html>', '<script src="erkado-nabidka.js?v=' . $v . '"></script><script src="erkado-tral-uloz.js?v=' . $v2 . '"></script></html>', $h);
