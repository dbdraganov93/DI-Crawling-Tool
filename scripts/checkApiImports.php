#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

use Marktjagd\Service\IprotoApi\ApiServiceProvider;

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->info('checkApiImports.php gestartet');

try {
    $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
    $cCrawlerLog = $sCrawlerLog->findImporting();

    /* @var $eCrawlerLog Marktjagd_Database_Entity_CrawlerLog */
    foreach ($cCrawlerLog as $eCrawlerLog) {
        // Fetch the current status for the given import from the correct backend-endpoint / -environment:
        $backendApi = $eCrawlerLog->getCrawlerConfig()->getBackendSystem();
        $backendApiEnv = $eCrawlerLog->getCrawlerConfig()->getBackendEnv();
        $import = ApiServiceProvider::getApiService($backendApi, $backendApiEnv)->findImportById($eCrawlerLog->getCrawlerConfig()->getIdCompany(), $eCrawlerLog->getImportId());

        if ('new' == $import['status'] || 'importing' == $import['status']
        ) {
            continue;
        }

        switch ($import['status']) {
            case 'new': // Only APIv3
            case 'importing': // Only APIv3
            case 'created':
            case 'submitted':
            case 'running':
                break;
            case 'skipped':
            case 'done':
                $eCrawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::IMPORT_SUCCESS);
                $eCrawlerLog->setImportEnd(date('Y-m-d H:i:s'));
                $eCrawlerLog->save();
                break;
            case 'error': // Only APIv3
            case 'failed':
                if (count($import['errors']) > 0) {
                    $errorstring = '';
                    $count = 0;
                    $logger->log('Fehler bei Unternehmen ' . $eCrawlerLog->getCrawlerConfig()->getIdCompany()
                            . ' beim Import', Zend_Log::INFO);
                    $errorMessages = '';
                    foreach ($import['errors'] as $importError) {
                        $errorMessage = 'Datensatz ' . $importError['record'] . ': ' . $importError['message'];
                        $errorMessages .= $errorMessage . "\n\n";
                        $logger->log($errorMessage, Zend_Log::INFO);
                        $eCrawlerLog->addErrorMessage($errorMessage);
                    }
                }

                $eCrawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::IMPORT_FAILURE);
                $eCrawlerLog->setImportEnd(date('Y-m-d H:i:s'));
                $eCrawlerLog->save();
                break;
            default:
                $errorMessage = 'Unbekannter Importtyp ' . $import['status']
                        . ' beim Import in die API';
                $logger->log('Fehler bei Unternehmen ' . $eCrawlerLog->getCrawlerConfig()->getIdCompany()
                        . ' beim Import', Zend_Log::ERR);
                $logger->log($errorMessage, Zend_Log::ERR);
                $eCrawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::IMPORT_FAILURE);
                $eCrawlerLog->addErrorMessage($errorMessage);
                $eCrawlerLog->setImportEnd(date('Y-m-d H:i:s'));
                $eCrawlerLog->save();
        }
    }
    $logger->info('checkApiImports.php beendet');
}
catch (Exception $e) {
    $logger->log('Fehler im checkApiImports-Script aufgetreten:' . "\n"
            . $e->getMessage() . "\n"
            . print_r($e->getTrace(), true), Zend_Log::CRIT);
    $logger->__destruct();
}