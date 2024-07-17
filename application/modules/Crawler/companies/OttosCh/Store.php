<?php

/* 
 * Store Crawler für OTTO'S CH (ID: 72157)
 */

class Crawler_Company_OttosCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.ottos.ch/';
        $searchUrl = $baseUrl . 'ottos-filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="amasty_locator_filter"[^>]*>(.+?)<\/script#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<div[^>]*class="location_header"[^>]*>(.+?<\/div>\s*<\/span>\s*)(<span|<script)#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#tel\s*\.?\s*:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<div[^>]*class="all_schedule"[^>]*>(.+)<\/div>\s*<\/div>\s*<\/span>#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#<\/svg>\s*([^<]+?)\s*<\/div>#';
            if (preg_match_all($pattern, $singleStore, $sectionMatches)) {
                $eStore->setSection(implode(', ', $sectionMatches[1]));
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH');

            if (preg_match('#7000#', $eStore->getZipcode())) {
                $eStore->setDefaultRadius(30);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}