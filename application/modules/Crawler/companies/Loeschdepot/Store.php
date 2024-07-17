<?php

/* 
 * Store Crawler für Loeschdepot (ID: 69766)
 */

class Crawler_Company_Loeschdepot_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.loesch-depot.de';
        $searchUrl = $baseUrl . '/filialen_data.php?mode=region&region=';

        $regions = array (  'Bitterfeld%2FWittenberg',
            'Leipzig%2FLeipziger+Land',
            'Oschatz%2FRiesa%2FGro%C3%9Fenhain',
            'Wei%C3%9Fenfels%2FNaumburg%2FZeitz'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        foreach ($regions as $region) {

            $sPage->open($searchUrl . $region);
            $jPage = $sPage->getPage()->getResponseAsJson();

            foreach ($jPage as $storeData) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($storeData->id);
                $eStore->setStreetAndStreetNumber($storeData->strasse);
                $eStore->setZipcode($storeData->plz);
                $eStore->setSubtitle($storeData->gebiet);
                $eStore->setCity($storeData->stadt);
                $eStore->setPhoneNormalized($storeData->telefon);
                $eStore->setLatitude($storeData->lat);
                $eStore->setLongitude($storeData->lon);
                $eStore->setStoreHoursNormalized($storeData->zeiten);
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
