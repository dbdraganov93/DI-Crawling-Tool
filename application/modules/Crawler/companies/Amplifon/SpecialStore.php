<?php

/**
 * Storecrawler für Amplifon 68248
 */
class Crawler_Company_Amplifon_SpecialStore extends Crawler_Generic_Company
{
    private const STORE_NUMBER      = 0;
    private const STREET_AND_NUMBER = 2;
    private const PLZ               = 4;
    private const CITY              = 5;
    private const OPENING_1         = 6;
    private const OPENING_2         = 7;
    private const OPENING_3         = 8;
    private const OPENING_4         = 9;
    private const OPENING_5         = 10;
    private const OPENING_6         = 11;
    private const OPENING_7         = 12;
    private const OPENING_8         = 13;

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cStore = new Marktjagd_Collection_Api_Store();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $campaignName = 'OU Kampagne 11-2021';
        $website      = 'https://www.amplifon.com/de/filiale-finden';
        $title        = 'Amplifon: ';

        $this->_logger->info('Getting stores from API3...');
        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $localFolder = $sFtp->connect($companyId, true);

        foreach ($sFtp->listFiles() as $singleFile) {
            if(preg_match('#\.xlsx$#', $singleFile)) {
                $this->_logger->info('Downloading Excel file: ' . $singleFile);
                $assignmentExcelFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
            }
        }

        $sFtp->close();

        if(!isset($assignmentExcelFile)) {
            throw new Exception('The crawler is missing the .xlsx file on FTP and cant continue');
        }

        $aDataTab1 = $sExcel->readFile($assignmentExcelFile)->getElement(0)->getData();
        $compaingStoreNumbers = [];
        foreach ($aDataTab1 as $singleLineTab1) {
            if(!empty($singleLineTab1[0])) {
                $compaingStoreNumbers[$singleLineTab1[0]] = $singleLineTab1[0];
            }
        }

        $aDataTab2 = $sExcel->readFile($assignmentExcelFile)->getElement(1)->getData();
        $allStoresData = [];
        foreach ($aDataTab2 as $singleLineTab2) {
            if(!empty($singleLineTab2[0])) {
                $allStoresData[$singleLineTab2[0]] = $singleLineTab2;
            }
        }

        $missingStoresNumbers = [];
        $storesFound          = [];
        foreach ($compaingStoreNumbers as $key => $singleCompaingStore) {
            if($key == 'Filial Nr.') {
                continue;
            }

            if(array_key_exists($key, $cStores)) {
                $storesFound[$singleCompaingStore] = $singleCompaingStore;
                continue;
            }

            $missingStoresNumbers[] = $singleCompaingStore;
        }

        var_dump('-----STORES IN DB----');
        var_dump(count($storesFound));
        var_dump(implode(',', $storesFound));
        var_dump('-----STORES NOT IN DB----');
        var_dump(count($missingStoresNumbers));
        var_dump(implode(',', $missingStoresNumbers));

        $incorrectStores = [];
        foreach ($storesFound as $storeNumberFound) {
            $store = $allStoresData[$storeNumberFound];

            /** @var Marktjagd_Entity_Api_Store $dbStore */
            $dbStore = $cStores[$storeNumberFound];

            if($store[self::PLZ] != $dbStore->getZipcode()) {
                $incorrectStores[$storeNumberFound] = $storeNumberFound;
            } else {
                $dbStore
                    ->setStoreNumber($storeNumberFound)
                    ->setDistribution($campaignName)
                    ->setWebsite($website)
                    ->setTitle($title . $store[self::CITY])
                ;

                $cStore->addElement($dbStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($store[self::STORE_NUMBER])
                ->setStreetAndStreetNumber($store[self::STREET_AND_NUMBER])
                ->setCity($store[self::CITY])
                ->setZipcode($store[self::PLZ])
                ->setWebsite($website)
                ->setDistribution($campaignName)
                ->setTitle($title . $store[self::CITY])
            ;

            $cStore->addElement($eStore);
        }

        var_dump('-----STORES INCORRECT ONES----');
        var_dump(count($incorrectStores));
        var_dump(implode(',', $incorrectStores));

        foreach ($missingStoresNumbers as $missingStoreNumber) {
            $missingStoreData = $allStoresData[$missingStoreNumber];

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($missingStoreData[self::STORE_NUMBER])
                ->setStreetAndStreetNumber($missingStoreData[self::STREET_AND_NUMBER])
                ->setCity($missingStoreData[self::CITY])
                ->setZipcode($missingStoreData[self::PLZ])
                ->setWebsite($website)
                ->setDistribution($campaignName)
                ->setTitle($title . $missingStoreData[self::CITY])
            ;

            $cStore->addElement($eStore);
        }

        // add stores that does not exist but is not on the campaign
        foreach ($allStoresData as $key => $allStoreData) {
            if($key == 'Filial Nr.') {
                continue;
            }

            if(array_key_exists($key, $missingStoresNumbers)) {
                continue;
            }

            if(array_key_exists($key, $cStores) && $key == $cStores[$key]->getStoreNumber()) {
                $cStores[$key]
                    ->setStoreNumber($key)
                    ->setWebsite($website)
                ;

                $cStore->addElement($cStores[$key]);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($allStoreData[self::STORE_NUMBER])
                ->setStreetAndStreetNumber($allStoreData[self::STREET_AND_NUMBER])
                ->setCity($allStoreData[self::CITY])
                ->setZipcode($allStoreData[self::PLZ])
                ->setWebsite($website)
                ->setTitle($title . $allStoreData[self::CITY])
            ;

            $cStore->addElement($eStore);
        }

        return $this->getResponse($cStore, $companyId);
    }
}
