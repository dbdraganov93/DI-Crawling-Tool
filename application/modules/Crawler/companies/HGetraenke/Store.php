<?php

/**
 * Store Crawler für H-Getränke Markt (ID: 71711)
 */
class Crawler_Company_HGetraenke_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.heurich.de';
        $searchUrl = $baseUrl . '/heurich/getraenkemaerkte/index.php';
        
        $sPage = new Marktjagd_Service_Input_Page();        
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="infowindow"[^>]*>(.+?)</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if (preg_match('#<span[^>]*class="row_2"[^>]*>(.+?)</span>#', $singleStore, $streetMatch)){
                $eStore->setStreet($sAddress->extractAddressPart('street', $streetMatch[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $streetMatch[1]));
            }
            
            if (preg_match('#<span[^>]*class="row_3"[^>]*>(.+?)</span>#', $singleStore, $cityMatch)){
                $eStore->setCity($sAddress->extractAddressPart('city', $cityMatch[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $cityMatch[1]));
            }
            
            if (preg_match('#<span[^>]*class="row_4"[^>]*>(.+?)</span>#', $singleStore, $phoneMatch)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                        
            }
            
            if (preg_match('#<span[^>]*class="row_6"[^>]*>(.+?)</span>#', $singleStore, $nameMatch)){
                $eStore->setSubtitle(trim($nameMatch[1]));                        
            }            
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}