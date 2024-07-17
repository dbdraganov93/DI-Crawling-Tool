<?php

/* 
 * Store Crawler für Biba (ID: 29064)
 */

class Crawler_Company_Biba_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.biba.de/';
        $searchUrl = $baseUrl . 'modules/sm/sm_stores/xhr/storelist.php';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#\d{5}#', $singleJStore->zip)) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setStoreHoursNormalized($singleJStore->businessHours);

            if (!strlen($eStore->getLatitude())
                    || !strlen($eStore->getLongitude())) {
                continue;
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}