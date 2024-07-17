<?php

/*
 * Store Crawler für Plana (ID: 71172)
 */

class Crawler_Company_Plana_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.plana.de/';
        $searchUrl = $baseUrl . 'wp-content/themes/PLANA2015/data/locations.xml?origLat=&origLng=&origAddress=';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();                  
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();                                         
        
        $xml = simplexml_load_string($page);
           
        foreach ($xml->marker as $marker) {           
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setWebsite((string) $marker->attributes()->web)
                    ->setLatitude((string) $marker->attributes()->lat)
                    ->setLongitude((string) $marker->attributes()->lng);
                        
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $eStore->getWebsite());
                continue;
            }
            
            if (preg_match('#<[^>]*itemprop="telephone"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
            }
            
            if (preg_match('#<[^>]*itemprop="faxNumber"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setFax($sAddress->normalizePhoneNumber($match[1]));
            }
            
            if (preg_match('#<[^>]*itemprop="email"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setEmail($match[1]);
            }                        
            
            if (preg_match('#ffnungszeiten[^<]*</h2>(.+?)</p#', $page, $hoursMatch)){
                $eStore->setStoreHoursNormalized($hoursMatch[1]);
            }
                                
            $eStore->setStreetAndStreetNumber($addressMatch[1])
                    ->setZipcodeAndCity($addressMatch[3]);
            
            $cStores->addElement($eStore);
        }      
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
