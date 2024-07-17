<?php

/*
 * Store Crawler für BHG Handelszentren (ID: 71306)
 */

class Crawler_Company_BhgHandelszentren_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://bhg-handelszentren.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<h3[^>]*class="widget-title"[^>]*>(.+?)</a>\s*</p>\s*</div>\s*</div>\s*</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#s';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel\.?:?\s*([^<]+?)<#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax\.?:?\s*([^<]+?)<#';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#Email:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#(<p[^>]*>\s*<a[^>]*href="([^"]+?\.jpg)"|<img[^>]*src="([^"]+?)")#';
            if (preg_match($pattern, $singleStore, $imageMatch)) {
                $eStore->setImage($imageMatch[count($imageMatch) - 1]);
            }
            
            $pattern = '#dd[^>]*class=\'wp-caption-text\s*gallery-caption\'[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $singleStore, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }
                        
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
