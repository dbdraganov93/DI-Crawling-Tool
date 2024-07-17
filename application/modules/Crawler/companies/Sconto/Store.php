<?php

/*
 * Store Crawler für Sconto (ID: 156)
 */

class Crawler_Company_Sconto_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sconto.de';
        $searchUrl = $baseUrl . '/filialfinder';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-flags="(.+)"[^>]*data-image-folder#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->abbreviation)
                ->setStreetAndStreetNumber($singleJStore->address)
                ->setLatitude($singleJStore->mapCoordinates->lat)
                ->setLongitude($singleJStore->mapCoordinates->lng)
                ->setStoreHoursNormalized($singleJStore->openingTimes)
                ->setWebsite($baseUrl . $singleJStore->path);

            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#addressAndPhone">[^<]+?<br\/>([^<]+?)<#';
            if (!preg_match($pattern, $page, $cityMatch)) {
                $this->_logger->err($companyId . ': unable to get store city ' . $eStore->getWebsite());
                continue;
            }

            $pattern = '#storeContactEntry__phoneNr[^>]*storeContactEntry__phoneNr--phoneNrBig"[^>]*href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $eStore->setZipcodeAndCity($cityMatch[1]);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

}
