<?php

/* 
 * Store Crawler für Werkmarkt24 (ID: 71689)
 */

class Crawler_Company_Werkmarkt24_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.werkmarkt24.com/';
        $searchUrl = $baseUrl . 'onibi_storelocator?address=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*stores\s*=\s*(.+?);#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1])->items as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleJStore->entity_id)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address)))
                    ->setZipcode($singleJStore->zipcode)
                    ->setCity($singleJStore->city)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                    ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax))
                    ->setWebsite(trim(preg_replace('#([^<]+?)<.+#s', '$1', $singleJStore->description)))
                    ->setEmail($singleJStore->store_url)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->long);
            
            if (!preg_match('#default#', $singleJStore->image)) {
                $eStore->setImage($singleJStore->image);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}