<?php

/**
 * Store Crawler für Depot (ID: 22304)
 */
class Crawler_Company_Depot_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.depot-online.com/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-DE-Site/de_DE/Stores-Calculate?maxDistance=1000.0&lat=50&lng=10';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singlejStore) {
            if (preg_match('#^(00)#', $singlejStore->phone)
                || strlen($singlejStore->postalCode) != 5) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreHoursNormalized(preg_replace('#([0-9]{2}):([0-9]{2}):([0-9]{2})#', '$1:$2', $singlejStore->storeHours))
                ->setStreetAndStreetNumber($singlejStore->address1)
                ->setSubtitle($singlejStore->name)
                ->setCity($singlejStore->city)
                ->setZipcode($singlejStore->postalCode)
                ->setFaxNormalized($singlejStore->fax)
                ->setPhoneNormalized($singlejStore->phone)
                ->setEmail($singlejStore->email)
                ->setLatitude($singlejStore->lat)
                ->setLongitude($singlejStore->lng)
                ->setStoreNumber($singlejStore->id);

            $cStores->addElement($eStore, true);
        }

        return $this->getResponse($cStores, $companyId);
    }
}