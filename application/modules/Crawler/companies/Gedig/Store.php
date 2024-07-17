<?php

/**
 * Store Crawler für Gedig (ID: 68883)
 */
class Crawler_Company_Gedig_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $cStore = new Marktjagd_Collection_Api_Store();
        
        $sFtp->connect($companyId);
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        $localFileNameStores = $sFtp->downloadFtpToDir('stores.xls', $localDirectory);

        $aStores = $sExcel->readFile($localFileNameStores, true);
        $aStores = $aStores->getElements();       
                    
        foreach ($aStores[0]->getData() as $singleElement) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setTitle($singleElement['TITLE'])
                    ->setSubtitle('GEDIG-Fachgroßhändler')
                    ->setStreetAndStreetNumber($singleElement['strasse'])
                    ->setZipcode($singleElement['plz'])
                    ->setCity($singleElement['stadt'])
                    ->setPhone($singleElement['telefon'])
                    ->setFax($singleElement['fax'])
                    ->setEmail($singleElement['email'])
                    ->setWebsite($singleElement['web'])
                    ->setStoreHoursNormalized($singleElement['oeffnungszeiten'])
                    ->setService(preg_replace('#[\[\]]#', '', $singleElement['service']));
                        
            $cStore->addElement($eStore, true);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }
}