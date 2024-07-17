<?php

/*
 * Store Crawler für Harley Davidson (ID: 69676)
 */

class Crawler_Company_HarleyDavidson_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.harley-davidson.com';
        $storeFinderUrl = $baseUrl . '/dealerservices/services/rest/dealers/v2/search.json?'
            . 'locale=de_DE&latlng=51.165691%2C10.451526000000058&bounds=47.2701115'
            . '%2C5.866342499999973%7C55.058347%2C15.041896199999996&_=1370253638638';
        $serviceLocatorUrl = $baseUrl . '/de_DE/Content/Pages/dealer-locator/dealer-locator.html';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($serviceLocatorUrl);
        $page = $sPage->getPage()->getResponseBody();

        $patternLists = '#<ul[^>]*class="sn_list"[^>]*>\s*(\<li[^>]*class="sn_list_title".*?)\s*</ul>#s';
        $aSpecialServices = array();

        if (preg_match_all($patternLists, $page, $matchesLists)) {
            $patternSpecialServices = '#<li[^>]*class="sn_list_item"[^>]*>.*?<input[^>]*value="(.*?)".*?'
                . '<span[^>]*class="sn_title"[^>]*>\s*(.*?)\s*</span>.*?'
                . '<div[^>]*class="sn_abstract"[^>]*>\s*(.*?)\s*</div>.*?</li>#s';
            foreach ($matchesLists[1] as $matchListElement) {
                if (preg_match_all($patternSpecialServices, $matchListElement, $matchesSpecialServices)) {
                    foreach ($matchesSpecialServices[1] as $keySpecial => $keySpecialServices) {
                        $aSpecialServices[$keySpecialServices] = $matchesSpecialServices[2][$keySpecial] . ':' . '<br>'
                            .  $matchesSpecialServices[3][$keySpecial] . '<br><br>';
                    }
                }
            }
        }

        $sPage->open($storeFinderUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        if (count($json->dealers) == 0) {
            throw new Exception('can not find stores, please check json answer');
        }

        foreach($json->dealers as $jsonStore) {
            if ($jsonStore->address->country != 'DEU') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setCity($jsonStore->address->city);
            $eStore->setZipcodeAndCity($jsonStore->address->zipPost);
            $eStore->setStreetAndStreetNumber($jsonStore->address->streetAddress[0]);
            $eStore->setStoreNumber($jsonStore->id);
            $eStore->setTitle($jsonStore->name);
            $eStore->setSubtitle('Motorräder');
            $eStore->setLatitude($jsonStore->position->lat);
            $eStore->setLongitude($jsonStore->position->lng);

            $eStore->setEmail($jsonStore->programCodes->RD->email);
            $eStore->setPhoneNormalized($jsonStore->programCodes->RD->phoneNumber);
            $eStore->setFaxNormalized($jsonStore->programCodes->RD->faxNumber);


            $sTimes = new Marktjagd_Service_Text_Times();
            $times = $sTimes->convertAmPmTo24Hours($sTimes->convertToGermanDays($jsonStore->programCodes->RD->hours));

            $eStore->setStoreHoursNormalized($times);
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
