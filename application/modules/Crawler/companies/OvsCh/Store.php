<?php

/* 
 * Store Crawler für OVS CH (ID: 72191)
 */

class Crawler_Company_OvsCh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://negozi.ovs.it/';
        $searchUrl = $baseUrl . 'googleXml/GetWorldSearch.ashx?rand=1&CountryID=CH&lang=de';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $xmlString = simplexml_load_string($page);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlString->marker as $singleMarker) {
            $sPage->open((string)$singleMarker->attributes()['url']);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*(.+?)\s*</#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos.');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $strTimes = '';
            for ($i = 0; $i < count($infoMatches[1]); $i++) {
                if (preg_match('#address$#', $infoMatches[1][$i]) && preg_match('#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4})#', $infoMatches[2][$i], $addressMatch)) {
                    $eStore->setStreetAndStreetNumber($addressMatch[1], 'CH')
                            ->setZipcode($addressMatch[2]);
                    continue;
                }
                
                if (preg_match('#addressLocality#', $infoMatches[1][$i])) {
                    $eStore->setCity($infoMatches[2][$i]);
                    continue;
                }
                
                if (preg_match('#telephone#', $infoMatches[1][$i])) {
                    $eStore->setPhoneNormalized(preg_replace('#Tel:?\s*0041\/#', '', $infoMatches[2][$i]));
                    continue;
                }
                
                if (preg_match('#openingHours#', $infoMatches[1][$i])) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= preg_replace('#\/#', '-', $infoMatches[2][$i]);
                }
            }
            
            $eStore->setStoreHoursNormalized($strTimes)
                    ->setWebsite((string)$singleMarker->attributes()['url']);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}