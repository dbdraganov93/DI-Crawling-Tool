<?php

/**
 * Store Crawler für Kaufpark (ID: 28977)
 */
class Crawler_Company_Kaufpark_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.rewe-ihr-kaufpark.de/';
        $searchUrl = $baseUrl . 'wp-content/plugins/superstorefinder-wp/ssf-wp-xml.php';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $xmlStores = simplexml_load_string($page);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores->store->item as $singleXmlStore) {
            $aAddress = preg_split('#\s*,\s*#', trim((string) $singleXmlStore->address));
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>\s*(\d+)\s*<[^>]*>([^<]+?Parkplätze)#';
            if (preg_match($pattern, $singleXmlStore->description, $parkingMatch)) {
                $eStore->setParking($parkingMatch[1] . ' ' . $parkingMatch[2]);
            }
                        
            $eStore->setAddress($aAddress[0], $aAddress[1])
                    ->setLatitude((string)$singleXmlStore->latitude)
                    ->setLongitude((string)$singleXmlStore->longitude)
                    ->setStoreHoursNormalized((string) $singleXmlStore->operatingHours)
                    ->setPhoneNormalized((string) $singleXmlStore->telephone)
                    ->setStoreNumber((string)$singleXmlStore->storeId)
                    ->setImage('https://www.rewe-ihr-kaufpark.de' . (string) $singleXmlStore->storeimage);
                        
            $cStores->addElement($eStore);
        }
        

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
