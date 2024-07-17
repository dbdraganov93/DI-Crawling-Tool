<?php

/**
 * Edeka Group Store Crawler
 *
 * Ids: 2, 5, 7, 28983, 67774, 67775, 67776, 67777, 67781, 67782, 67783, 67784, 67785, 67786, 67787, 67788, 67790
 *
 * Class Crawler_Company_Edeka_Store
 */
class Crawler_Company_EdekaGroup_Store extends Crawler_Generic_Company {

    protected $_baseUrl = 'https://www.edeka.de/';
    protected $_skipStoresFromCompany = array();
    protected $_skipStoresWithTitle = array();
    protected $_subtitle = '';
    protected $_aSkipZipCodes = array();
    protected $_website = '';
    protected $_patternDistribution = '';

    /**
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $aStoreListUrl = array();
        $this->_initializeConfig($companyId);
        $aCompanyIds = array(
            '2' => '71668',
            '67775' => '71669',
            '7' => '71670',
            '67786' => '71672',
            '28983' => '71673',
        );
        if (array_key_exists($companyId, $aCompanyIds)) {
            $aStores = $sApi->findStoresByCompany($aCompanyIds[$companyId]);

            foreach ($aStores->getElements() as $singleStore) {
                $aStoreNumbers[] = md5($singleStore->getZipCode()
                        . $sAddress->normalizeStreet($singleStore->getStreet())
                        . $singleStore->getStreetNumber());
            }
        }
        for ($counter = 0; $counter <= 9; $counter++) {
            $url = $this->_baseUrl
                    . 'search.xml?'
                    // Auswahlparameter für Abfrage
                    . 'fl=marktID_tlc%2Cplz_tlc%2Cort_tlc%2Cstrasse_tlc%2Cname_tlc%2C'
                    . 'geoLat_doubleField_d%2CgeoLng_doubleField_d%2Ctelefon_tlc%2Cfax_tlc%2C'
                    . 'services_tlc%2Coeffnungszeiten_tlc%2ChandzettelUrl_tlc%2CknzUseUrlHomepage_tlc%2C'
                    . 'urlHomepage_tlc%2CurlExtern_tlc%2CmarktTypName_tlc%2CmapsBildURL_tlc%2C'
                    . 'vertriebsschieneName_tlc%2CvertriebsschieneKey_tlc'
                    // restliche Parameter
                    . '&hl=false&indent=off&q=indexName%3Ab2c'
                    . 'MarktDBIndex%20AND%20plz_tlc%3A'
                    . $counter
                    . '*%20AND%20kanalKuerzel_tlcm%3Aedeka%20AND%20'
                    . 'freigabeVonDatum_longField_l%3A%5B0%20TO%201389999599999%5D%20AND%20'
                    . 'freigabeBisDatum_longField_l%3A%5B1389913200000%20TO%20*%5D&rows=1000';
            
            $aStoreListUrl[] = $url;
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreListUrl as $url) {
            if (!$sPage->open($url)) {
                throw new Exception('unable to get store-list for company with id ' . $companyId);
            }

            $page = $sPage->getPage()->getResponseBody();
            $jsonStores = json_decode($page);                        
            foreach ($jsonStores->response->docs as $jsonStore) {
                if (preg_match('#cap#i', $jsonStore->name_tlc) || preg_match('#^9#', $jsonStore->vertriebsschieneKey_tlc)) {
                    continue;
                }                                               
                if (array_key_exists($companyId, $aCompanyIds)) {
                    $hashToCheck = md5($jsonStore->plz_tlc
                            . $sAddress->normalizeStreet($sAddress->extractAddressPart('street', $jsonStore->strasse_tlc))
                            . $sAddress->extractAddressPart('streetnumber', $jsonStore->strasse_tlc));
                    if (in_array($hashToCheck, $aStoreNumbers)) {
                        continue;
                    }
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($jsonStore->marktID_tlc)
                        ->setSubtitle($this->_subtitle)
                        ->setWebsite($this->_website)
                        ->setLongitude($jsonStore->geoLng_doubleField_d)
                        ->setLatitude($jsonStore->geoLat_doubleField_d)
                        ->setStreet($sAddress->extractAddressPart('street', $jsonStore->strasse_tlc))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $jsonStore->strasse_tlc))
                        ->setZipcode($jsonStore->plz_tlc)
                        ->setCity($jsonStore->ort_tlc)
                        ->setBarrierFree(true)
                        ->setToilet(true);
                if ($companyId != '67790') {
                    $eStore->setTitle($jsonStore->name_tlc);
                }
                
                if ($companyId == '67774') {
                    $eStore->setSubtitle($jsonStore->name_tlc)
                            ->setTitle(NULL);
                }

                if (property_exists($jsonStore, 'telefon_tlc')) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($jsonStore->telefon_tlc));
                }

                if (property_exists($jsonStore, 'urlExtern_tlc')) {
                    $eStore->setWebsite($jsonStore->urlExtern_tlc);
                }

                $sOpenings = '';
                $aOpenings = explode(';', $jsonStore->oeffnungszeiten_tlc);

                if (count($aOpenings) == 8 && $aOpenings[0] == 'standard1'
                ) {
                    if ($aOpenings[1] != '0' && $aOpenings[2] != '0'
                    ) {
                        $sOpenings = 'Mo-Fr ' . $aOpenings[1] . '-' . $aOpenings[2];
                    }

                    if ($aOpenings[3] != '0' && $aOpenings[4] != '0'
                    ) {
                        $sOpenings .= ', Sa ' . $aOpenings[3] . '-' . $aOpenings[4];
                    }

                    if ($aOpenings[5] != '0' && $aOpenings[6] != '0'
                    ) {
                        $sOpenings .= ', So ' . $aOpenings[5] . '-' . $aOpenings[6];
                    }

                    if (strlen($aOpenings[7])) {
                        $eStore->setStoreHoursNotes($aOpenings[7]);
                    }
                }

                if (count($aOpenings) == 30 && $aOpenings[0] == 'standard2'
                ) {
                    $aWeekday = array(
                        1 => 'Mo ',
                        5 => 'Di ',
                        9 => 'Mi ',
                        13 => 'Do ',
                        17 => 'Fr ',
                        21 => 'Sa ',
                        25 => 'So '
                    );

                    for ($count = 1; $count <= 25; $count += 4) {
                        if ($aOpenings[$count] != '0' && $aOpenings[$count + 1] != '0' && $aOpenings[$count + 2] == '0' && $aOpenings[$count + 3] == '0'
                        ) {
                            if (strlen($sOpenings)) {
                                $sOpenings .= ', ';
                            }

                            $sOpenings .= $aWeekday[$count];
                            $sOpenings .= $aOpenings[$count] . '-' . $aOpenings[$count + 1];
                        } elseif (
                                $aOpenings[$count] != '0' && $aOpenings[$count + 1] != '0' && $aOpenings[$count + 2] != '0' && $aOpenings[$count + 3] != '0'
                        ) {
                            if (strlen($sOpenings)) {
                                $sOpenings .= ', ';
                            }

                            $sOpenings .= $aWeekday[$count] . $aOpenings[$count] . '-' . $aOpenings[$count + 2];
                            $sOpenings .= ', ' . $aWeekday[$count] . $aOpenings[$count + 3] . '-' . $aOpenings[$count + 1];
                        }
                    }

                    if (strlen($aOpenings[29])) {
                        $eStore->setStoreHoursNotes($aOpenings[29]);
                    }
                }

                $eStore->setStoreHours($sOpenings);

                // Falsche Parameter korrigieren
                if ($eStore->getWebsite() == 'http://htttp://www.frischemarkt-meyer.de') {
                    $eStore->setWebsite('http:///www.frischemarkt-meyer.de');
                }
                                                
                if ($eStore->getStoreNumber() == '12135') {
                    $eStore->setStoreHours('Mo-Fr 07:00-21:00');
                }
                
                if ($eStore->getStoreNumber() == '8000727') {
                    $eStore->setStoreHours('Mo-Do 08:00-19:00,Fr 08:00-20:00,Sa 07:30-16:00');
                }
                
                if ($eStore->getStoreNumber() == '77373') {
                    $storeHours = $eStore->getStoreHours() . ', So 08:30-10:00';
                    
                    $eStore->setStoreHours($storeHours);
                }
                
                if ($eStore->getStoreNumber() == '5691267') {
                    $eStore->setStoreHours('Mo-Fr 08:30-12:30, Mo-Fr 15:00-17:30, Sa 08:00-11:00');
                }
                
                if ($eStore->getStoreNumber() == '12521') {
                    $eStore->setStoreHours('Mo-Sa 08:00-20:00');
                }

                // Prüfen, ob Store übersprungen werden soll
                if (!$this->_skipStore($companyId, $eStore)) {
                    // Prüfen ob Company-Id zu ermitteltem Vertriebsbereich passt
                    $checkField = 'vertriebsschieneKey_tlc';
                    $checkJson = $jsonStore->vertriebsschieneKey_tlc;
                    if ($companyId == 67783 || $companyId == 67787) {
                        $checkField = 'urlExtern_tlc';
                        $checkJson = $jsonStore->urlExtern_tlc;
                    }
                    
                    if ($companyId == 67782 || $companyId == 67781) {
                        $checkField = 'handzettelUrl_tlc';
                        $checkJson = $jsonStore->handzettelUrl_tlc;
                    }
                    if (property_exists($jsonStore, $checkField)) {
                        if (preg_match($this->_patternDistribution, $checkJson)
                        ) {                            
                            $cStores->addElement($eStore, true);
                        }
                    } elseif ($companyId == 2) {
                        // Wenn kein Vertriebsbereich angegeben => EDEKA zuordnen
                     
                        $cStores->addElement($eStore, true);
                    }
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * Überprüft, ob der Standort übersprungen werden muss und
     *
     * @param int $companyId
     * @param Marktjagd_Entity_Api_Store $eStore
     *
     * @return int|bool
     */
    protected function _skipStore($companyId, $eStore) {
        if ($companyId == 2 && $eStore->getStoreNumber() == '363035') {
            return true;
        }

        // Simmel nicht unter Edeka erfassen
        if ($companyId == 2 && preg_match('#simmel#is', $eStore->getTitle())
        ) {
            return true;
        }

        if ($companyId == 67788 && !preg_match('#simmel#is', $eStore->getTitle())
        ) {
            return true;
        }

        // Schmidt's Markt nicht unter E aktiv Markt erfassen
        if ($companyId == 67774 && 'http://www.schmidts-maerkte.de/' == $eStore->getWebsite()
        ) {
            return true;
        }

        // Schmidt's Markt nicht unter E aktiv Markt erfassen
        if ($companyId == 67775 && 'http://www.schmidts-maerkte.de/' == $eStore->getWebsite()
        ) {
            return true;
        }

        if ($companyId == 67787 && 'http://www.schmidts-maerkte.de/' != $eStore->getWebsite()
        ) {
            return true;
        }

        if (is_array($this->_skipStoresFromCompany)) {
            $this->_findZipCodeSkip($this->_skipStoresFromCompany);

            if (array_key_exists($eStore->getZipcode(), $this->_aSkipZipCodes)) {
                $this->_logger->info('skipped store by company');
                return true;
            }
        }

        if (isset($this->_skipStoresWithTitle) && is_array($this->_skipStoresWithTitle)
        ) {
            foreach ($this->_skipStoresWithTitle as $pattern) {
                if (preg_match($pattern, $eStore->getTitle())) {
                    $this->_logger->info('skipped store by title');
                    return true;
                }
            }
        }

        if ('0.0' == $eStore->getLatitude() && '0.0' == $eStore->getLongitude()
        ) {
            $this->_logger->info('skipped store by lat/lon');
            return true;
        }

        return false;
    }

