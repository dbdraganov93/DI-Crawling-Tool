<?php

/* 
 * Store Crawler für FasMas (ID: 71879)
 */

class Crawler_Company_FasMas_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'https://fasmas.de/';
        $searchUrl = $baseUrl . 'storelocator/liststores/45.0/5.0/56.0/17.0/100/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
                
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->pk)
                    ->setCity($singleJStore->fields->city)
                    ->setZipcode($singleJStore->fields->zip)
                    ->setFaxNormalized($singleJStore->fields->fax)
                    ->setStoreHoursNormalized($singleJStore->fields->oppening_hours)
                    ->setStreetAndStreetNumber($singleJStore->fields->street)
                    ->setPhoneNormalized($singleJStore->fields->phone)
                    ->setWebsite($singleJStore->fields->link)
                    ->setLatitude($singleJStore->fields->lat)
                    ->setLongitude($singleJStore->fields->lng)
                    ->setEmail($singleJStore->fields->email)
                    ->setSubtitle($singleJStore->fields->name);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}