<?php

/* 
 * Store Crawler für Hörl Getränke (ID: 29080)
 */

class Crawler_Company_HoerlGetraenke_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.xn--getrnke-hrl-o8a3v.de/';
        $searchUrl = $baseUrl . '/index.php/getraenkefachmaerkte/uebersicht-getraenkefachmaerkte-2?option=com_hotspots&'
            . 'view=jsonv4&task=gethotspots&hs-language=de-DE&page=1&per_page=100&cat=&level=8&ne=49.421957%2C'
            . '12.95215&sw=48.019595%2C10.03528&c=48.725664%2C11.493715&fs=0&offset=0&format=raw';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseAsJson();
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($page->items as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreHoursNormalized($jStore->description);
            $eStore->setStoreNumber($jStore->id);

            $pattern = '#Telefon([^<]*)<#i';
            if (preg_match($pattern, $jStore->description, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#Telefax([^<]*)<#i';
            if (preg_match($pattern, $jStore->description, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $eStore->setStreetAndStreetNumber($jStore->street);
            $eStore->setZipcode($jStore->zip);
            $eStore->setCity($jStore->city);
            $eStore->setLatitude($jStore->lat);
            $eStore->setLongitude($jStore->lng);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}