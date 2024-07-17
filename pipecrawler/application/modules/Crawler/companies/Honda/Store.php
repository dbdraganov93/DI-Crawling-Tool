<?php

/*
 * Store Crawler für Honda (ID: 68791)
 */

class Crawler_Company_Honda_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://haendler.honda.de/';
        $searchUrl = $baseUrl . 'cars/dealer-search/search.html?q=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 10);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div\s*class="dealerResult(.+?)</div>\s*</div>\s*</div>\s*</div>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores found for: ' . $singleUrl);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#dealerAddress[^>]*>\s*<p[^>]*>(.+?)</p#';
                if (!preg_match($pattern, $singleStore, $addressListMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address list: ' . $singleStore);
                }

                $pattern = '#<span[^>]*>(.+?)</span#';
                if (!preg_match_all($pattern, $addressListMatch[1], $addressDetailMatches)) {
                    $this->_logger->err($companyId . ': unable to get store address details from list: ' . $singleStore);
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $pattern = '#data-result-coords="([^,]+?),([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                }

                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#href="mailto:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }

                $pattern = '#href="\/([^"]*?)"\s*class="analyticsEvent"#';
                if (preg_match($pattern, $singleStore, $linkMatch)) {
                    $sPage->open($baseUrl . $linkMatch[1]);
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#itemprop="url"[^>]*href="([^"]+?)"#';
                    if (preg_match($pattern, $page, $storeUrlMatch)) {
                        $eStore->setWebsite($storeUrlMatch[1]);
                    }
                }

                $eStore->setStreetAndStreetNumber($addressDetailMatches[1][0])
                        ->setZipcode(preg_replace('#[^\d]#', '', $addressDetailMatches[1][1]))
                        ->setCity($addressDetailMatches[1][2]);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
