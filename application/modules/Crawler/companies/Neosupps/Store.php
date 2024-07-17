<?php

/**
 * Store Crawler for Neosupps (ID: 90237)
 */

class Crawler_Company_Neosupps_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $url = 'https://sl-front.proguscommerce.com/api/front/locations?shopId=102&shop=neosupps-live.myshopify.com&locationsQty=217&limit=500&lat=49.483462484848665&lng=6.417484286223436&d=1188.364&maxResults=1000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($url);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Ger#', $singleJStore->country)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->id)
                ->setTitle($singleJStore->name)
                ->setStreetAndStreetNumber($singleJStore->address)
                ->setCity($singleJStore->city)
                ->setZipcode($singleJStore->zipCode)
                ->setStoreHoursNormalized($singleJStore->openingHours)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lon)
                ->setPhoneNormalized($singleJStore->phone);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
