<?php

/**
 * Store Crawler für Helbing mein Lieblingsbäcker (ID: 68928)
 */
class Crawler_Company_Helbing_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.lieblingsbaecker.de/';
        $searchUrl = $baseUrl . 'backstuben';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#geolocation-list-info"(.+?)</div>\s*</div>\s*<a#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#info-address"[^>]*>\s*([^,]+?)\s*<div[^>]*class="info-city"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)){
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }
            
            $pattern = '#data-uid="([^"]+?)"#';
            if (!preg_match($pattern, $singleStore, $idMatch)){
                $this->_logger->err($companyId . ': unable to get store id.');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#data-latitude="([^"]+?)"[^>]*data-longitude="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $pattern = '#Tel\.?:?\s*([^<]+?)<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setStoreNumber($idMatch[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}