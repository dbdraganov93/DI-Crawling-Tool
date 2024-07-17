<?php

/**
 * Store Crawler für RaiffeisenMarkt (ID: 22305)
 */
class Crawler_Company_RaiffeisenMarkt_Store extends Crawler_Generic_Company {    
    public function crawl($companyId) {
        $baseUrl = 'http://www.raiffeisenmarkt.de';
        $storeUrl = '/standorte/marktDetailSeite.jsp?markt=';
        $searchUrl = $baseUrl . '/standorte/ausgabeausbildungsstandorte.jsp'
                . '?Suche=Klickpunkt%3Cbr%3E' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '%20' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . ''
                . '&zeige=';

        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.4);

        $i = 0; $c = 0;
        $aStoreIds = array ();
        foreach ($aUrls as $singleUrl) {
            $c++; $i = 1;
            $this->_logger->info('open ' . $baseUrl . '/.../' . substr($singleUrl, -18, 11) . ' (' . $c . ' of ' . count($aUrls) . ')');
            $sPage->open($singleUrl . $i . '&radius100=1');            
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#layerAus\(([^\)]+?)\)#i';
            if (!preg_match_all($pattern, $page, $aSubPages)) {
                $this->_logger->info('Company ID-' . $companyId . ': unalbe to preg_match subpage count -> no stores');
                continue;
            }

            for ($j = 1; $j <= count($aSubPages[1]); $j++) {
                $this->_logger->info('SubPage : ' . $j . ' of '. count($aSubPages[1]));
                if ($j !== 1) {
                    $sPage->open($singleUrl . $j . '&radius100=1');
                    $page = $sPage->getPage()->getResponseBody();
                }

                if (!preg_match_all('#<div.+?getMarktInfos\(([^\)]+?)\).+?drawPoi\((\d+\.\d+\,\d+\.\d+)#i', $page, $aIdMatches)) {
                        $this->_logger->info('preg_match for store ids failed.');
                        continue ;
                } elseif (count($aIdMatches[1]) < 2) {
                        $this->_logger->info('no store ids found.');
                        continue;
                }

                foreach ($aIdMatches[1] as $key => $sStoreId) {
                    if (!array_key_exists($sStoreId, $aStoreIds)) {
                        $aStoreIds[$sStoreId] = $aIdMatches[2][$key];
                    } else {
                        continue;
                    }
                }
            }
        }

        foreach ($aStoreIds as $key => $sLatLng) {
            $this->_logger->info('open ' . $baseUrl . $storeUrl . $key);
            $sPage->open($baseUrl . $storeUrl . $key);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $aGeo = preg_split('#\,#', $sLatLng);
            $eStore->setLongitude($aGeo[0])
                    ->setLatitude($aGeo[1]);

            if (!preg_match_all('#<p[^>]*>(.+?)</p>#', $page, $storeLines)){
                $this->warn('Company ID-' . $companyId . 'cannot match store address for store: ' . $singleStoreId);
            }

            foreach ($storeLines as $storeLine){
                $eStore->setWebsite($baseUrl . $storeUrl . $key);

                $addressLines = preg_split('#<br[^>]*>#', $storeLine[1]);
                $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                        ->setCity($sAddress->extractAddressPart('city', $addressLines[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]));

                foreach ($storeLine as $singleLine){
                    if (preg_match('#href="mailto:([^"]+)"#', $singleLine, $mailMatch)){
                        $eStore->setEmail($mailMatch[1]);
                    }

                    if (preg_match('#Tel\.*\s*([^<]+?)<#i', $singleLine, $phoneMatch)){
                        $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
                    }

                    if (preg_match('#Fax\.*\s*([^<]+?)<?$#i', $singleLine, $faxMatch)){
                        $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
                    }
                }
            }

            $pattern = '#<div\s+class=\"tab_oz\"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>#i';
            if (!preg_match($pattern, $page, $aStoreHoursMatch)) {
                $this->_logger->warn('Company ID-' . $companyId . 'cannot match store opening hours for store: ' . $singleStoreId);
            } else {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#<\/div>#', ' ', preg_replace('#<div[^>]+?>#', '', $aStoreHoursMatch[1]))));
            }

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
