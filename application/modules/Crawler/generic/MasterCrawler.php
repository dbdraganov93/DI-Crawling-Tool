<?php
/**
 * Mastercrawler-Klasse
 *
 * Class Crawler_Generic_MasterCrawler
 */
class Crawler_Generic_MasterCrawler extends Crawler_Generic_Abstract
{
    /**
     * Methode, die den Crawlingprozess initiert
     *
     * @param int $companyId
     * @param int $configurationId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId, $configurationId)
    {
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $baseUrl = $configIni->masterCrawler->urlBase . ':' . $configIni->masterCrawler->port;
        $pathExtractionStart = $baseUrl . $configIni->masterCrawler->urlStart . $configurationId;

        try {
            // Crawler starten
            $ch = curl_init($pathExtractionStart);

            if (FALSE === $ch) {
                $this->_logger->log('curl for starting mastercrawler failed to initialize', Zend_Log::ERR);
                $this->_response->generateResponseByFileName(false);
                $this->_response->setLoggingCode(Crawler_Generic_Response::COULDNT_START);
                return $this->_response;
            }

            curl_setopt($ch, CURLOPT_ENCODING, "");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $responseStart = json_decode(curl_exec($ch));

            if (null == $responseStart) {
                // Falls Status ERROR
                $this->_logger->log('curl for starting mastercrawler failed with status: ' . curl_errno($ch) . "\n"
                    . 'response: ' . curl_error($ch), Zend_Log::ERR);

                $this->_response->generateResponseByFileName(false);
                $this->_response->setLoggingCode(Crawler_Generic_Response::COULDNT_START);
                return $this->_response;
            }

            curl_close($ch);

            if ($responseStart->code == 'success') {
                $processId = $responseStart->data->id;
                $pathExtractionStatus = $baseUrl . $configIni->masterCrawler->urlStatus . $processId;

                $validStatus = array('PREPARED', 'STARTED', 'FINISHED');

                // Periodisch den Status des Crawlers abfragen
                while (true) {
                    $ch = curl_init($pathExtractionStatus);

                    if (FALSE === $ch) {
                        $this->_logger->log('curl for getting status of mastercrawler failed to initialize', Zend_Log::ERR);
                        $this->_response->generateResponseByFileName(false);
                        $this->_response->setLoggingCode(Crawler_Generic_Response::COULDNT_START);
                        return $this->_response;
                    }

                    curl_setopt($ch, CURLOPT_ENCODING, "");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $responseStatus = json_decode(curl_exec($ch));

                    if (null == $responseStatus) {
                        // Falls Status ERROR
                        $this->_logger->log('curl for getting status of mastercrawler failed with status: ' . curl_errno($ch) . "\n"
                            . 'response: ' . curl_error($ch), Zend_Log::ERR);

                        $this->_response->generateResponseByFileName(false);
                        $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
                        return $this->_response;
                    }

                    curl_close($ch);
                    if ($responseStatus->data->status == 'FINISHED') {
                        return $this->_exportData($companyId, $responseStatus);

                    }

                    if (!in_array($responseStatus->data->status, $validStatus)) {
                        // Falls Status ERROR
                        $this->_logger->log('an error occured: mastercrawler failed with status: '. $responseStatus->data->status . "\n"
                            . 'response: ' . print_r($responseStatus, TRUE), Zend_Log::ERR);

                        return $this->_response->generateResponseByFileName(false);
                    }

                    $this->_logger->log('crawler currently running . waiting 5sec', Zend_Log::INFO);
                    sleep(5);
                }
            }
        } catch (Exception $e) {
            $this->_logger->log('curl for mastercrawler failed with status: ' . $e->getCode() . "\n"
                . 'response: ' . $e->getMessage(), Zend_Log::ERR);

            return $this->_response->generateResponseByFileName(false);
        }

        // Should not be reached
        return $this->_response->generateResponseByFileName(false);
    }

    /**
     * @param $store
     * @return Marktjagd_Entity_Api_Store
     */
    protected function _mapStoreToEntity ($store)
    {
        $eStore = new Marktjagd_Entity_Api_Store();

        // Mapping von Parametername auf Funktionsname
        $aParams = array(
            'storeNumber' => 'storeNumber',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'text'=> 'text',
            'zipcode' => 'zipcode',
            'city'=> 'city',
            'street' => 'street',
            'streetNumber' => 'streetNumber',
            'distribution' => 'distribution',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'phone' => 'phone',
            'fax' => 'fax',
            'email' => 'email',
            'storeHours' => 'storeHours',
            'storeHoursNotes' => 'storeHoursNotes',
            'payment' => 'payment',
            'imageUrl' => 'image',
            'website' => 'website',
            'logo' => 'logo',
            'parking' => 'parking',
            'barrierFree' => 'barrierFree',
            'bonusCard' => 'bonusCard',
            'section' => 'section',
            'service' => 'service',
            'toilet' => 'toilet',
            'defaultRadius' => 'defaultRadius',
        );

        foreach ($aParams as $param => $function) {
            $functionName = 'set' . ucfirst($function);

            if ($store->$param
                && method_exists($eStore, $functionName)
            ) {
                $eStore->$functionName($store->$param);
            }
        }

        return $eStore;
    }

    /**
     * @param $companyId
     * @param $responseStatus
     * @return Crawler_Generic_Response
     */
    private function _exportData($companyId, $responseStatus)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $baseUrl = $configIni->masterCrawler->urlBase . ':' . $configIni->masterCrawler->port;

        // Export der Daten
        $pathExport = $baseUrl . $configIni->masterCrawler->urlExport . $responseStatus->data->id;
        $ch = curl_init($pathExport);

        if (FALSE === $ch) {
            $this->_logger->log('curl for mastercrawler failed to initialize', Zend_Log::ERR);
            $this->_response->generateResponseByFileName(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::COULDNT_START);
            return $this->_response;
        }

        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $responseExport = json_decode(curl_exec($ch));

        if (null == $responseExport) {
            // Falls Status ERROR
            $this->_logger->log('curl for getting export of mastercrawler failed with status: ' . curl_errno($ch) . "\n"
                . 'response: ' . curl_error($ch), Zend_Log::ERR);

            $this->_response->generateResponseByFileName(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::FAILED);
            return $this->_response;
        }

        curl_close($ch);

        // Mappen der Daten auf MJ-Collection
        foreach ($responseExport->data as $store) {
            $eStore = $this->_mapStoreToEntity($store);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}