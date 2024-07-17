<?php

/**
 * Store Crawler für Bosch Car Service (ID: 28875)
 */
class Crawler_Company_BoschCarService_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://beat.webspace.bosch.com/';
        $searchUrl = $baseUrl . 'RB.GEOLOCATOR/GeoLocator.svc/SearchLocations/ByLocator/WW/488?geo=50,10&rad=15000000&format=json&numResults=1500';
        $sAddress = new Marktjagd_Service_Text_Address();

        $aDays = array(
            '0' => 'Mo',
            '1' => 'Di',
            '2' => 'Mi',
            '3' => 'Do',
            '4' => 'Fr',
            '5' => 'Sa',
            '6' => 'So'
        );

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.boschcarservice.com/de/de/workshop_search_de/workshop_search');
        $page = curl_exec($ch);
        curl_close($ch);

        $jStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($jStores->Locations as $singleStore) {
            if ($singleStore->Address->Country != 'DE') {
                continue;
            }

            $arTimes = array();
            $days = preg_split('#;#', (string)$singleStore->Hours);
            foreach($days as $day){
                $dayVals = preg_split('#,#', $day);
                
                $offset = 0;                
                while (array_key_exists($offset*2+1 , $dayVals)){
                    $arTimes[] = $aDays[$dayVals[0]] . " " . $dayVals[$offset*2+1] . "-" . $dayVals[$offset*2+2];
                    $offset++;
                }
            }         
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreHours(implode(',', $arTimes))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore->Address->Address1)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore->Address->Address1)))
                    ->setCity($sAddress->normalizeCity($singleStore->Address->City))
                    ->setZipcode($singleStore->Address->PostalCode)
                    ->setLatitude($singleStore->Geo->Latitude)
                    ->setLongitude($singleStore->Geo->Longitude)
                    ->setStoreNumber($singleStore->LocationID)
                    ->setSubtitle($singleStore->LocationName)
                    ->setPhone($sAddress->normalizePhoneNumber($singleStore->Resources[1]->Value))
                    ->setFax($sAddress->normalizePhoneNumber($singleStore->Resources[2]->Value))
                    ->setEmail($singleStore->Resources[3]->Value);
            
            if ($eStore->getLatitude() == '0.1') {
                $eStore->setLatitude('');
            }
            
            if ($eStore->getLongitude() == '0.1') {
                $eStore->setLongitude('');
            }
            
            if($eStore->getStoreNumber() == '1437326') {
                $eStore->setFax(NULL)
                        ->setPhone('083196080870')
                        ->setStoreHours('Mo-Fr 08:00-12:00, Mo-Fr 13:00-17:30');
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
