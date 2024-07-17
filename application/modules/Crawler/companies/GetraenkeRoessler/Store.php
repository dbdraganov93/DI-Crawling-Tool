<?php

/* 
 * Store Crawler für Getränke Rössler (ID: 71083)
 */

class Crawler_Company_GetraenkeRoessler_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.getraenke-roessler.de/';
        $searchUrl = $baseUrl . 'getraenkemaerkte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\s*href="getraenkemaerkte\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#</table>\s*<p\s*class="bodytext"[^>]*>\s*(.+?)\s*</p#s';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $storeAddressMatch[1]);
            
            $pattern = '#ffnungszeiten(.+?)</tbody#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }
            
            $pattern = '#(tele)?fon:?([^<]+?)<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[2]));
            }
            
            $pattern = '#sll=([^\&]+?),([^\&]+?)\&#';
            if (preg_match($pattern, $page, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }
            
            $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 2])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 2])))
                    ->setWebsite($storeDetailUrl)
                    ->setStoreNumber($eStore->getHash());
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}