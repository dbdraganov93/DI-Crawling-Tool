<?php

/**
 * Store Crawler für Nanu Nana (ID: 22392)
 */
class Crawler_Company_NanuNana_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://unternehmen.nanu-nana.de/';
        $searchUrl = $baseUrl . 'filialfinder/?tx_stores_stores[filter]='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&tx_stores_stores[country]=DE';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 5);
        
        $cStores = new Marktjagd_Collection_Api_Store();        
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a\s*rel="marker(.+?)</a#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId. ': unable to get any stores: ' . $singleUrl);
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<h3[^>]*>(.+?)</h3>\s*<span[^>]*>(.+?)<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId. ': unable to get store address: ' . $singleStore);
                }
                $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);
                
                $pattern = '#ffnungszeiten(.+?)Telefon#s';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }
                
                $pattern = '#Telefon.+?<span[^>]*>(.+?)<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                }
                
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 1])))
                    ->setCity($sAddress->extractAddressPart('city', $addressMatch[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[2]));
                
                if (count($aAddress) > 1) {
                    $eStore->setSubtitle($aAddress[0]);
                }
                
                $eStore->setStoreNumber($eStore->getHash());
                
            $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}