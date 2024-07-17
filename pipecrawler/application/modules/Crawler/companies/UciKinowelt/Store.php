<?php

/* 
 * Store Crawler für UCI Kinowelt (ID: 72071)
 */

class Crawler_Company_UciKinowelt_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.uci-kinowelt.de/';
        $searchUrl = $baseUrl . 'kinoprogramm';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<ul[^>]*class="city--list"[^>]*>(.+?)<img#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*id="program-city[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeInfoUrl = preg_replace('#programm#', 'information', $singleStoreUrl);
            
            $pattern = '#(\d+)$#';
            if (!preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number - ' . $singleStoreUrl);
            }
            
            $sPage->open($storeInfoUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\([^\)]+?\),?\s*<[^>]*>\s*)?(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address - ' . $storeInfoUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Kartenreservierung:?(\s*<[^>]*>\s*)*Tel:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }
            
            $pattern = '#Infos\s*<[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $textMatch)) {
                $eStore->setText($textMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[3])
                    ->setWebsite($singleStoreUrl)
                    ->setStoreNumber($storeNumberMatch[1]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}