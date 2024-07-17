<?php

/**
 * Store Crawler für Quiksilver (ID: 68085)
 */
class Crawler_Company_Quiksilver_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        
        $baseUrl = 'http://www.quiksilver.de';
        $searchUrl = $baseUrl . '/s/QS-DE/dw/shop/v14_2/stores'
                . '?latitude=50.963896'
                . '&longitude=11.071368'
                . '&country_code=DE'
                . '&distance_unit=MI'
                . '&max_distance=1000'
                . '&client_id=38bb2962-3bb4-46ab-8068-d9840d414ba4'
                . '&_=1452604049139';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();         
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $sPage->open($searchUrl);                            
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json->data as $jStore){            
            if ($jStore->country_code != 'DE'){
                continue;
            }            
            
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStreetAndStreetNumber($jStore->address1)
                    ->setCity($jStore->city)
                    ->setStoreNumber($jStore->id)
                    ->setLatitude($jStore->latitude)
                    ->setLongitude($jStore->longitude)
                    ->setZipcode(preg_replace('#D\-#', '', $jStore->postal_code))
                    ->setPhoneNormalized($jStore->phone)
                    ->setSubtitle($jStore->name);

            Zend_Debug::dump($jStore);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
