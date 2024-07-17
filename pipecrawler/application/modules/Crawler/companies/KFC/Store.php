<?php

/**
 * Standortcrawler für KFC (ID: 29027)
 */
class Crawler_Company_KFC_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $localPath = $sFtp->connect($companyId);

        $arrB = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^220510_Storeübersicht_Corona\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $arrB = $localStoreFile;
                break;
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($arrB, TRUE)->getElement(0)->getData();

        foreach ($aData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStore["Store Id"])
                ->setTitle($singleStore["Store Name"])
                ->setCity($singleStore["City"])
                ->setZipcode($singleStore["Postal Code"])
                ->setStreet($singleStore["Address"])
                ->setStoreHours($singleStore["Öffnungszeiten"]);

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores, $companyId);
    }
}
