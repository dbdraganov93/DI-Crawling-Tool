<?php

/**
 * Storecrawler für Olymp (ID: 69772)
 */
class Crawler_Company_Olymp_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.olymp.com/';
        $searchUrl = $baseUrl . 'de_de/view/StoreFinderComponentController/get-all-pos/';
        $storeDetailUrl = $baseUrl . 'de_de/view/StoreFinderComponentController/get-pos/?name=';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            if (!preg_match('#STORE#', $singleJStore->type)) {
                continue;
            }

            $sPage->open($storeDetailUrl . $singleJStore->name);
            $jStoreDetail = $sPage->getPage()->getResponseAsJson();
            
            if (strlen($jStoreDetail->address->postalCode) != 5) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->name)
                    ->setStreetAndStreetNumber($jStoreDetail->address->streetname)
                    ->setZipcode($jStoreDetail->address->postalCode)
                    ->setCity($jStoreDetail->address->town)
                    ->setPhoneNormalized($jStoreDetail->address->phone)
                    ->setLatitude($jStoreDetail->geoPoint->latitude)
                    ->setLongitude($jStoreDetail->geoPoint->longitude)
                    ->setStoreHoursNormalized($jStoreDetail->openingHours);
            
            $cStores->addElement($eStore);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
