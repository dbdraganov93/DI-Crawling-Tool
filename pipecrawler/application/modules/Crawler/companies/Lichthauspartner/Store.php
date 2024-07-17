<?php

/**
 * Store Crawler für Lichthauspartner (ID: 71339)
 */
class Crawler_Company_Lichthauspartner_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.kooperation.lichthauspartner.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php'
                . '?action=store_search'
                . '&lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . ''
                . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . ''
                . '&max_results=50'
                . '&radius=100';
                        
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
             
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 1);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $this->_logger->info('open ' . $singleUrl);                    
            
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (!count($jStores)) {
                continue;
            }
            
            foreach ($jStores as $singleJStore) {
                if (strlen($singleJStore->zip) != 5){
                    continue;
                }                
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setStoreNumber($singleJStore->id)
                        ->setTitle($singleJStore->store)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address)))
                        ->setCity($singleJStore->city)
                        ->setZipcode($singleJStore->zip)
                        ->setLatitude((string) $singleJStore->lat)
                        ->setLongitude((string) $singleJStore->lng)
                        ->setText($singleJStore->description)
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                        ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax))
                        ->setEmail($singleJStore->email)
                        ->setWebsite($singleJStore->url)
                        ->setStoreHours($sTimes->generateMjOpenings($singleJStore->hours))
                        ->setLogo($singleJStore->thumb);
                
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
