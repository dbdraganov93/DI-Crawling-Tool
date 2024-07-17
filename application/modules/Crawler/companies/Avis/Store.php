<?php

/**
 * Store Crawler für Avis (ID: 28690)
 */
class Crawler_Company_Avis_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://secure.avis.de';
        $searchUrl = $baseUrl . '/JsonProviderServlet/de_DE?requestType=keyword&isproxy=false&keyword=%%ZIP%%';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $sFtp->connect($companyId);
        $localLkwFile = $sFtp->downloadFtpToCompanyDir('avis_lkw.xls', $companyId);
        $aDataLkw = $sExcel->readFile($localLkwFile, TRUE)->getElement(0)->getData();
        $xlsFilePath = $sFtp->downloadFtpToCompanyDir('Stationsliste.xlsx', $companyId);
        $aData = $sExcel->readFile($xlsFilePath, TRUE)->getElement(0)->getData();

        $weekdays = array(
            'Montag' => 'Mo',
            'Dienstag' => 'Di',
            'Mittwoch' => 'Mi',
            'Donnerstag' => 'Do',
            'Freitag' => 'Fr',
            'Samstag' => 'Sa',
            'Sonntag' => 'So',
        );

        $aZipcodes = $sDbGeo->findAllZipCodes();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZip) {
            try {
                $zipUrl = preg_replace('#%%ZIP%%#', $singleZip, $searchUrl);
                $sPage->open($zipUrl);
                $json = $sPage->getPage()->getResponseAsJson();

                if (!$json->results) {
                    $this->_logger->info($companyId . ': skipping... no stores for ' . $singleZip);
                    continue;
                }
                foreach ($json->results[0]->data as $singleStore) {
                    if (!preg_match('#DE#', $singleStore->Address->countryCode)) {
                        continue;
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setLatitude($singleStore->GeoData->Latitude)
                            ->setLongitude($singleStore->GeoData->Longitude)
                            ->setZipcode($singleStore->Address->PostCode)
                            ->setCity($singleStore->Address->City)
                            ->setStreetAndStreetNumber($singleStore->Address->Address1)
                            ->setText($singleStore->Address->Address2)
                            ->setPhoneNormalized($singleStore->PhoneNumber)
                            ->setStoreNumber($singleStore->StationCode)
                            ->setSubtitle($singleStore->StationName);

                    $sOpenings = '';
                    foreach ($singleStore->OpeningTimes as $openingDay) {
                        if (!$openingDay->FirstText || strlen($openingDay->FirstText) == 0) {
                            continue;
                        }

                        if (strlen($sOpenings)) {
                            $sOpenings .= ', ';
                        }

                        $sOpenings .= $weekdays[$openingDay->DayOfWeek] . ' ' . $openingDay->FirstText;

                        if ($openingDay->ShouldDisplaySecondText) {
                            $sOpenings .= ', ' . $weekdays[$openingDay->DayOfWeek] . ' ' . $openingDay->SecondText;
                        }
                    }

                    $eStore->setStoreHoursNormalized($sOpenings, 'text', TRUE);


                    foreach ($aData as $singleLine) {
                        if ($singleLine['PLZ'] == $eStore->getZipcode()
                                && $sAddress->normalizePhoneNumber($eStore->getPhone()) == $sAddress->normalizePhoneNumber($singleLine['TELEFON'])) {
                            $eStore->setDistribution('Prospektstreuung 03-05 2017');
                        }
                    }

                    foreach ($aDataLkw as $singleLineLkw) {
                        if ($singleLineLkw['Location'] == $eStore->getStoreNumber()) {
                            if (strlen($eStore->getDistribution())) {
                                $eStore->setDistribution($eStore->getDistribution() . ', LKW Prospektstreuung 03-05 2017');
                            } else {
                                $eStore->setDistribution('LKW Prospektstreuung 03-05 2017');
                            }
                        }
                    }
                    Zend_Debug::dump($eStore);
                    $cStores->addElement($eStore, TRUE);
                }
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': unable to get response for zipcode ' . $singleZip);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
