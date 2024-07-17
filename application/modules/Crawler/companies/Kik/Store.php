<?php

/**
 * Store Crawler für KiK (ID: 340 ,82329, 82328)
 */
class Crawler_Company_Kik_Store extends Crawler_Generic_Company
{
    protected string $country = '';
    protected const DEFAULT_COUNTRY = 'DE';

    public function crawl($companyId)
    {
        $countryCodes = [
            340 => self::DEFAULT_COUNTRY,
            82329 => 'SLO',
            82328 => 'SLK',
            73747 => 'AT'
        ];

        $this->country = $countryCodes[$companyId] ?: self::DEFAULT_COUNTRY;
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect(340, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#stores\.xlsx?$#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#turkish\.xls$#', $singleRemoteFile)) {
                $turkishFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#ukrainian\.xls$#', $singleRemoteFile)) {
                $ukrainianFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();
        $aDataTurkish = $sPss->readFile($turkishFile, TRUE)->getElement(0)->getData();
        $aDataUkrainian = $sPss->readFile($ukrainianFile, TRUE)->getElement(0)->getData();

        $aZipcodes = [];
        foreach ($aDataTurkish as $singleRow) {
            if (array_key_exists($singleRow['PLZ5_2022'], $aZipcodes)) {
                continue;
            }
            if ($singleRow['Tük'] >= 8) {
                $aZipcodes[$singleRow['PLZ5_2022']][] = 'TR';
            }
        }
        foreach ($aDataUkrainian as $singleRow) {
            if (array_key_exists($singleRow['PLZ5_2022'], $aZipcodes)) {
                continue;
            }
            if ($singleRow['Anteil ukrainischer Einwohner in Prozent'] >= 8) {
                $aZipcodes[$singleRow['PLZ5_2022']][] = 'UKR';
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match('#' . $this->country . '#', $singleRow['Land'])) {
                continue;
            }
            $strTimes = '';
            foreach ($singleRow as $key => $value) {
                if (!preg_match('#^(\S+?)\s+(von|bis)$#', $key, $dayMatch) || !$value) {
                    continue;
                }
                if (preg_match('#von#', $dayMatch[2])) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $dayMatch[1] . ' ' . $value;
                } elseif (preg_match('#bis#', $dayMatch[2])) {
                    $strTimes .= '-' . $value;
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setStreetAndStreetNumber($singleRow['Straße'])
                ->setStoreNumber($singleRow['Filialnummer'])
                ->setLongitude($singleRow['Longitude'])
                ->setLatitude($singleRow['Latitude'])
                ->setStoreHoursNormalized($strTimes);

            if (array_key_exists($singleRow['PLZ'], $aZipcodes)) {
                $eStore->setDistribution(implode(',', $aZipcodes[$singleRow['PLZ']]));
            }

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores);
    }
}
