<?php

/* 
 * Store Crawler für Reformhaus (ID: 28609)
 */

class Crawler_Company_Reformhaus_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.reformhaus.de/';
        $searchUrl = $baseUrl . 'nc/in-ihrer-naehe/?type=333&tx_storelocator_brand%5Bcontroller%5D=Brand&tx_storelocator_brand%5Baction%5D=ajaxout';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();
        
        $aZipcodes = $sDbGeoRegion->findZipCodesByNetSize(5);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aParams = array(
            'tx_storelocator_brand[storagePidList]' => '70');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZip) {
            $aParams['tx_storelocator_brand[search]'] = $singleZip;
            
            $sPage->open($searchUrl, $aParams);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (!count($jStores->results[0]->dealer)) {
                continue;
            }
            foreach ($jStores->results[0]->dealer as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setStreetAndStreetNumber($singleJStore->street)
                        ->setCity($singleJStore->city)
                        ->setZipcode($singleJStore->zip)
                        ->setPhoneNormalized($singleJStore->phone)
                        ->setWebsite($singleJStore->url)
                        ->setLatitude($singleJStore->lat)
                        ->setLongitude($singleJStore->long);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}