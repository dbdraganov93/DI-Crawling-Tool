<?php

/* 
 * Store Crawler für Le Crobag (ID: 29091)
 */

class Crawler_Company_LeCrobag_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.lecrobag.de/';
        $searchUrl = $baseUrl . 'shops.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="option"[^>]*>(.+?)</div>\s*</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*class="address"[^>]*>(\s*Anschrift:<br[^>]*>\s*)?(.+?)\s*</div#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[2]);
            
            for($i = 0; $i < count($aAddress); $i++) {
                if (preg_match('#^[0-9]{5}#', $aAddress[$i])) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[$i]))
                            ->setCity($sAddress->extractAddressPart('city', $aAddress[$i]))
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[$i - 1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[$i - 1])));
                    if (preg_match('#(bahnsteig|gleis|kuppelhalle|zwischendeck|im\s*[su]-b|durchgang|nord-süd-bahn)#i', $eStore->getStreet())) {
                        $eStore->setStreetNumber(NULL)
                                ->setStreet($aAddress[$i - 2]);
                    }
                    continue;
                }
                if (preg_match('#Tel#', $aAddress[$i])) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($aAddress[$i]));
                }
            }
            
            $pattern = '#<div[^>]*class="times"[^>]*>(.+?)</div#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1], 'text', true));
                if (preg_match('#durchgehend\s*geöffnet#', $storeHoursMatch[1])) {
                    $eStore->setStoreHours('Mo-So 00:00-24:00');
                }
            }
                        
            if (strlen($eStore->getZipcode()) != 5) {
                continue;
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}