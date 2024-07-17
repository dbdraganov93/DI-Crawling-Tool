<?php

/* 
 * Store Crawler für TTL / TTM (IDs: 68055, 68056)
 */

class Crawler_Company_TtlTtm_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.ttl-ttm.de/';
        $searchUrl = $baseUrl . 'standorte/standorte.php';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $aPattern = array(
            '68055' => 'ttm',
            '68056' => 'ttl'
        );
        
        $pattern = '#<a[^>]*href="\/(standorte\/[^\/]+?\/' . $aPattern[$companyId] . '[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<td[^>]*>\s*<div[^>]*class="adress_block1"[^>]*>\s*([^<]+?)\s*<[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<td[^>]*>\s*Telefon:?(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }
            
            $pattern = '#<td[^>]*>\s*Fax:?(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[2]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</div>\s*</div>\s*</div#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}