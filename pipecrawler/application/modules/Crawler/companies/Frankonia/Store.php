<?php

/* 
 * Store Crawler für Frankonia (ID: 29224)
 */

class Crawler_Company_Frankonia_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.frankonia.de/';
        $searchUrl = $baseUrl . 'service/filialen/filialfinder.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
                
        $pattern = '#<li[^>]*class="fr-left-menu__sub-item[^"]*fr-left-menu__child-item"[^>]*>\s*<a[^>]*href="\/(service\/filialen\/[^"]+?)"[^>]*class="fr-left-menu__link"[^>]*>#s';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreUrl) {            
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $this->_logger->info($companyId . ': opening ' . $storeDetailUrl);
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#Kontaktdaten(.+?)</div#';
            if (!preg_match($pattern, $page, $storeInfoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from info list: ' . $storeInfoListMatch[1]);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Tel\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $storeInfoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#Fax\.?:?\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $storeInfoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#Unser\s*Service-Angebot(.+?)</ul#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            if (preg_match('#schiessanlagen#', $eStore->getWebsite())) {
                $eStore->setSubtitle('Schießanlage');
            }
            
            if (preg_match('#outlet#', $eStore->getWebsite())) {
                $eStore->setSubtitle('Outlet');
            }
            
            $cStores->addElement($eStore);            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}