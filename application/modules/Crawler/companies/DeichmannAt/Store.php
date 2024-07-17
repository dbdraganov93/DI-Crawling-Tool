<?php
/**
 * Store Crawler für Deichmann AT (ID: 72840)
 */

class Crawler_Company_DeichmannAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://stores.deichmann.com/';
        $searchUrl = $baseUrl . 'at-de/index.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*class="region-list__link"[^>]*href\s*=\s*"(([^\/]+?)\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $aStores = array_combine($storeMatches[1], $storeMatches[2]);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $storeUrl => $county) {
            sleep(5);
            $storeDetailUrl = $baseUrl . 'at-de/' . $storeUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*class="button-link"[^>]*href="([^"]*\/[^\/"]+?)"[^>]*>\s*Details#';
            if (!preg_match_all($pattern, $page, $detailUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get store detail url for ' . $storeDetailUrl);
                continue;
            }

            foreach ($detailUrlMatches[1] as $singleUrl) {
                sleep(2);
                if (!$sPage->checkUrlReachability($singleUrl)) {
                    $this->_logger->info($companyId . ': unable to open ' . $singleUrl);
                    continue;
                }
                $this->_logger->info($companyId . ': opening ' . $singleUrl);

                $sPage->open($singleUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<script[^>]*type\s*=\s*\'application\/ld\+json\'[^>]*>\s*(.+?)\s*<\/script#';
                if (!preg_match($pattern, $page, $jInfoMatch)) {
                    $this->_logger->err($companyId . ': unable to get info json: ' . $storeDetailUrl);
                    continue;
                }

                $jInfos = json_decode($jInfoMatch[1]);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setPhoneNormalized($jInfos->telephone)
                    ->setStreetAndStreetNumber($jInfos->address->streetAddress)
                    ->setCity($jInfos->address->addressLocality)
                    ->setZipcode($jInfos->address->postalCode)
                    ->setLatitude($jInfos->geo->latitude)
                    ->setLongitude($jInfos->geo->longitude)
                    ->setStoreHoursNormalized($jInfos->openingHours)
                    ->setWebsite($storeDetailUrl);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}