<?php

/**
 * Store Crawler für AWG (ID: 84)
 */
class Crawler_Company_AWG_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.awg-mode.de/';
        $searchUrl = $baseUrl . 'store-locator?lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lon='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&distance=50km';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $json = $sPage->getPage()->getResponseAsJson();

            foreach ($json->storeLocations as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setStreetAndStreetNumber($singleJStore->address1)
                    ->setStoreNumber($singleJStore->id_store_location)
                    ->setWebsite($baseUrl . preg_replace('#^\/#', '', $singleJStore->url))
                    ->setLatitude($singleJStore->location->latitude)
                    ->setLongitude($singleJStore->location->longitude);

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}