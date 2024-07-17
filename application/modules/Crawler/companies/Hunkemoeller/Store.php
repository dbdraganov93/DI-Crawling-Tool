<?php

/**
 * Store Crawler für Hunkemöller (ID: 22234)
 */

class Crawler_Company_Hunkemoeller_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.hunkemoller.de/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-hunkemoller-de-Site/de_DE/Stores-GetStoresJSON';
        $sPage = new Marktjagd_Service_Input_Page();

        $aDays = array(
            'Sonntag' => 'So',
            'Montag' => 'Mo',
            'Dienstag' => 'Di',
            'Mittwoch' => 'Mi',
            'Donnerstag' => 'Do',
            'Freitag' => 'Fr',
            'Samstag' => 'Sa'
        );

        $sPage->open($searchUrl);

        $page = $sPage->getPage()->getResponseBody();        

        if (strpos($page, '}][{') === FALSE) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        if (strpos($page, '}][{') !== FALSE) {
            $storesJsonToArray = explode('}][{', $page);
            $storesMasterArray = [];
            
            if (is_array($storesJsonToArray)) {
                $count = count($storesJsonToArray);
                for($i=0; $i<$count; $i++) {
                    if ($i === 0) {
                        $stores = $storesJsonToArray[$i]. '}]';                        
                    }
                    else if ($i === 9) {
                        $stores = '[{'. $storesJsonToArray[$i];                        
                    }
                    else {
                        $stores = '[{'. $storesJsonToArray[$i]. '}]';       
                    }

                    $arrayStoresNext = json_decode($stores);   
                    
                    if (empty($storesMasterArray)) {
                        $storesMasterArray = $arrayStoresNext;
                    }
                    if (is_array($arrayStoresNext) && !empty($storesMasterArray)) {
                        $storesMasterArray = array_merge($storesMasterArray, $arrayStoresNext);
                    }
                }
            }         
            
            if (empty($storesMasterArray)) {
                throw new Exception($companyId . ': unable to get any stores.');
            }

            $cStores = new Marktjagd_Collection_Api_Store();
            $address = new Marktjagd_Service_Text_Address();     

            foreach ($storesMasterArray as $singleJStore) {  
                //"Geschlossen" should get skipped
                if (empty($singleJStore->openingHours)) {
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->ID)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setCity($singleJStore->city)
                    ->setImage($singleJStore->imageUrl);

                if (strpos($singleJStore->address, ',') !== FALSE) {
                    $storeAddress = strstr($singleJStore->address, ',', TRUE);
                    $eStore->setStreetAndStreetNumber($storeAddress);                    
                }  
                else {
                    $eStore->setStreetAndStreetNumber($singleJStore->address);        
                }  

                $zipCode = $address->getGerZipCode($eStore->getCity(), $eStore->getStreet(), $eStore->getStreetNumber());
                
                if ( ($zipCode) && (strlen($zipCode) > 4)) {
                    $eStore->setZipcode($zipCode);                
                }
                $eStore->setWebsite($baseUrl .'filiale'. $singleJStore->storeUrlPath);
                
                $strTimes = '';
                foreach ($singleJStore->openingHours as $aTime) {
                    
                    if (!strlen($aTime->open)) {
                        continue;
                    }
                    if (strlen($strTimes)) {
                        $strTimes .= ', ';
                    }

                    $strTimes .= $aDays[$aTime->dayOfWeek] . ' ' . $aTime->open . '-' . $aTime->close;
                }
                
                $eStore->setStoreHours($strTimes);
                $cStores->addElement($eStore, true);
            }

            $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
            $fileName = $sCsv->generateCsvByCollection($cStores);

            return $this->_response->generateResponseByFileName($fileName);
        }        
    }

}
