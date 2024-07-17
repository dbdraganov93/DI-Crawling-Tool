<?php

/**
 * Klasse zum Vergleich der Vollständigkeit von Standortdaten
 *
 * Class Marktjagd_Service_Compare_Collection_Store
 */
class Marktjagd_Service_Compare_Collection_StoreCompletely
{

    /**
     * Vergleicht die Daten in der CVS mit Daten im KERN-System
     *
     * @param $pathToCSV
     * @param $companyId
     * @param $companyTitle
     * @return $float value of differences
     */
    public function compareStoresCompletely($pathToCSV, $companyId)
    {
        $logger = Zend_Registry::get('logger');
        if (preg_match('#amazonaws#', $pathToCSV)) {
            $sS3File = new Marktjagd_Service_Output_S3File('mjcsv', basename($pathToCSV));
            $localPath = $sS3File->generateLocalDownloadFolder($companyId);
            $logger->log($companyId . ': folder created ' . $localPath, Zend_Log::INFO);
            $pathToCSV = $sS3File->getFileFromBucket($pathToCSV, $localPath);
            if ($pathToCSV) {
                $logger->log($companyId . ': file downloaded ' . $pathToCSV, Zend_Log::INFO);
            }
        }


        $sUnvStores = new Marktjagd_Service_Input_MarktjagdApi();
        $aUnvStores = $sUnvStores->findAllStoresForCompany($companyId);

        if (is_array($aUnvStores) && count($aUnvStores) == 0) {
            return false;
        }

        if (!$aUnvStores) {
            $logger->log('Fehler beim Erstellen der Standort-Collection für Unternehmen ' . $companyId, Zend_Log::ERR);
            return false;
        }

        $completelyUNV = array();
        foreach ($aUnvStores as $storyKey => $storeEntry) {
            foreach ($storeEntry as $storeEntryKey => $storeEntryVal) {
                if ($storeEntryKey == 'title') {
                    continue;
                }

                if ($storeEntryVal && strlen($storeEntryVal)) {
                    if (!array_key_exists($storeEntryKey, $completelyUNV)) {
                        $completelyUNV[$storeEntryKey] = 0;
                    }
                    $completelyUNV[$storeEntryKey]++;
                }
            }
        }

        $sCSVStores = new Marktjagd_Service_Input_MarktjagdCsv();
        $cCSVStores = $sCSVStores->convertToCollection($pathToCSV, 'stores');
        $cCSVStores = $cCSVStores->getElements();

        $mapProperties = array('storeNumber' => 'number',
            'streetNumber' => 'street_number',
            'phone' => 'phone_number',
            'fax' => 'fax_number',
            'storeHoursNotes' => 'store_hours_notes',
            'storeHours' => 'store_hours',
            'barrierFree' => 'barrier_free',
            'bonusCard' => 'bonus_card',
        );

        $completelyCSV = array();
        foreach ($cCSVStores as $csvStore) {
            $aProperties = $csvStore->getProperties();
            foreach ($aProperties as $propertyName => $propertyValue) {
                if (preg_match('#(logo|image|number|title)#', $propertyName)) {
                    continue;
                }

                if ($csvStore->$propertyName && strlen($csvStore->$propertyName)) {
                    $mappedPropName = $propertyName;
                    if (array_key_exists($propertyName, $mapProperties)) {
                        $mappedPropName = $mapProperties[$propertyName];
                    }

                    if (!array_key_exists($mappedPropName, $completelyCSV)) {
                        $completelyCSV[$mappedPropName] = 0;
                    }
                    $completelyCSV[$mappedPropName]++;
                }
            }

            if ($csvStore->getLogo() && strlen($csvStore->getLogo()) || $csvStore->getImage() && strlen($csvStore->getImage())) {
                if (!array_key_exists('has_images', $completelyCSV)) {
                    $completelyCSV['has_images'] = 0;
                }
                $completelyCSV['has_images']++;
            }
        }

        $completelyCSV['number'] = count($cCSVStores);

        return $this->getLostsByCumulatedData($completelyUNV, $completelyCSV);
    }

    /**
     * Vergleicht zwei kumulierte Standortdaten-Array
     *
     * @param $aOldStores
     * @param $aNewStores
     * @return $float value of differences
     */
    private function getLostsByCumulatedData($aOldStores, $aNewStores)
    {
        $skipKeys = array('number', 'datetime_modified', 'text');

        $aCompletionValues = array(
            'subtitle' => 0.01,
            'description' => 0.05,
            'store_hours' => 0.2,
            'logo' => 0.15,
            'phone' => 0.1,
            'website' => 0.07,
            'email' => 0.07,
            'fax' => 0.01,
            'payment' => 0.04,
            'bonus_card' => 0.04,
            'image' => 0.1,
            'section' => 0.04,
            'service' => 0.04,
            'parking' => 0.04,
            'toilet' => 0.02,
            'barrier_free' => 0.02
        );

        $lostValues = array();
        $lostValues['total_lost'] = 0;

        foreach ($aOldStores as $oldStoreKey => $oldStoreVal) {
            if (in_array($oldStoreKey, $skipKeys)) {
                continue;
            }

            $currentPercent = $aOldStores[$oldStoreKey] * $aCompletionValues[$oldStoreKey] / $aOldStores['number'];
            $newPercent = !array_key_exists($oldStoreKey, $aNewStores) ? 0 : $aNewStores[$oldStoreKey] * $aCompletionValues[$oldStoreKey] / $aNewStores['number'];

            if ($newPercent < $currentPercent) {
                $lostValues[$oldStoreKey] = $currentPercent - $newPercent;
                $lostValues['total_lost'] += $lostValues[$oldStoreKey];
            }
        }

        return $lostValues;
    }

}
