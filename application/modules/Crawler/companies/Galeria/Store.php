<?php

/* 
 * Store Crawler für Galeria (ID: 20)
 */

class Crawler_Company_Galeria_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.galeria.de/filialfinder';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<option\svalue="(\d+?)"#';
        if (!preg_match_all($pattern, $page, $storeNumberMatches)) {
            throw new Exception($companyId . ': unable to get store numbers');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeNumberMatches[1] as $storeNumber) {
            $this->_logger->info('crawling store: ' . $storeNumber);
            $cityMatch = [];
            $streetMatch = [];
            $zipcodeMatch = [];
            $imageMatches = [];
            $eStore = new Marktjagd_Entity_Api_Store();
            $page = $sPage->getDomElFromUrlByID('https://www.galeria.de/on/demandware.store/Sites-Galeria-Site/de/Stores-Details?StoreID=' . $storeNumber, 'primary');

            $pattern = '#itemprop="streetAddress">([^<]+?)<#im';
            if (!preg_match_all($pattern, $page->C14N(), $streetMatch)) {
                $this->_logger->warn('unable to find street for ' . $storeNumber);
                var_dump($page->C14N());
            } else {
                $eStore->setStreetAndStreetNumber($streetMatch[1][0]);
            }

            $pattern = '#itemprop="postalCode">([^<]+?)<#im';
            if (!preg_match_all($pattern, $page->C14N(), $zipcodeMatch)) {
                $this->_logger->warn('unable to find zip code for ' . $storeNumber);
                var_dump($page->C14N());
            } else {
                $eStore->setZipcode($zipcodeMatch[1][0]);
            }

            $pattern = '#itemprop="addressLocality">([^<]+?)<#im';
            if (!preg_match_all($pattern, $page->C14N(), $cityMatch)) {
                $this->_logger->warn('unable to find city for ' . $storeNumber);
                var_dump($page->C14N());
            } else {
                $eStore->setCity($cityMatch[1][0]);
            }

            $pattern = '#img\ssrc="(https://www.galeria.de/on/demandware.static/-/Sites-Galeria-Library/default/.+?/stores.+?)"#';
            if (preg_match_all($pattern, $page->C14N(), $imageMatches)) {
                $eStore->setImage($imageMatches[1][0]);
            }

            $eStore->setStoreNumber($storeNumber);

//            <span datetime="Montag&#xA;         - Samstag 10:00 Uhr - 18:00 Uhr" itemprop="openingHours" style="display:none;">Montag
//            - Samstag 10:00 Uhr - 18:00 Uhr</span>
//            <span class="days g-opening__days">
//            Montag
//            - Samstag
//            </span>
//            <span class="hours g-opening__hours">
//            10:00 Uhr - 18:00 Uhr
//            </span><br></br>


            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
