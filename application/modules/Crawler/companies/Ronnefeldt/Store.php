<?php

/* 
 * Store Crawler für Ronnefeldt (ID: 71885)
 */

class Crawler_Company_Ronnefeldt_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.ronnefeldt-fachhandel.com/';
        $searchUrl = $baseUrl . 'de/unsere-markenpartner/uebersicht.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<p>\s*<a[^>]*href="(de/unsere-markenpartner/das-jahr-[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<h1[^>]*class="ce_headline[^>]*noImg"[^>]*>(.+?)</div#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#phone([^<]+?)<#i';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax([^<]+?)<#i';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setFaxNormalized($phoneMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#i';
            if (preg_match($pattern, $infoMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#web:?\s*<a[^>]*href="([^"]+?)"#i';
            if (preg_match($pattern, $infoMatch[1], $urlMatch)) {
                $eStore->setWebsite($urlMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#i';
            if (preg_match($pattern, $infoMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}