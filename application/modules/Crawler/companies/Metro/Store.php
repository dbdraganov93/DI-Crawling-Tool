<?php

/*
 * Store Crawler für Metro (ID: 69631)
 */

class Crawler_Company_Metro_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx$#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $aAddress = preg_split('#\s*,\s*#', $singleRow['Address']);
            $aAddress[1] = preg_replace('#^(\d{4})\s+#', '0$1 ', $aAddress[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setAddress($aAddress[0], $aAddress[1])
                ->setTitle($singleRow['Store name']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
