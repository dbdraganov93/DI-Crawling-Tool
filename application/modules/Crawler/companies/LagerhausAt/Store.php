<?php
/**
 * Store Crawler für Lagerhaus (AT) (ID: 73029)
 */

class Crawler_Company_LagerhausAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://static.lagerhaus.at/';
        $searchUrl = $baseUrl . 'loc/v1/find/locations/full?lat=47&lon=10';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Österreich#', $singleJStore->address->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->id)
                ->setLatitude($singleJStore->geoLocation->lat)
                ->setLongitude($singleJStore->geoLocation->lon)
                ->setZipcode($singleJStore->address->postcode)
                ->setCity($singleJStore->address->city)
                ->setStreetAndStreetNumber($singleJStore->address->line)
                ->setSection(implode(', ', $singleJStore->businessAreas));

            $cStores->addElement($eStore, TRUE);
        }

        return $this->getResponse($cStores, $companyId);
    }
}