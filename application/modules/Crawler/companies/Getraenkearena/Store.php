<?php

/**
 * Store Crawler für Getränkearena (ID: 71699)
 */
class Crawler_Company_Getraenkearena_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.getraenkearena.de';
        $searchUrl = $baseUrl . '/wp-admin/admin-ajax.php?action=store_search&lat=51.455643&lng=7.011555000000044&max_results=5&radius=10&autoload=1';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singlejStore) {            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singlejStore->id)
                    ->setStreet($sAddress->extractAddressPart('street', $singlejStore->address))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $singlejStore->address))
                    ->setCity($singlejStore->city)
                    ->setZipcode($singlejStore->zip)
                    ->setLatitude($singlejStore->lat)
                    ->setLongitude($singlejStore->lng)
                    ->setText($singlejStore->description)
                    ->setPhone($singlejStore->phone)
                    ->setFax($singlejStore->fax)
                    ->setEmail($singlejStore->email)
                    ->setStoreHours($sTimes->generateMjOpenings($singlejStore->hours));                                
            
            if ($eStore->getStoreNumber() == 35){
                $eStore->setWebsite('http://www.getraenke-arena-krefeld.de');
            }
            
            $cStores->addElement($eStore);
        } 
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}