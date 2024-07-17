<?php

/* 
 * Store Crawler für Bäckerei Riegler (ID: 71881)
 */

class Crawler_Company_BaeckereiRiegler_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.baeckerei-riegler.de/';
        $searchUrl = $baseUrl . 'fachgeschaefte/standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#ffnungszeiten\s*auf\s*einen\s*blick(.+?)</table#i';
        if (!preg_match($pattern, $page, $storeUrlListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="(fachgeschaefte/standorte/[^"]+?)"#';
        if (!preg_match_all($pattern, $storeUrlListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#adresse(\s*<[^>]*>\s*)+([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#i';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#tel\.?:?\s+(\d+[^<]+?)<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax([^<]+?)<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten:?<[^>]*>(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[2], $addressMatch[3])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}