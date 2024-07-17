<?php

/* 
 * Store Crawler für Cinemaxx (ID: 72066)
 */

class Crawler_Company_Cinemaxx_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.cinemaxx.com/';
        $searchUrl = $baseUrl . 'de/unternehmen/standorte.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#Listenansicht(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*target="_blank"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $aStoreUrlsToChange = array(
            'http://www.cinemaxx.de/hannover-raschplatz' => 'https://www.cinemaxx.de/hannover',
            'http://www.cinemaxx.de/stutgart-bosch-areal' => 'https://www.cinemaxx.de/stuttgart-liederhalle/',
            'http://www.cinemaxx.de/wandsbek' => 'https://www.cinemaxx.de/hamburg-wandsbek/',
            'http://www.cinemaxx.de/halle-charlottencenter' => 'https://www.cinemaxx.de/halle'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStoreUrl) {
            if (array_key_exists($singleStoreUrl, $aStoreUrlsToChange)) {
                $singleStoreUrl = $aStoreUrlsToChange[$singleStoreUrl];
            }
            $sPage->open($singleStoreUrl . '/kinoinfo/');
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#ANFAHRT\s*ZUM\s*KINO\s*(\s*<[^>]*>\s*)*[^<]*?<[^>]*>\s*([^<]+?)\s*<[^>]*>\s*(\(?[^<]+?\)?\s*<[^>]*>\s*)?(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten(.+?)</div>\s*</div>\s*</div>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#ab([^<]+)#i', '$1-24:00', $storeHoursMatch[1]));
            }
            
            $pattern = '#RESERVIERUNGSHOTLINE\s*(\s*<[^>]*>\s*)*(\d+[^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }
            
            $pattern = '#PARKMÖGLICHKEITEN\s*(\s*<[^>]*>\s*)*([^<»]+?)\s*»<#';
            if (preg_match($pattern, $page, $parkingMatch)) {
                $eStore->setParking($parkingMatch[2]);
            }
            
            $eStore->setAddress($addressMatch[2], $addressMatch[4])
                    ->setWebsite($singleStoreUrl)
                    ->setToilet(1);
            
            if (strlen($addressMatch[3])) {
                $eStore->setSubtitle(strip_tags(preg_replace('#(\(|\))#', '', $addressMatch[3])));
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}