    /**
     * Ermittelt die zu überspringenden Postleitzahlen für die zu überspringenen Stores einer Company
     *
     * @param array $aSkipStoresFromCompany
     * @return array
     */
    protected function _findZipCodeSkip($aSkipStoresFromCompany) {

        // Wenn $skipStoresFromCompany gesetzt ist, dann dürfen nicht alle Standorte übernommen werden.
        if (is_array($aSkipStoresFromCompany)) {
            $sMarktjagdApi = new Marktjagd_Service_Input_MarktjagdApi();

            foreach ($aSkipStoresFromCompany as $companyValue) {
                if (in_array($companyValue, $this->_aSkipZipCodes)
                ) {
                    continue;
                }

                $this->_logger->info('getting stores to exclude for company ' . $companyValue);
                $cStores = $sMarktjagdApi->findStoresByCompany($companyValue,700);
                $this->_logger->info('finished getting stores to exclude for company ' . $companyValue);

                /* @var $eStore Marktjagd_Entity_Api_Store */
                foreach ($cStores->getElements() as $eStore) {
                    $this->_aSkipZipCodes[$eStore->getZipcode()] = $companyValue;
                }
            }
        }
    }

    /**
     * Ließt die Konfiguration bzgl. zu überspringender Stores aus dem Konfigurationsfile aus
     *
     * @param $companyId
     */
    protected function _initializeConfig($companyId) {
        $configEdeka = new Zend_Config_Ini(
                APPLICATION_PATH . '/modules/Crawler/companies/EdekaGroup/edeka.ini', 'production'
        );

        if (isset($configEdeka->$companyId)) {
            if (isset($configEdeka->$companyId->subtitle)) {
                $this->_subtitle = (string) $configEdeka->$companyId->subtitle;
            }

            if (isset($configEdeka->$companyId->website)) {
                $this->_website = $configEdeka->$companyId->website;
            }

            if (isset($configEdeka->$companyId->skipStoresFromCompany)) {
                $this->_skipStoresFromCompany = $configEdeka->$companyId->skipStoresFromCompany->toArray();
            }

            if (isset($configEdeka->$companyId->pattern)) {
                $this->_patternDistribution = $configEdeka->$companyId->pattern;
            } else {
                throw new Exception('Edeka Group Crawler für Company ' . $companyId
                . ' konnte kein Distribution-Pattern ermittelt werden.');
            }

            if (isset($configEdeka->skipStoresWithTitle)) {
                $this->_skipStoresWithTitle = $configEdeka->skipStoresWithTitle->toArray();
            }
        }
    }

}
