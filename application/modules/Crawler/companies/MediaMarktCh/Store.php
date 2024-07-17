<?php

/**
 * Storecrawler für Media Markt (CH) (ID: 72176)
 */
class Crawler_Company_MediaMarktCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.mediamarkt.ch/';
        $searchUrl = $baseUrl . 'de/marketselection.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul[^>]*class="all-markets-list"[^>]*>(.+?)<\/ul#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<li[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#data-storeId="(\d+)"#';
            if (!preg_match($pattern, $page, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos.');
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $aStreet = preg_split('#\s*,\s*#', $aInfos['streetAddress']);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }

            $pattern = '#itemprop="(latitude|longitude)"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $geoMatches)) {
                $aGeo = array_combine($geoMatches[1], $geoMatches[2]);

                $eStore->setLatitude($aGeo['latitude'])
                    ->setLongitude($aGeo['longitude']);
            }

            $eStore->setStoreNumber($storeNumberMatch[1])
                ->setStreetAndStreetNumber(end($aStreet))
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