<?php

/**
 * Store Crawler für Akzente Plus (ID: 71206)
 */
class Crawler_Company_AkzentePlus_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.akzenteplus.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $storeLocatorUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat=50.0&lng=10.0&max_results=1000&radius=700&autoload=1';
        $sPage->open($storeLocatorUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($json as $jsonElement) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $hours = preg_replace('#<[^>]*time[^>]*>#', '', $jsonElement->hours);
            $hours = $sTimes->convertAmPmTo24Hours($hours);

            $eStore->setStreetAndStreetNumber($jsonElement->address)
                    ->setCity($jsonElement->city)
                    ->setZipcode($jsonElement->zip)
                    ->setPhoneNormalized($jsonElement->phone)
                    ->setFaxNormalized($jsonElement->fax)
                    ->setEmail($jsonElement->email)
                    ->setStoreHoursNormalized($hours, 'table')
                    ->setStoreNumber($jsonElement->id)
                    ->setLatitude($jsonElement->lat)
                    ->setLongitude($jsonElement->lng);

            $cStore->addElement($eStore, true);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
