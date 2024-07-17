<?php

/**
 * Store Crawler für Violife (ID: 82319)
 */

class Crawler_Company_Violife_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();


        $sFtp->connect($companyId);

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $arrB = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^Marktliste_Offerista_B-Maerkte only_220427\.xls$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $arrB = $localStoreFile;
                break;
            }
        }
        $sFtp->close();
        $aData = $sPss->readFile($arrB, TRUE)->getElement(0)->getData();


        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleColumn) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleColumn["Marktnummer"])
                ->setTitle($singleColumn["Markt"])
                ->setZipcodeAndCity($singleColumn["PLZ"])
                ->setStreetAndStreetNumber($singleColumn["Straße"])
                ->setCity($singleColumn["Ort"])
                ->setLatitude($singleColumn["Lat"])
                ->setLongitude($singleColumn["Long"]);



            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores,  $companyId, 2, false);
    }
}