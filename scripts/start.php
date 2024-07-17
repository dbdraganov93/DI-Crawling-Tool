#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';
declare(ticks=1);

function shutdown()
{
    if ($error = error_get_last()) {
        if (isset($error['type']) && ($error['type'] == E_ERROR ||
                $error['type'] == E_PARSE ||
                $error['type'] == E_COMPILE_ERROR)
        ) {
            ob_end_clean();

            $message = 'Es ist ein Fehler aufgetreten: ' . "\n"
                . 'Message: ' . $error['message'] . "\n"
                . 'File: ' . $error['file'] . "\n"
                . 'Line: ' . $error['line'];
            /* @var $logger Zend_Log */
            $logger = Zend_Registry::get('logger');
            $logger->log($message, Zend_Log::CRIT);
            $logger->__destruct();
        }
    }
}

//// signal handler function
function sig_handler($signo)
{
    switch ($signo) {
        case SIGTERM:
        case SIGINT:
        case SIGHUP:
        case SIGUSR1:
            $message = 'Es ist ein Fehler beim Ermitteln und Starten der Crawler aufgetreten. Check DB!';
            /* @var $logger Zend_Log */
            $logger = Zend_Registry::get('logger');
            $logger->log($message, Zend_Log::CRIT);
            $logger->__destruct();
            exit;
        default:
            // handle all other signals
    }
}

register_shutdown_function('shutdown');


$sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
$crawlerConfigFile = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

// check if running processes under limit => maxDiff
$runningProcesses = $sCrawlerLog->countRunningProcesses();
$maxProcesses = (int) $crawlerConfigFile->crawler->max;
$processSlots = $maxProcesses - $runningProcesses;

// check if running articles < runningArticlesMax => articleDiff
$runningProcessesArticles = $sCrawlerLog->countRunningProcesses('articles');
$maxProcessesArticles = (int) $crawlerConfigFile->crawler->articles->max;
$articleSlots = $maxProcessesArticles - $runningProcessesArticles;

// check if running pdfs < runningPdfsMax => diff pdfs
$runningProcessesBrochures = $sCrawlerLog->countRunningProcesses('brochures');
$maxProcessesBrochures = (int) $crawlerConfigFile->crawler->brochures->max;
$brochureSlots = $maxProcessesBrochures - $runningProcessesBrochures;

// check if running stores < runningStoresMax => diff stores
$runningProcessesStores = $sCrawlerLog->countRunningProcesses('stores');
$maxProcessesStores = (int) $crawlerConfigFile->crawler->stores->max;
$storeSlots = $maxProcessesStores - $runningProcessesStores;


$cCrawlerLog = $sCrawlerLog->findNextProcesses($processSlots, $articleSlots, $brochureSlots, $storeSlots);
foreach ($cCrawlerLog as $eCrawlerLog) {
    /* @var $eCrawlerLog Marktjagd_Database_Entity_CrawlerLog */
    $a = $b = null;
    exec('php init.php ' . escapeshellarg($eCrawlerLog->getIdCrawlerLog())
        . ' > /dev/null </dev/null 2>/dev/null & echo $!', $a, $b);
}

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->log('start.php gestartet', Zend_Log::INFO);
