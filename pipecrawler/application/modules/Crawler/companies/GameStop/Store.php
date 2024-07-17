<?php

/**
 * Storecrawler für GameStop (ID: 22381)
 */
class Crawler_Company_GameStop_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.gamestop.de/';
        $storeListUrl = $baseUrl . 'StoreLocator/GetStoresForStoreLocatorByProduct';

        $ch = curl_init($storeListUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $jStores = json_decode($result);
        

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $singleJStore->Id)
                    ->setSubtitle((string) $singleJStore->Name)
                    ->setStreetAndStreetNumber((string) $singleJStore->Address)
                    ->setZipcode((string) $singleJStore->Zip)
                    ->setCity((string) $singleJStore->City)
                    ->setStoreHoursNormalized((string) $singleJStore->Hours)
                    ->setPhoneNormalized((string) $singleJStore->Phones);

            if ($singleJStore->StreetNumber != 'undefined') {
                $eStore->setStreetNumber($singleJStore->StreetNumber);
            }

            if ($singleJStore->Latitude != 'undefined') {
                $eStore->setLatitude($singleJStore->Latitude);
            }

            if ($singleJStore->Longitude != 'undefined') {
                $eStore->setLongitude($singleJStore->Longitude);
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
