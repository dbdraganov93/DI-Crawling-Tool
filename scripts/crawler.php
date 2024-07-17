#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
if (count($argv) < 3
    || !$argv[1]) {
    $logger->log($argv[0] . " <stores|articles|pdfs> [company id] [<production|staging|testing>]", Zend_Log::INFO);
    $logger->log("Startet den ausgewaehlten Crawler für ein Unternehmen", Zend_Log::INFO);
    die();
}

$crawlType = $argv[1];
if ($crawlType!='stores'
    && $crawlType!='articles'
    && $crawlType!='pdfs'
) {
    $logger->log('Unbekannter Crawler-Typ ' . $argv[1], Zend_Log::INFO);
}

$companyId = $argv[2];
if (!is_numeric($companyId)) {
    $logger->log('CompanyId ist kein numerischer Wert: ' . $companyId, Zend_Log::INFO);
}

if ($argc >= 4) {
    $env = $argv[3];
    if (!in_array($env, Marktjagd_Database_Entity_CrawlerConfig::VALID_BACKEND_ENVS)) {
        $logger->log('Invalid backende-env: ' . $env, Zend_Log::ERR);
        exit(-1);
    }
} else {
    $env = Marktjagd_Database_Entity_CrawlerConfig::BACKEND_ENV_PROD;
}

require_once __DIR__ . '/index.php';

$sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
$cCrawlerConfig = $sCrawlerConfig->findByCompanyTypeStatus($companyId, $crawlType, null, $env);
if ($cCrawlerConfig->count() == 0) {
    $logger->log('Unable to find any crawler-configs for the given parameters', Zend_Log::ERR);
    exit(-1);
}

$sScheduler = new Crawler_Generic_Scheduler();
$sScheduler->scheduleEntries($cCrawlerConfig, true);
