<?php

/* 
 * Store Crawler für TommyHilfiger (ID: 28665)
 */

class Crawler_Company_TommyHilfiger_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://tommy.com.gotlocations.com/';
        $searchUrl = $baseUrl . 'temp.php';
                
        $sPage = new Marktjagd_Service_Input_Page();        
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $client = $sPage->getPage();
        $client->setUseCookies(true);
        $client->setMethod('POST');
        $sPage->setPage($client);                                
        
        $params = array(
            'address' => '',            
            'language' => 'German',
            'c' => 'DE',
            'type2' => '',
            //'address' => '01277',
            'ip_country' => 'DE',
            'Submit' => 'SUCHE'          
        );
        
        $zipcodes = $sGeo->findZipCodesByNetSize(80);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($zipcodes as $idx => $zipcode){
            $params['address'] = $zipcode;
            
            $this->_logger->info('request zipcode ' . $zipcode . ' / ' . count($zipcodes));
            $sPage->open($searchUrl, $params);
            $page = $sPage->getPage()->getResponseBody();                                    
            
            if (!preg_match_all('#addMarker\((.+?)\);#is', $page, $markerMatch)){
                $this->_logger->info($companyID . ': found no stores for zipcode ' . $zipcode);
                continue;
            }
            
            foreach ($markerMatch[1] as $marker){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if (preg_match('#^([0-9\.]+)\,([0-9\.]+)\,(.+)$#', $marker, $geoMatch)){
                    $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                    
                    $eStore->setStoreNumber($eStore->getLatitude() . '-' . $eStore->getLatitude());
                    
                    if (preg_match('#<span[^>]*class=maptitles[^>]*>(.+?)</span>#', $geoMatch[3], $titleMatch)){
                        $eStore->setSubtitle(trim(strip_tags($titleMatch[1])));
                    }
                    
                    if (preg_match('#Telefon([^<]+)<#i', $geoMatch[3], $phoneMatch)){
                        $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                    }
                    
                    
                    if (preg_match('#href="[^"]+daddr=([^"]+)"#i', $geoMatch[3], $addressMatch)){
                        $addressLines = explode(',', str_replace('+', '', $addressMatch[1]));
                                                
                        if ($addressLines[4] != 'Germany'){
                            continue;
                        }
                        
                        $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                                ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                                ->setCity($addressLines[1])
                                ->setZipcode($addressLines[3]);                        
                    }
                                                                
                   if (preg_match('#<span[^>]*>Stunden(.+?)<span[^>]*>#is', $geoMatch[3], $hoursMatch)){     
                       $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                   }
                   
                   if (preg_match('#<span[^>]*class=productsmap[^>]*>(.+?)</span>#is', $geoMatch[3], $productsMatch)){     
                       $eStore->setSection(preg_replace('#<[^>]*>#', ' ', $productsMatch[1]));
                   }
                }
                                                
                $cStores->addElement($eStore);
            }
        }               
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}