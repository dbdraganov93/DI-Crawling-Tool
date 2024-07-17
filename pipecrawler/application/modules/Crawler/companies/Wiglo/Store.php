<?php

/* 
 * Store Crawler für Wiglo Wunderland (ID: 69249)
 */

class Crawler_Company_Wiglo_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.wiglo.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#ul\s*id="navi"[^>]*>(.+?)</ul#s';
        if (!preg_match_all($pattern, $page, $storeListMatches)) {
            throw new Exception($companyId . ': unable to get any store lists.');
        }
        
        foreach ($storeListMatches[1] as $singleStoreList) {
            $pattern = '#a\s*href="\.\/([^"]+?)"#';
            if (preg_match_all($pattern, $singleStoreList, $storeUrlMatches)) {
                foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                    $aStoreUrls[] = $searchUrl . $singleStoreUrl;
                }
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreDetailUrl) {
            $sPage->open($singleStoreDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#div\s*class="entry-content"[^>]*>(.+?)</div>\s*</div>#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId. ': unable to get store infos: ' . $singleStoreDetailUrl);
            }
            
            $pattern = '#</strong>(.+?)<br[^>]*>\s*<a#';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeAddressMatch)) {
                $this->_logger->err($companyId. ': unable to get store address: ' . $singleStoreDetailUrl);
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[1]);
            
            $pattern = '#>[^<]+?ffnungszeiten(.+)#';
            if (preg_match($pattern, $storeInfoMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
                        
            $eStore->setWebsite($singleStoreDetailUrl)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[ count($aAddress) - 4])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 4])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 3]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 3]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[count($aAddress) - 2]))
                    ->setFax($sAddress->normalizePhoneNumber($aAddress[count($aAddress) - 1]))
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}