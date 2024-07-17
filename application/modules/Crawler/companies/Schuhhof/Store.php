<?php

/**
 * Store Crawler für Schuhhof (ID: 67895)
 */

class Crawler_Company_Schuhhof_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $generateUrls = new Marktjagd_Service_Generator_Url();
                
        $baseUrl = 'http://www.schuhhof.de/wp-admin/admin-ajax.php?action=store_search&'
                . 'lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON
                . '&max_results=100&radius=500';
        
        $aRequestLinks = $generateUrls->generateUrl($baseUrl, Marktjagd_Service_Generator_Url::$_TYPE_COORDS, 1);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        foreach ($aRequestLinks as $sRequestLink) {
            $this->_logger->log('open ' . $sRequestLink, Zend_Log::INFO);
            
            if (!$sPage->open($sRequestLink)) {
                throw new Exception('unable to get link ' . $companyId);
            }

            $page = $sPage->getPage()->getResponseBody();
            if (!strlen(preg_match('#\[\s*(.+?)\s*\]#', $page))) {
                continue;
            }
            
            $jStores = json_decode($page);
            
            foreach ($jStores as $jStore) {         
                $eStore = new Marktjagd_Entity_Api_Store();
                                
                $jStore->address = ucwords(strtolower(preg_replace('#\, #', ' ', $jStore->address)));

                if ($jStore->country != "Deutschland"){
                    continue;
                }

                $street = $jStore->address;
                
                $pattern = '#\d[a-z]?\s+([A-Z].+)#';
                if (preg_match($pattern, $jStore->address, $subTitleMatch)) {
                    $street = preg_replace('#' . $subTitleMatch[1] . '#', '', $jStore->address);
                    $eStore->setSubtitle($subTitleMatch[1]);
                }
                
                $eStore->setStoreNumber($jStore->id)
                        ->setZipcode($jStore->zip)
                        ->setCity(ucwords(strtolower($jStore->city)))    
                        ->setStreetAndStreetNumber($street)
                        ->setLatitude($jStore->lat)
                        ->setLongitude($jStore->lng)
                        ->setText($jStore->description)
                        ->setPhone($mjAddress->normalizePhoneNumber($jStore->phone))
                        ->setFax($mjAddress->normalizePhoneNumber($jStore->fax))
                        ->setEmail($jStore->email)
                        ->setWebsite($jStore->url)
                        ->setStoreHours($sTimes->generateMjOpenings($jStore->hours));
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}


