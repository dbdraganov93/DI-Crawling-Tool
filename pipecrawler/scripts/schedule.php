#!/usr/bin/php
<?php
chdir(__DIR__);
/**
 * Trägt einen Crawlereintrag mit Status WAITING in die CrawlerLog Tabelle in die Datenbank ein
 */
require_once __DIR__ . '/index.php';

$cCrawlerConfig = new Marktjagd_Database_Collection_CrawlerConfig();
$sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
$sCrawlerConfig->fetchAll(array('CrawlerStatus LIKE \'%zeitgesteuert%\''), $cCrawlerConfig);
$sScheduler = new Crawler_Generic_Scheduler();
$sScheduler->scheduleEntries($cCrawlerConfig);

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->log('schedule.php started', Zend_Log::INFO);

$sCrawlerStart = new Marktjagd_Service_Input_ScriptStart();
$sCrawlerStart->startByCron('10 2 * * *', 'updateCrawler.php');
$sCrawlerStart->startByCron('* * * * *', 'ftp.php');

if (preg_match('#production#', APPLICATION_ENV)) {
    $sCrawlerStart->startByCron('* * * * *', 'checkApiImports.php');
}
