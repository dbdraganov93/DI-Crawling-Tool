<?php

/**
 * Store Crawler für Subway (ID: 28995)
 */
class Crawler_Company_Subway_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.subway.com';
        
        $searchUrl = $baseUrl . '/webservices/js-storelocator/wsvclocator.asmx/GetLocations'
                . '?callback=jQuery111101847048913235373_1450181507507'
                . '&Location=%2299099%22'
                . '&ClientGeocode=%22%7B%5C%22Latitude%5C%22%3A50.9629108%2C%5C%22Longitude%5C%22%3A11.074406299999964%2C%5C%22type%5C%22%3A%5C%22postal_code%5C%22%2C%5C%22name%5C%22%3A%5C%2299099%5C%22%2C%5C%22country%5C%22%3A%5C%22DE%5C%22%7D%22'
                . '&BiasingPoint=%2250.92380927447117%2C11.122949999999946%22'
                . '&ConsumerData=%22%7B%5C%22consumerId%5C%22%3A%5C%2217%5C%22%2C%5C%22consumerKey%5C%22%3A%5C%22SUBWAY_PROD%5C%22%2C%5C%22consumerLocale%5C%22%3A%5C%22de-de%5C%22%2C%5C%22consumerStoreSelectors%5C%22%3Afalse%2C%5C%22useSingleSelector%5C%22%3Atrue%2C%5C%22defaultMetricSystem%5C%22%3Atrue%2C%5C%22overrideTemplateControl%5C%22%3A%5C%22%5C%22%7D%22'
                . '&CurrentPage=1'
                . '&ResultsPerPage=50'
                . '&LongLat=%22' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '%7C' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '%22'
                . '&MetricOption=true'
                . '&MapFilters=%22%22'
                . '&ResultDataOnly=true'
                . '&_=1450181507510';
        
        $weekdays = array('Sunday' => 'So',
                            'Monday' => 'Mo',
                            'Tuesday' => 'Di',
                            'Wednesday' => 'Mi',
                            'Thursday' => 'Do',
                            'Friday' => 'Fr',
                            'Saturday' => 'Sa'
                        );        
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $oPage = $sPage->getPage();
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);
                
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 1);
        
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $page = preg_replace('#^jQuery111101847048913235373_1450181507507\(#', '', $page);
            $page = preg_replace('#\)$#', '', $page);
            
            $jStores = json_decode($page); 
            
            if (is_null($jStores)) {
                $this->_logger->err($companyId . ': invalid json: ' . $singleUrl);
                continue;
            }
                    
            foreach ($jStores->d->ResultData as $result){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if ($result->Country != 'GER' || !$result->IsOpen){
                    continue;
                }
                
                $eStore->setStreetAndStreetNumber($result->Address1)
                        ->setSubtitle(trim($result->Address2 . ' ' . $result->Address3))
                        ->setCity($result->City)
                        ->setZipcode($result->Zip)
                        ->setPhoneNormalized($result->Phone)
                        ->setLatitude((string) $result->Latitude)
                        ->setLongitude((string) $result->Longitude)
                        ->setStoreNumber($result->StoreNumber);
                      
                if (!preg_match('#^0#', $eStore->getPhone())){
                    $eStore->setPhone('0' . $eStore->getPhone());
                }                
                      
                $hours = array();
                foreach ($weekdays as $dayKey => $dayVal){
                    $dayFrom = $dayKey . 'OpenTime';
                    $dayTo = $dayKey . 'CloseTime';
                    if ($result->LocalStoreHours->$dayFrom){
                        $hours[] = $dayVal . ' ' . $result->LocalStoreHours->$dayFrom . '-' . $result->LocalStoreHours->$dayTo;
                    }
                }                
                
                $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $hours), 'text', true));
                
                Zend_Debug::dump($eStore);
                $cStores->addElement($eStore);
            }  
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}