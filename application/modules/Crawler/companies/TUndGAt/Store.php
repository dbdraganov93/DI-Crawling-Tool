<?php
/**
 * Store Crawler für T&G AT (ID: 72854)
 */

class Crawler_Company_TUndGAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tundg.at/';
        $searchUrl = $baseUrl . 'wo-du-uns-findest/';
        $sPage = new Marktjagd_Service_Input_Page();

        $aDists = [
            'Tirol' => 'T',
            'Oberösterreich' => 'OÖ',
            'Kärnten' => 'K',
            'Salzburg' => 'S'
        ];

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="filiale\s+(.+?)<\/a#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{4}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#data-([^=]+?)="([^"]+?)"#';
            if (preg_match_all($pattern, $singleStore, $geoMatches)) {
                $aGeoInfos = array_combine($geoMatches[1], $geoMatches[2]);

                $eStore->setLatitude($aGeoInfos['lat'])
                    ->setLongitude($aGeoInfos['lng']);
            }

            $pattern = '#geöffnet:(.+?)<\/p#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#js_hover[^>]*data-location="([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $distMatch)) {
                if (!array_key_exists($distMatch[1], $aDists)) {
                    throw new Exception($companyId . ' new distribution found: ' . $distMatch[1]);
                }
                $eStore->setDistribution($aDists[$distMatch[1]]);
            }

            $pattern = '#href="tel[^>]*>(.+)#';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}