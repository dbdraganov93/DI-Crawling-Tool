<?php

/**
 * Store Crawler für CENTERSHOP (ID: 69971)
 */
class Crawler_Company_CenterShop_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $cStores   = new Marktjagd_Collection_Api_Store();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles() as $file) {
            if (!preg_match('#CENTERSHOP_Filialen_2022#', $file)) {
                continue;
            }

            $xlsRefFile = $sFtp->downloadFtpToDir($file, $localPath);
        }

        if(!isset($xlsRefFile)) {
            throw new Exception('No .xls file found in our FTP!');
        }

        $storesDataArray = $sExcel->readFile($xlsRefFile, true)->getElement(0)->getData();

        foreach ($storesDataArray as $storeData) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $openingHours = [
                'Mo ' . $storeData['Montag'],
                'Di ' . $storeData['Dienstag'],
                'Mi ' . $storeData['Mittwoch'] ,
                'Do ' . $storeData['Donnerstag'] ,
                'Fr ' . $storeData['Freitag'] ,
                'Sa ' . $storeData['Samstag']
            ];

            $eStore->setTitle($storeData['Filiale'])
                ->setStreetAndStreetNumber($storeData['Adresse'])
                ->setZipcode($storeData['PLZ'])
                ->setCity($storeData['Stadt'])
                ->setStoreNumber($storeData['Kürzel'])
                ->setStoreHoursNormalized(implode(', ', $openingHours))
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
