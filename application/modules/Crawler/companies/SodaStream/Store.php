<?php

/**
 * Store Crawler for Sodastream (ID: 81357)
 */

class Crawler_Company_SodaStream_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }

        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $aAddress = preg_split('#\s*,\s*#', $singleRow['address']);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($singleRow['name'])
                ->setStreetAndStreetNumber($aAddress[0])
                ->setZipcodeAndCity($aAddress[1]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);

    }
}