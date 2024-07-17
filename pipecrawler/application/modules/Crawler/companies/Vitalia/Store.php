<?php

/*
 * Store Crawler für Vitalia (ID: 354)
 */

class Crawler_Company_Vitalia_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.vitalia-reformhaus.de/';
        $searchUrl = $baseUrl . 'marktfinder/vitalia/markt';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#new\s*google\.maps\.InfoWindow\(\s*\{\s*(.+?)\s*\}\s*\);#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\'\s*\+\s*\'<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#T:\s+(\d+[^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</table#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<a[^>]*href="([^"]+?marktfinder[^"]+?)"#';
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
