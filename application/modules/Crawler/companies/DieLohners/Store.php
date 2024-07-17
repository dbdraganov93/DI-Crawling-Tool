<?php

/**
 * Store Crawler für Die Lohners (ID: 68930)
 */
class Crawler_Company_DieLohners_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://die-lohners.de';
        $searchUrl = $baseUrl . '/wp-admin/admin-ajax.php';        
        
        $params = array (
            'action' => 'csl_ajax_search',                        
            'radius' => '200',            
        );               
        
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $oPage->setTimeout(120);
        $sPage->setPage($oPage);                        
                
        $southLat = 47.200;     // 47.2701270
        $northLat = 55.200;     // 55.081500
        $westLong = 5.800;      // 5.8663566
        $eastLong = 15.200;     // 15.0418321
        $geoSteps = 0.2;

        for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
            for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                $params['lat'] = $lat;
                $params['lng'] = $long;
                               
                $sPage->open($searchUrl, $params);
                $jStores = $sPage->getPage()->getResponseAsJson();

                foreach ($jStores->response as $singlejStore) {        
                    $eStore = new Marktjagd_Entity_Api_Store();             
                    
                    $eStore->setSubtitle($singlejStore->name)
                            ->setStreet($sAddress->extractAddressPart('street', $singlejStore->address))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $singlejStore->address))
                            ->setCity($singlejStore->city)
                            ->setZipcode($singlejStore->zip)
                            ->setLatitude($singlejStore->lat)
                            ->setLongitude($singlejStore->lng)
                            ->setText($singlejStore->description)
                            ->setWebsite($singlejStore->url)
                            ->setEmail($singlejStore->email)
                            ->setStoreHours($sTimes->generateMjOpenings($singlejStore->hours))
                            ->setPhone($sAddress->normalizePhoneNumber($singlejStore->phone))
                            ->setFax($sAddress->normalizePhoneNumber($singlejStore->fax))
                            ->setStoreNumber($singlejStore->id)
                            ->setService($singlejStore->category_names);                                    
                                                                        
                    $cStores->addElement($eStore, true);
                }
            }
        }
                        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}