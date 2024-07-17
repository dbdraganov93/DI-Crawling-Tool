<?php

/* 
 * Store Crawler für Zweygart (ID: 68893)
 */

class Crawler_Company_Zweygart_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www1.zweygart.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="/([^"]+?)"[^>]*target="_blank"[^>]*>\s*\d{5}#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="module_text"[^>]*>\s*(.+?\d{5}.+?)\s*</div#s';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
                        
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $infoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setStreetAndStreetNumber($addressMatch[1])
                    ->setZipcodeAndCity($addressMatch[3])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}