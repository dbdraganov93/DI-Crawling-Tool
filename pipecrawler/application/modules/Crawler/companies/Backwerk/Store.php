<?php

/**
 * Store Crawler für Backwerk (ID: 68932)
 */
class Crawler_Company_Backwerk_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.back-werk.de';
        $searchUrl = $baseUrl . '/wp-json/bw/v1/location';                
        
        $sPage = new Marktjagd_Service_Input_Page(true);        
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();        
        
        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json as $singleStore){
            if (!(strlen($sAddress->validateGeoCoords('lat', $singleStore->position->lat))
                    && strlen($sAddress->validateGeoCoords('lng', $singleStore->position->lng))
                    )){
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
                                    
            $eStore->setLatitude($singleStore->position->lat)
                    ->setLongitude($singleStore->position->lng)
                    ->setCity($singleStore->info->city)
                    ->setZipcode($singleStore->info->zip)
                    ->setStreetAndStreetNumber($singleStore->info->street)
                    ->setPhoneNormalized($singleStore->info->phone)
                    ->setFaxNormalized($singleStore->info->fax);
            
            $hours = array();
            foreach ($singleStore->info->opening as $open){
                if ($open->closed == true){
                    continue;
                }
                $hours[] = $open->weekday . ' ' . $open->time_from . '-' . $open->time_to; 
            }
                        
            $eStore->setStoreHoursNormalized(implode(',', $hours));
            $cStores->addElement($eStore);
        }       
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}