<?php

/*
 * Store Crawler für ALLE EDEKA-Standorte
 */

class Crawler_Company_EdekaGroup_StoreAll extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.edeka.de/';
        $aStoreListUrl = array();
        for ($counter = 0; $counter <= 9; $counter++) {
            $url = $baseUrl
                    . 'search.xml?'
                    // Auswahlparameter für Abfrage
                    . 'fl=marktID_tlc%2Cplz_tlc%2Cort_tlc%2Cstrasse_tlc%2Cname_tlc%2C'
                    . 'geoLat_doubleField_d%2CgeoLng_doubleField_d%2Ctelefon_tlc%2ChandzettelUrl_tlc%2Cfax_tlc%2C'
                    . 'services_tlc%2Coeffnungszeiten_tlc%2CknzUseUrlHomepage_tlc%2C'
                    . 'urlHomepage_tlc%2CurlExtern_tlc%2CmarktTypName_tlc%2CmapsBildURL_tlc%2C'
                    . 'vertriebsschieneName_tlc%2CvertriebsschieneKey_tlc'
                    // restliche Parameter
                    . '&hl=true&indent=off&q=indexName%3Ab2c'
                    . 'MarktDBIndex%20AND%20plz_tlc%3A'
                    . $counter
                    . '*%20AND%20kanalKuerzel_tlcm%3Aedeka+AND+geoLat_doubleField_d%3A%5B40+TO+60'
                    . '%5D+AND+geoLng_doubleField_d%3A%5B5+TO+20%5D&rows=10000';

            $aStoreListUrl[] = $url;
        }
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeoRegion->findZipCodesByNetSize(5, TRUE);

        $aDist = array(
            '#\d04#is' => 'EDEKA',
            '#\d40#is' => 'NP Discount',
            '#\d50#is' => 'Marktkauf',
            '#\d01#is' => 'EDEKA Aktiv',
            '#\d84#is' => 'E-Center',
            '#(\d02|1003)#is' => 'E-Neukauf',
            '#\d44#is' => 'inkoop',
            '#\d3[1|4]#is' => 'nah und gut',
            '#\d93#is' => 'Profi Getränke Shop',
            '#\d11#is' => 'WEZ',
            '#\d22#is' => 'diska',
            '#\d32#is' => 'Treff 3000',
            '#\d34#is' => 'CAP-Markt'
        );

        $aRegionals = array(
            'MINDEN' => 'EDEKA Minden-Hannover',
            'SUEDBAYERN' => 'EDEKA Südbayern',
            'NORDBAYERN' => 'EDEKA Nordbayern-Sachsen-Thüringen',
            'HESSEN' => 'EDEKA Hessenring',
            'SUEDWEST' => 'EDEKA Südwest',
            'RHEINRUHR' => 'EDEKA Rhein-Ruhr',
            'NORD' => 'EDEKA Nord'
        );

        $aDists = array();
        foreach ($aStoreListUrl as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->response->docs as $singleJStore) {
                if (preg_match('#handzettel\/([A-ZÄÖÜ]+?)\/#', $singleJStore->handzettelUrl_tlc, $distMatch)) {
                    $aDists[$singleJStore->plz_tlc] = $distMatch[1];
                } else {
                    continue;
                }
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreListUrl as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->response->docs as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStreetAndStreetNumber($singleJStore->strasse_tlc)
                        ->setZipcode($singleJStore->plz_tlc)
                        ->setCity($singleJStore->ort_tlc);

                foreach ($aDist as $distNumber => $distName) {
                    if (preg_match($distNumber, $singleJStore->vertriebsschieneKey_tlc)) {
                        $eStore->setDistribution($distName);
                        break;
                    }
                }
                
                if (!strlen($eStore->getDistribution())) {
                    $eStore->setDistribution('Franchise');
                }

                if (strlen($aDists[$singleJStore->plz_tlc])) {
                    $eStore->setTitle($aRegionals[$aDists[$singleJStore->plz_tlc]]);
                } else {
                    $maxDistance = 5000;
                    foreach ($aDists as $distZip => $distRegional) {
                        $distance['distance'] = $sAddress->calculateDistanceFromGeoCoordinates(
                                $aZipcodes[$distZip]['lat'],
                                $aZipcodes[$distZip]['lng'],
                                $singleJStore->geoLat_doubleField_d,
                                $singleJStore->geoLng_doubleField_d);
                        if ($distance['distance'] < $maxDistance) {
                            $maxDistance = $distance['distance'];
                            $distance['region'] = $distRegional;
                        }
                    }
                }
                
                $eStore->setTitle($aRegionals[$distance['region']]);

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('EDEKA');
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
