<?php

/* 
 * Store Crawler für aktiv & irma (ID: 71911)
 */

class Crawler_Company_AktivIrma_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.aktiv-irma.de/';
        $searchUrl = $baseUrl . 'standorte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*id="primNavBox"[^>]*>(.+?)</div#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list: ' . $searchUrl);
        }
        
        $pattern = '#a[^>]*href="(standorte/[^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list: ' . $searchUrl);
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($storeMatches[1] as $singleStoreUrl) {
            $pattern = '#verwaltung#i';
            if (preg_match($pattern, $singleStoreUrl)) {
                continue;
            }
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="contentContainer"[^>]*>(.+?)</div>\s*</div#s';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#fon([^<]+?)<#';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax([^<]+?)<#';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#class="mail"[^>]*>\s*([^\s]+?)(\s+|<)#';
            if (preg_match($pattern, $infoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten:?(\s*<[^>]*>\s*)*[^<]+(\s*<[^>]*>\s*)*(.+?)(<b[^>]*>|</p)#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[3]);
            }
            
            $pattern = '#plopp#';
            if (preg_match($pattern, $storeDetailUrl)) {
                $eStore->setSubtitle('Plopp Getränkemarkt');
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}