<?php

/**
 * Store Crawler für Penny (ID: 122)
 */
class Crawler_Company_Penny_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Marktliste#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['WAWI_MA_NR'])
                ->setLongitude($singleRow['lon'])
                ->setLatitude($singleRow['lat'])
                ->setStreetAndStreetNumber($singleRow['MA_STR'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['MA_ORT'])
                ->setStoreHoursNormalized($singleRow['Öffnungszeiten'])
                ->setDistribution($singleRow['Regions.WK'] . '-' . preg_replace('#[^\d]+#', '', $singleRow['WKR_40']));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}