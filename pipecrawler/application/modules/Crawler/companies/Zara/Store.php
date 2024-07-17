<?php

/* 
 * Store Crawler für Zara (ID: 67353)
 */

class Crawler_Company_Zara_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();
                
        $baseUrl = 'http://www.zara.com/';
        $overViewUrl = $baseUrl . 'webapp/wcs/stores/servlet/StoreLocatorResultPage'
                . '?showOnlyDeliveryShops=false'
                . '&storeCountryCode=DE'
                . '&isPopUp=false'
                . '&langId=-3'
                . '&showSelectButton=false'
                . '&storeId=10705'
                . '&latitude=' . $sGenerator::$_PLACEHOLDER_LAT
                . '&longitude=' . $sGenerator::$_PLACEHOLDER_LON
                . '&country=DE'
                . '&ajaxCall=true';
        
        $searchUrls = $sGenerator->generateUrl($overViewUrl, 'coords', 0.5);        
        
        foreach ($searchUrls as $idx => $searchUrl){
            $this->_logger->info('open ' . $idx . ' of ' . count($searchUrls));
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            if (!preg_match_all('#<li[^>]*id="liShop[^"]*"[^>]*>(.+?)</li>#', $page, $storeMatches)){
                $this->_logger->warn('cannot find stores on ' . $searchUrl);
            }
            
            foreach ($storeMatches[1] as $storeMatch){
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if (preg_match('#<input[^>]*class="lat"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setLatitude($match[1]);
                }
                
                if (preg_match('#<input[^>]*class="lng"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setLongitude($match[1]);
                }
                
                if (preg_match('#<input[^>]*class="storeId"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setStoreNumber($match[1]);
                }
                
                if (preg_match('#<input[^>]*class="storeName"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setSubtitle(trim($match[1]));
                }
                
                if (preg_match('#<input[^>]*class="storeAddress"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setStreet(preg_replace('#\s*\,[^\,]*$#', '', $sAddress->extractAddressPart('street', $match[1])))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $match[1]));
                }
                
                if (preg_match('#<input[^>]*class="storeZipCode"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setZipcode($match[1]);
                }
                
                if (preg_match('#<input[^>]*class="storeCity"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setCity(ucfirst(strtolower($match[1])));
                }
                
                if (preg_match('#<input[^>]*class="storePhone1"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                }
                
                if (preg_match('#<input[^>]*class="storeSections"[^>]*value="([^"]+)"#', $storeMatch, $match)){
                    $eStore->setSection($match[1]);
                }
                
                $cStore->addElement($eStore);
            }                        
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}