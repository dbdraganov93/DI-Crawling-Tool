<?php

/**
 * Store Crawler für Ebken (ID: 67861)
 */
class Crawler_Company_Ebken_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $baseUrl = 'http://www.ebken.de';
        $searchUrl = $baseUrl . '/wp-admin/admin-ajax.php'
                . '?action=store_search'
                . '&lat=52.912679'
                . '&lng=8.818134999999984'
                . '&max_results=75'
                . '&radius=50'
                . '&autoload=1';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();         
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $sPage->open($searchUrl);            
                
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json as $jStore){
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $jStore->id)
                    ->setStreet($sAddress->extractAddressPart('street', $jStore->address))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $jStore->address))
                    ->setCity($jStore->city)
                    ->setZipcode($jStore->zip)
                    ->setLatitude($jStore->lat)
                    ->setLongitude($jStore->lng)
                    ->setText($jStore->description)
                    ->setPhone($jStore->phone)
                    ->setFax($jStore->fax)
                    ->setEmail($jStore->email)
                    ->setStoreHours($sTimes->generateMjOpenings($jStore->hours))
                    ->setWebsite($baseUrl . $jStore->url)
                    ->setImage($jStore->thumb);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
