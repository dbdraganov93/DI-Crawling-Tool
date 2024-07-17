<?php

/**
 * Storecrawler für Ernstings Family (ID: 22133)
 */
class Crawler_Company_Ernstings_Store extends Crawler_Generic_Company
{
    private const STORE_NUMBER = 1;
    private const STORE_PLZ    = 2;
    private const STORE_CITY   = 3;
    private const STORE_STREET = 4;
    private const STORE_LAND   = 5;

    private const MON_OPEN   = 4;
    private const MON_CLOSE  = 5;
    private const TUE_OPEN   = 8;
    private const TUE_CLOSE  = 9;
    private const WED_OPEN   = 12;
    private const WED_CLOSE  = 13;
    private const THU_OPEN   = 16;
    private const THU_CLOSE  = 17;
    private const FRI_OPEN   = 20;
    private const FRI_CLOSE  = 21;
    private const SAT_OPEN   = 24;
    private const SAT_CLOSE  = 25;
    private const SUN_OPEN   = 28;
    private const SUN_CLOSE  = 29;

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cStoresNew = new Marktjagd_Collection_Api_Store();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $listFile) {
            if (preg_match('#filialen.csv#', $listFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($listFile, $localPath);
                continue;
            }

            if (preg_match('#oeffnung.csv#', $listFile)) {
                $localStoreOpeningFile = $sFtp->downloadFtpToDir($listFile, $localPath);
            }
        }

        $storeData = $sPss->readFile($localStoreFile)->getElement(0)->getData();
        $openingData = $sPss->readFile($localStoreOpeningFile)->getElement(0)->getData();

        foreach ($storeData as $singleStore) {
            if (!preg_match('#DE#', $singleStore[self::STORE_LAND])) {
                continue;
            }

            foreach($openingData as $openingHours) {
                if($openingHours[self::STORE_NUMBER] !== $singleStore[self::STORE_NUMBER]) {
                    continue;
                }

                $readyOpeningHours = $this->normalizeStoreHours($openingHours);
            }

            if(empty($readyOpeningHours)) {
                $readyStoreHours = '';
            } else {
                $readyStoreHours = str_replace('.', ':', $readyOpeningHours);
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore[self::STORE_NUMBER])
                ->setStreetAndStreetNumber($singleStore[self::STORE_STREET])
                ->setZipcode($singleStore[self::STORE_PLZ])
                ->setCity($singleStore[self::STORE_CITY])
                ->setStoreHoursNormalized($readyStoreHours)
            ;

            $cStoresNew->addElement($eStore);
        }

        return $this->getResponse($cStoresNew, $companyId);
    }

    private function normalizeStoreHours(array $rawStoreHours) : string
    {
        $readyOpeningHours = [];

        $rawStoreHours[self::MON_OPEN] = $this->formatHour($rawStoreHours[self::MON_OPEN]);
        $rawStoreHours[self::MON_CLOSE] = $this->formatHour($rawStoreHours[self::MON_CLOSE]);
        if($rawStoreHours[self::MON_OPEN + 2] !== 0 && $rawStoreHours[self::MON_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Mo ' . $rawStoreHours[self::MON_OPEN] . '-' . $rawStoreHours[self::MON_CLOSE + 2];
        } elseif($rawStoreHours[self::MON_OPEN] !== 0) {
            $readyOpeningHours[] = 'Mo ' . $rawStoreHours[self::MON_OPEN] . '-' . $rawStoreHours[self::MON_CLOSE];
        }

        $rawStoreHours[self::TUE_OPEN] = $this->formatHour($rawStoreHours[self::TUE_OPEN]);
        $rawStoreHours[self::TUE_CLOSE] = $this->formatHour($rawStoreHours[self::TUE_CLOSE]);
        if($rawStoreHours[self::TUE_OPEN + 2] !== 0 && $rawStoreHours[self::TUE_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Di ' . $rawStoreHours[self::TUE_OPEN] . '-' . $rawStoreHours[self::TUE_CLOSE + 2];
        } elseif($rawStoreHours[self::TUE_OPEN] !== 0) {
            $readyOpeningHours[] = 'Di ' . $rawStoreHours[self::TUE_OPEN] . '-' . $rawStoreHours[self::TUE_CLOSE];
        }

        $rawStoreHours[self::WED_OPEN] = $this->formatHour($rawStoreHours[self::WED_OPEN]);
        $rawStoreHours[self::WED_CLOSE] = $this->formatHour($rawStoreHours[self::WED_CLOSE]);
        if($rawStoreHours[self::WED_OPEN + 2] !== 0 && $rawStoreHours[self::WED_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Mi ' . $rawStoreHours[self::WED_OPEN] . '-' . $rawStoreHours[self::WED_CLOSE + 2];
        } elseif($rawStoreHours[self::WED_OPEN] !== 0) {
            $readyOpeningHours[] = 'Mi ' . $rawStoreHours[self::WED_OPEN] . '-' . $rawStoreHours[self::WED_CLOSE];
        }

        $rawStoreHours[self::THU_OPEN] = $this->formatHour($rawStoreHours[self::THU_OPEN]);
        $rawStoreHours[self::THU_CLOSE] = $this->formatHour($rawStoreHours[self::THU_CLOSE]);
        if($rawStoreHours[self::THU_OPEN + 2] !== 0 && $rawStoreHours[self::THU_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Do ' . $rawStoreHours[self::THU_OPEN] . '-' . $rawStoreHours[self::THU_CLOSE + 2];
        } elseif($rawStoreHours[self::THU_OPEN] !== 0) {
            $readyOpeningHours[] = 'Do ' . $rawStoreHours[self::THU_OPEN] . '-' . $rawStoreHours[self::THU_CLOSE];
        }

        $rawStoreHours[self::FRI_OPEN] = $this->formatHour($rawStoreHours[self::FRI_OPEN]);
        $rawStoreHours[self::FRI_CLOSE] = $this->formatHour($rawStoreHours[self::FRI_CLOSE]);
        if($rawStoreHours[self::FRI_OPEN + 2] !== 0 && $rawStoreHours[self::FRI_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Fr ' . $rawStoreHours[self::FRI_OPEN] . '-' . $rawStoreHours[self::FRI_CLOSE + 2];
        } elseif($rawStoreHours[self::FRI_OPEN] !== 0) {
            $readyOpeningHours[] = 'Fr ' . $rawStoreHours[self::FRI_OPEN] . '-' . $rawStoreHours[self::FRI_CLOSE];
        }

        $rawStoreHours[self::SAT_OPEN] = $this->formatHour($rawStoreHours[self::SAT_OPEN]);
        $rawStoreHours[self::SAT_CLOSE] = $this->formatHour($rawStoreHours[self::SAT_CLOSE]);
        if($rawStoreHours[self::SAT_OPEN + 2] !== 0 && $rawStoreHours[self::SAT_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'Sa ' . $rawStoreHours[self::SAT_OPEN] . '-' . $rawStoreHours[self::SAT_CLOSE + 2];
        } elseif($rawStoreHours[self::SAT_OPEN] !== 0) {
            $readyOpeningHours[] = 'Sa ' . $rawStoreHours[self::SAT_OPEN] . '-' . $rawStoreHours[self::SAT_CLOSE];
        }

        $rawStoreHours[self::SUN_OPEN] = $this->formatHour($rawStoreHours[self::SUN_OPEN]);
        $rawStoreHours[self::SUN_CLOSE] = $this->formatHour($rawStoreHours[self::SUN_CLOSE]);
        if($rawStoreHours[self::SUN_OPEN] !== 0 && $rawStoreHours[self::SUN_OPEN + 2] !== 0.0){
            $readyOpeningHours[] = 'So ' . $rawStoreHours[self::SUN_OPEN] . '-' . $rawStoreHours[self::SUN_CLOSE];
        }

        return implode(',', $readyOpeningHours);
    }

    private function formatHour($rawHour)
    {
        if(preg_match('#\.#', $rawHour)){
            return  $rawHour . '0';
        }

        return $rawHour;
    }
}
