<?php

/* 
 * Store Crawler für TintenTonerTankstation (ID: 28460)
 */

class Crawler_Company_TintenTonerTankstation_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.tttankstation.de/';
        $overViewUrl = $baseUrl . 'standorte_deutschland.html';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        if(!$sPage->open($overViewUrl)){
            throw new Exception('Could not open url:' . $overViewUrl);
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        if(!preg_match('#data-standorte=\'(.+?)\'>#', $page, $jsonMatch)){                
               throw new Exception('Cannot find Jason String on url'.$overViewUrl); 
            }
            
        $jStores = json_decode($jsonMatch[1]);
                
        foreach ($jStores as $jStore){
            $eStore = new Marktjagd_Entity_Api_Store();
        
            $eStore->setLatitude($jStore->lat)
                    ->setLongitude($jStore->lng)
                    ->setSubtitle($jStore->name);
            
            $addressLines = preg_split('#<br[^>]*>#', $jStore->adresse);
            
            if ($addressLines[2] != 'Deutschland'){
                continue;
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                    ->setCity($sAddress->extractAddressPart('city', $addressLines[1]));
                        
            if (preg_match('#href="([^"]+)"#', $jStore->link, $linkMatch)){                
                $subLink = $baseUrl . str_replace('%2F', '/', rawurlencode($linkMatch[1]));
                $this->_logger->info('open ' . $subLink);                               
                                                
                $sPage->open($subLink);
                $page = $sPage->getPage()->getResponseBody();
                
                $eStore->setWebsite($subLink);
                
                if (preg_match('#<span[^>]*class="[^"]*earphone[^"]*">\s*</span>(.+?)<br>#', $page, $phoneMatch)){    
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                if (preg_match('#<span[^>]*class="[^"]*print[^"]*">\s*</span>(.+?)<br>#', $page, $faxMatch)){    
                    $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                }
                
                if (preg_match('#<span[^>]*class="[^"]*envelope[^"]*">\s*</span>\s*<a[^>]*href="mailto:([^"]+)"#', $page, $mailMatch)){    
                    $eStore->setEmail($mailMatch[1]);
                }
                
                if (preg_match('#<ul[^>]*class="standortZusatz"[^>]*>(.+?)</ul>#is', $page, $serviceMatch)){    
                    $eStore->setService(preg_replace('#<[^>]*>#', '', preg_replace('#</li>\s*<li[^>]*>#', ', ', $serviceMatch[1])));
                }
                
                if (preg_match('#<table[^>]*class="oefTop[^"]*"[^>]*>(.+?)</table>#is', $page, $hoursMatch)){    
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
            }                        
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}