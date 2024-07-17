<?php
/**
 * Store Crawler für METRO AT (ID: 72951)
 */

class Crawler_Company_MetroAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.metro.at/';
        $searchUrl = $baseUrl . 'metro-maerkte';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*class="store-menu-item"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos.');
                continue;
            }

            $aInfos = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="(latitude|longitude)"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $geoMatches)) {
                $aGeo = array_combine($geoMatches[1], $geoMatches[2]);

                $eStore->setLatitude($aGeo['latitude'])
                    ->setLongitude($aGeo['longitude']);
            }

            $pattern = '#<div[^>]*class="opening-hours"[^>]*>\s*<h2[^>]*>\s*Markt(.+?)<\/table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#<\/td>\s*<td>#', '-', $storeHoursMatch[1]));
            }

            $eStore->setPhoneNormalized($aInfos['telephone'])
                ->setEmail($aInfos['email'])
                ->setFaxNormalized($aInfos['faxNumber'])
                ->setStreetAndStreetNumber($aInfos['streetAddress'])
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);

    }
}