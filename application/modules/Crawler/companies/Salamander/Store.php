<?php

/* 
 * Store Crawler für Salamander (ID: 28826)
 */

class Crawler_Company_Salamander_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.salamander.de/';
        $searchUrl = $baseUrl . 'haendlersuche/fachhaendler/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $aZipcodes = $sDbGeo->findZipCodesByNetSize(50);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aParams = array('tx_nxsshop_pi1[land]' => 'DE');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['tx_nxsshop_pi1[zip]'] = $singleZipcode;
            
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<table[^>]*class="retailers"[^>]*>(.+?)</table#s';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no store list for ' . $singleZipcode);
                continue;
            }
            
            $pattern = '#<b[^>]*>\s*Salamander[^<]+?</b>\s*(.+?)\s*<tr[^>]*>\s*<td[^>]*>\s*</td>\s*<td[^>]*>\s*Web#s';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->info($companyId . ': no stores from list for ' . $singleZipcode);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>\s*([^,]+?)\s*,\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#Tel\.?\s*:?\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $eStore->setAddress($addressMatch[1], $addressMatch[2]);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}