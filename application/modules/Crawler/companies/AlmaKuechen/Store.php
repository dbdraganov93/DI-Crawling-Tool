<?php

/*
 * Store Crawler für Alma Küchen (ID: 71001)
 */

class Crawler_Company_AlmaKuechen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.alma-kuechen.de/';
        $searchUrl = $baseUrl . 'kuechenstudio/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<aside[^>]*id="sidebar"[^>]*>(.+?)<\/aside#', $page, $sidebarMatch)) {
            throw new Exception($companyId . ': unable to get any stores from sidebar.');
        }

        if (!preg_match_all('#<li[^>]*>\s*<a[^>]*href="\/([^"]+)"[^>]*>#', $sidebarMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores infos: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#itemprop="(latitude|longitude)"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $geoMatches)) {
                $aGeo = array_combine($geoMatches[1], $geoMatches[2]);
                $eStore->setLatitude($aGeo['latitude'])
                    ->setLongitude($aGeo['longitude']);
            }

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setPhoneNormalized($aInfos['telephone'])
                ->setFaxNormalized($aInfos['faxNumber'])
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
