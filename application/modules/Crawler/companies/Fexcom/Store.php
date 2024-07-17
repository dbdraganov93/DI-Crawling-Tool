<?php

/**
 * Store Crawler für Fexcom (ID: 71631)
 */
class Crawler_Company_Fexcom_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.fexcom.de';
        $searchUrl = $baseUrl . '/anystores_liste/suche/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '/land/de.html?noscript=1';
                
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 50);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $storeLinks = array();
        foreach ($aLinks as $singleLink) {
            $this->_logger->info('open ' . $singleLink);
            if (!$sPage->open($singleLink)) {
                throw new Exception ($companyId . ': unable to open store list page. url: ' . $singleLink);
            }
            
            $page = $sPage->getPage()->getResponseBody();            
            
            if (preg_match_all('#<a[^>]*href="([^"]+)"[^>]*>\s*mehr Informationen\s*</a>#', $page, $match)){
                $storeLinks = array_merge($storeLinks, $match[1]);                                                
            }
        }
        
        $storeLinks = array_unique($storeLinks);        
        
        foreach ($storeLinks as $storeLink){
            try {
                $sPage->open($baseUrl . '/' . $storeLink . '?noscript=1');
            } catch (Exception $ex){
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();                              
            
            if (preg_match('#<[^>]*class="name"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setSubtitle($match[1]);
            }
            
            if (preg_match('#<[^>]*class="street"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setStreetAndStreetNumber($match[1]);
            }
            
            if (preg_match('#<[^>]*class="postal"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setZipcode($match[1]);
            }
            
            if (preg_match('#<[^>]*class="city"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setCity($match[1]);
            }
            
            if (preg_match('#<div[^>]*class="phone"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setPhoneNormalized(strip_tags($match[1]));
            }
            
            if (preg_match('#<div[^>]*class="fax"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setFaxNormalized(strip_tags($match[1]));
            }
            
            if (preg_match('#<[^>]*class="email"[^>]*>([^<]+)<#', $page, $match)){
                $eStore->setEmail($match[1]);
            }
            
            if (preg_match('#<ul[^>]*class="opening-times"[^>]*>(.+?)</ul>#', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);
            }
            
            if (preg_match('#google\.maps\.LatLng\(([^\,]+)\,([^\)]+)\);#', $page, $match)){
                $eStore->setLatitude(trim($match[1]))
                        ->setLongitude(trim($match[2]));
            }

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}