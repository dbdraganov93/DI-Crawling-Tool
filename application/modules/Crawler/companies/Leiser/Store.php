<?php

/**
 * Store Crawler für Leiser (ID: 281)
 */
class Crawler_Company_Leiser_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $baseUrl = 'http://www.leiser.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat=&lng=&max_results=100&radius=5000&autoload=1';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();         
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $sPage->open($searchUrl);            
                
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json as $jStore){
            if (preg_match('#^00#', $jStore->phone)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $jStore->id)
                    ->setStreetAndStreetNumber($jStore->address)
                    ->setSubtitle($jStore->address2)
                    ->setCity($jStore->city)
                    ->setZipcode($jStore->zip)
                    ->setLatitude($jStore->lat)
                    ->setLongitude($jStore->lng)
                    ->setText($jStore->description)
                    ->setPhoneNormalized($jStore->phone)
                    ->setFaxNormalized($jStore->fax)
                    ->setEmail($jStore->email)
                    ->setStoreHoursNormalized($jStore->hours)
                    ->setWebsite($jStore->url)
                    ->setImage($jStore->thumb);
            
            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
