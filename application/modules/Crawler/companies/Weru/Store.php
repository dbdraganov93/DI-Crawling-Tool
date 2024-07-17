<?php

/*
 * Store Crawler für Weru Sonnenstudios (ID: 71820)
 */

class Crawler_Company_Weru_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.weru.de';
        $searchUrl = $baseUrl . '/de/fachbetriebe.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipCodes = $sDb->findZipCodesByNetSize(80);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $knownUrls = array();
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $singleZipCode) {
            $aParams = array(
                'tx_werufachbetriebe_pi1[__referrer][extensionName]' => 'WeruFachbetriebe',
                'tx_werufachbetriebe_pi1[__referrer][controllerName]' => 'Fachbetrieb',
                'tx_werufachbetriebe_pi1[__referrer][actionName]' => 'search',
                'tx_werufachbetriebe_pi1[__hmac]' => 'a:2:{s:6:"filter";a:12:{s:12:"wasSubmitted";i:1;s:3:"zip";i:1;s:6:"radius";i:1;s:4:"land";i:1;s:6:"isWeru";i:1;s:8:"isUnilux";i:1;s:6:"award1";i:1;s:6:"award3";i:1;s:3:"ral";i:1;s:6:"award4";i:1;s:6:"award5";i:1;s:6:"award6";i:1;}s:10:"controller";i:1;}df367d258e9bd8df6dc5ad832826a7b244c778cc',
                'tx_werufachbetriebe_pi1[filter][wasSubmitted]' => '1',
                'tx_werufachbetriebe_pi1[filter][zip]' => $singleZipCode,
                'tx_werufachbetriebe_pi1[filter][radius]' => '100',
                'tx_werufachbetriebe_pi1[filter][isWeru]' => '',
                'tx_werufachbetriebe_pi1[filter][isUnilux]' => '',
                'tx_werufachbetriebe_pi1[filter][award1]' => '',
                'tx_werufachbetriebe_pi1[filter][award3]' => '',
                'tx_werufachbetriebe_pi1[filter][ral]' => '',
                'tx_werufachbetriebe_pi1[filter][award4]' => '',
                'tx_werufachbetriebe_pi1[filter][award5]' => '',
                'tx_werufachbetriebe_pi1[filter][award6]' => '',
            );
           
            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match_all('#<a[^>]*href="([^"]+)"[^>]*>\s*Zum\s+Fachh#', $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores for zipcode: ' . $singleZipCode);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStoreMatch) {
                if (in_array($singleStoreMatch, $knownUrls)){
                    $this->_logger->info('known url, skip ' . $singleStoreMatch);
                }
                
                $knownUrls[] = $singleStoreMatch;                
                
                $eStore = new Marktjagd_Entity_Api_Store();
            
                $this->_logger->info('open ' . $singleStoreMatch);
                $sPage->open($singleStoreMatch);
                $page = $sPage->getPage()->getResponseBody();                      
                
                $eStore->setWebsite($singleStoreMatch);
                
                if (preg_match('#<h1>(.+?)</h1>#', $page, $match)){
                    $eStore->setSubtitle(trim($match[1]));
                }
                
                if (preg_match('#<div[^>]*class="detail-shortinfo"[^>]*>\s*<p>(.+?)</p>#', $page, $match)){
                    $addressLines = preg_split('#<br[^>]*>#', $match[1]);
                    
                    if (!preg_match('#deutschland#is', $addressLines[2])){
                        $this->_logger->info('no german store, skip ' . $singleStoreMatch);
                        continue;
                    }
                    
                    $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                            ->setCity($sAddress->extractAddressPart('city', $addressLines[1]));
                                        
                    if (preg_match('#data-phone="([^"]+)"#', $page, $match)){
                        $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                    }
                }
                
                if (preg_match('#<div[^>]*class="tx-weru-fachbetriebe-details-bewertungen-awards"[^>]*>(.+?)</div>#', $page, $match)){
                    if (preg_match_all('#title="\s*([^"]+)\s*"#', $match[1], $subMatch)){
                        $eStore->setService(implode(', ', $subMatch[1]));
                    }
                }
                
                if (preg_match('#<h2>Leistungen</h2>\s*<div[^>]*>(.+?)</div>#', $page, $match)){
                    if (preg_match('#<p>(.+?)<#', $match[1], $subMatch)){
                        $eStore->setText($subMatch[1]);
                    }
                }
                
                if (preg_match('#<div[^>]*class="tx-weru-fachbetriebe-details-logo"[^>]*>\s*<img[^>]*src="([^"]+)"#', $page, $match)){
                    if (preg_match('#http#', $match[1])){
                        $eStore->setLogo($match[1]);
                    } else {
                        $eStore->setLogo($baseUrl . '/' . $match[1]);
                    }
                }                
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
