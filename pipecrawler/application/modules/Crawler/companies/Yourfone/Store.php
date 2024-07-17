<?php

/*
 * Store Crawler für Yourfone (ID: 71740)
 */

class Crawler_Company_Yourfone_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.yourfone.de/';
        $searchUrl = $baseUrl . 'shop-finder';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
       
        if (!preg_match('#var\s+shops\s*=\s*(\[.+?\]);#', $page, $jsonMatch)) {
            throw new Exception('unable to get any stores');            
        }
                      
        $jsonString = preg_replace('#\'#','"', $jsonMatch[1]);
        $jsonString = preg_replace('#\}\s*\,\s*\]#','}]', $jsonString);
                
        $json = json_decode($jsonString);
        
        foreach ($json as $entry) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $entry->id)
                    ->setSubtitle(trim((string) $entry->name))
                    ->setStreet((string) $entry->street)
                    ->setStreetNumber((string) $entry->nb)
                    ->setZipcode((string) $entry->zip)
                    ->setCity((string) $entry->city)
                    ->setLatitude((string) $entry->lat)
                    ->setLongitude((string) $entry->lng)
                    ->setPhoneNormalized((string) $entry->prefixPhone . (string) $entry->phone)
                    ->setStoreHoursNormalized('Mo-Fr ' . (string) $entry->openingWeekday . ', Sa ' . (string) $entry->openingWeekend);
                     
            $cStores->addElement($eStore);
        }     
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
