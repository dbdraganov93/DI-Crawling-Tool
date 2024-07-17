<?php

use Marktjagd\ApiClient\Resource;
use Marktjagd\Service\IprotoApi\ApiServiceProvider;

\Marktjagd\ApiClient\Resource\ResourceFactory::setClasses(array());

/**
 * Service zum Importieren von Daten in die Marktjagd-API
 */
class Marktjagd_Service_Output_MarktjagdApi {

    /**
     * Führt den Import für Crawler bei der API durch
     *
     * @param Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig
     * @param Crawler_Generic_Response $response
     * @return Crawler_Generic_Response
     */
    public function import($eCrawlerConfig, $response)
    {
        return ApiServiceProvider::getApiService()->import($eCrawlerConfig, $response);
    }

    /**
     * Mappt die Storenumbers von Stores im Kern mit denen in der übergebenen CSV
     *
     * @param int $companyId
     * @param string $pathToCsv
     * @param string $system
     */
    public function mapStores($companyId, $pathToCsv) {
        // XXX: We have not implemented this function-call for the iProto-API, so it should either be removed or implemented.
        // Only invoked in: `tools/mapStores.php`
        if (ApiServiceProvider::getDefaultApi() == ApiServiceProvider::IPROTO) throw new \BadMethodCallException('not implemented for iProto-API');

        $sMjCsv = new Marktjagd_Service_Input_MarktjagdCsv();
        $cNewStores = $sMjCsv->convertToCollection($pathToCsv, 'stores');
        $sPartner = new Marktjagd_Database_Service_Partner();
        $ePartner = $sPartner->findByCompanyId($companyId);

        $logger = Zend_Registry::get('logger');

        /* @var $apiClient Marktjagd_Entity_MarktjagdApi */
        $apiClient = new Marktjagd_Entity_MarktjagdApi();
        $apiClient->setEnvironment($ePartner);

        $change = 0;
        $duplicate = 0;
        $mjStores = new Marktjagd_Service_Input_MarktjagdApi();
        $mjAddress = new Marktjagd_Service_Text_Address();

        $unvStores = $mjStores->findStoresByCompany($companyId);
        $cUnvStores = $unvStores->getElements();
        $cNewStores = $cNewStores->getElements();
        $aUsedNewStores = array();
        $aUsedUnvStores = array();
        $aUnvStores = array();
        $aCsvStores = array();
        $aOldNumber = array();

        /* @var $eNewStore Marktjagd_Entity_Api_Store*/
        /* @var $eUnvStore Marktjagd_Entity_Api_Store*/

        foreach ($cUnvStores as $eUnvStore) {
            $unvStorePre = Resource\Store\StoreResource::find($eUnvStore->getId());
            $newStoreNumberPre = uniqid('', true);
            $oldNumberPre = $unvStorePre->getNumber();
            if (!$unvStorePre->setNumber($newStoreNumberPre)->save()) {
                $unvStorePre->delete();
                $logger->log('couldn\'t change store number from ' . $oldNumberPre . ' to '
                    . $newStoreNumberPre . ', already exist?', Zend_Log::ERR);
            } else {
                $logger->log('changed number of the store ' . $oldNumberPre . ' to '
                    . $newStoreNumberPre, Zend_Log::INFO);
                $aOldNumber[] = $oldNumberPre;
            }
        }

        foreach ($cNewStores as $eNewStore) {
            foreach ($cUnvStores as $eUnvStore) {
                $aUnvStores[$eUnvStore->getId()] = $eUnvStore;
                if (($mjAddress->normalizeStreet($eNewStore->getStreet())
                        == $mjAddress->normalizeStreet($eUnvStore->getStreet()))
                        && ($mjAddress->normalizeStreetNumber((string)$eNewStore->getStreetNumber())
                                == $mjAddress->normalizeStreetNumber((string)$eUnvStore->getStreetNumber()))
                        && (trim($eNewStore->getZipCode()) == trim($eUnvStore->getZipCode()))) {
                    $aCsvStores[$eUnvStore->getId()] = $eNewStore;
                    $aUsedUnvStores[$eUnvStore->getStoreNumber()] = $eUnvStore;
                    if (!isset($aUsedNewStores[$eUnvStore->getId()])) {
                        $aUsedNewStores[$eUnvStore->getId()] = $eNewStore;
                    } else {
                        foreach ($cNewStores as $eNewStore) {
                            if (($mjAddress->normalizeStreet($eNewStore->getStreet())
                                    == $mjAddress->normalizeStreet($eUnvStore->getStreet()))
                                    && ($mjAddress->normalizeStreetNumber((string)$eNewStore->getStreetNumber())
                                            == $mjAddress->normalizeStreetNumber((string)$eUnvStore->getStreetNumber()))
                                    && ($eNewStore->getZipCode() == $eUnvStore->getZipCode())
                                    && (substr($eNewStore->getLatitude(), 0, 7)
                                            == substr($eUnvStore->getLatitude(), 0, 7))
                                    && (substr($eNewStore->getLongitude(), 0, 7)
                                            == substr($eUnvStore->getLongitude(), 0, 7))) {
                                $aCsvStores[$eUnvStore->getId()] = $eNewStore;
                                $aUsedNewStores[$eUnvStore->getId()] = $eNewStore;
                            }
                        }
                        if (!array_key_exists($eUnvStore->getId(), $aUsedNewStores)) {
                            $logger->log('unable to identify unique csv-store.', Zend_Log::ERR);
                            $duplicate++;
                        }
                    }
                }
            }
        }

        foreach ($cNewStores as $eNewStore) {
            if (in_array($eNewStore, $aUsedNewStores)) {
                continue;
            }
            foreach ($cUnvStores as $eUnvStore) {
                if (($mjAddress->normalizeStreet($eNewStore->getStreet())
                        == $mjAddress->normalizeStreet($eUnvStore->getStreet()))
                        && (trim($eNewStore->getZipCode()) == trim($eUnvStore->getZipCode()))) {
                    if (!isset($aUsedNewStores[$eUnvStore->getId()])) {
                        $aCsvStores[$eUnvStore->getId()] = $eNewStore;
                        $aUsedNewStores[$eUnvStore->getId()] = $eNewStore;
                    }
                }
            }
        }

        foreach ($cNewStores as $eNewStore) {
            if (in_array($eNewStore, $aUsedNewStores)) {
                continue;
            }
            foreach ($cUnvStores as $eUnvStore) {
                if (trim($eNewStore->getZipCode()) == trim($eUnvStore->getZipCode())) {
                    if (!isset($aUsedNewStores[$eUnvStore->getStoreNumber()])) {
                        $aCsvStores[$eUnvStore->getId()] = $eNewStore;
                        $aUsedNewStores[$eUnvStore->getId()] = $eNewStore;
                    }
                }
            }
        }

        if (count($aUsedNewStores)) {
            foreach ($aUsedNewStores as $key => $value) {
                $changeStore = Resource\Store\StoreResource::find($key);
                $oldNumber = $changeStore->getNumber();
                if ($value->getStoreNumber() != $oldNumber) {
                    if (!$changeStore->setNumber($value->getStoreNumber())->save()) {
                        $logger->log('couldn\'t change store number from ' . $oldNumber . ' to '
                            . $changeStore->getNumber() . ', already exist?', Zend_Log::ERR);
                    } else {
                        $logger->log('changed number of the store ' . $oldNumber . ' to '
                            . $changeStore->getNumber(), Zend_Log::INFO);
                        if (!in_array($changeStore->getNumber(), $aOldNumber)) {
                            $change++;
                        }
                    }
                } else {
                    $logger->log('equal storenumbers detected, store number: ' . $changeStore->getNumber(),
                        Zend_Log::INFO);
                }
            }
        } else {
            $logger->info('no store numbers changed.');
        }

        $logger->info('-----------------');

        

        $aCountUnvNotMatched = count($aUnvStores) - count($aUsedNewStores);
        $aCountCsvNotMatched = count($cNewStores) - count($aUsedNewStores);
        
        $aUnvNotMatched = array_diff_key($aUnvStores, $aUsedNewStores);

        // Ausgabe der 
        foreach ($aUnvNotMatched as $unvStore) {
            /* @var $unvStore Marktjagd_Entity_Api_Store */
            $logger->info('unv store not matched: store number: ' . $unvStore->getStoreNumber() . ', address: '
                . $unvStore->getZipcode() . ' ' . $unvStore->getCity() . ', '
                . $unvStore->getStreet() . ' ' . $unvStore->getStreetNumber());
        }

        $logger->info('-----------------');

        // Ausgabe der nicht  gemappten Stores aus der CSV
        foreach ($cNewStores as $eNewStore) {
            /* @var $csvStore Marktjagd_Entity_Api_Store */
            if (!in_array($eNewStore, $aCsvStores)) {
                $logger->info('csv store not matched: store number: ' . $eNewStore->getStoreNumber() . ', address: '
                    . $eNewStore->getZipcode() . ' ' . $eNewStore->getCity() . ', '
                    . $eNewStore->getStreet() . ' ' . $eNewStore->getStreetNumber());
            }
        }
        
        if (count($cNewStores) > count($cUnvStores)) {
            $percentMatchedUnv = (1 - ($aCountUnvNotMatched / count($cUnvStores))) * 100;
            $percentMatchedCsv = (1 - ($aCountCsvNotMatched / count($cUnvStores))) * 100;
        } else {
            $percentMatchedUnv = (1 - ($aCountUnvNotMatched / count($cNewStores))) * 100;
            $percentMatchedCsv = (1 - ($aCountCsvNotMatched / count($cNewStores))) * 100;
        }

        $logger->info('-----------------');
        $logger->info($change . ' storenumber(s) changed.');
        $logger->info($aCountUnvNotMatched . ' unv store(s) not matched.');
        $logger->info(number_format($percentMatchedUnv, 2, ',', '') . '% of possible unv-stores mapped.');
        $logger->info($aCountCsvNotMatched . ' csv store(s) not matched.');
        $logger->info(number_format($percentMatchedCsv, 2, ',', '') . '% of possible csv-stores mapped.');
             
        if ($duplicate != 0) {
            $logger->info($duplicate . ' non-identifiable csv-store(s) found.');
        }
    }
}