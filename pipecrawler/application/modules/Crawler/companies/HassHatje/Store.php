<?php

/* 
 * Store Crawler für Hass+Hatje (ID: 71694)
 */

class Crawler_Company_HassHatje_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.hass-hatje.de/';
        $searchUrl = $baseUrl . 'publish/15301e8f_7e90_43c1_7f21e037bbac4ff4.cfm';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#li\s*id="h_m_195"[^>]*>.+?menItem"[^>]*>(.+?)</ul#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a\s*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $storeDetailUrl = $baseUrl . $singleUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<table[^>]*>(.+?)</table#';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#</strong>\s*<br[^>]*>\s*(.+?)\s*<br[^>]*>\s*(.+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatches[1][0], $addressMatch)) {
                $this->_logger->err($companyId. ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#>\s*fon(.+?)\s*<[^>]*>\s*fax(.+?)<#i';
            if (preg_match($pattern, $storeInfoMatches[1][0], $contactMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($contactMatch[1]))
                        ->setFax($sAddress->normalizePhoneNumber($contactMatch[2]));
            }
            
            $pattern = '#src="(binarydata[^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($storeInfoMatches[1][1]))
                    ->setWebsite($storeDetailUrl)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1])))
                    ->setCity($sAddress->extractAddressPart('city', $addressMatch[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[2]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}