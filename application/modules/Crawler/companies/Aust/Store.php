<?php

/*
 * Store Crawler für Aust (ID: 29145)
 */

class Crawler_Company_Aust_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.austfashion.com';
        $searchUrl = $baseUrl . '/xml-export/';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();                      

        $cStores = new Marktjagd_Collection_Api_Store();
                   
        $sPage->open($searchUrl);        
        $xmlPage = simplexml_load_string($sPage->getPage()->getResponseBody());
                
        foreach ($xmlPage as $singleXmlStore) {
            $addressLines = explode(',', (string) $singleXmlStore->attributes()->address);
            
            if (!preg_match('#(deutsch|german)#i', $addressLines[2])){
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setWebsite((string) $singleXmlStore->attributes()->detail)
                    ->setSubtitle((string) $singleXmlStore->attributes()->name)
                    ->setLatitude((string) $singleXmlStore->attributes()->lat)
                    ->setLongitude((string) $singleXmlStore->attributes()->lng)
                    ->setStreetAndStreetNumber((string) $singleXmlStore->attributes()->street)
                    ->setZipcode((string) $singleXmlStore->attributes()->zip)
                    ->setCity(trim($addressLines[1]));
                                    
            $sPage->open($eStore->getWebsite());
            $page = $sPage->getPage()->getResponseBody();
            
            if (preg_match('#<table[^>]*id="b-open-hours"[^>]*>(.+?)</table>#', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);
            }
            
            if (preg_match('#>telefon\s*(.+?)<#i', $page, $match)){
                $eStore->setPhoneNormalized($match[1]);
            }
            
            if (preg_match('#>.*?mail\s*(.+?)<#i', $page, $match)){
                $eStore->setEmail($match[1]);
            }
            
            if (preg_match('#>(ansprechpart[^\:]+\s*.+?)<#i', $page, $match)){
                $eStore->setText($match[1]);
            }

            Zend_Debug::dump($eStore);
            $cStores->addElement($eStore);
        }
                                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
