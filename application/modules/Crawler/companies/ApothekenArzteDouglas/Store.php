<?php

/**
 * Storecrawler für Apotheken_Arzte_Douglas (ID: 82345)
 *
 * Class Crawler_Company_Apotheken_Arzte_Douglas_Store
 */
class Crawler_Company_ApothekenArzteDouglas_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '6G');
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect('82345', TRUE);
        $sFtp->changedir('test_data');


        $newarr = [];
        foreach ($sFtp->listFiles() as $main) {
            if (preg_match('#^datalist3\.csv?$#', $main)) {
                $localStoreFile = $sFtp->downloadFtpToDir($main, $localPath);
                $newarr = $localStoreFile;
                break;
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($newarr, TRUE,)->getElement(0,)->getData();

        $storeDistributions = [];

        foreach ($aData as $singleStore) {
            if (empty($singleStore['Channel']) && empty($singleStore['Adresse'])) {

                continue;
            }

            // Doble entries to their feed file.
            // This checks if all data is reading correctly from their data.
            $storeDistributions[] = [

                'streeName' => $singleStore['Adresse'],
                'distribution' => $singleStore['Channel'],
                'city' => $singleStore['Stadt'],
                'PLZ' => $singleStore['PLZ']
            ];

        }

        foreach ($storeDistributions as $storedata) {

            $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setTitle($storedata['distribution'])
                    ->setStreetAndStreetNumber($storedata['streeName'])
                    ->setDistribution($storedata['distribution'])
                    ->setCity($storedata['city'])
                    ->setZipcode($storedata['PLZ']);

                $cStores->addElement($eStore);

        }

        return$this->getResponse($cStores,  $companyId,2,false);
    }
}
