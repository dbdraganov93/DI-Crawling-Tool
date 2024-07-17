<?php

/* 
 * Store Crawler für Herwig (ID: 71886)
 */

class Crawler_Company_Herwig_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.herwig-online.de/';
        $searchUrl = $baseUrl . 'filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<li[^>]*class="first"[^>]*>(.+?)<li[^>]*class="last#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<table[^>]*class="filiale_time_tab"[^>]*>(.+?)</table#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store info: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
             $pattern = '#tel([^<]+?)<#i';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax([^<]+?)<#i';
            if (preg_match($pattern, $infoMatch[1], $phoneMatch)) {
                $eStore->setFaxNormalized($phoneMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#i';
            if (preg_match($pattern, $infoMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $strTimes = '';
            $pattern = '#style="width: 50px;"[^>]*(>.+?)</tr#i';
            if (preg_match($pattern, $infoMatch[1], $storeHoursListMatch)) {
                $pattern = '#>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches)) {
                    for ($i = 0; $i < count($storeHoursMatches[1]) / 2; $i++) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[1][$i + count($storeHoursMatches[1]) / 2];
                    }
                }
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}