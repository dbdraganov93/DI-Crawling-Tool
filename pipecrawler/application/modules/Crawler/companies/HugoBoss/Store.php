<?php

/**
 * Store Crawler für Hugo Boss (ID: 67692)
 */
class Crawler_Company_HugoBoss_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://production-web-hugo.demandware.net';
        $searchUrl = $baseUrl . '/s/DE/dw/shop/v15_2/stores'
                . '?client_id=871c988f-3549-4d76-b200-8e33df5b45ba'
                . '&latitude=50.985190169443456'
                . '&longitude=11.015335499999992'
                . '&count=200'
                . '&max_distance=1000'
                . '&distance_unit=km'
                . '&start=0';
        
        $hoursMap = array (1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa');
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $jsonStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jsonStores->data as $singleJStore) {
            if ($singleJStore->country_code != 'DE') {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $singleJStore->address1))
                    ->setStoreNumber($sAddress->extractAddressPart('street_number', $singleJStore->address1))
                    ->setCity($singleJStore->city)
                    ->setStoreNumber($singleJStore->id)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setSubtitle($singleJStore->name)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                    ->setZipcode($singleJStore->postal_code)
                    ->setEmail($singleJStore->c_contactEmail)
                    ->setImage($singleJStore->c_storeImage)
                    ->setSection(implode(', ', $singleJStore->c_categories));
            
            $jsonHours = json_decode($singleJStore->store_hours);
            
            $hourAr = array();
            foreach ($hoursMap as $idx => $dayMap){
                if ($jsonHours->$idx){
                    $tempAr = $jsonHours->$idx;
                    
                    $hourAr[] = $dayMap . ' ' . $tempAr[0] . '-' . $tempAr[1];
                }
            }  
            $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $hourAr)));
   
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}