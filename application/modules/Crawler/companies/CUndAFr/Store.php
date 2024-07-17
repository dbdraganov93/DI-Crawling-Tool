<?php

/*
 * Store Crawler für C&A (ID: )
 */

class Crawler_Company_CUndAFr_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.c-and-a.com/';
        $searchUrl = $baseUrl . 'stores/fr-fr/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*class="allcities[^"]*citystartingwith[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $cityMatches)) {
            throw new Exception('unable to get store urls: ' . $searchUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($cityMatches[1] as $singleCity) {
            $cityUrl = $searchUrl . $singleCity;

            $sPage->open($cityUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="addressBox"[^>]*>\s*(.+?)\s*</div>\s*</div#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from ' . $cityUrl);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4,5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<p[^>]*lat="([^"]+?)"[^>]*lng="([^"]+?)"#';
                if (preg_match($pattern, $page, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
                }

                $pattern = '#tel\.?:?\s*([^<]+?)\s*<#i';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $strTimes = '';
                $pattern = '#data-openingtime="([^"]+?)"[^>]*data-closingtime="([^"]+?)"[^>]*data-day="([^"]+?)"#';
                if (preg_match_all($pattern, $singleStore, $storeHoursMatches)) {
                    for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $storeHoursMatches[3][$i] . ' ' . $storeHoursMatches[1][$i] . '-' . $storeHoursMatches[2][$i];
                    }
                }

                $pattern = '#class="btn[^"]*cabtnBigRed"[^>]*href="\.\.\/\.\.\/([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $websiteMatch)) {
                    $eStore->setWebsite($searchUrl . $websiteMatch[1]);

                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#Notre\s*service\s*en\s*magasin\s*<[^>]*>(.+?)</ul#i';
                    if (preg_match($pattern, $page, $serviceListMatch)) {
                        $pattern = '#>\s*([^<]{5,})\s*<#';
                        if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                            $eStore->setService(implode(', ', $serviceMatches[1]));
                        }
                    }
                }

                $eStore->setAddress($addressMatch[1], str_pad($addressMatch[2], 5, '0', STR_PAD_LEFT))
                    ->setStoreHoursNormalized($strTimes, 'text', TRUE, 'eng');

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
