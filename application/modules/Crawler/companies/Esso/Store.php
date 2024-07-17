<?php

/**
 * Store Crawler für Esso Tankstellen (ID: 67196)
 */
class Crawler_Company_Esso_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.esso.de/';
        $searchUrl = $baseUrl . 'get_station_data';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        
        $aZipcodes = $sDbGeo->findZipCodesByNetSize(5, TRUE);
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);
        
        $aParams['locale'] = 'de-de';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['lat'] = $singleZipcode['lat'];
            $aParams['long'] = $singleZipcode['lng'];
            
            $sPage->open($searchUrl, $aParams);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            foreach ($jStores as $singleJStore) {
                if (!preg_match('#Germany#', $singleJStore->Country)) {
                    continue;
                }
                
                $strServices = '';
                foreach ($singleJStore->FeaturedItems as $singleFeature) {
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }
                    $strServices .= $singleFeature->Name;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setStreetAndStreetNumber($singleJStore->AddressLine1)
                        ->setCity(ucwords(strtolower($singleJStore->City)))
                        ->setStoreNumber($singleJStore->LocationID)
                        ->setStoreHoursNormalized($singleJStore->WeeklyOperatingHours, 'text', TRUE, 'eng')
                        ->setZipcode($singleJStore->PostalCode)
                        ->setLatitude($singleJStore->Latitude)
                        ->setLongitude($singleJStore->Longitude)
                        ->setWebsite($singleJStore->fullStationDetailURL)
                        ->setService($strServices);
                                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
