<?php

/**
 * Store Crawler für Badische Backstub' (ID: 71422)
 */
class Crawler_Company_BadischeBackstub_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.badische-backstub.de/';
        $searchUrl = $baseUrl . 'fachgeschaefte/standorte/?type=99';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#/fachgeschaefte/standorte/(\?tx_stores%5Buid%5D=[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleUrl) {
            $storeUrl = $baseUrl . 'fachgeschaefte/standorte/' . $singleUrl;
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div\s*class="tx_stores_pi1[^>]*>(.+?)</div>\s*</div>#s';
            if (!preg_match($pattern, $page, $storeDetailMatch)) {
                $this->_logger->err($companyId . ': unable to get store details: ' . $singleUrl);
                continue;
            }
            
            $pattern = '#<h2[^>]*>\s*(.+?)\s*</h2#';
            if (!preg_match($pattern, $storeDetailMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);
            
            $pattern = '#Öffnungszeiten(.+)#s';
            if (preg_match($pattern, $storeDetailMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#So/Feiertag#', 'So', $storeHoursMatch[1])));
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress)-3])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-3])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress)-2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress)-2]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[count($aAddress)-1]))
                    ->setStoreNumber($eStore->getHash());
            
            if (count($aAddress) == 4) {
                $eStore->setSubtitle($aAddress[0]);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
