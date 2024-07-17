<?php

/* 
 * Store Crawler für s.Oliver (ID: 22045)
 */

class Crawler_Company_Soliver_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://storefinder.soliver.com/';
        $searchUrl = $baseUrl . 'BYPASS/storefinder-rest-ws/rest/'
                . 'storesByGeoLocation/soliver-de/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '/'
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '?language=de&country=de';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sDb = new Marktjagd_Database_Service_GeoRegion();
        
        $aWeekDays = array(
            '1' => 'So',
            '2' => 'Mo',
            '3' => 'Di',
            '4' => 'Mi',
            '5' => 'Do',
            '6' => 'Fr',
            '7' => 'Sa'
        );
        
        $aServices = array(
            11 => 's.Oliver Gutschein',
            14 => 'Packmee',
            23 => 'Click & Collect',
            27 => 'Presale',
            28 => 'scan and win'
        );
        
        $aSections = array(
            16 => 's.Oliver women',
            17 => 's.Oliver men',
            18 => 's.Oliver junior'
        );
        
        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5);
        $aGermanZipcodes = $sDb->findAllZipCodes();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            foreach ($jStores as $singleJStore) {
                if ($singleJStore->typeId != 1
                        || !in_array($singleJStore->address->postCode, $aGermanZipcodes)
                        || strlen($singleJStore->address->postCode) != 5) {
                    continue;
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $strTimes = '';
                foreach ($singleJStore->openingHours as $singleTime) {
                    if (strlen($strTimes)) {
                        $strTimes .= ', ';
                    }
                    $strTimes .= $aWeekDays[$singleTime->dayFrom] . '-' . $aWeekDays[$singleTime->dayTo]
                            . ' ' . $singleTime->timeFrom . '-' . $singleTime->timeTo;
                }
                
                $strServices = '';
                foreach ($singleJStore->filterIds as $singleService) {
                    if (array_key_exists($singleService, $aServices)) {
                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }
                        $strServices .= $aServices[$singleService];
                    }
                }
                
                $strSections = '';
                foreach ($singleJStore->filterIds as $singleSection) {
                    if (array_key_exists($singleSection, $aSections)) {
                        if (strlen($strSections)) {
                            $strSections .= ', ';
                        }
                        $strSections .= $aSections[$singleSection];
                    }
                }
                
                $eStore->setStoreNumber($singleJStore->id)
                        ->setLatitude($singleJStore->latitude)
                        ->setLongitude($singleJStore->longitude)
                        ->setZipcode($singleJStore->address->postCode)
                        ->setCity($singleJStore->address->city)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address->street)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address->street)))
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->contact->phone))
                        ->setStoreHours($sTimes->generateMjOpenings($strTimes))
                        ->setSection($strSections)
                        ->setService($strServices);
                
                if (in_array(12, $singleJStore->filterIds)) {
                    $eStore->setBonusCard('s.Oliver Card');
                }
                if (in_array(15, $singleJStore->filterIds)) {
                    if (strlen($eStore->getBonusCard())) {
                        $eStore->setBonusCard($eStore->getBonusCard() . ', ');
                    }
                    $eStore->setBonusCard($eStore->getBonusCard() . 's.Oliver Geschenkkarte');
                }
                
                $cStores->addElement($eStore, true);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}