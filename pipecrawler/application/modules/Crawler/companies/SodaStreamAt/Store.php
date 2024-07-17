<?php

/**
 * Store Crawler for Sodastream AT (ID: 81132)
 */

class Crawler_Company_SodaStreamAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.storepoint.co/';
        $searchUrl = $baseUrl . 'v1/15f61bc85b9089/locations?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&long=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&radius=50';
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $ch = curl_init($singleUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            $result = curl_exec($ch);
            curl_close($ch);

            $jStores = json_decode($result);

            foreach ($jStores->results->locations as $singleJStore) {
                if (!preg_match('#Österreich#', $singleJStore->streetaddress)) {
                    continue;
                }

                $aAddress = preg_split('#\s*,\s*#', $singleJStore->streetaddress);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setTitle($singleJStore->name)
                    ->setStoreNumber($singleJStore->id)
                    ->setLatitude($singleJStore->loc_lat)
                    ->setLongitude($singleJStore->loc_long)
                    ->setStreetAndStreetNumber($aAddress[0])
                    ->setZipcode($aAddress[1])
                    ->setCity($aAddress[2]);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores);
    }
}