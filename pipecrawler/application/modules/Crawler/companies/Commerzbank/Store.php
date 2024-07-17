<?php

/**
 * Store Crawler für Commerzbank (ID: 71653)
 */
class Crawler_Company_Commerzbank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.filialsuche.commerzbank.de/';
        $searchUrl = $baseUrl . 'get-results-coordinates?lat='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&type=P-ATM&accuracy=50';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aDays = array(
            'mo',
            'di',
            'mi',
            'do',
            'fr'
        );

        $aServices = array(
            'kontoauszugsdrucker' => 'Kontoauszugsdrucker',
            'geldautomat' => 'Geldautomat',
            'einzahlautomat' => 'Einzahlautomat',
            'cashin' => 'CashIn',
            'sbTerminal' => 'SB-Terminal'
        );

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1);
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            if (!count($jStores)) {
                continue;
            }
            foreach ($jStores as $singleJStore) {
                if (!preg_match('#commerzbank#i', $singleJStore->bezeichnung)) {
                    continue;
                }

                $strServices = '';
                foreach ($aServices as $singleServiceKey => $singleServiceValue) {
                    if (!$singleJStore->{$singleServiceKey}) {
                        continue;
                    }
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }
                    $strServices .= $singleServiceValue;
                }

                $strTimes = '';
                foreach ($aDays as $singleDay) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= ucwords($singleDay) . ' ' . $singleJStore->{$singleDay . 'MorgenVon'}
                            . '-' . $singleJStore->{$singleDay . 'MorgenBis'};
                    if (property_exists($singleJStore, $singleDay . 'NachmiVon')) {
                        $strTimes .= ',' . ucwords($singleDay) . ' ' . $singleJStore->{$singleDay . 'NachmiVon'}
                                . '-' . $singleJStore->{$singleDay . 'NachmiBis'};
                    }
                }

                $strTimes = $sTimes->generateMjOpenings($strTimes);

                if ($singleJStore->kasse) {
                    $strNotes = 'Kassenöffnungszeiten: ';
                    foreach ($aDays as $singleDay) {
                        if (!property_exists($singleJStore, 'kasse' . ucwords($singleDay) . 'MorgenVon')) {
                            continue;
                        }
                        if (strcmp('Kassenöffnungszeiten: ', $strNotes) < 0) {
                            $strNotes .= ',';
                        }
                        $strNotes .= ucwords($singleDay) . ' ' . $singleJStore->{'kasse' . ucwords($singleDay) . 'MorgenVon'}
                                . '-' . $singleJStore->{'kasse' . ucwords($singleDay) . 'MorgenBis'};
                        if (property_exists($singleJStore, 'kasse' . ucwords($singleDay) . 'NachmiVon')) {
                            $strNotes .= ',' . $singleJStore->{'kasse' . ucwords($singleDay) . 'NachmiVon'}
                                    . '-' . $singleJStore->{'kasse' . ucwords($singleDay) . 'NachmiBis'};
                        }
                    }
                    if (strcmp('Kassenöffnungszeiten: ', $strNotes) == 0) {
                        $strNotes = '';
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($singleJStore->nummer)
                        ->setZipcode($singleJStore->anschriftPostleitzahl)
                        ->setCity($singleJStore->anschriftOrt)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->anschriftStrasse)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->anschriftStrasse)))
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->telefon))
                        ->setFax($sAddress->normalizePhoneNumber($singleJStore->telefax))
                        ->setSubtitle($singleJStore->name)
                        ->setStoreHours($strTimes)
                        ->setLatitude($singleJStore->position[0])
                        ->setLongitude($singleJStore->position[1])
                        ->setStoreHoursNotes($strNotes)
                        ->setService($strServices);

                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
