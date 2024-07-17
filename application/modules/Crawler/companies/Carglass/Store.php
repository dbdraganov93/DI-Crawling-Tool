<?php

/* 
 * Store Crawler für Carglass (ID: 28663)
 */

class Crawler_Company_Carglass_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.carglass.de/';
        $searchUrl = $baseUrl . 'carglass-service-center-in-deutschland/';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();
        $aStoreUrls = array();
        
        $aZipCodes = $sDb->findZipCodesByNetSize(10);
        
        
        foreach ($aZipCodes as $singleZip) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $oPage->setUseCookies(true);
            $sPage->setPage($oPage);
            $aParams = array(
                'location' => $singleZip,
                'submit' => 'Suche'
            );
            
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a\s*href="\/standorte\/([^"]+?)"\s*class="vmore"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                continue;
            }
            foreach ($storeUrlMatches[1] as $singleUrl) {
                if (!in_array($singleUrl, $aStoreUrls)) {
                    $aStoreUrls[] = $singleUrl;
                }
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        foreach ($aStoreUrls as $singleStoreUrl) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('GET');
            $sPage->setPage($oPage);
            
            $storeDetailUrl = $baseUrl . 'standorte/' . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
           
            $pattern = '#icon-info(.+?)<a\s*href="\/termin-vereinbaren\/"#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->info($companyId . ': unable to get store info: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#<p[^>]*>\s*(.+?)\s*([0-9]{5}.+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddressMatch[1])))
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