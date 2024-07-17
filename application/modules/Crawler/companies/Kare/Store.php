<?php

/*
 * Store Crawler für Kare (ID: 71849)
 */

class Crawler_Company_Kare_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.kare.de';
        $searchUrl = $baseUrl . '/stores';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
                 
        $page = $sPage->getPage();
        $page->setUseCookies(true);
        $client = $page->getClient();        
        $client->setHeaders('X-Requested-With', 'XMLHttpRequest');                
        $page->setClient($client);
        $sPage->setPage($page); 
                
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#locations\s*=\s*(\[\{.+?\}\]);#', $page, $locationMatch)){
            throw new Exception('no stores found for location');                        
        }

        $jsonStores = json_decode($locationMatch[1]);
        
        foreach ($jsonStores as $jsonStore){
            if (!preg_match('#(deutsch|german)#i', $jsonStore->country)){
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($jsonStore->id)
                    ->setLatitude($jsonStore->latitude)
                    ->setLongitude($jsonStore->longitude);
            
            $addressLines = preg_split('#\s*\,\s*#', $jsonStore->address);
            $eStore->setStreetAndStreetNumber($addressLines[0])
                   ->setZipcodeAndCity($addressLines[1]);

            $eStore->setSubtitle($jsonStore->name);
            $eStore->setStoreHoursNormalized($jsonStore->businesshours);
            $cStores->addElement($eStore);
        }        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}

