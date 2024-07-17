<?php

/* 
 * Store Crawler für Galeria Kaufhof (ID: 20)
 */

class Crawler_Company_GaleriaKaufhof_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.galeria-kaufhof.de/';
        $searchUrl = $baseUrl . 'dynamic/storesForStoreLocator';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $strTime = '';
            foreach ($singleJStore->openingHours as $aTimeInfos) {
                if (strlen($strTime)) {
                    $strTime .= ',';
                }
                $strTime .= $aTimeInfos->dayOfWeek . ' ' . $aTimeInfos->openingPeriod;
            }
            
            $strSpecialTimes = '';
            foreach ($singleJStore->specialBusinessHours as $aSpecialTimeInfos) {
                if (strlen($strSpecialTimes)) {
                    $strSpecialTimes .= ', ';
                }
                $strSpecialTimes .= $aSpecialTimeInfos->date . ': ' . $aSpecialTimeInfos->reason . ' ' . $aSpecialTimeInfos->openingPeriod;
            }
            
            $eStore->setStoreNumber($singleJStore->storeCode)
                    ->setWebsite($singleJStore->publicLandingPageLocation)
                    ->setStreet($singleJStore->storeAddress->street)
                    ->setStreetNumber($singleJStore->storeAddress->streetNumber)
                    ->setZipcode($singleJStore->storeAddress->postalCode)
                    ->setCity($singleJStore->storeAddress->city)
                    ->setLongitude($singleJStore->storeLocation->longitude)
                    ->setLatitude($singleJStore->storeLocation->latitude)
                    ->setStoreHoursNormalized($strTime)
                    ->setStoreHoursNotes($strSpecialTimes)
                    ->setPhone('022158870588');
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}