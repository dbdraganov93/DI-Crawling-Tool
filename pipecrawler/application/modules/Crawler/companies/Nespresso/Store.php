<?php

/* 
 * Store Crawler für Nespresso (ID: 71965)
 */

class Crawler_Company_Nespresso_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nespresso.com/';
        $searchUrl = $baseUrl . 'storelocator/app/find_poi.php?country=DE&lang=DE&'
                . 'lat=52.0706318&lng=14.37785759999997&geo=false&type=boutique&'
                . 'nearby=false&countryRestriction=';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setLatitude($singleJStore->position->latitude)
                    ->setLongitude($singleJStore->position->longitude)
                    ->setStreetAndStreetNumber($singleJStore->point_of_interest->address->address_line)
                    ->setCity(trim($singleJStore->point_of_interest->address->city->name))
                    ->setZipcode($singleJStore->point_of_interest->address->postal_code)
                    ->setStoreHoursNormalized($singleJStore->point_of_interest->opening_hours_text->text)
                    ->setPhoneNormalized($singleJStore->point_of_interest->phone);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}