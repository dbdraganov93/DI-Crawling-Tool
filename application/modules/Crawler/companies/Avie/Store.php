<?php

/* 
 * Store Crawler für Avie (ID: 29118)
 */

class Crawler_Company_Avie_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.avie.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#pharmacy_search\.autocomplete\(\{\s*source:\s*(\[[^\]]+?\])#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleStoreUrl) {
            $sPage->open(preg_replace('#start#', 'ueber_uns/vorstellung', $singleStoreUrl->url));
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<h2[^>]*>\s*Kontakt\s*</h2>(.+?)</p>\s*</p>#s';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $singleStoreUrl->url);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÜÖ][^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $singleStoreUrl->url);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#fon:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $infoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}