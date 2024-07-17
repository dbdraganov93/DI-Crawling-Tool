<?php

/**
 * Store Crawler für denn's Biomarkt AT (ID: 72801)
 */

class Crawler_Company_DennsBiomarktAt_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.denns-biomarkt.at/';
        $searchUrl = $baseUrl
            . '?eID=apertoMarkets&map-standalone=1&map-current-page=1&map-filter-location='
            . '&map-filter-gps=&map-filter-radius=&map-filter-opening=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($json->items as $singleStore) {
            $strTimes = '';
            foreach ($singleStore->openingHours[0]->fromTo as $singleTime) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $singleTime->weekday . ' ' . $singleTime->hours;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($singleStore->address->street)
                ->setZipcode($singleStore->address->zip)
                ->setCity($singleStore->address->city)
                ->setLatitude($singleStore->position->lat)
                ->setLongitude($singleStore->position->lng)
                ->setPhoneNormalized($singleStore->phone)
                ->setStoreHoursNormalized($strTimes)
                ->setStoreNumber($singleStore->marketId)
                ->setSection(implode(', ', $singleStore->services));

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}