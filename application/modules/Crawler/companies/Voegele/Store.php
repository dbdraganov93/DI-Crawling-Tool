<?php

/**
 * Store Crawler für Voegele (ID: 356)
 */
class Crawler_Company_Voegele_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://de.charles-voegele.ch';
        $searchUrl = $baseUrl . '/foomo/modules/CharlesVoegele.Storefinder/services/storefinder.php/Foomo.Services.RPC/serve/find/';
        $detailUrl = $baseUrl . '/foomo/modules/CharlesVoegele.Storefinder/services/storefinder.php/Foomo.Services.RPC/serve/getStore/[STOREID]/de';
                
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();                
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->value as $singlejStore) {            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if ($singlejStore->address->country != 'Germany'){
                continue;
            }
            
            $eStore->setStoreNumber($singlejStore->id)
                    ->setStreet($sAddress->extractAddressPart('street', $singlejStore->address->street))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $singlejStore->address->street))
                    ->setCity($singlejStore->address->city)
                    ->setZipcode($singlejStore->address->zip)
                    ->setLatitude((string) $singlejStore->geoLocation->latitude)
                    ->setLongitude((string) $singlejStore->geoLocation->longitude)                    
                    ->setPhone($sAddress->normalizePhoneNumber($singlejStore->address->phone))
                    ->setFax($sAddress->normalizePhoneNumber($singlejStore->address->fax));                    
            
            if (strlen($singlejStore->address->streetNumber)){
                $eStore->setStreetNumber($singlejStore->address->streetNumber);
            }
            
            $sPage->open(str_replace('[STOREID]', $singlejStore->id, $detailUrl));
            $jStore = $sPage->getPage()->getResponseAsJson();  

            $storeHours = array();
            foreach ($jStore->value->openingTimes as $openingTime){
                foreach ($openingTime->periods as $period){
                    $storeHours[] = $openingTime->day . ' ' .  $period->from . '-' . $period->to;
                }
            }
            
            if (is_array($jStore->value->hollidays) && count($jStore->value->hollidays)){
                $eStore->setStoreHoursNotes('Feiertage: ' . implode(', ', $jStore->value->hollidays));
            }            
            
            $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $storeHours)));
            
            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        } 
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}