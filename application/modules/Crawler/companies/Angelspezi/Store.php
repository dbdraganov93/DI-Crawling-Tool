<?php

/**
 * Store Crawler für Angelspezi (ID: 67874)
 */
class Crawler_Company_Angelspezi_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.angelspezi.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php';
        
        $params = array (
            'action' => 'csl_ajax_onload',                        
            'radius' => '1000',            
        );               
        
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);                        
                
        $southLat = 47.200;     // 47.2701270
        $northLat = 55.200;     // 55.081500
        $westLong = 5.800;      // 5.8663566
        $eastLong = 15.200;     // 15.0418321
        $geoSteps = 2;

        for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
            for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                $params['lat'] = $lat;
                $params['lng'] = $long;
                               
                $sPage->open($searchUrl, $params);        
                                
                $jStores = $sPage->getPage()->getResponseAsJson();
                
                foreach ($jStores->response as $singlejStore) {        
                    $eStore = new Marktjagd_Entity_Api_Store();             

                    $eStore->setStoreNumber($singlejStore->id)
                            ->setImage($singlejStore->image)
                            ->setFax($singlejStore->fax)
                            ->setPhone($sAddress->normalizePhoneNumber($singlejStore->phone))
                            ->setStoreHours($sTimes->generateMjOpenings(html_entity_decode($singlejStore->hours)))
                            ->setEmail($singlejStore->email)
                            ->setWebsite($singlejStore->url)
                            ->setText($singlejStore->description)
                            ->setLongitude((string) $singlejStore->lng)
                            ->setLatitude((string) $singlejStore->lat)
                            ->setZipcode($singlejStore->zip)
                            ->setCity($singlejStore->city)
                            ->setStreet($sAddress->extractAddressPart('street', $singlejStore->address))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $singlejStore->address));

                    $cStores->addElement($eStore);
                }                                
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}