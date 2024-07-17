<?php

/**
 * Store Crawler für Adeg AT (ID: 72774)
 */

class Crawler_Company_AdegAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.adeg.at/';
        $searchUrl = $baseUrl . 'stores/data/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if ($singleJStore->isClosed) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setStreetAndStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setPhoneNormalized($singleJStore->telephoneAreaCode . $singleJStore->telephoneNumber)
                ->setStoreHoursNormalized($singleJStore->openingTimes)
                ->setTitle($singleJStore->displayName)
                ->setDistribution($singleJStore->province->provinceName)
                ->setEmail($singleJStore->email)
                ->setWebsite($singleJStore->website);

            if ($singleJStore->parkingSpotCount > 0) {
                $eStore->setParking($singleJStore->parkingSpotCount . ' Parkplätze vorhanden');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}