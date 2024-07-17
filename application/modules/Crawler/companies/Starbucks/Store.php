<?php

/**
 * Storecrawler für Starbucks (ID: 69893)
 */
class Crawler_Company_Starbucks_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $sUrl  = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'https://openapi.starbucks.com/';
        $searchUrl = $baseUrl . 'v1/stores/nearby?radius=100&latLng='
            . $sUrl::$_PLACEHOLDER_LAT . '%2C' 
            . $sUrl::$_PLACEHOLDER_LON . '&ignore=operatingStatus'
            . '%2CtimeZoneInfo%2CextendedHours'
            . '%2ChoursNext7Days%2Ctoday&brandCodes=SBUX&access_token=';
        $tokenUrl = 'https://www.starbucks.de/resources/apitkn';

        $cityPattern = array(
            '#Cologne#',
            '#Munich#'
        );

        $cityReplacement = array(
            'Köln',
            'München'
        );
        
        $sText = new Marktjagd_Service_Text_TextFormat();
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($tokenUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#\s*(.+)\s*#';
        if (!preg_match($pattern, $page, $match)) {
            throw new Exception('unable to get access token');
        }
        
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $aUrls = $sUrl->generateUrl($searchUrl, $sUrl::$_TYPE_COORDS, 0.5);

        foreach ($aUrls as $geoUrl) {
            $sPage->open($geoUrl . $match[1]);
            $page = $sPage->getPage()->getResponseBody();

            // XML-Element erzeugen
            try {
                $xml = new SimpleXMLElement($page);
            } catch (Exception $exception) {
                continue;
            }
            if (!$xml) {
                $this->_logger->err('unable to generate XML');
                continue;
            }

            // Leere Storeanfrageseiten überspringen
            if ($xml->paging->returned == 0) {
                continue;
            }

            // Daten der einzelnen Stores auslesen
            foreach ($xml->stores->storeByDistance as $tmpStore) {
                if ($tmpStore->store->address->countryCode != 'DE') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                // Storenummer
                $eStore->setStoreNumber((string)$tmpStore->store->storeNumber);

                // Anschrift                
                $tmpStreet = $sText->uncapitalize(trim((string)$tmpStore->store->address->streetAddressLine1));
                $eStore->setStreetAndStreetNumber($tmpStreet);
                $eStore->setZipcode((string)$tmpStore->store->address->postalCode);

                $tmpCity = $sText->uncapitalize(trim((string)$tmpStore->store->address->city));
                $eStore->setCity(preg_replace($cityPattern, $cityReplacement, $tmpCity));

                // Geodaten
                $eStore->setLatitude((string)$tmpStore->store->coordinates->latitude);
                $eStore->setLongitude((string)$tmpStore->store->coordinates->longitude);

                // Beschreibungstext
                $eStore->setText((string)$tmpStore->store->features);

                // Uhrzeiten
                $aTimes = array();
                if ($tmpStore->store->regularHours->open24x7 == 'true') {
                    $eStore->setStoreHoursNormalized('Mo-So 00:00-24:00');
                } else {
                    if ($tmpStore->store->regularHours->monday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->monday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->monday->closeTime)
                        ) {
                            $aTimes[] = 'Mo ' . $this->_cutSeconds($tmpStore->store->regularHours->monday->openTime) . '-24:00';
                            $aTimes[] = 'Di 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->monday->closeTime);
                        } else {
                            $aTimes[] = 'Mo ' . $this->_cutSeconds($tmpStore->store->regularHours->monday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->monday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->tuesday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->tuesday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->tuesday->closeTime)
                        ) {
                            $aTimes[] = 'Di ' . $this->_cutSeconds($tmpStore->store->regularHours->tuesday->openTime) . '-24:00';
                            $aTimes[] = 'Mi 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->tuesday->closeTime);
                        } else {
                            $aTimes[] = 'Di ' . $this->_cutSeconds($tmpStore->store->regularHours->tuesday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->tuesday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->wednesday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->wednesday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->wednesday->closeTime)
                        ) {
                            $aTimes[] = 'Mi ' . $this->_cutSeconds($tmpStore->store->regularHours->wednesday->openTime) . '-24:00';
                            $aTimes[] = 'Do 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->wednesday->closeTime);
                        } else {
                            $aTimes[] = 'Mi ' . $this->_cutSeconds($tmpStore->store->regularHours->wednesday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->wednesday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->thursday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->thursday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->thursday->closeTime)
                        ) {
                            $aTimes[] = 'Do ' . $this->_cutSeconds($tmpStore->store->regularHours->thursday->openTime) . '-24:00';
                            $aTimes[] = 'Fr 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->thursday->closeTime);
                        } else {
                            $aTimes[] = 'Do ' . $this->_cutSeconds($tmpStore->store->regularHours->thursday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->thursday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->friday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->friday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->friday->closeTime)
                        ) {
                            $aTimes[] = 'Fr ' . $this->_cutSeconds($tmpStore->store->regularHours->friday->openTime) . '-24:00';
                            $aTimes[] = 'Sa 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->friday->closeTime);
                        } else {
                            $aTimes[] = 'Fr ' . $this->_cutSeconds($tmpStore->store->regularHours->friday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->friday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->saturday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->saturday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->saturday->closeTime)
                        ) {
                            $aTimes[] = 'Sa ' . $this->_cutSeconds($tmpStore->store->regularHours->saturday->openTime) . '-24:00';
                            $aTimes[] = 'So 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->saturday->closeTime);
                        } else {
                            $aTimes[] = 'Sa ' . $this->_cutSeconds($tmpStore->store->regularHours->saturday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->saturday->closeTime));
                        }
                    }

                    if ($tmpStore->store->regularHours->sunday->open == 'true') {
                        if ((int)preg_replace('#:#', '', $tmpStore->store->regularHours->sunday->openTime)
                            > (int)preg_replace('#:#', '', $tmpStore->store->regularHours->sunday->closeTime)
                        ) {
                            $aTimes[] = 'So ' . $this->_cutSeconds($tmpStore->store->regularHours->sunday->openTime) . '-24:00';
                            $aTimes[] = 'Mo 00:00-' . $this->_cutSeconds($tmpStore->store->regularHours->sunday->closeTime);
                        } else {
                            $aTimes[] = 'So ' . $this->_cutSeconds($tmpStore->store->regularHours->sunday->openTime) . '-'
                                . preg_replace('#00:00#', '24:00', $this->_cutSeconds($tmpStore->store->regularHours->sunday->closeTime));
                        }
                    }
                }
                if (count($aTimes)) {
                    $eStore->setStoreHoursNormalized(implode(', ', $aTimes));
                }

                // Telefon
                $eStore->setPhoneNormalized((string) $tmpStore->store->phoneNumber);
                $cStores->addElement($eStore);
            }

        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
    
    protected function _cutSeconds($time = '') {
        return preg_replace('#\:[0-9]{2}$#', '', $time);
    }
}
