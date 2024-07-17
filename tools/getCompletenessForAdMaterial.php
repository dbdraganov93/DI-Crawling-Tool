#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

//if ($argc < 3) {
//    throw new Exception('invalid amount of parameters given.');    
//}

$startTime = 1466504706;
$endTime = 1466511906;

$sDbCompleteness = new Marktjagd_Database_Service_CompaniesWithAdMaterial();

Zend_Debug::dump($sDbCompleteness->findCompletenessByTimeSpan($startTime, $endTime, '14'));