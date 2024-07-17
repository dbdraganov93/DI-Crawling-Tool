<?php

use Marktjagd\Service\IprotoApi\ApiServiceProvider;

/**
 * Steuert den Crawling-Prozess
 *
 * Class Crawler_Generic_Crawler
 */
class Crawler_Generic_Crawler
{
    protected $_logId;

    /**
     * Startet den Crawling-Prozess
     *
     * @param $idCrawlerLog
     * @param $processNumber
     */
    public function start($idCrawlerLog, $processNumber)
    {
        $logger = Zend_Registry::get('logger');
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
        $eCrawlerLog = $sCrawlerLog->findById($idCrawlerLog);
        $eCrawlerConfig = $sCrawlerConfig->findById($eCrawlerLog->getIdCrawlerConfig());

        // Configure the Backend-API to use the correct endpoint as defined in the crawler's config:
        ApiServiceProvider::setDefaultApi($eCrawlerConfig->getBackendSystem());
        ApiServiceProvider::setDefaultEnv($eCrawlerConfig->getBackendEnv());
        $logger->log("using ".ApiServiceProvider::getDefaultEnv()." ".ApiServiceProvider::getDefaultApi()." backend-api", Zend_Log::INFO);

        if (!strlen($eCrawlerConfig->getIdCrawlerConfig())) {
            $logger->log('Fehler beim Starten eines Crawlers aufgetreten.'
                . ' Kein Crawler für CrawlerConfig ID ' . $eCrawlerLog->getIdCrawlerConfig()
                . ' in der Datenbank gefunden. CrawlerConfig-Tabelle ueberpruefen!', Zend_Log::CRIT);
            return;
        }

        $logger->log('Starte ' . ucfirst($eCrawlerConfig->getCrawlerType()->getType()) . '-Crawler'
            . ' für Unternehmen ' . $eCrawlerConfig->getCompany()->getName(), Zend_Log::INFO);
        $this->_initStart($idCrawlerLog, $processNumber);

        if (substr($eCrawlerConfig->getFileName(), 0, 11) == 'application') {
            // Crawler über ZendFramework
            try {
                $className = $this->_mapFileToClass($eCrawlerConfig->getFileName());
                /* @var $crawler Crawler_Generic_Company */
                $crawler = new $className();
                $response = $crawler->crawl($eCrawlerConfig->getIdCompany());
            } catch (Exception $e) {
                $logger->log($e->getMessage() . ' ' . $e->getTraceAsString(), Zend_Log::CRIT);
                $response = new Crawler_Generic_Response();
                $response->setLoggingCode(Crawler_Generic_Response::FAILED)
                    ->setIsImport(false);
            }
        } else {
            $response = new Crawler_Generic_Response();
            $response->setLoggingCode(Crawler_Generic_Response::FAILED)
                ->setIsImport(false);
        }

        $eCrawlerLog = $sCrawlerLog->findById($idCrawlerLog);
        $response->save($this->_logId);

        // durchschnittliche Runtime neu berechnen
        $calculatedRuntime = $sCrawlerLog->calculateEstimatedRuntime($eCrawlerLog->getIdCrawlerConfig());

        if ((int)$calculatedRuntime > 0) {
            $eCrawlerConfig->setRuntime($calculatedRuntime);
            $eCrawlerConfig->save();
        }

        // Wenn Standortdaten, dann vor dem Import die Vollständigkeit prüfen um einen Verlust zu vermeiden
        if ($eCrawlerLog->getPrio() == '1' && $eCrawlerConfig->getCrawlerType()->getType() == Marktjagd_Database_Entity_CrawlerType::$TYPE_STORES && $response->getIsImport() && $response->getFileName()) {
            $sStoreCompletely = new Marktjagd_Service_Compare_Collection_StoreCompletely();
            $lostValues = $sStoreCompletely->compareStoresCompletely($response->getFileName(), $eCrawlerConfig->getCompany()->getIdCompany(), $eCrawlerConfig->getCompany()->getName());

            if ($lostValues['total_lost'] >= 0.2) {
                $response = new Crawler_Generic_Response();
                $response->setLoggingCode(Crawler_Generic_Response::FAILED)
                    ->setIsImport(false);
                $response->save($this->_logId);

                if (preg_match('#amazonaws#', $response->getFileName())) {
                    $s3File = new Marktjagd_Service_Output_S3File();
                    $s3File->removeFileFromBucket($response->getFileName());
                }

                $eCrawlerLog->addErrorMessage('lost store data detected: ' . Zend_Debug::dump($lostValues, null, false));
                $eCrawlerLog->save();
            }
        }


        if (APPLICATION_ENV != 'development') {
            if ($eCrawlerConfig->getCompany()->getIdPartner() != 5) {
                // Import über die API
                $sMarktjagdApi = new Marktjagd_Service_Output_MarktjagdApi();
                $response = $sMarktjagdApi->import($eCrawlerConfig, $response);
            } elseif ($eCrawlerConfig->getCompany()->getIdPartner() == 5) {
                $sWgwApi = new Wgw_Service_Import_Import();
                $response = $sWgwApi->import($eCrawlerConfig, $response);
            }
        } else {
            if ($response->getLoggingCode() == Crawler_Generic_Response::SUCCESS) {
                $response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
                $eCrawlerLog->setErrorMessage($response->getFileName());
                $eCrawlerLog->save();
            }
        }

        $logger->log(ucfirst($eCrawlerConfig->getCrawlerType()->getType()) . '-Crawler'
            . ' für Unternehmen ' . $eCrawlerConfig->getCompany()->getName()
            . ' (ID:' . $eCrawlerConfig->getCompany()->getIdCompany() . ') beendet', Zend_Log::INFO);

        $response->save($this->_logId);
        $eCrawlerLog = $sCrawlerLog->findById($idCrawlerLog);
        if ($eCrawlerConfig->getTicketCreate()) {
            $sRedmine = new Marktjagd_Service_Output_Redmine();
            $sRedmineIn = new Marktjagd_Service_Input_Redmine();
            $aTickets = $sRedmineIn->findAllTicketsForSpecificVersionId();
            if ($eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '3' && $eCrawlerLog->getPrio() == '1') {
                foreach ($aTickets as $singleTicket) {
                    if (preg_match('#' . addcslashes($eCrawlerLog->getCrawlerConfig()->getCompany()->getName(), '()') . '#i', $singleTicket['subject'])
                        && preg_match('#' . $eCrawlerLog->getCrawlerConfig()->getCompany()->getIdCompany() . '#i', $singleTicket['subject'])
                        && preg_match('#' . $eCrawlerLog->getCrawlerConfig()->getCrawlerType()->getType() . '#i', $singleTicket['subject'])) {
                        return;
                    }
                }

                $sRedmine->generateErrorTicket($eCrawlerLog);
            } elseif ($eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '2'
                || $eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '4'
                || $eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '8'
                || $eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '10'
                || $eCrawlerLog->getCrawlerLogType()->getIdCrawlerLogType() == '11') {
                foreach ($aTickets as $singleTicket) {
                    if (preg_match('#' . $eCrawlerLog->getCrawlerConfig()->getCompany()->getName() . '#i', $singleTicket['subject'])
                        && preg_match('#' . $eCrawlerLog->getCrawlerConfig()->getCompany()->getIdCompany() . '#i', $singleTicket['subject'])
                        && preg_match('#' . $eCrawlerLog->getCrawlerConfig()->getCrawlerType()->getType() . '#i', $singleTicket['subject'])) {
                        $sRedmine->updateTicket($singleTicket['id'], array('status_id' => 37));
                    }
                }
            }
        }

    }

