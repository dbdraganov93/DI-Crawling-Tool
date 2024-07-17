<?php

/**
 * Store Crawler für Grün Erleben (ID: 69571)
 */
class Crawler_Company_GruenErleben_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.gruen-erleben.de/';
        $searchUrl = $baseUrl . 'kontakt/gartencenter-vor-ort/?no_cache=1';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
                     
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
               
        if (!preg_match_all('#var\s+market\s*=\s*new\s+Array\(\)\;(.+?)locations\.push#', $page, $storeMatches)){
            throw new Exception('cannot get any store on ' . $searchUrl);
        }
                
        $namedFields = array ('name1' => 'setSubtitle',
                            'latitude' => 'setLatitude',
                            'longitude' => 'setLongitude',
                            'phone' => 'setPhone',
                            'fax' => 'setFax',
                            'uid' => 'setStoreNumber',
                            'street' => 'setStreet',
                            'zip' => 'setZipcode',
                            'city' => 'setCity',
                            'oeffnungszeiten' => 'setStoreHours',
                            'kontaktdaten' => 'setWebsite',
                            'email' => 'setEmail'
                        );
        
        foreach ($storeMatches[1] as $storeMatch){
            $eStore = new Marktjagd_Entity_Api_Store();
            
            foreach ($namedFields as $fieldName => $fieldFunc){
                if (preg_match('#market\[\"'. $fieldName .'\"\]\s*=\s*(\"|\')?([^\;^\"^\']+)(\"|\')?\;#', $storeMatch, $fieldMatch)){
                    $eStore->$fieldFunc(trim($fieldMatch[2]));                    
                }
            }
            
            $eStore->setStoreHours($sTimes->generateMjOpenings($eStore->getStoreHours()))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $eStore->getStreet()))
                    ->setStreet($sAddress->extractAddressPart('street', $eStore->getStreet()));
                        
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}