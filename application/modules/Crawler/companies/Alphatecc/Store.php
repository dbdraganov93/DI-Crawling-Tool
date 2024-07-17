<?php

/*
 * Store Crawler für Alphatecc (ID: 29055)
 */

class Crawler_Company_Alphatecc_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.alphatecc.de';
        $searchUrl = '/marktauswahl/';                
                
        $sPage = new Marktjagd_Service_Input_Page();        
        
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
                
        $cStores = new Marktjagd_Collection_Api_Store();
             
        $this->_logger->info('open ' . $baseUrl . $searchUrl);
        $sPage->open($baseUrl . $searchUrl);
        $mainPage = $sPage->getPage()->getResponseBody();

        if (preg_match_all('#href="(https://www.alphatecc.de/markt/[^"]+)"#i', $mainPage, $storeMatch)){    
            
            $storeMatch[1] = array_unique($storeMatch[1]);
            
            foreach ($storeMatch[1] as $storeUrl){
                
                if (strpos($storeUrl, 'bundesweit') !== false){
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();

                $this->_logger->info('open ' . $storeUrl);
                $sPage->open($storeUrl);
                $page = $sPage->getPage()->getResponseBody();

                $eStore->setWebsite($storeUrl);

                if (preg_match('#<a[^>]*href="https://www.alphatecc.de/markt/[^"]+"><span>.+?</span>(.+?)</a>#', $page, $match)){
                    $addressLines = explode(',', $match[1]);                                
                    
                    $eStore->setStreetAndStreetNumber($addressLines[2])
                            ->setZipcodeAndCity($addressLines[1]);
                }
                
                if (preg_match('#<td>\s*<strong>\s*Tel.*?</strong>\s*</td>\s*<td>(.+?)</td>#', $page, $match)){
                    $eStore->setPhoneNormalized($match[1]);
                }
                
                if (preg_match('#"mailto:([^"]+)"#', $page, $match)){
                    $eStore->setEmail($match[1]);
                }
                
                if (preg_match('#var markerLatLng = new google.maps.LatLng\(([^\,]+)\,\s*([^\)]+)\);#', $page, $match)){
                    $eStore->setLatitude($match[1])
                            ->setLongitude($match[2]);
                }
                
                if (preg_match('#<strong>\s*Öffnungszeiten\s*</strong>.+?<table>(.+?)</table>#', $page, $match)){
                    $eStore->setStoreHoursNormalized($match[1]);
                }
                
                Zend_Debug::dump($eStore);
                $cStores->addElement($eStore);
            }                     
        }
       
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
