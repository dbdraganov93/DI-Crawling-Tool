<?php

/**
 * Store Crawler für Vestino (ID: 67871)
 */
class Crawler_Company_Vestino_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $searchUrl = 'https://storelocator.fortuneglobe.eu/companies/7/stores?latitude=50.0&longitude=10.0&distance=700.0';
        $sPage = new Marktjagd_Service_Input_Page();
        $sEncoding = new Marktjagd_Service_Text_Encoding();

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($json as $jsonElement) {
            if ($jsonElement->geolocation->address->country_code != 'DE') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setPhoneNormalized($jsonElement->contact->phone);
            $eStore->setFaxNormalized($jsonElement->contact->fax);
            $eStore->setEmail($jsonElement->contact->email);
            $eStore->setWebsite($jsonElement->contact->www);
            $eStore->setStoreHoursNormalized($jsonElement->contact->opening_time);
            $eStore->setStreetAndStreetNumber($jsonElement->geolocation->address->street);
            $eStore->setZipcode($jsonElement->geolocation->address->postal_code);
            $eStore->setCity($sEncoding::fixUTF8($jsonElement->geolocation->address->locality));
            $eStore->setLatitude($jsonElement->geolocation->geocoordinate->latitude);
            $eStore->setLongitude($jsonElement->geolocation->geocoordinate->longitude);
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}