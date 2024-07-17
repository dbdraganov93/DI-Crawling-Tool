<?php

/**
 * Store Crawler für Rosenthal (ID: 28303)
 */
class Crawler_Company_Rosenthal_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.rosenthal.de';
        $searchUrl = $baseUrl . '/storelocator/liststores/47.0/5.0/57.0/16.0/100/';
        
        $productMap = array(
            '1' => 'studio-line',
            '2' => 'Selection',
            '3' => 'Thomas',
            '4' => 'Hutschenreuther',
            '5' => 'Versace',
            '6' => 'Sambonet',
            '7' => 'DiVino'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
                               
        $curl = curl_init($searchUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $json = json_decode(curl_exec($curl));
        curl_close($curl);       

        foreach ($json as $singleStore){            
            if (!preg_match('#rosenthal#i', $singleStore->email)
                    || $singleStore->country != 81){
                continue;
            }                        
            
            $eStore = new Marktjagd_Entity_Api_Store();
                        
            $eStore->setStoreNumber($singleStore->pos_id)
                    ->setWebsite($singleStore->web)
                    ->setFaxNormalized($singleStore->fax)
                    ->setCity($singleStore->city)
                    ->setZipcode($singleStore->zipcode)
                    ->setPhoneNormalized($singleStore->telephone)
                    ->setStreetAndStreetNumber($singleStore->street)
                    ->setLatitude((string) $singleStore->lat)
                    ->setLongitude((string) $singleStore->lng)
                    ->setEmail($singleStore->email);
            
            if (preg_match('#(rosenthal|studiohaus)\.[^\@]+?\@#', $eStore->getEmail())) {
                $eStore->setTitle('Rosenthal Flagship Store');
            } elseif (preg_match('#outlet#', $eStore->getEmail())) {
                $eStore->setTitle('Rosenthal Outlet');
            }
                    
            $products = array();
            foreach ($singleStore->sells_productrange as $productidx){
                $products[] = $productMap[$productidx];
            }
            
            $eStore->setSection(implode(', ', $products));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}