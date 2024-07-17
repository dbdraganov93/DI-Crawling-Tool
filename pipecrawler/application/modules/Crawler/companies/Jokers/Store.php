<?php

/**
 * Store Crawler für Jokers (ID: 72027)
 */

class Crawler_Company_Jokers_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.jokers.de';
        $searchUrl = $baseUrl . '/service/filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li\s*property="chainStoreItem"\s*'
            . 'data-id="([^"]*)"\s*'
            . 'data-lat="([^"]*)"\s*'
            . 'data-lng="([^"]*)"\s*'
            . 'data-street="([^"]*)"\s*'
            . 'data-description="([^"]*)"\s*'
            . 'data-zip="([^"]*)"\s*'
            . 'data-city="([^"]*)"\s*'
            . 'data-phone="([^"]*)"\s*'
            . 'data-hours="([^"]*)"\s*'
            . '>#is';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        foreach ($storeMatches[1] as $key => $value) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($storeMatches[1][$key]);
            $eStore->setLatitude($storeMatches[2][$key]);
            $eStore->setLongitude($storeMatches[3][$key]);
            $eStore->setStreetAndStreetNumber($storeMatches[4][$key]);
            $eStore->setZipcode($storeMatches[6][$key]);
            $eStore->setCity($storeMatches[7][$key]);
            $eStore->setPhoneNormalized($storeMatches[8][$key]);
            $eStore->setStoreHoursNormalized($storeMatches[9][$key]);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}