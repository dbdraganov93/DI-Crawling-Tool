<?php

/* 
 * Store Crawler für Relay (ID: 71079)
 */

class Crawler_Company_Relay_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.ls-travelretail.de';
        $searchUrl = $baseUrl . '/filialen/deutschland/';
                
        $detailUrl = $baseUrl . '/filialen/deutschland/?tx_dbhdsbranches_pi1[a]=detail&tx_dbhdsbranches_pi1[uid]=';
        
        $sPage = new Marktjagd_Service_Input_Page();        
        $sAddress = new Marktjagd_Service_Text_Address();
        $sText = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
       
        $pattern = '#<div[^>]*class="[^"]*concept1"[^>]* id="branch([0-9]+)">#is';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to find store divs');                            
        }
        
        foreach ($storeMatches[1] as $storeMatch) {             
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($storeMatch);
            
            $sPage->open($detailUrl . $storeMatch);
            $json = $sPage->getPage()->getResponseAsJson();
                       
            if (preg_match('#<strong>([^<]+)</strong>#', $json->view, $titleMatch)){
                $eStore->setTitle(trim($titleMatch[1]));
            }                         

            if (preg_match('#<span[^>]*id="gmap_lat"[^>]*>([^<]+)</span>#', $json->view, $latMatch)){
                $eStore->setLatitude(trim($latMatch[1]));
            }
            
            if (preg_match('#<span[^>]*id="gmap_lng"[^>]*>([^<]+)</span>#', $json->view, $lngMatch)){
                $eStore->setLongitude(trim($lngMatch[1]));
            }                                            
            
            if (preg_match('#</strong>(.+?)</div>#is', $json->view, $addressMatch)){                
                if (preg_match('#Tel:([^<]+)<#', $addressMatch[1], $phoneMatch)){
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                if (preg_match('#Fax:([^<]+)<#', $addressMatch[1], $phoneMatch)){
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                if (preg_match('#zeiten:(.+?)$#is', $addressMatch[1], $hoursMatch)){                    
                    $eStore->setStoreHours($sText->generateMjOpenings($hoursMatch[1]));
                }
                
                if (preg_match('#^(.+?)([0-9]{5}[^<]+)<#is', $addressMatch[1], $locationMatch)){                    
                    $eStore->setCity($sAddress->extractAddressPart('city', $locationMatch[2]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $locationMatch[2]));
                     
                    $addressData = explode('#', preg_replace('#<[^>]+>#', '#', $locationMatch[1]));
                                        
                    Zend_Debug::dump($addressData);
                    
                    foreach ($addressData as $addressLine){
                        if (preg_match('#(Bahnhof\s[A-Za-z]|Flughafen\s[A-Za-z]|[A-Za-z]\sHauptbahnhof)#', $addressLine)){
                            $eStore->setTitle('RELAY '. trim(preg_replace('#\s*RELAY\s*#', '', $addressLine)));                                    
                        }
                        
                        if (preg_match('#\s*[0-9]+$#', $addressLine)){
                            $eStore->setStreet($sAddress->extractAddressPart('street', $addressLine))
                                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLine));                            
                        }                        
                        
                         if (preg_match('#(Terminal|platz)#', $addressLine) && !strlen($eStore->getStreet())){
                            $eStore->setStreet(trim($addressLine));                                    
                        }
                    }
                }
            }            

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}