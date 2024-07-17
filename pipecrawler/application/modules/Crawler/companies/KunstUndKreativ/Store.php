<?php

/**
 * Store Crawler für Kunst und Kreativ (ID: 68075)
 */
class Crawler_Company_KunstUndKreativ_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.kuk-markt.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#Deutschland</h2>(.+?)<h2#s';
        if (!preg_match($pattern, $page, $listMatch)) {
            throw new Exception($companyId .': unable to get store list.');
        }
        
        $pattern = '#href="\/(news[^"]+?)"#';
        if (!preg_match_all($pattern, $listMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($storeUrlMatches[1] as $singleUrl) {
            $url = $baseUrl . str_replace(array('ä', 'ö', 'ü'), array('%C3%A4', '%C3%B6', '%C3%BC'), $singleUrl);
            if (!$sPage->open($url)) {
                $this->_logger->err($companyId . ': unable to open store detail page. url: ' . $url);
                continue;
            }
            
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<table[^>]*>(.+?)</table#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->err($companyId . ': unable to get detail list. url: ' . $url);
                continue;
            }
            
            $pattern = '#<td[^>]*>(.+?)</td>#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeDetailMatches)) {
                $this->_logger->err($companyId . ': unable to get any details. url: ' . $url);
                continue;
            }
            
            foreach ($storeDetailMatches[1] as $singleDetail) {
                if (preg_match('#^[0-9]{5}#', $singleDetail)) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $singleDetail))
                        ->setCity($sAddress->extractAddressPart('city', $singleDetail));
                }
                
                if (preg_match('#Öffnungszeiten#', $singleDetail)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#von\s+#', ' ', $singleDetail)));
                }
                
                if (preg_match('#Telefon#', $singleDetail) && is_null($eStore->getPhone())) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($singleDetail));
                }
                
                if (preg_match('#Fax#', $singleDetail) && is_null($eStore->getFax())) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($singleDetail));
                }
                
                if (preg_match('#E-Mail:\s+(.+)#', $singleDetail, $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }
                
                if (preg_match('#[a-z.]+\s+([0-9]+(\s*\-\s*[0-9]+)*[a-zA-Z]*)$#', preg_replace('#\s*\?\s*#', '-', utf8_decode(trim($singleDetail)))) && is_null($eStore->getStreet())) {
                    $eStore->setStreet($sAddress->extractAddressPart('street', $singleDetail))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', preg_replace('#\s*\?\s*#', '-', utf8_decode($singleDetail))));
                }

            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}