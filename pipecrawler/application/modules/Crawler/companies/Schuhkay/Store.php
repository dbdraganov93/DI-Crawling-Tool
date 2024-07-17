<?php

/* 
 * Store Crawler für Schuhkay (ID: 29142)
 */

class Crawler_Company_Schuhkay_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://schuhkay.de/';
        $searchUrl = $baseUrl . 'schuhkay-in-ihrer-naehe/search?searchterm=all';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->fi_intnr)
                    ->setSubtitle($singleJStore->volksmund)
                    ->setStreetAndStreetNumber($singleJStore->strasse)
                    ->setZipcode($singleJStore->plz)
                    ->setCity($singleJStore->ort)
                    ->setPhoneNormalized($singleJStore->tel)
                    ->setStoreHoursNormalized($singleJStore->oeffnungszeiten)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng);
            
            $cStores->addElement($eStore, TRUE);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}