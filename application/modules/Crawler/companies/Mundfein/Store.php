<?php

/**
 * Store Crawler für Mundfein (ID: 71506)
 */
class Crawler_Company_Mundfein_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.mundfein.de/';
        $searchUrl = $baseUrl . 'standorte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="standorte-infobox"[^>]*>(.+?)<div[^>]*style=\'clear:both;\'>#is';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if (preg_match('#<div[^>]*class="standorte-adresse"[^>]*>\s*<address>(.+?)<br[^>]*>(.+?)</address>\s*<address>(.+?)</address>.*?</div>#', $singleStore, $addressMatch)){
                $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[1]))
                        ->setCity($sAddress->extractAddressPart('city', $addressMatch[1]))
                        ->setStreet($sAddress->extractAddressPart('street', $addressMatch[2]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressMatch[2]))
                        ->setPhone($sAddress->normalizePhoneNumber($addressMatch[3]));                
            }
                                
            if (preg_match('#<div[^>]*class="standorte-oeffnungszeiten">(.+?)</div>#', $singleStore, $hoursMatch)){
                $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
            }
            
            $cStores->addElement($eStore);
        }
       
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}