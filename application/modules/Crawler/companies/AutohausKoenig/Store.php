<?php

/* 
 * Store Crawler für Autohaus König (ID: 71992)
 */

class Crawler_Company_AutohausKoenig_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $aBaseUrls = array(
        //    'Dacia' => 'http://dacia-koenig.de/',
       //     'Jeep' => 'http://jeep-koenig.de/',
            'Renault' => 'http://renault-koenig.de/'
        );
        $sPage = new Marktjagd_Service_Input_Page();
        
        $aStoreUrls = array();
        foreach ($aBaseUrls as $carType => $singleBaseUrl) {
            $sPage->open($singleBaseUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a[^>]*href="(filialen[^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                throw new Exception($companyId . ': unable to get store urls: ' . $singleBaseUrl);
            }
            
            foreach (array_unique($storeUrlMatches[1]) as $singleStoreUrl) {
                if (!array_key_exists($singleStoreUrl, $aStoreUrls)) {
                    $aStoreUrls[$singleStoreUrl][] = $carType;
                    continue;
                }
                $aStoreUrls[$singleStoreUrl][] = $carType;
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreDetailUrl => $carTypes) {
            $storeDetailUrl = $aBaseUrls[$carTypes[0]] . $singleStoreDetailUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*id="filial-data-contact"[^>]*>(.+?)</div#';
            if (!preg_match($pattern, $page, $contactMatch)) {
                $this->_logger->err($companyId . ': unable to get store contact info list: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $contactMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address from contact info list: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#Tel\.?\:?\s*(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $contactMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }
            
            $pattern = '#Fax\.?\:?\s*(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $contactMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[2]);
            }
            
            $pattern = '#Lager\:?\s*(\s*<[^>]*>\s*)*([^<]+?)\s*<#';
            if (preg_match($pattern, $contactMatch[1], $phoneMatch)) {
                $eStore->setText('Telefonnummer Lager: ' . $phoneMatch[2]);
            }
            
            $pattern = '#<div[^>]*id="filial-data-opening-times"[^>]*>(.+?)(<hr|</div)#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#<div[^>]*id="filial-data-service-times"[^>]*>\s*(.+?)\s*</div#';
            if (preg_match($pattern, $page, $storeHoursNotesMatch)) {
                $eStore->setStoreHoursNotes(strip_tags(preg_replace(array('#(Uhr)<[^>]*>#', '#<[^>]*>#', '#\s{2,}#'), array('$1,', ' $1', ' '), $storeHoursNotesMatch[1])));
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl)
                    ->setSection(implode(', ', $carTypes))
                    ->setDistribution(implode(', ', $carTypes));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}