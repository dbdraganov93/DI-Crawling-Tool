<?php

/**
 * Store Crawler für Kochlöffel (ID: 28993)
 */
class Crawler_Company_Kochloeffel_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.kochloeffel.de';
        $storeUrl = $baseUrl . '/filialfinder/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        if (!$sPage->open($storeUrl)) {
            throw new Exception ($companyId . ': unable to open store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match("#allStores: '([^']+)',#",$page,$jsonMatch)){
            throw new Exception ($companyId . ': unable to open store json list.');
        }
        
        $jStores = json_decode($jsonMatch[1], true);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
                        
            $eStore->setStoreNumber($singleStore['id'])
                    ->setStreet($sAddress->extractAddressPart('street', $singleStore['street']))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore['street']))
                    ->setCity($singleStore['city'])
                    ->setZipcode($singleStore['plz'])
                    ->setPhone($singleStore['telephone'])
                    ->setWebsite($baseUrl . $singleStore['link'])
                    ->setLatitude(trim(array_shift(explode(',',$singleStore['latlng']))))
                    ->setLongitude(trim(array_shift(array_reverse(explode(',',$singleStore['latlng'])))));
            
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            // Öffnungszeiten
            if (preg_match('#<strong>Öffnungszeiten</strong>(.+?)(</div>|<h3>)#', $page, $hourMatch)){                
                $eStore->setStoreHours($sTimes->generateMjOpenings($hourMatch[1]));            
            }
            
            // Zusatzinformationen zu den Öffnungszeiten
            if (preg_match('#<h3>(Öffnungszeiten[^<]*)</h3>(.+?)</div>#', $page, $hourMatch)){                
                $eStore->setStoreHoursNotes($hourMatch[1] . ": ". $sTimes->generateMjOpenings($hourMatch[2]));            
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}

