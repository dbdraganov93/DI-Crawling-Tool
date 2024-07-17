<?php

/*
 * Store Crawler für JET (ID: 67281)
 */

class Crawler_Company_Jet_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.jet-tankstellen.de';
        $searchUrl = $baseUrl . '/kraftstoff/filialfinder/?location=[[ZIP]]&radius=25';
        $sUrl = new Marktjagd_Service_Generator_Url();
        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = new Marktjagd_Collection_Api_Store();
        $aUrls = $sUrl->generateUrl($searchUrl, 'zipcode', 20);

        foreach ($aUrls as $url) {
            try {
                usleep(500000);
                $sPage->open($url);
                $page = $sPage->getPage()->getResponseBody();

                if (!preg_match_all('#<td[^>]*>\s*<a\s*href="(/kraftstoff/filialfinder/[^"]*)"[^>]*>.*?</td>#is', $page, $matchStoreUrls)) {
                    $this->_logger->log($companyId . ': no stores available for url: ' . $url, Zend_Log::INFO);
                    continue;
                }

                foreach ($matchStoreUrls[1] as $sStores) {
                    $storeUrl = $baseUrl . $sStores;
                    $sPage->open($storeUrl);
                    $pageStore = $sPage->getPage()->getResponseBody();

                    $eStore = new Marktjagd_Entity_Api_Store();
                    if (preg_match('#<h2>([^<]*)</h2>\s*<h3\s*class="station-owner"[^>]*>\s*'
                                    . '<span>([^<]*)</span>\s*'
                                    . '<span>([^<]*)</span>\s*'
                                    . '<span>([^<]*)</span>\s*'
                                    . '#is', $pageStore, $matchAddress)) {
                        $eStore->setSubtitle($matchAddress[1]);
                        $eStore->setStreetAndStreetNumber($matchAddress[2]);
                        $eStore->setZipcodeAndCity($matchAddress[3]);
                        $eStore->setPhoneNormalized($matchAddress[4]);
                    }

                    if (preg_match('#<div[^>]*>\s*<p>.*?ffnungszeiten</p>\s*(.*?)</div>#', $pageStore, $matchOpenings)) {
                        $eStore->setStoreHoursNormalized($matchOpenings[1]);
                    }

                    $cStores->addElement($eStore);
                }
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': unable to open ' . $url);
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
