<?php

/**
 * Store Crawler für Spiele Max (ID: 335)
 */
class Crawler_Company_SpieleMax_Store extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $localPath = $sFtp->connect($companyId);

        $arrB = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^company_335_stores\.xls?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $arrB = $localStoreFile;
                break;
            }
        }
        $sFtp->close();

        $aData = $sPss->readFile($arrB, TRUE)->getElement(0)->getData();
//        var_dump($aData);die();
// find store distributions

        foreach ($aData as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
//           var_dump($singleStore);die();

            $eStore->setStoreNumber($singleStore["store_number"])
                ->setTitle('SPIEL MAX')
                ->setCity($singleStore["city"])
                ->setZipcode($singleStore["zipcode"])
                ->setStreet($singleStore["street"])
                ->setStreetNumber($singleStore['street_number'])
                //change the distribution name if last column of data feed is renamed by customer
                ->setStoreHours($singleStore['store_hours'])
                ->setImage($singleStore['image'])
                ->setWebsite($singleStore['website']);

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores,  $companyId, 2,false);
    }
}
