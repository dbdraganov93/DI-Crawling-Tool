<?php

/*
 * Store Crawler für mymüsli (ID: 70878)
 */

class Crawler_Company_Mymuesli_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mymuesli.com/';
        $searchUrl = $baseUrl . 'ueber-uns/laden';        
        $sPage = new Marktjagd_Service_Input_Page();
               
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<ul[^>]*class="stores[^>]*>(.+?)<\/ul>#i', $page, $storeListMatch)){
            throw new Exception ('cannot get store list from ' . $searchUrl);
        }                                           
        
        if (!preg_match_all('#<li[^>]*>\s*<h4[^>]*>\s*<a[^>]*href="\/?([^"]+)"[^>]*>#', $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception('unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $storeDetailUrl = $baseUrl . $singleUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^<]+?)Uhr#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }
            
            $pattern = '#<div[^>]*class="storeDetail-icon"[^>]*>(.+?</div>)\s*</div>\s*</div>#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<img[^>]*>\s*<div[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setPhoneNormalized($addressMatch[3])
                    ->setWebsite($storeDetailUrl);
                        
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
