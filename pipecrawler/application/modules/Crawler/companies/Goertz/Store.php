<?php

/* 
 * Store Crawler für Görtz (ID: 304)
 */

class Crawler_Company_Goertz_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.goertz.de/';
        $searchUrl = $baseUrl . 'ajax/locationfinder/store/?latitude=50&longitude=10&distance=10000';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#Deutschland#', $singleJStore->country)) {
                continue;
            }
            
            $strTimes = '';
            foreach ($singleJStore->openinghours as $singleDayKey => $singleDayValue) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $singleDayKey . ' ' . $singleDayValue->open . '-' . $singleDayValue->close;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->id)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setStreet($singleJStore->street)
                    ->setStreetNumber($singleJStore->streetno)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setWebsite($singleJStore->url)
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}