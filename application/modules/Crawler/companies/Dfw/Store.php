<?php

/*
 * Store Crawler für Dürrröhrsdorfer Fleisch- und Wurstwaren (ID: 28555)
 */

class Crawler_Company_Dfw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.dfw24.de/';
        $searchUrl = $baseUrl . 'filial-finder';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="(filiale-detail[^"]+?)"#s';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="adresse"[^>]*>(.+?)</div#s';
            if (!preg_match($pattern, $page, $addressListMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#s';
            if (!preg_match($pattern, $addressListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address details: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#href="tel:([^"]+?)"#s';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#<h[^<]+?ffnungszeiten(.+?)</div#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<img[^>]*>\s*<p[^>]*>\s*<strong[^>]*>[^<]+?</strong>\s*<br[^>]*>(.+?)</div#s';
            if (preg_match($pattern, $page, $storeHoursNotesMatch)) {
                $eStore->setStoreHoursNotes(trim(strip_tags(preg_replace('#\s*<p[^>]*>\s*#', '. ', $storeHoursNotesMatch[1]))));
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
