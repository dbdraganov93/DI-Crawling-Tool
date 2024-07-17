<?php

/*
 * Store Crawler für Fielmann (ID: 22387)
 */

class Crawler_Company_Fielmann_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect('22387', TRUE);
        $sFtp->changedir('test');

        $newarr = [];
        foreach ($sFtp->listFiles() as $main) {
            if (preg_match('#^Datalist\.xlsx?$#', $main)) {
                $localStoreFile = $sFtp->downloadFtpToDir($main, $localPath);
                $newarr = $localStoreFile;
                break;
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($newarr, TRUE)->getElement(0)->getData();

        $storeDistributions = [];
        foreach ($aData as $singleStore) {


//          var_dump($singleStore["Folder"]);die();

            if ($singleStore['Folder'] == '19/69/19') {
                $this->_logger->info("Found Region: {$singleStore['Folder']}");
                $storeDistributions = $singleStore['Folder'] = "REGION A";

            }
            if ($singleStore['Folder'] == '16/48/16') {
                $this->_logger->err($companyId . ': Found store: ' . $singleStore["Folder"]);
                $storeDistributions = $singleStore['Folder'] = "REGION B";

            }

//            var_dump($storeDistributions);
//            die();

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore["NDL"])
                ->setText($singleStore["Firmierung"])
                ->setStreet($singleStore["Straße"])
                ->setDistribution($storeDistributions)
                ->setCity($singleStore['Ort'])
                ->setZipcode($singleStore['PLZ']);

            $cStores->addElement($eStore);


        }
        return $this->getResponse($cStores, $companyId);
    }

}
