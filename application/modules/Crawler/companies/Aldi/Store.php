<?php

/**
 * Store Crawler für Aldi Süd (ID: 29)
 */
class Crawler_Company_Aldi_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        ini_set('memory_limit', '2G');
        $baseUrl = 'https://www.aldi-sued.de/';
        $searchUrl = $baseUrl . 'de/de/.get-stores-in-radius.json?' .
            'latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT .
            '&longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&radius=500';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aStoresToCheck = array(
            29 => 'S'
        );

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!property_exists($jStores, 'stores')) {
                continue;
            }

            foreach ($jStores->stores as $singleJStore) {
                if (!$singleJStore->available
                    || !preg_match('#DE#', $singleJStore->countryCode)
                    || !preg_match('#' . $aStoresToCheck[$companyId] . '#', $singleJStore->storeType)) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setLatitude($singleJStore->latitude)
                    ->setStoreHoursNormalized(preg_replace('#\s+-\s+#', ' ', $singleJStore->fullOpenUntil))
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreNumber($singleJStore->storeId)
                    ->setWebsite($baseUrl . 'store/' . $eStore->getStoreNumber())
                    ->setStreetAndStreetNumber($singleJStore->streetAddress);

                $cStores->addElement($eStore);

            }

        }

        return $this->getResponse($cStores);
    }
}
