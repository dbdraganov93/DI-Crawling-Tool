<?php

/* 
 * Store Crawler für Swidro CH (ID: 72252)
 */

class Crawler_Company_SwidroCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.swidro.ch/';
        $searchUrl = $baseUrl . 'ueber-uns/standortsuche/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<table[^>]*class="tx_spxgooglestorelocator_results_table[^>]*>(.+?)<\/table>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<td[^>]*>\s*<a[^>]*href="\/([^\#]+?)\##';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<table[^>]*class="tx_spxgooglestorelocator_details">(.+?)</table>\s*</div#';
            if(!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<h5>Drogerie\s*Details</h5>\s*<h1[^>]*>\s*(.+?)\s*</h1>#is';
            if (preg_match($pattern, $page, $matchTitle)) {
                $eStore->setTitle($matchTitle[1]);
            }

            $pattern = '#fon\s*(\s*<[^>]*>\s*)*(\d[^<]+?)\s*<#i';
            if (preg_match($pattern, $storeInfoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }
            
            $pattern = '#fax\s*(\s*<[^>]*>\s*)*(\d[^<]+?)\s*<#i';
            if (preg_match($pattern, $storeInfoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[2]);
            }
            
            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $storeInfoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $storeInfoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#store_id\=(\d+)#';
            if (preg_match($pattern, $storeDetailUrl, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2], 'CH')
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        return $this->getResponse($cStores, $companyId);
    }
}