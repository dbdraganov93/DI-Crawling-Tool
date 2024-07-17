<?php

/*
 * Store Crawler für JYSK (ID: 184)
 */

class Crawler_Company_Jysk_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://jysk.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#"StoresLocatorLayout"[^>]*data-jysk-react-properties="(\{[^>]+})"[^>]*>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception('Store list not found');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1])->storesCoordinates as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setStoreNumber($singleJStore->id)
                ->setWebsite($baseUrl . trim($singleJStore->url, '/'));

            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="locate-stores"[^>]*>(.+?)</div>#';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err('Store infos not found');
                continue;
            }

            $pattern = '#>\s*([^,>]+?),\s*([^\s]+?)?\s*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatch[1], $addressMatch)) {
                $this->_logger->err('Store address not found: ' . $storeInfoMatch[1]);
                continue;
            }

            $eStore->setAddress($addressMatch[1] . ' ' . $addressMatch[2], $addressMatch[3]);

            $pattern = '#<table[^>]*class="collect-info-hours"[^>]*>(.+?)</table>#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $pattern = '#<td[^>]*>([^<]+?)</td>\s*<td[^>]*>([^<]+?)</td>\s*<td[^>]*>([^<]+?)</td>#';
                if (preg_match_all($pattern, $storeHoursMatch[1], $storeHoursInfoMatches)) {
                    $strTimes = '';
                    for ($i = 0; $i < count($storeHoursInfoMatches[0]); $i++) {
                        $strTimes .= $storeHoursInfoMatches[1][$i] . ' ' . $storeHoursInfoMatches[3][$i] . ',';
                    }
                }
                $eStore->setStoreHoursNormalized($strTimes);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
