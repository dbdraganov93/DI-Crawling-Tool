<?php

/**
 * Store Crawler für VR - Bank (ID: )
 */
class Crawler_Company_Volksbank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        Zend_Debug::dump('start time: ' . date('d.m.Y H:i'));
        $baseUrl = 'https://ots-vrnw.de/';
        $searchUrl = $baseUrl . 'vrde/filialsuche.php?service=banks&city='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&teilnahme_vrde=true&api_radius=100&api_limit=100&callback=banksearch';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aServices = array(
            'cash_box' => 'Kasse',
            'credit_transfer_scanner' => 'Überweisungsscanner',
            'bankcard_pin_changeable' => 'Bankcard PIN änderbar',
            'processing_service' => 'Umzugsservice',
            'atm' => 'Geldautomat',
            'bank_statement_printer' => 'Kontoauszugsdrucker',
            'safe_deposit_box' => 'Schließfach',
            'letter_locker' => 'Brieffach',
            'night_depository' => 'Nacht-Depot',
            'deposit_machine' => 'Einzahlautomat',
            'coin_counter' => 'Münzzähler',
            'self_service_terminal' => 'SB-Terminal',
            'pay_card_charging' => 'Geldkarte aufladbar',
            'mobile_card_charging' => 'Mobilfunk-Karte aufladbar',
            'post_service' => 'Postdienstleistungen',
            'bankcard_service_network' => 'Bankcard Service-Netzwerk',
            'account_info_service_network' => 'Kontoinfo'
        );

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#banksearch\((.+)\)#s';
            if (!preg_match($pattern, $page, $storeMatch)) {
                $this->_logger->err($companyId . ': unable to get any stores for: ' . $singleUrl);
                continue;
            }

            $jStores = json_decode($storeMatch[1]);

            foreach ($jStores as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                if ($singleJStore->free_parking) {
                    $eStore->setParking('verfügbar, kostenlos');
                }

                if ($singleJStore->wheelchair_accessible) {
                    $eStore->setBarrierFree(true);
                }

                $strServices = '';
                foreach ($aServices as $singleServiceKey => $singleServiceValue) {
                    if ($singleJStore->$singleServiceKey) {
                        if (strlen($strServices)) {
                            $strServices .= ',';
                        }
                        $strServices .= $singleServiceValue;
                    }
                }
                if (!preg_match('#true_facility#', $singleJStore->facility_type)) {
                    continue;
                }

                $strTimes = '';
                for ($i = 1; $i <= 8; $i++) {
                    $field = 'opening_time_' . $i;
                    if (strlen($singleJStore->$field)) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleJStore->$field;
                    }
                }

                $eStore->setStoreNumber($singleJStore->id)
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone_area . $singleJStore->phone_number))
                        ->setFax($sAddress->normalizePhoneNumber($singleJStore->fax_area . $singleJStore->fax_number))
                        ->setEmail($singleJStore->email)
                        ->setCity($singleJStore->city)
                        ->setZipcode($singleJStore->zip_code)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->street)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->street)))
                        ->setStoreHours($sTimes->generateMjOpenings($strTimes))
                        ->setLatitude($singleJStore->latitude)
                        ->setLongitude($singleJStore->longitude)
                        ->setService($strServices)
                        ->setTitle($singleJStore->central_office_name);

                $cStores->addElement($eStore);
            }
        }
        Zend_Debug::dump('end time: ' . date('d.m.Y H:i'));
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
