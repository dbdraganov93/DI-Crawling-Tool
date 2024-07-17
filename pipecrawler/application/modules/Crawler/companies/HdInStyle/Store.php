<?php

/* 
 * Store Crawler für H&D in Style (ID: 72062)
 */

class Crawler_Company_HdInStyle_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://hdinstyle.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#FILIALEN\s*<[^>]*>\s*(.+?)\s*</ul#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href=\'([^\']+?)\'#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($baseUrl . $singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#Allgemeine\s*Informationen\s*<[^>]*>(.+?)</div>\s*</div#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($baseUrl . $singleStoreUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}