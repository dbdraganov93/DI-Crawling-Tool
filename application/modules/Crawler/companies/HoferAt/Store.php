<?php

/*
 * Store Crawler für Hofer AT (ID: 72982)
 */

class Crawler_Company_HoferAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hofer.at/';
        $searchUrl = $baseUrl . 'at/de/.get-stores-in-radius.json?latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&radius=100';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            sleep(5);
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!$jStores->stores) {
                $this->_logger->info($companyId . ': no stores found: ' . $singleUrl);
                continue;
            }

            foreach ($jStores->stores as $singleJStore) {
                if (!preg_match('#AT#', $singleJStore->countryCode) || !$singleJStore->available) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreHoursNormalized(preg_replace('#\s+-\s+#', ' ', $singleJStore->fullOpenUntil))
                    ->setStreetAndStreetNumber($singleJStore->streetAddress)
                    ->setStoreNumber($singleJStore->storeId);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

}
