<?php

/**
 * Store Crawler für Douglas (ID: 326)
 */

class Crawler_Company_Douglas_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Filialliste\.xlsx#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aData as $singleRow) {
            if (!strlen(trim($singleRow['Adresse']))) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber(trim($singleRow['VST']))
                ->setStreetAndStreetNumber($singleRow['Adresse'] . ' ' . $singleRow['H-Nr.'])
                ->setZipcodeAndCity($singleRow['PLZ'] . ' ' . $singleRow['City']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}