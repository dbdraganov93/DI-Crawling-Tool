<?php

/*
 * Store Crawler für Snipes (ID: 29016)
 */

class Crawler_Company_Snipes_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.snipes.com/';
        $searchUrl = $baseUrl . 'resultStore.html';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $cStores = new Marktjagd_Collection_Api_Store();
        
        $southLat = 47.200;     // 47.2701270
        $northLat = 55.200;     // 55.081500
        $westLong = 5.800;      // 5.8663566
        $eastLong = 15.200;     // 15.0418321 
        
        $geoSteps = 0.3;
        
        for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
            for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                 $aParams = array(
                    'address' => 'Deutschland',
                    'country' => 'DE',
                    'crm' => '',
                    'javaScriptGeocoded' =>	'true',
                    'noVariants' => 'true',
                    'productCode' => '',
                    'result' => '|' . $lat . ',' . $long . ';',
                    'selectedProductCode' => '',
                    'variantArtVarNo' => '',
                    'variantArtVarNo' => 'unbekannt'
                );
                 
                $sPage->open($searchUrl, $aParams);
                $page = $sPage->getPage()->getResponseBody();

                if (!preg_match('#var\s+locations\s*=\s*(\[\{.+?\}\]);#', $page, $locationMatch)){
                    $this->_logger->info('no stores found for location ' . $lat . '/' . $long);
                    continue;
                }

                $jsonStores = json_decode($locationMatch[1]);

                foreach ($jsonStores as $jsonStore){
                    if (!preg_match('#\d{5}#', $jsonStore->zip)){
                        continue;
                    }
                    
                    $eStore = new Marktjagd_Entity_Api_Store();
                    
                    $eStore->setStoreNumber($jsonStore->name)
                            ->setLatitude((string) $jsonStore->latitude)
                            ->setLongitude((string) $jsonStore->longitude)
                            ->setStreet($sAddress->extractAddressPart('street', $jsonStore->street))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $jsonStore->street))
                            ->setZipcode($jsonStore->zip)
                            ->setCity($jsonStore->city)
                            ->setPhone($jsonStore->phone)
                            ->setStoreHours($sTimes->generateMjOpenings($jsonStore->openingDays));
                                                
                    $cStores->addElement($eStore);
                } 
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
