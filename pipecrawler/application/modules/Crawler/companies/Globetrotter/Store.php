<?php

/* 
 * Store Crawler für Globetrotter (ID: 22233)
 */

class Crawler_Company_Globetrotter_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.globetrotter.de';
        $searchUrl = $baseUrl . '/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*id="filialen_flyout"[^>]*>(.+?)</div#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/filialen\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<span[^>]*class="address"[^>]*>\s*(.+?)(\s*<[^>]*span[^>]*>\s*)+(.+?)</div>\s*</div>#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<li[^>]*class="[^"]*icon-telephone[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setAddress($aAddress[0], $aAddress[1])
                    ->setStoreHoursNormalized($addressMatch[3])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}