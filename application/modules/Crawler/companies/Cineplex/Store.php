<?php

/* 
 * Store Crawler für Cineplex (ID: 72070)
 */

class Crawler_Company_Cineplex_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.cineplex.de/';
        $infoUrl = $baseUrl . 'infos/anfahrt-und-oeffnungszeiten/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*class="standort"[^>]*data-cityslug[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $sPage->open($infoUrl . $singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#class="map-change"\s*data-geodata="([^,]+?),([^"]+?)"\s*data-address="([^\(<]+?)\s*(\([^\)]+?\)\s*)?<br>([^"]+?)"#';
            if (!preg_match($pattern, $page, $addressGeoMatch)) {
                $this->_logger->err($companyId . ': unable to get store address - ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#ffnungszeiten<[^>]*>(.+?)</tbody#';
            if (preg_match($pattern, $page, $storeHoursListMatch)) {
                $pattern = '#<td[^>]*class="desc-col"[^>]*>\s*(.+?)\s*</td>'
                        . '\s*<td[^>]*>\s*(.+?)\s*</td>\s*<td[^>]*>\s*(.+?)\s*</td>#';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches)) {
                    $strStoreHoursNotes = '';
                    for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                        if (strlen($strStoreHoursNotes)) {
                            $strStoreHoursNotes .= ', ';
                        }
                        
                        $strStoreHoursNotes .= strip_tags($storeHoursMatches[1][$i]
                                . ' ' . $storeHoursMatches[2][$i] . ' bis '
                                . $storeHoursMatches[3][$i]);
                    }
                }
            }
            
            $eStore->setLatitude($addressGeoMatch[1])
                    ->setLongitude($addressGeoMatch[2])
                    ->setStreetAndStreetNumber($addressGeoMatch[3])
                    ->setZipcodeAndCity($addressGeoMatch[5])
                    ->setStoreHoursNotes($strStoreHoursNotes)
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}