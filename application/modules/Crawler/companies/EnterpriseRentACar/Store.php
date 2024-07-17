<?php

/* 
 * Store Crawler für Enterprise Rent A Car (ID: 28941)
 */
class Crawler_Company_EnterpriseRentACar_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'https://www.enterprise.de/';
        $searchUrl = $baseUrl . 'de/autovermietung/standorte/deutschland.html';
        $sStoreHourUrl = 'https://prd.location.enterprise.com/enterprise-sls/search/location/dotcom/hours/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Stadtfilialen[^<]*<[^>]+>\s*<ul[^>]*>(.*?)</ul>#i';
        if (!preg_match($pattern, $page, $tmp)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $searchUrl);
        }

        $pattern = '#<li>\s*<a[^>]*href=\"([^\"]+?)\"[^>]*>([^<]+?)<\/a>\s*<\/li>#i';
        if (!preg_match_all($pattern, $tmp[1], $aCityMatches)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $searchUrl);
        }


        $pattern = '#Flughafenstationen[^<]*<[^>]+>\s*<ul[^>]*>(.*?)</ul>#i';
        if (!preg_match($pattern, $page, $tmp)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $searchUrl);
        }
        $pattern = '#<li>\s*<a[^>]*href=\"([^\"]+?)\"[^>]*>([^<]+?)<\/a>\s*<\/li>#i';
        if (!preg_match_all($pattern, $tmp[1], $aAirportMatches)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $searchUrl);
        }

        unset ($aAirportMatches[0]);
        unset ($aCityMatches[0]);

        foreach ($aCityMatches[1] as $key => $link) {
            $lat = false;
            $long = false;
            $addressFound = false;
            $sPage->open($link);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class=\"street\"[^>]*>\s*<a[^>]*>([^<]+?)<#';
            if (!preg_match($pattern, $page, $aAddressMatch)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not grep store address on url ' . $link);
                continue;
            }

            $aAddressMatch = preg_split('#,#', $aAddressMatch[1]);
            $eStore = new Marktjagd_Entity_Api_Store();

            foreach ($aAddressMatch as $key => $value) {
                if(preg_match('#\d{5}#', $value)) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $value))
                            ->setCity(preg_replace('#\([^\)]*\)#', '', $sAddress->normalizeCity($aAddressMatch[$key-3])));
                    if (preg_match('#\d+#', $aAddressMatch[$key-4])) {
                            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddressMatch[$key-4])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddressMatch[$key-4])));
                            $addressFound = true;
                    } elseif (preg_match('#\d+#', $aAddressMatch[$key-5])) {
                            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddressMatch[$key-5])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddressMatch[$key-5])));
                            $addressFound = true;
                    }
                }
            }

            $pattern = '#\"lat\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aLatmatch)) {
                $eStore->setLatitude($aLatmatch[1]);
                $lat = true;
            }

            $pattern = '#\"long\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aLongmatch)) {
                $eStore->setLongitude($aLongmatch[1]);
                $long = true;
            }

            if (!($addressFound || ($lat && $long))) {
                $this->_logger->err('Company ID ' . $companyId . ': could not grep store address on url ' . $link);
                continue;
            }

            $pattern = '#<a[^>]*tel[^>]*>([^<]*?)<#i';
            if (preg_match($pattern, $page, $aTelMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($aTelMatch[1]));
            } else {
                $this->_logger->warn('Company ID ' . $companyId . ': could not grep phone number on url ' . $link);
            }

            $eStore->setWebsite($link);

            $pattern = '#\"locationId\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aIdMatch)) {
                $eStore->setStoreNumber($aIdMatch[1]);
            }

            $date = date_create(date('Y-m-d'));
            date_add($date, date_interval_create_from_date_string('7 days'));
            $sStoreHourLink = $sStoreHourUrl . $aIdMatch[1] .
                    '?from=' . date('Y-m-d') . '&to=' . date_format($date, 'Y-m-d') . '&locale=de_DE';

            $sStoreHours = '';
            $sPage->open($sStoreHourLink);
            $jStoreHours = $sPage->getPage()->getResponseAsJson();
            foreach (get_object_vars($jStoreHours->data) as $key => $value) {
                $sStoreHours .= date_format(new DateTime($key), 'D') . ' ';
                $sOpens = $value->STANDARD->hours[0]->open;
                $sCloses = $value->STANDARD->hours[0]->close;
                if (strlen($sOpens) > 2) {
                    $sStoreHours .= $sOpens . ' - ';
                }

                if (strlen($sCloses) > 2) {
                    $sStoreHours .= $sCloses;
                }
                $sStoreHours .= ', ';
            }

            $eStore->setStoreHours($sTimes->generateMjOpenings($sTimes->convertToGermanDays($sStoreHours)));
            $cStores->addElement($eStore);
        }

        foreach ($aAirportMatches[1] as $key => $link) {
            $lat = false;
            $long = false;
            $addressFound = false;
            $sPage->open($link);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class=\"street\"[^>]*>\s*<a[^>]*>([^<]+?)<#';
            if (!preg_match($pattern, $page, $aAddressMatch)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not grep store address on url ' . $link);
                continue;
            }

            $aAddressMatch = preg_split('#,#', $aAddressMatch[1]);
            $eStore = new Marktjagd_Entity_Api_Store();

            foreach ($aAddressMatch as $key => $value) {
                if(preg_match('#\d{5}#', $value)) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $value))
                            ->setCity(preg_replace('#\([^\)]*\)#', '', $sAddress->normalizeCity($aAddressMatch[$key-3])));
                    if (preg_match('#\d+#', $aAddressMatch[$key-4])) {
                            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddressMatch[$key-4])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddressMatch[$key-4])));
                            $addressFound = true;
                    } elseif (preg_match('#\d+#', $aAddressMatch[$key-5])) {
                            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddressMatch[$key-5])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddressMatch[$key-5])));
                            $addressFound = true;
                    }
                }
            }

            $pattern = '#\"lat\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aLatmatch)) {
                $eStore->setLatitude($aLatmatch[1]);
                $lat = true;
            }

            $pattern = '#\"long\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aLongmatch)) {
                $eStore->setLongitude($aLongmatch[1]);
                $long = true;
            }

            if (!($addressFound || ($lat && $long))) {
                $this->_logger->err('Company ID ' . $companyId . ': could not grep store address on url ' . $link);
                continue;
            }

            $pattern = '#<a[^>]*tel[^>]*>([^<]*?)<#i';
            if (preg_match($pattern, $page, $aTelMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($aTelMatch[1]));
            } else {
                $this->_logger->warn('Company ID ' . $companyId . ': could not grep phone number on url ' . $link);
            }

            $eStore->setWebsite($link);

            $pattern = '#\"locationId\"[^\"]*\"([^\"]+)\"#i';
            if (preg_match($pattern, $page, $aIdMatch)) {
                $eStore->setStoreNumber($aIdMatch[1]);
            }

            $date = date_create(date('Y-m-d'));
            date_add($date, date_interval_create_from_date_string('7 days'));
            $sStoreHourLink = $sStoreHourUrl . $aIdMatch[1] .
                    '?from=' . date('Y-m-d') . '&to=' . date_format($date, 'Y-m-d') . '&locale=de_DE';

            $sStoreHours = '';
            $sPage->open($sStoreHourLink);
            $jStoreHours = $sPage->getPage()->getResponseAsJson();
            foreach (get_object_vars($jStoreHours->data) as $key => $value) {
                $sStoreHours .= date_format(new DateTime($key), 'D') . ' ';
                $sOpens = $value->STANDARD->hours[0]->open;
                $sCloses = $value->STANDARD->hours[0]->close;
                if (strlen($sOpens) > 2) {
                    $sStoreHours .= $sOpens . ' - ';
                }

                if (strlen($sCloses) > 2) {
                    $sStoreHours .= $sCloses;
                }
                $sStoreHours .= ', ';
            }
            $eStore->setStoreHours($sTimes->generateMjOpenings($sTimes->convertToGermanDays($sStoreHours)));
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
