<?php

/**
 * Store crawler for Lorenz Bahlsen (ID: 82583)
 */

class Crawler_Company_LorenzBahlsen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx?$#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, FALSE)->getElement(3)->getData();
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!$singleRow[1]) {
                $salesRegion = $singleRow[6];
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($singleRow[1])
                ->setCity($singleRow[2])
                ->setStreetAndStreetNumber($singleRow[3])
                ->setZipcode($singleRow[4])
                ->setDistribution($salesRegion);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, 82583, 2, FALSE);
    }
}
