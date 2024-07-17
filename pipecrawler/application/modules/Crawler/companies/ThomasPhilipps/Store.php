<?php

/*
 * Store Crawler für Thomas Philipps (ID: 352)
 */

class Crawler_Company_ThomasPhilipps_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#marktliste#i', $singleRemoteFile)) {
                $localFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPss->readFile($localFile, TRUE)->getElement(0)->getData() as $singleRow) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setStreetAndStreetNumber($singleRow['Anschrift'])
                ->setStoreNumber($singleRow['Markt Nr.']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}