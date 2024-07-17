<?php

/*
 * Store Crawler für Ayk Sonnenstudios (ID: 29061)
 */

class Crawler_Company_Ayk_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ayk.de/';
        $searchUrl = $baseUrl . 'studio-finder-liste-mit-ergebnissen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipCodes = $sDb->findZipCodesByNetSize(50);
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $singleZipCode) {
            $aParams = array(
                'tx_locator_pi1[country]' => 'de',
                'tx_locator_pi1[mode]' => 'search',
                'tx_locator_pi1[radius]' => '200',
                'tx_locator_pi1[zipcode]' => $singleZipCode
            );

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<form(.+?)</a>\s*</div#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores for zipcode: ' . $singleZipCode);
                continue;
            }
            foreach ($storeMatches[1] as $singleStoreMatch) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $pattern = '#<p[^>]*>(.+?)</p#';
                if (!preg_match($pattern, $singleStoreMatch, $addressMatch)) {
//                    $this->_logger->info($companyId . ': unable to get store address for store: ' . $singleStoreMatch);
                    continue;
                }
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
                
                $strTimes = '';
                $pattern = '#(\:00|Uhr)$#s';
                foreach ($aAddress as $singleAddress) {
                    if (preg_match($pattern, $singleAddress)) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleAddress;
                    }
                }

                $pattern = '#href="([^"]+?)"#';
                if (preg_match($pattern, $aAddress[count($aAddress) - 1], $urlMatch)) {
                    $eStore->setWebsite($urlMatch[1]);
                }
                
                
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#(\s*u\.)?\s*Feiertag(s)?\s*#s', '', $strTimes)))
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                        ->setZipCode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                        ->setStoreNumber($eStore->getHash());

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
