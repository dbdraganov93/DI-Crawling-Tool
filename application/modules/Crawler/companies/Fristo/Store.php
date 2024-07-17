<?php

/**
 * Store Crawler für Fristo (ID: 90)
 */
class Crawler_Company_Fristo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.fristo.de/';
        $searchUrl = $baseUrl . 'maerkte/sortiert-von-a-z/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="boxcontent"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<div[^>]*class="row"[^>]*>(.+?)</a>\s*</div>\s*<div[^>]*class="clear"[^>]*>\s*</div>\s*</div>#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<div[^>]*class="plz"[^>]*>\s*(\d{5})\s*</div>\s*<div[^>]*class="ort"[^>]*>\s*<strong[^>]*>\s*([^<]+?)\s*</strong>\s*<br[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)
                    && !preg_match('#<div[^>]*class="plz"[^>]*>\s*A#', $singleStore)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="tel"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#<div[^>]*class="oeff"[^>]*>(.+?)</div>\s*</div>\s*</div#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<a[^>]*href="(no_cache\/maerkte\/info[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite($baseUrl . $websiteMatch[1]);
            }
            
            $eStore->setZipcode($addressMatch[1])
                    ->setCity($addressMatch[2])
                    ->setStreetAndStreetNumber($addressMatch[3]);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
