<?php

/*
 * Store Crawler für Apollo (ID: 22386)
 */

class Crawler_Company_Apollo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://filialen.apollo.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul[^>]*class="row[^>]*city">(.+?)<\/ul#';
        if (!preg_match_all($pattern, $page, $stateMatches)) {
            throw new Exception($companyId . ': unable to get any states.');
        }
        $aCityUrls = [];
        foreach ($stateMatches[1] as $singleState) {
            $pattern = '#<li[^>]*class="col-12[^>]*col-sm-4">\s*<a[^>]*href="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                throw new Exception($companyId . ': unable to get any store urls.');
            }
            $aCityUrls = array_merge($aCityUrls, $storeUrlMatches[1]);
        }

        $aCityUrls = array_unique($aCityUrls);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aCityUrls as $singleCityUrl) {
            $cityUrl = $baseUrl . $singleCityUrl;

            $sPage->open($cityUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<p[^>]*class="resultStore">(.+?)<use#';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos for ' . $cityUrl);
                continue;
            }

            foreach ($storeInfoMatches[1] as $singleStore) {
                $pattern = '#class="resultAddress"[^>]*>\s*([^,]+?)\s*,\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $strTimes = '';
                $pattern = '#data-openinfo\s*=\s*\'([^\']+?)\'#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $jStoreHours = json_decode($storeHoursMatch[1]);
                    foreach ($jStoreHours as $day => $aStoreHours) {
                        if (!$aStoreHours) {
                            continue;
                        }
                        foreach ($aStoreHours as $singleTimeFrame) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $day . ' ' . $singleTimeFrame->openTime . '-' . $singleTimeFrame->closeTime;
                        }
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#href="\.\.\/\.\.\/([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($baseUrl . $urlMatch[1]);
                }

                $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setStoreHoursNormalized($strTimes);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores);
    }

}
