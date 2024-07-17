<?php

/**
 * Store Crawler for DM (ID: 27)
 */
class Crawler_Company_Dm_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://store-data-service.services.dmtech.com/';
        $searchUrl = $baseUrl . 'stores/bbox/[NE_LAT]%2C[SW_LNG]%2C[SW_LAT]%2C[NE_LNG]';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        $localStoreNumberFile = '';
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#filial[^.]*\.xlsx#i', $singleRemoteFile)) {
                $localStoreNumberFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $storeNumberData = $sPss->readFile($localStoreNumberFile, FALSE)->getElement(0)->getData();
        foreach ($storeNumberData as $singleRow) {
            if (!is_int($singleRow[3])) {
                continue;
            }
            $aStoreNumber[$singleRow[3]] = $singleRow[2];
        }

        $aData = $sGRead->getCustomerData('dmGer_Kufi');

        try {
            $aRadiiData = $sGRead->getFormattedInfos($aData['spreadsheetId'], 'A1', 'Z', 'DM Kufi_' .
                date('m', strtotime($aData['validStart']))
            );
        } catch (Exception $e) {
            $aRadiiData = FALSE;
        }

        if (!$aRadiiData) {
            throw new Exception($companyId . ': unable to get radii data');
        }

        $aRadii = [];
        foreach ($aRadiiData as $singleRow) {
            $aRadii[$singleRow['store_number']] = preg_replace('#\s*km#', '', $singleRow['radius']);
        }

        $southWestLat = 47.000;    // 47.2701270
        $southWestLng = 5.000;    // 5.8663566
        $northEastLat = 56.000;    // 55.081500
        $northEastLng = 16.000;    // 15.0418321
        $geoStep = 0.5;

        $storeIds = [];
        $cStores = new Marktjagd_Collection_Api_Store();
        for ($lat = $southWestLat; $lat <= $northEastLat; $lat += $geoStep) {
            for ($lng = $southWestLng; $lng < $northEastLng; $lng += $geoStep) {
                $swLat = number_format($lat, 3);
                $swLng = number_format($lng, 3);
                $neLat = number_format($lat + $geoStep, 3);
                $neLng = number_format($lng + $geoStep, 3);
                $this->_logger->info('crawl area from ' . $swLat . ' - ' . $swLng . ' to ' . $neLat . ' - ' . $neLng);
                $geoUrl = $searchUrl;
                $geoUrl = str_replace('[SW_LAT]', $swLat, $geoUrl);
                $geoUrl = str_replace('[SW_LNG]', $swLng, $geoUrl);
                $geoUrl = str_replace('[NE_LAT]', $neLat, $geoUrl);
                $geoUrl = str_replace('[NE_LNG]', $neLng, $geoUrl);

                sleep(2);
                $sPage->open($geoUrl);

                $json = $sPage->getPage()->getResponseAsJson();
                if (!$json) {
                    continue;
                }

                foreach ($json->stores as $singleJsonStore) {
                    if (!preg_match('#DE#', $singleJsonStore->localeCountry)) {
                        continue;
                    }
                    if (in_array($singleJsonStore->storeNumber, $storeIds)) {
                        continue;
                    }
                    $storeTimes = $this->getStoreHoursFromApi($singleJsonStore->storeNumber);
                    if ($storeTimes['closedDays'] == 'closed') {
                        $storeIds[] = $singleJsonStore->storeNumber;
                        continue;
                    }
                    $storeNumber = $singleJsonStore->storeNumber;
                    if (array_key_exists($singleJsonStore->storeNumber, $aStoreNumber)) {
                        $storeNumber = $aStoreNumber[$singleJsonStore->storeNumber];
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setStoreNumber($storeNumber)
                        ->setWebsite('https://www.dm.de/store' . $singleJsonStore->storeUrlPath)
                        ->setPhoneNormalized($singleJsonStore->phone)
                        ->setStreetAndStreetNumber($singleJsonStore->address->street)
                        ->setZipcode($singleJsonStore->address->zip)
                        ->setCity($singleJsonStore->address->city)
                        ->setLatitude($singleJsonStore->location->lat)
                        ->setLongitude($singleJsonStore->location->lon)
                        ->setStoreHoursNormalized($storeTimes['openingHours'])
                        ->setDefaultRadius($aRadii[$storeNumber]);

                    // add StoreId to prevent setting the same store again and again, huge performance boost
                    $storeIds[] = $eStore->getStoreNumber();

                    if ($cStores->addElement($eStore)) {
                        $this->_logger->info($companyId . ': store added.');
                    }
                }
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function getStoreHoursFromApi($storeId)
    {
        $weekDayArray = [
            0 => 'Mo',
            1 => 'Di',
            2 => 'Mi',
            3 => 'Do',
            4 => 'Fr',
            5 => 'Sa',
        ];

        $url = 'https://services.dm.de/storedata/stores/item/de/' . $storeId;
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);

        $json = $sPage->getPage()->getResponseAsJson();
        $timesPerDay = array();
        foreach ($weekDayArray as $key => $value) {
            $timesPerDay['openingHours'] .= $value . ": " . $json->openingDays[$key]->timeSlices[0]->opening . ' - ' .
                $json->openingDays[$key]->timeSlices[0]->closing . ', ';

        }
        // Durchsucht Json nach Schließtagen
        if (!empty($json->extraClosingDates)) {
            $timesPerDay['closedDays'] = $json->extraClosingDates;
            foreach ($timesPerDay['closedDays'] as $singleDay) {
                if (date('Y\-m\-d') == $singleDay->date) {
                    $timesPerDay['closedDays'] = 'closed';
                }
            }
        }

        return $timesPerDay;


    }
}

