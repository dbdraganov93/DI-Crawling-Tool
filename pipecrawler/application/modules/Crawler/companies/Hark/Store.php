<?php

/* 
 * Store Crawler für Hark Kamin- und Kachelofenbau (ID: 69068)
 */

class Crawler_Company_Hark_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.hark.de/';
        $searchUrl = $baseUrl . 'kaminausstellungen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="location-item"[^>]*>(.+?)</i#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)
                    || preg_match('#(á)#', $singleStore)) {
                $this->_logger->info($companyId . ': not a german store. skipping...');
                continue;
            }
            
            $pattern = '#title="Hark[^"]*Studio"#';
            if (!preg_match($pattern, $singleStore, $studioMatch)) {
                $this->_logger->info($companyId . ': not a studio. skipping...');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#tel:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)(Abholzeiten|</div)#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#data-href="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}