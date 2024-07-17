<?php

/**
 * Store Crawler für Lila Bäcker (ID: 68942)
 */
class Crawler_Company_LilaBaecker_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://lila-world.com/';
        $searchUrl = $baseUrl . 'component/shops/'
                . '?task=listaAjax';
        
        $polskaDays = array(
            'Pon' => 'Mo',
            'Wt' => 'Di',
            'Sr' => 'Mi',
            'Czw' => 'Do',
            'Pt' => 'Fr',
            'Sob' => 'Sa',
            'Nd' => 'So'
        );
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {            
            $sPage->open($singleUrl);            
            $json = $sPage->getPage()->getResponseAsJson();
                        
            foreach ($json->data as $singleStore) {                      
                $eStore = new Marktjagd_Entity_Api_Store();
        
                $eStore->setStoreNumber($singleStore->numerFilii)
                        ->setSubtitle($singleStore->strNazwa)
                        ->setStreet($sAddress->extractAddressPart('street', $singleStore->strUlica))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $singleStore->strUlica))
                        ->setZipcode(str_pad($singleStore->strKod, 5, '0', STR_PAD_LEFT))
                        ->setCity($singleStore->strMiasto)
                        ->setLatitude($singleStore->lat)
                        ->setLongitude($singleStore->lng);
                                          
                $hours = array();                
                foreach ($polskaDays as $polskaDay => $germanDay){
                    $keyFrom = 'godz' . $polskaDay . 'Od';
                    $keyTo = 'godz' . $polskaDay . 'Do';
                    
                    if ($singleStore->$keyFrom && strlen($singleStore->$keyFrom)){
                        $hours[] = $germanDay . ' ' . $singleStore->$keyFrom . '-' . $singleStore->$keyTo;
                    }                    
                }
                
                if ($singleStore->godzNdOd2 && strlen($singleStore->godzNdOd2)){
                    $hours[] = 'So ' . $singleStore->godzNdOd2 . '-' . $singleStore->godzNdDo2;
                }
                
                $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $hours)));
                
                $cStores->addElement($eStore, TRUE);                
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}