#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$sTimes = new Marktjagd_Service_Text_Times();

Zend_Debug::dump(preg_replace('# #', '', ':<br />Mo.-Fr  9:00-12:30, 13:00-17:30 Uhr'));
