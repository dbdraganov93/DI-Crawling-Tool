<?php

/**
 * Store Crawler für BMW (ID: 68769)
 */
class Crawler_Company_Bmw_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://c2b-services.bmw.com/';
        $searchUrl = $baseUrl . 'c2b-localsearch/services/api/v3/clients/'
                . 'BMWDIGITAL_DLO/DE/pois?country=DE&category=BM'
                . '&maxResults=9999&language=de&name=&lat=50&lng=10';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#callback\((.+)\)#s';
        if (preg_match($pattern, $page, $jsonMatch)) {
            $jStores = json_decode($jsonMatch[1]);
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->data->pois as $singleJStore) {
            if (!preg_match('#de#i', $singleJStore->countryCode)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->key)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->street)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->street)))
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setWebsite($singleJStore->attributes->homepage)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->attributes->phone))
                    ->setFax($sAddress->normalizePhoneNumber($singleJStore->attributes->fax))
                    ->setEmail($singleJStore->attributes->mail);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}