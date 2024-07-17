<?php

/**
 * Store Crawler for Alliance Healthcare (ID: 89979)
 */
class Crawler_Company_Alliance_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.csv#', $singleRemoteFile)) {
                $storeFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($storeFile, TRUE, ';')->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $storeNumber = pathinfo($singleRow['Logodatei'], PATHINFO_FILENAME);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($storeNumber)
                ->setTitle($singleRow['Apothekenbezeichnung'])
                ->setStreetAndStreetNumber($singleRow['Straße'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setWebsite($singleRow['URL']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId, 2, FALSE);
    }
}
