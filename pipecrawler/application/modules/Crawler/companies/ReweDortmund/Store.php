<?php
/**
 * Store Crawler für REWE Dortmund (ID: 73661)
 */

class Crawler_Company_ReweDortmund_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^stores\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleRow['PLZ'])
                ->setStoreNumber($singleRow['KUNDEN_NR'])
                ->setTitle($singleRow['NAME1'])
                ->setStreet($singleRow['STR'])
                ->setStreetNumber($singleRow['HAUSNR'])
                ->setCity($singleRow['ORT']);

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores);
    }
}