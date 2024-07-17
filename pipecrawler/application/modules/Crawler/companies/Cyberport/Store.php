<?php

/*
 * Store Crawler für Cyberport (ID: 67998)
 */

class Crawler_Company_Cyberport_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.cyberport.de/';
        $searchUrl = $baseUrl . 'stores';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<strong[^>]*>Cyberport\s+Stores</strong>(.+?)</ul>\s*</li>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#>([^<]+?)<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel\.:([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax:([^<]+?)<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#>\s*([^<]+?Uhr)\s*<#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }
            
            $pattern = '#onclick="showLightBox\(\'\/newajax\/content\/cmsimage\',\s*\{\'PATH\':\'([^\']+?)\'#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
