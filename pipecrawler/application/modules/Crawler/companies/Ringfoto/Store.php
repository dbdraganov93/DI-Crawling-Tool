<?php

/* 
 * Store Crawler für Ringfoto (ID: 28656)
 */

class Crawler_Company_Ringfoto_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.ringfoto.de/';
        $searchUrl = $baseUrl . 'api/stores/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON
                . '/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '/50000';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'rect', 0.2);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (!$jStores) {
                continue;
            }
            
            foreach ($jStores as $singleJStore) {
                if (!preg_match('#deutschland#i', $singleJStore->country)) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $strTimes = '';
                foreach ($singleJStore->hours as $singleDay => $singleOpenings) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay . ' ' . $singleOpenings[0] . '-' . $singleOpenings[1];
                    if (array_key_exists(2, $singleOpenings)) {
                        $strTimes .= ',' . $singleDay . ' ' . $singleOpenings[2] . '-' . $singleOpenings[3];
                    }
                }
                
                $eStore->setStoreNumber($singleJStore->id)
                        ->setTitle($singleJStore->name)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->street)))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->street))
                        ->setZipcode($singleJStore->zipcode)
                        ->setCity($singleJStore->city)
                        ->setEmail($singleJStore->mail)
                        ->setWebsite($singleJStore->www)
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->tel))
                        ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax))
                        ->setLongitude($singleJStore->loc->coordinates[0])
                        ->setLatitude($singleJStore->loc->coordinates[1])
                        ->setStoreHours($sTimes->generateMjOpenings($sTimes->convertToGermanDays($strTimes)));
                
                if ($eStore->getStoreNumber() == '2058') {
                    $eStore->setStoreHours('Mo 10:00-18:00, Di 14:00-19:00, Do 14:00-19:00, Fr 10:00-18:00');
                }
                
                $cStores->addElement($eStore, TRUE);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}