<?php

/**
 * Store Crawler für Sachsenlotto (ID: 71719)
 */
class Crawler_Company_Sachsenlotto_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.sachsenlotto.de';
        $searchUrl = $baseUrl . '/portal/user/poi/verkaufsstellen/suchen.do?cityOrZip=0';

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $page = $sPage->getPage();
        $page->setUseCookies(true);
        $sPage->setPage($page);
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (preg_match_all(
            '#createMarker\('
                .'map\,"([^"]*)"\,"([^"]*)","([^"]*)"'
                .'\);#is',
            $page,
            $matchStores)
        ) {
            foreach ($matchStores[1] as $key => $lat) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setLatitude($matchStores[1][$key]);
                $eStore->setLongitude($matchStores[2][$key]);

                $storeText = $matchStores[3][$key];

                $storeText = preg_replace('#<div[^>]*>#', '', $storeText);
                $aStoreText = explode('</div>', $storeText);
                $eStore->setSubtitle($aStoreText[0]);
                $eStore->setStreetAndStreetNumber($aStoreText[1]);
                $eStore->setZipcodeAndCity($aStoreText[2]);
                $cStores->addElement($eStore);
            }

        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }      
}