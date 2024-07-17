<?php

/*
 * Store Crawler für Injoy (ID: 68929)
 */

class Crawler_Company_Injoy_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.injoy.de/';
        $searchUrl = $baseUrl . 'studios/studiofinder/fitnessstudio-ergebnisse.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $aZipCodes = $sDb->findZipCodesByNetSize(100);
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'tx_locator_pi1[country]' => 'de',
            'tx_locator_pi1[mode]' => 'search',
            'tx_locator_pi1[radius]' => '200'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $singleZipCode) {
            $aParams['tx_locator_pi1[zipcode]'] = $singleZipCode;

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<li[^>]*resultinfos[^>]*>\s*(.+?)\s*</li#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to find any stores for zipcode ' . $singleZipCode);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#<div[^>]*class="storeadresse"[^>]*>\s*([^<]+?)\s*\/\/\s*([^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->info($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<div[^>]*class="storename"[^>]*>\s*<a[^>]*href="([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $websiteMatch)) {
                    if (!preg_match('#(\.de)$#', $websiteMatch[1])) {
                        continue;
                    }
                    $eStore->setWebsite($websiteMatch[1]);
                }

                $eStore->setAddress($addressMatch[2], $addressMatch[1]);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
