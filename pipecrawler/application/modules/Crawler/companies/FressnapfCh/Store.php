<?php

/* 
 * Store Crawler für Fressnapf CH (ID: 72183)
 */

class Crawler_Company_FressnapfCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.fressnapf.ch/';
        $searchUrl = $baseUrl . 'de/uber-uns/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="panel-body[^"]*panel-custom">\s*<a[^>]*href="([^\/]+?\/)">\s*<h4>#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*location-info[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
            if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get address from store info list: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#\s*(\d[^,]*)\s*,\s*(.+)#';
            if (preg_match($pattern, $addressMatch[1], $streetMatch)) {
                $addressMatch[1] = $streetMatch[2] . ' ' . $streetMatch[1];
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#fon([^<]+?)<#';
            if (preg_match($pattern, $storeInfoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</table#';
            if (preg_match($pattern, $storeInfoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH')
                    ->setWebsite($storeDetailUrl);
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}