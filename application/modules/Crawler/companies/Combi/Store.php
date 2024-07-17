<?php

/**
 * Store Crawler für Combi (ID: 28832)
 */
class Crawler_Company_Combi_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect(29026, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Marktdaten\.csv#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localStoreFile, TRUE, ';')->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aData as $singleRow) {
            if (!preg_match('#^combi#i', $singleRow['Bezeichnung'])) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleRow['Markt'])
                ->setStreetAndStreetNumber($singleRow['Straße'])
                ->setZipcode($singleRow['PLZ'])
                ->setCity($singleRow['Ort'])
                ->setPhoneNormalized($singleRow['Tel'])
                ->setDistribution($singleRow['Mand']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}