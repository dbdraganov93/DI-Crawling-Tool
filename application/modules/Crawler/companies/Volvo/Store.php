<?php

/**
 * Store Crawler für Volvo (ID: 68842)
 */
class Crawler_Company_Volvo_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.volvocars.com/';
        $searchUrl = $baseUrl . 'data/dealers/?marketSegment=%2Fde&expand=Services%2CUrls&format=json&northToSouthSearch=False&filter=MarketId+eq+\'de\'+and+LanguageId+eq+\'de\'';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $serviceMap = array('car_rental' => 'Autovermietung',
                            'insurance' => 'Versicherung',
                            'used_car_sales' => 'Gebrauchtwagen',
                            'workshop' => 'Workshop',
                            'pre_order' => 'Vorbestellungen',
                            'time_booking' => 'Termine',
                            'new_car_sales' => 'Neuwagen',
                            'volvo_selekt_member' => 'ausgewählter Händler',
                            'fleet_sales' => 'Flotten',
                            'gas' => 'Gas'  
                        );

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
                     
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singlejStore) {
            if (!preg_match('#Germany#', $singlejStore->Country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singlejStore->DealerId)
                    ->setSubtitle($singlejStore->Name)                                        
                    ->setStreetAndStreetNumber($singlejStore->AddressLine1)
                    ->setZipcodeAndCity($singlejStore->AddressLine2)
                    ->setPhoneNormalized($singlejStore->Phone)
                    ->setFaxNormalized($singlejStore->Fax)
                    ->setEmail($singlejStore->GeneralContactEmail)
                    ->setLatitude((string) $singlejStore->GeoCode->Latitude)
                    ->setLongitude((string) $singlejStore->GeoCode->Longitude)
                    ->setWebsite($singlejStore->Url)
                    ->setStoreHoursNormalized($singlejStore->Services[6]->OpeningHours);             
            
            // Services            
            if (is_array($singlejStore->Services)){
                $services = array();
                foreach ($singlejStore->Services as $service){
                    if (array_key_exists($service->ServiceType, $serviceMap)){
                        $services[] = $serviceMap[$service->ServiceType];
                    }
                }    
                $eStore->setService(implode(', ', $services));
            }
            
            $cStores->addElement($eStore, TRUE);
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}