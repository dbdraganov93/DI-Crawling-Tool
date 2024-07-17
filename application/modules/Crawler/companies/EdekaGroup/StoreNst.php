<?php

class Crawler_Company_EdekaGroup_StoreNst extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $iCompId = 10;

        $aDays = array(
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa'
        );

        $localDirectory = APPLICATION_PATH . '/../public/files/ftp/' . $companyId . '/';
        if (!is_dir($localDirectory)) {
            mkdir($localDirectory, 0777, true);
        }

        $aConfigFtp = $sFtp->getMjFtpConfig();
        $sFtp->connect($aConfigFtp);
        $sFtp->download('69470/edeka_stores.xls', $localDirectory . 'Edeka_Stores.xls');
        $sFtp->close();
        $aStores = $sPhpExcel->readFile($localDirectory . 'Edeka_Stores.xls', true);

        // Mithilfe der Company-ID die passende Collection finden
        switch ($companyId) {
            case '69469': {
                    foreach ($aStores->getElements() as $aStore) {
                        if ($aStore->getTitle() == 'E-Center') {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            case '69470': {
                    foreach ($aStores->getElements() as $aStore) {
                        if ($aStore->getTitle() == 'Edeka') {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            case '69471': {
                    foreach ($aStores->getElements() as $aStore) {
                        if ($aStore->getTitle() == 'Nah & Gut') {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            case '69472': {
                    foreach ($aStores->getElements() as $aStore) {
                        if ($aStore->getTitle() == 'Marktkauf') {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            case '69473': {
                    foreach ($aStores->getElements() as $aStore) {
                        if (preg_match('#diska#i', $aStore->getTitle())) {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            case '69474': {
                    foreach ($aStores->getElements() as $aStore) {
                        if (preg_match('#kupsch#i', $aStore->getTitle())) {
                            $iCompId = $aStore->getId();
                        }
                    }
                    break;
                }
            default : {
                    $logger->log($companyId . ': invalid company id.', Zend_Log::CRIT);
                    return $this->_response->generateResponseByFileName(false);
                }
        }

        if ($iCompId == 10) {
            $logger->log($companyId . ': unable to find correct table.', Zend_Log::CRIT);
            return $this->_response->generateResponseByFileName(false);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();

        foreach ($aStores->getElement($iCompId)->getData() as $aStore) {
            if (strlen($aStore['Fil.-Nr.'])) {
                $storeNumber = $aStore['Fil.-Nr.'];
            } elseif (strlen($aStore['Fil. Nr.'])){
                $storeNumber = $aStore['Fil. Nr.'];
            } elseif (strlen($aStore['Fil.Nr.'])){
                $storeNumber = $aStore['Fil.Nr.'];
            } elseif (strlen($aStore['Filnr.'])){
                $storeNumber = $aStore['Filnr.'];
            } elseif (strlen($aStore['Kdnr.'])){
                $storeNumber = $aStore['Kdnr.'];
            } else {
                $logger->log($companyId . ': no store number for store address: '
                        . $aStore['Straße'] . ', ' . $aStore['PLZ'] . ' '
                        . $aStore['Ort'], Zend_Log::ERR);
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            $count = 0;
            $sTime = '';
            foreach ($aStore as $key => $value) {
                if (preg_match('#von#i', $key) || preg_match('#bis#i', $key)) {
                    $aStore['Times_' . $count++] = (float) $value;
                    unset($aStore[$key]);
                }
            }

            if (!strlen($aStore['Straße']) || !strlen($aStore['PLZ']) || !strlen($aStore['Ort'])){
                throw new Exception($companyId . ': invalid data or column name in Excel file');
            }           
            
            $eStore->setStoreNumber($storeNumber)
                    ->setStreet($mjAddress->extractAddressPart('street', $aStore['Straße']))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aStore['Straße']))
                    ->setZipcode(str_pad($aStore['PLZ'], 5, '0', STR_PAD_LEFT))
                    ->setCity($aStore['Ort'])
                    ->setDistribution($sGeo->findEastWestByZipCode(str_pad($aStore['PLZ'], 5, '0', STR_PAD_LEFT)));

            
            
            
            // Uhrzeitenstring zusammensetzen
            for ($i = 0; $i < 6; $i++) {
                if (strlen($sTime)) {
                    $sTime .= ', ';
                }
                if (($aStore['Times_' . (1 + (4 * $i))] == '0') && ($aStore['Times_' . (2 + (4 * $i))] == '0')) {
                    $sTime .= $aDays[$i] . ' ' . $this->changeTime($aStore['Times_' . (0 + (4 * $i))])
                            . '-' . $this->changeTime($aStore['Times_' . (3 + (4 * $i))]);
                } else if (($aStore['Times_' . (2 + (4 * $i))] == '0') && ($aStore['Times_' . (3 + (4 * $i))] == '0')) {
                    $sTime .= $aDays[$i] . ' ' . $this->changeTime($aStore['Times_' . (0 + (4 * $i))])
                            . '-' . $this->changeTime($aStore['Times_' . (1 + (4 * $i))]);
                } else {
                    $sTime .= $aDays[$i] . ' ' . $this->changeTime($aStore['Times_' . (0 + (4 * $i))])
                            . '-' . $this->changeTime($aStore['Times_' . (1 + (4 * $i))])
                            . ', ' . $aDays[$i] . ' ' . $this->changeTime($aStore['Times_' . (2 + (4 * $i))])
                            . '-' . $this->changeTime($aStore['Times_' . (3 + (4 * $i))]);
                }
            }

            $eStore->setStoreHours($sTime);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * Funktion um aus einer float-Zahl einen Uhrzeitenstring zu generieren
     * 
     * @param float $fTime Uhrzeit als float-Zahl 
     * @return string $sTimes Uhrzeit als String
     */
    protected function changeTime($fTime) {
        $sHours = floor(24 * $fTime);
        $sMinutes = (round(2400 * $fTime) % 100) / 10 * 6;
        $sTimes = $sHours . ':';
        if ($sMinutes == 0) {
            $sTimes .= '00';
        } else {
            $sTimes .= $sMinutes;
        }
        return $sTimes;
    }

}
