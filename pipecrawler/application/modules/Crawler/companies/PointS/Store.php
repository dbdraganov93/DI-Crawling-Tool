<?php

/**
 * Storecrawler für Point S (ID: 28672)
 */
class Crawler_Company_PointS_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
       $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
       $sExcel = new Marktjagd_Service_Input_PhpExcel();
       
       $sFtp->connect($companyId);
       $localPath = $sFtp->generateLocalDownloadFolder($companyId);
       
       $pattern = '#Standorte\.xls#';
       foreach ($sFtp->listFiles() as $singleFile) {
           if (preg_match($pattern, $singleFile)) {
               $localStoreFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
               break;
           }
       }
       
       $aData = $sExcel->readFile($localStoreFile, TRUE)->getElement(0)->getData();
       
       $cStores = new Marktjagd_Collection_Api_Store();
       foreach ($aData as $singleColumn) {
           $eStore = new Marktjagd_Entity_Api_Store();
                   
           $eStore->setDistribution($singleColumn['Region'])
                   ->setStoreNumber($singleColumn['M_KdNr'])
                   ->setTitle($singleColumn['Name_1'])
                   ->setStreetAndStreetNumber($singleColumn['Straße'])
                   ->setZipcode($singleColumn['PLZ'])
                   ->setCity($singleColumn['Ort'])
                   ->setEmail($singleColumn['Email'])
                   ->setPhoneNormalized($singleColumn['Telefon'])
                   ->setFaxNormalized($singleColumn['Fax']);
           
           $cStores->addElement($eStore);
       }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
