<?php

/* 
 * Store Crawler für Pitarello AT (ID: 72302)
 */

class Crawler_Company_PitarelloAt_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.pittarello.com/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&max_results=5000&radius=5000';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'AT');
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            foreach ($jStores as $singleJStore) {
                if (!preg_match('#Austria#', $singleJStore->country)) {
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $eStore->setStreetAndStreetNumber(preg_replace('#,#', '', $singleJStore->address))
                        ->setCity($singleJStore->city)
                        ->setZipcode(substr($singleJStore->zip, -4))
                        ->setLatitude($singleJStore->lat)
                        ->setLongitude($singleJStore->lng)
                        ->setPhoneNormalized($singleJStore->phone)
                        ->setFaxNormalized($singleJStore->fax)
                        ->setEmail($singleJStore->email)
                        ->setStoreHoursNormalized($singleJStore->hours)
                        ->setWebsite($singleJStore->url);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}