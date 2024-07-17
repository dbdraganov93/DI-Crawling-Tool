<?php

/**
 * Store Crawler für Marquardt Küchen (ID: 29110)
 */
class Crawler_Company_MarquardtKuechen_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $storeData = $sPss->readFile($localStoreFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleStore['PLZ'])
                ->setCity($singleStore['Ort'])
                ->setStreet($singleStore['Straße'])
                ->setStreetNumber($singleStore['Hausnr.'])
                ->setStoreHoursNormalized(preg_replace(['#\s*,\s*#', '#\n#'], [' ', ','], $singleStore['Öffnungszeiten Werktage'] . ' ' . $singleStore['Öffnungszeiten Wochenende']))
                ->setDistribution('Werkstudios');

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}