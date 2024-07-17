<?php

/**
 * Store Crawler für New Yorker (ID: 22389)
 */
class Crawler_Company_NewYorker_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.newyorker.de/';
        $searchUrl = $baseUrl . 'fashion/?type=7004';
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $weekdays = array ('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($json as $jStore){
            if ($jStore->country->uid != 1
            || strlen($jStore->zipcode) < 5){
                continue;
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $jStore->uid)
                    ->setSubtitle($jStore->storeinfo)
                    ->setStreetAndStreetNumber($jStore->address)
                    ->setZipcode($jStore->zipcode)
                    ->setCity($jStore->city->title)
                    ->setLatitude($jStore->lat)
                    ->setLongitude($jStore->lng);
                    
            $hours = array();
            foreach ($weekdays as $weekday){
                if (strlen($jStore->data->$weekday->data)){
                    $hours[] = $jStore->data->$weekday->label . ' ' . $jStore->data->$weekday->data;
                }
            }

            $eStore->setStoreHoursNormalized(implode(', ', $hours));
                    
            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
