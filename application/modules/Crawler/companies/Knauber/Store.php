<?php

/**
 * Store Crawler für Knauber (ID: 70819)
 */
class Crawler_Company_Knauber_Store extends Crawler_Generic_Company {    
    public function crawl($companyId) {
        $baseUrl = 'https://www.knauber-freizeit.de/';
        $searchUrl = $baseUrl . '/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*>\s*Filialen\s*<span[^>]*>(.+?)<a[^>]*>\s*Filialfinder\s*</a>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="emotion--wrapper"[^>]*data-controllerUrl="\/([^"]+?(\d{3})[^"]+?)"#';
            if (!preg_match($pattern, $page, $storeDetailUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get store detail url: ' . $singleStoreUrl);
                continue;
            }
                        
            $sPage->open($baseUrl . $storeDetailUrlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: '. $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</div>\s*</div>\s*</div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
                        
            $eStore->setStoreNumber($storeDetailUrlMatch[2])
                    ->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($singleStoreUrl);
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