    /**
     * Gibt den Klassennamen zurück, wenn es sich um ein neuen Crawler handelt, sonst wird false zurück gegeben
     *
     * @param $fileName
     * @return bool|string
     */
    protected function _mapFileToClass($fileName)
    {
        $className = trim($fileName, 'application/modules/');
        $className = trim($className, '.php');
        $className = str_replace('companies', 'Company', $className);
        $className = str_replace('generic', 'Generic', $className);
        $className = str_replace('/', '_', $className);
        return $className;
    }

    /**
     * Bereitet das DB-Logging für den Crawler vor
     *
     * @param $idCrawlerLog
     * @param $processNumber
     */
    protected function _initStart($idCrawlerLog, $processNumber)
    {
        $crawlerLog = new Marktjagd_Database_Entity_CrawlerLog();
        $crawlerLog->setIdCrawlerLog($idCrawlerLog)
            ->setIdCrawlerLogType(Crawler_Generic_Response::PROCESSING)
            ->setStart(date('Y-m-d H:i:s'))
            ->setProcessNumber($processNumber);
        $crawlerLog->save();
        $this->_logId = $idCrawlerLog;
    }

    /**
     * Beendet den Crawler in der Datenbank
     * @param $idCrawlerLog
     * @param $message
     */
    public function finishError($idCrawlerLog, $message)
    {
        $crawlerLog = new Marktjagd_Database_Entity_CrawlerLog();
        $crawlerLog->setIdCrawlerLog($idCrawlerLog)
            ->setIdCrawlerLogType(Crawler_Generic_Response::FAILED)
            ->setEnd(date('Y-m-d H:i:s'))
            ->setErrorMessage($message);
        $crawlerLog->save();;
    }
}