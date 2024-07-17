<?php

/* 
 * Store Crawler für Mega Möbel SB (ID: 71738)
 */

class Crawler_Company_MegaMoebel_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://megashop.brotz.de/';
        $searchUrl = $baseUrl . 'standorte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\s*name="standort([0-9].+?)<form#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#^([0-9]+?)"#';
            if (!preg_match($pattern, $singleStore, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number: ' . $singleStore);
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*([0-9]{5}\s+[A-Z]+.+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeNumberMatch[1]);
                continue;
            }
            
            $pattern = '#fon:?([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#fax:?([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#img\s*src="\/([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $storeImageMatch)) {
                $eStore->setImage($baseUrl . preg_replace('#sml#', 'nor', $storeImageMatch[1]));
            }
            
            $eStore->setStoreNumber($storeNumberMatch[1])
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddressMatch[1])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressMatch[1])))
                    ->setCity($sAddress->extractAddressPart('city', $storeAddressMatch[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressMatch[2]));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}