<?php

/*
 * Store Crawler für Renault (ID: 67449)
 */

class Crawler_Company_Renault_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://api-eu.renault.com/';
        $searchUrl = $baseUrl . 'v2/dealers?lat=51.0491316&long=13.78742109999996&size=5000';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cApiStores = $sApi->findStoresByCompany(71992);
        $aZipcodesToSkip = array();
        foreach ($cApiStores->getElements() as $eApiStore) {
            if (!in_array($eApiStore->getZipcode(), $aZipcodesToSkip)) {
                $aZipcodesToSkip[] = $eApiStore->getZipcode();
            }
        }

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'clientKey: silrwUvUxepFUUGzvg0XwJAEMq37sixd',
            'apiKey: 06w0FLgcHKfbgLyQp6FwxDDc7BAJr3Lx',
            'Referer: https://www.renault.de/haendlersuche.html'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $jStores = json_decode($result);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->dealers as $singleJStore) {
            if (in_array($singleJStore->address->postalCode, $aZipcodesToSkip)) {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->dealerId)
                    ->setCity($singleJStore->address->city)
                    ->setStreetAndStreetNumber($singleJStore->address->addressLine1)
                    ->setZipcode($singleJStore->address->postalCode)
                    ->setLongitude($singleJStore->geolocation->longitude)
                    ->setLatitude($singleJStore->geolocation->latitude)
                    ->setPhoneNormalized($singleJStore->contact->phone);
            
            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
