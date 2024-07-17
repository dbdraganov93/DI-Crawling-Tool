<?php

/**
 * Store Crawler für Volkswagen (ID: 72424)
 */
class Crawler_Company_Volkswagen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $aData = $sPhpSpreadsheet->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleColumn) {


            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleColumn['Händlername'])
                ->setTitle($singleColumn['Händler'])
                ->setZipcodeAndCity($singleColumn['PLZ/ORT'])
                ->setStreetAndStreetNumber($singleColumn['Straße'])
               ->setWebsite($singleColumn['LP/URL']);
//                ->setDefaultRadius((int)(preg_replace('#Radius:\s*(\d+)\s*m#', '$1', $singleColumn['Display gebuchte PLZ-Gebiete']) / 1000));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores,  $companyId, 2, false);
    }

}