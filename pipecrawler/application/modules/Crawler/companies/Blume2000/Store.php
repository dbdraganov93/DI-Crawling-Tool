<?php

/* 
 * Store Crawler für Blume 2000 (ID: 411)
 */

class Crawler_Company_Blume2000_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://unternehmen.blume2000.de/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?action=store_search&lat=50&lng=10&max_results=1000&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStreetAndStreetNumber($singleJStore->address)
                    ->setStoreNumber($singleJStore->id)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zip)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setEmail($singleJStore->email)
                    ->setStoreHoursNormalized($singleJStore->hours)
                    ->setWebsite($singleJStore->url);
            
            if ($singleJStore->service_bouquet == '1') {
                $eStore->setService('Blumenbouquet');
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}