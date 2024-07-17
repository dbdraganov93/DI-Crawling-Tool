<?php

/**
 * Store Crawler für Käse Umfrage (ID: 82321)
 */
class Crawler_Company_KaeseUmfrage_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)

    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $localPath = $sFtp->connect($companyId);

        $arrB = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#^check_distrib_list\.xls?$#', $singleFile)) {
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


            $eStore->setStoreNumber($singleStore["Kundennummer"])
                ->setTitle($singleStore["Markt"])
                ->setCity($singleStore["Ort"])
                ->setZipcode($singleStore["PLZ"])
                ->setLatitude($singleStore["Breitengrad"])
                ->setLongitude($singleStore["Längengrad"])
                ->setStreet($singleStore["Straße"])
                //change the distribution name if last column of data feed is renamed by customer
                ->setDistribution($singleStore["MARKTEINTEILUNG"]);


                 $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores, $companyId);
    }

}