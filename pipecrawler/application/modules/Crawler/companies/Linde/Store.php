<?php

/**
 * Store Crawler für Linde Gas & More (ID: 73650)
 */
class Crawler_Company_Linde_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        // sourcePage = https://www.gasandmore.de/de/ueber-uns/standorte#!/l/
        $uberallAPI = 'https://uberall.com/api/storefinders/IunQos1bXU71kbb60CfaQjdWfCpQ8c/locations/all?' .
            'fieldMask=identifier&' .
            'fieldMask=name&' .
            'fieldMask=streetAndNumber&' .
            'fieldMask=city&' .
            'fieldMask=zip&' .
            'fieldMask=lat&' .
            'fieldMask=lng&';

        $sPage->open($uberallAPI);
        foreach ($sPage->getPage()->getResponseAsJson()->response->locations as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($jStore->identifier)
                ->setTitle($jStore->name)
                ->setStreetAndStreetNumber($jStore->streetAndNumber)
                ->setCity($jStore->city)
                ->setZipcode($jStore->zip)
                ->setLatitude($jStore->lat)
                ->setLongitude($jStore->lng);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}