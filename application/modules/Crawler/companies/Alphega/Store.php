<?php
/**
 * Store Crawler für Alphage (ID: 89979)
 */

class Crawler_Company_Alphega_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#Kundenselektion_Offerista\s*Kampagne_Stand\s*Juni\s*2024\.xls#', $singleRemoteFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['Kunden ID'])
                ->setTitle($singleRow['Apothekenbezeichnung'])
                ->setStreetAndStreetNumber($singleRow['Str.'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setDistribution('Push Campaigns July 2024');

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
