<?php

/*
 * Store Crawler für C+S - Ceramic + Stein (ID: 72027)
 */

class Crawler_Company_CS_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ceramic-stein.de/';
        $searchUrl = $baseUrl . 'haendlersuche-ergebnisseite/suche/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '/land/de.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 100);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div\s*class="anystores-details[^"]*"[^>]*>(.*?)<div\s*id="rightpart"[^>]*>(.+?)</div>#is';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get any stores from list - ' . $singleUrl);
                continue;
            }

            foreach ($storeMatches[1] as $key => $storeMatch) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<div\s*class="name"[^>]*><h2>(.+?)</h2>#is';
                if (preg_match($pattern, $storeMatch, $matchTitle)) {
                    $eStore->setSubtitle($matchTitle[1]);
                }

                $pattern = '#<div\s*class="street"[^>]*>(.+?)</div>#is';
                if (preg_match($pattern, $storeMatch, $matchStreeet)) {
                    $eStore->setStreetAndStreetNumber($matchStreeet[1]);
                }

                $pattern = '#<span\s*class="postal"[^>]*>(.+?)</span>#is';
                if (preg_match($pattern, $storeMatch, $matchZip)) {
                    $eStore->setZipcode($matchZip[1]);
                }

                $pattern = '#<span\s*class="city"[^>]*>(.+?)</span>#is';
                if (preg_match($pattern, $storeMatch, $matchCity)) {
                    $eStore->setCity($matchCity[1]);
                }

                $pattern = '#<div\s*class="phone"[^>]*>(.+?)</div>#is';
                if (preg_match($pattern, $storeMatch, $matchPhone)) {
                    $eStore->setPhoneNormalized($matchPhone[1]);
                }

                $pattern = '#<div\s*class="fax"[^>]*>(.+?)</div>#is';
                if (preg_match($pattern, $storeMatch, $matchFax)) {
                    $eStore->setFaxNormalized($matchFax[1]);
                }

                $pattern = '#<a\s*href="mailto:(.+?)"#is';
                if (preg_match($pattern, $storeMatch, $matchMail)) {
                    $eStore->setEmail($matchMail[1]);
                }

                $pattern = '#<span>Internet\:</span>\s*<a\s*href="(.+?)"#is';
                if (preg_match($pattern, $storeMatch, $matchWebsite)) {
                    $eStore->setWebsite($matchWebsite[1]);
                }

                $eStore->setStoreHoursNormalized($storeMatches[2][$key], 'text');

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
