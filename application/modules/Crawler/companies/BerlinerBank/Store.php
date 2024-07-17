<?php

/**
 * Store Crawler für Berliner Bank (ID: 71657)
 */
class Crawler_Company_BerlinerBank_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://secure.berliner-bank.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();

        $aWeekDays = array(
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        );

        for ($i = 4; $i < 19; $i++) {
            $site = 1;
            $searchUrl = $baseUrl . 'tools/gdata/api_js/V1/AddressSearches.asmx/'
                    . 'SearchBranchesCoordBasedRectangle?'
                    . 'SystemPartner=BerlinerBank&SecurityID=FdWwd3u1HyMHd99fEFycZg%3D%3D&'
                    . 'Channel=&Branches=PBCxxBB%26PBCxSEL%7CPBCxINV&Catchwords=&'
                    . 'IsoCountryCode=DE&IsoLocale=de-DE&CoordFormatIn=GEODECIMAL&'
                    . 'Top=1000&OrderBy=DISTANCE&Page=' . $site . '&'
                    . 'Addition=DatabaseID%3DYM_FILIALEN%26LocXForDistanceCalculation%3D'
                    . '13.78816%26LocYForDistanceCalculation%3D51.04587&FreeFilter=&'
                    . 'MaxRadius=20000&Lux=' . $i . '.0&Luy=58.9&Rlx=' . ($i + 1) . '.0&'
                    . 'Rly=43.0&LocX=13.78816&LocY=51.04587';

            $oPage = $sPage->getPage();
            $oPage->setUseCookies(TRUE);
            $sPage->setPage($oPage);
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#(\{"GeoItems.+)<#s';
            if (!preg_match($pattern, $page, $storeMatch)) {
                continue;
            }

            $jData = json_decode($storeMatch[1]);
            $maxSite = $jData->Paging->MaxPage;

            while ($site <= $maxSite) {
                $searchUrl = $baseUrl . 'tools/gdata/api_js/V1/AddressSearches.asmx/'
                        . 'SearchBranchesCoordBasedRectangle?'
                        . 'SystemPartner=BerlinerBank&SecurityID=FdWwd3u1HyMHd99fEFycZg%3D%3D&'
                        . 'Channel=&Branches=PBCxxBB%26PBCxSEL%7CPBCxINV&Catchwords=&'
                        . 'IsoCountryCode=DE&IsoLocale=de-DE&CoordFormatIn=GEODECIMAL&'
                        . 'Top=1000&OrderBy=DISTANCE&Page=' . $site . '&'
                        . 'Addition=DatabaseID%3DYM_FILIALEN%26LocXForDistanceCalculation%3D'
                        . '13.78816%26LocYForDistanceCalculation%3D51.04587&FreeFilter=&'
                        . 'MaxRadius=20000&Lux=' . $i . '.0&Luy=58.9&Rlx=' . ($i + 1) . '.0&'
                        . 'Rly=43.0&LocX=13.78816&LocY=51.04587';

                $sPage->open($searchUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#(\{"GeoItems.+)<#s';
                if (!preg_match($pattern, $page, $storeMatch)) {
                    continue;
                }
                $jData = json_decode($storeMatch[1]);

                foreach ($jData->AddressItems as $singleStore) {
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $strTimes = '';
                    $strServices = '';
                    foreach ($singleStore->BasicData->BranchListElements as $singleService) {
                        if (preg_match('#barrierefrei#i', $singleService->BranchText)) {
                            $eStore->setBarrierFree(true);
                            continue;
                        }

                        if (preg_match('#(parkplatz|filiale\s*der\s*berliner\s*bank)#i', $singleService->BranchText)) {
                            continue;
                        }

                        if (strlen($strServices)) {
                            $strServices .= ', ';
                        }

                        $strServices .= $singleService->BranchText;
                    }

                    foreach ($singleStore->BasicData->ObjectListItems as $singleElement) {
                        if (preg_match('#park#i', $singleElement->Description)) {
                            $eStore->setParking($singleElement->Description);
                            break;
                        }
                    }

                    foreach ($aWeekDays as $singleWeekDay) {
                        if (strlen($singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'MorningFrom'}) && strlen($singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'MorningUntil'})) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $singleWeekDay . $singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'MorningFrom'}
                                    . '-' . $singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'MorningUntil'};
                        }

                        if (strlen($singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'AfternoonFrom'}) && strlen($singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'AfternoonUntil'})) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $singleWeekDay . $singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'AfternoonFrom'}
                                    . '-' . $singleStore->BasicData->MemoItems[0]->{'OpeningHours' . $singleWeekDay . 'AfternoonUntil'};
                        }
                    }

                    $eStore->setStoreNumber($singleStore->BasicData->Identifiers->YMIDDecoded)
                            ->setZipcode($singleStore->BasicData->Address->Zip)
                            ->setCity($singleStore->BasicData->Address->City)
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore->BasicData->Address->Street)))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore->BasicData->Address->Street)))
                            ->setPhone($sAddress->normalizePhoneNumber($singleStore->BasicData->Contact->Phone))
                            ->setFax($sAddress->normalizePhoneNumber($singleStore->BasicData->Contact->Fax))
                            ->setEmail($singleStore->BasicData->Contact->Email)
                            ->setWebsite($singleStore->BasicData->Contact->Url)
                            ->setLatitude($singleStore->BasicData->Geo->YCoord)
                            ->setLongitude($singleStore->BasicData->Geo->XCoord)
                            ->setStoreHours($sTimes->generateMjOpenings($sTimes->convertToGermanDays($strTimes)))
                            ->setService(htmlspecialchars_decode($strServices));
                    
                    $cStores->addElement($eStore);
                }

                $site++;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
