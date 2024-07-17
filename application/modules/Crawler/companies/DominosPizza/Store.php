<?php

/* 
 * Store Crawler für Domino's Pizza (ID: 72063)
 */

class Crawler_Company_DominosPizza_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.dominos.de/';
        $searchUrl = $baseUrl . 'store';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<ul[^>]*id="region-links"[^>]*>(.+?)</ul#s';
        if (!preg_match($pattern, $page, $regionUrlListMatch)) {
            throw new Exception ($companyId . ': unable to get region url list.');
        }
        
        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $regionUrlListMatch[1], $regionUrlMatches)) {
            throw new Exception ($companyId . ': unable to get any region urls from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($regionUrlMatches[1] as $singleRegionUrl) {
            $regionUrl = $baseUrl . $singleRegionUrl; 
            
            $sPage->open($regionUrl);
            $page = $sPage->getPage()->getResponseBody();
                        
            $pattern = '#<a[^>]*class="no-underline"[^>]*>(.+?)</a#s';
            if (!preg_match_all($pattern, $page, $storeDetailUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any store urls for: ' . $singleRegionUrl);
                continue;
            }

            $storeElements = $sPage->getDomElsFromUrlByClass($regionUrl, 'store-information', 'div', true);

            foreach ($storeElements as $storeElement) {
                $singleStoreDetailUrl = $sPage->getDomElsFromDomEl($storeElement, 'no-underline')[0]->getAttribute('href');
                $singleStoreDetailUrl = ltrim($singleStoreDetailUrl, '/');
                $storeDetailUrl = $baseUrl . $singleStoreDetailUrl;

                $storeHoursString = '';
                $storeHoursString = $this->extractStoreHours($sPage, $storeDetailUrl); 
                
                $pattern = '#-(\d+)$#';
                if (preg_match($pattern, $singleStoreDetailUrl, $storeNumberMatch)) {
                    $strStoreNumber = $storeNumberMatch[1];
                }
                
                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();
                
                $addressMatch = [];
                $pattern = '#<a[^>]*id="open-map-address"[^>]*>\s*([^<,]+?),?\s*<br[^>]*>\s*(\d{5}\s+[a-zäöü][^<]+?)\s*<#i';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }
                     
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<input[^>]*id="store-(lat|lon)"[^>]*value="([^"]+?)"#';
                if (preg_match_all($pattern, $page, $geoMatches)) {
                    $aGeo = array_combine($geoMatches[1], $geoMatches[2]);
                    $eStore->setLatitude($aGeo['lat'])
                            ->setLongitude($aGeo['lon']);
                }
                                
                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                if ($storeHoursString) {
                    $eStore->setStoreHours($storeHoursString);
                }
                
                $eStore->setAddress($addressMatch[1], ucwords($addressMatch[2]))
                        ->setPhoneNormalized($phoneMatch[1])
                        ->setWebsite($storeDetailUrl)
                        ->setStoreNumber($strStoreNumber);
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

    private function extractStoreHours($sPage, $storePage)
    {
        $elements = $sPage->getDomElsFromUrlByClass($storePage, 'trading-time', 'span', true);
        $storeOpeningHours = '';
        
        if (count($elements)) {

            $aDays = array(
                'Sonntag' => 'So',
                'Montag' => 'Mo',
                'Dienstag' => 'Di',
                'Mittwoch' => 'Mi',
                'Donnerstag' => 'Do',
                'Freitag' => 'Fr',
                'Samstag' => 'Sa'
            );
            $strTimes = '';

            foreach ($elements as $storeElement) {
                $day = $sPage->getDomElsFromDomEl($storeElement, 'trading-day')[0]->textContent;
                $hours = $sPage->getDomElsFromDomEl($storeElement, 'trading-hour')[0]->textContent;
                $day = trim($day);
                $hours = trim($hours);

                if (strpos($hours, ' - ') === FALSE) {
                    continue;
                }

                if (strlen($strTimes)) {
                    $strTimes .= ', ';
                }                
                $strTimes .= $aDays[$day] .' '. $hours; 
            }
            $storeOpeningHours = $strTimes;
        }
        return $storeOpeningHours;
    }
}