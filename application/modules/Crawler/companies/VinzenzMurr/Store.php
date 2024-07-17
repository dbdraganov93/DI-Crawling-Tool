<?php

/**
 * Store Crawler für Vinzenz Murr (ID: 67866)
 */
class Crawler_Company_VinzenzMurr_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://vinzenzmurr.de/';
        $searchUrl = $baseUrl . 'relaunch13/wp-admin/admin-ajax.php?';

        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $geoSteps = 0.1;
        $southLat = 47.0;     // 47.2701270
        $northLat = 55.5;     // 55.081500
        $westLong = 5.5;      // 5.8663566
        $eastLong = 15.5;     // 15.0418321

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($long = $westLong; $long <= $eastLong; $long += $geoSteps) {
            for ($lat = $southLat; $lat <= $northLat; $lat += $geoSteps) {
                $params = array(
                    'action' => 'csl_ajax_onload',
                    'lat' => $lat,
                    'lng' => $long,
                    'radius' => '100000'
                );

                $sPage->open($searchUrl, $params);
                $aStores = json_decode($sPage->getPage()->getResponseBody(), true);                
                
                if (!count($aStores['response'])) {
                    $this->_logger->info($companyId . ': no stores for ' . $lat . '-' . $long);
                    continue;
                }
                
                foreach ($aStores['response'] as $singleStore) {                    
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $eStore->setStoreNumber($singleStore['id'])
                            ->setImage($singleStore['image'])
                            ->setStreetAndStreetNumber($singleStore['address'])
                            ->setZipcode($singleStore['zip'])
                            ->setCity($singleStore['city'])
                            ->setLatitude($singleStore['lat'])
                            ->setLongitude($singleStore['lng'])
                            ->setWebsite($singleStore['url'])
                            ->setSection($singleStore['description'])
                            ->setEmail($singleStore['email'])
                            ->setPhoneNormalized($singleStore['phone'])
                            ->setFaxNormalized($singleStore['fax']);
                    
                    $cStores->addElement($eStore,true);
                }
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
