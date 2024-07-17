#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

if (count($argv) < 3
    || $argv[1] == ''
    || $argv[2] == ''
) {
    $logger->log($argv[0] . " [company id] [pathToCsv] <demo|live> ", Zend_Log::INFO);
    $logger->log("Mappt die Storenumbers von Stores im Kern mit denen in der übergebenen CSV", Zend_Log::INFO);
    die();
}

$system = null;

$companyId = $argv[1];
$path = $argv[2];

if (count($argv) == 4
    && $argv[3] != ''
) {
    $system = $argv[3];
}

$sMjApi = new Marktjagd_Service_Output_MarktjagdApi();
if ($system) {
    $sMjApi->mapStores($companyId, $path, $system);
} else {
    $sMjApi->mapStores($companyId, $path);
}
