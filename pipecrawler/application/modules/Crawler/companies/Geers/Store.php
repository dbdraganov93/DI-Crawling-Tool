<?php

/**
 * Standortcrawler für Geers (ID: 29121)
 */
class Crawler_Company_Geers_Store extends Crawler_Generic_Company
{
    private const ID      = 1;
    private const NAME    = 2;
    private const GROUP   = 7;
    private const ADDRESS = 11;
    private const ZIP     = 13;
    private const CITY    = 14;
    // Opening hours
    private const MON_OPEN   = 19;
    private const MON_CLOSE  = 22;
    private const TUE_OPEN   = 23;
    private const TUE_CLOSE  = 26;
    private const WED_OPEN   = 27;
    private const WED_CLOSE  = 30;
    private const THUR_OPEN  = 31;
    private const THUR_CLOSE = 34;
    private const FRI_OPEN   = 35;
    private const FRI_CLOSE  = 38;
    private const SAT_OPEN   = 39;
    private const SAT_CLOSE  = 42;

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp    = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss    = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#md_2021-03-16-HTT-APR-2021\.xlsx#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }

            if (preg_match('#FG-Liste_HTT_Februar\.xlsx#', $singleFile)) {
                $fgListStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $storeData = $sPss->readFile($localStoreFile)->getElement(0)->getData();
        $rawAdditionalStoreData = $sPss->readFile($fgListStoreFile, true)->getElement(0)->getData();

        unset($storeData[0]); // Unset first naming line
        $additionalStoreData = $this->changeArrayKeysToStoreId($rawAdditionalStoreData);

        foreach ($storeData as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($store[self::ID])
                ->setDistribution($store[self::GROUP])
                ->setTitle($store[self::NAME])
                ->setStreetAndStreetNumber($store[self::ADDRESS])
                ->setZipcode($store[self::ZIP])
                ->setCity($store[self::CITY])
                ->setStoreHours($this->generateOpeningHoursString($store))
                ->setPhoneNormalized($additionalStoreData[$store[self::ID]]['Telefon'])
                ->setEmail($additionalStoreData[$store[self::ID]]['E-Mail'])
                ->setFaxNormalized($additionalStoreData[$store[self::ID]]['Fax'])
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function changeArrayKeysToStoreId($storesList)
    {
        $result = [];
        foreach ($storesList as $store) {
            $result[$store['FG-ID']] = $store;
        }

        return $result;
    }

    private function generateOpeningHoursString($store)
    {
        $openingHoursCollection = [];

        if ($store[self::MON_OPEN] != null && $store[self::MON_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Mo %s - %s', $store[self::MON_OPEN], $store[self::MON_CLOSE]);
        }

        if ($store[self::TUE_OPEN] != null && $store[self::TUE_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Di %s - %s', $store[self::TUE_OPEN], $store[self::TUE_CLOSE]);
        }

        if ($store[self::WED_OPEN] != null && $store[self::WED_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Mi %s - %s', $store[self::WED_OPEN], $store[self::WED_CLOSE]);
        }

        if ($store[self::THUR_OPEN] != null && $store[self::THUR_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Do %s - %s', $store[self::THUR_OPEN], $store[self::THUR_CLOSE]);
        }

        if ($store[self::FRI_OPEN] != null && $store[self::FRI_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Fr %s - %s', $store[self::FRI_OPEN], $store[self::FRI_CLOSE]);
        }

        if ($store[self::SAT_OPEN] != null && $store[self::SAT_CLOSE] != null) {
            $openingHoursCollection[] = sprintf('Sa %s - %s', $store[self::SAT_OPEN], $store[self::SAT_CLOSE]);
        }

        return implode(', ', $openingHoursCollection);
    }
}
