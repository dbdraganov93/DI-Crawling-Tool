<?php

/* 
 * Brochure Crawler für Wreesmann (ID: 68891)
 */

class Crawler_Company_Wreesmann_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $week = 'next ';
        $KW = 'KW' .  date('W', strtotime($week . 'week'));

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $isChangedFtpDir = $sFtp->changedir($KW);
        if (!$isChangedFtpDir) {
            $this->_logger->warn(
                '--- Warning! --- The crawler was not able to find dir:' . $KW .
                ' -> Looking on the root folder of the client ' . $companyId . '!'
            );
        }

        $brochures = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#\.xls$#', $singleFile)) {
                $localExcelFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#' . $KW . '([^\.]*)\.pdf$#', $singleFile)) {
                $brochures[] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        if (empty($brochures)) {
            throw new Exception('No Brochures found');
        }

        $sFtp->close();

        $cStores = $sApi->findStoresByCompany($companyId);

        $aStoreNumbers = [];
        if (empty($localExcelFile)) {
            $this->_logger->warn('--- Warning! --- No Excel File found!' . PHP_EOL .
                '-> Setting to all stores. Please make sure this is run on Thursdays to get the right start and end date if no excel is provided!'
            );
            $aStoreNumbers['alle'] = '';
        } else {
            $aData = $sPss->readFile($localExcelFile)->getElement(0)->getData();

            $excelBrochureData = [];
            foreach ($aData as $key => $singleExcel) {
                if ($key == 0 || $key == 1) {
                    continue;
                }

                $citiesToIntegrate = $singleExcel[2];
                $brochureName = $singleExcel[3];
                $rawDates = $singleExcel[4];

                $excelBrochureData[] = [$citiesToIntegrate, $brochureName, $rawDates];
            }

            foreach ($excelBrochureData as $excelData) {
                if ($excelData[0] == 'alle') {
                    continue;
                }

                $allStores = [];
                foreach ($cStores->getElements() as $eStore) {
                    if (!preg_match('#' . $excelData[0] . '#', $eStore->getCity())) {
                        $allStores[] = $eStore->getStoreNumber();
                        continue;
                    }

                    $aStoreNumbers[$excelData[0]] = $eStore->getStoreNumber();
                }

                $aStoreNumbers['alle'] = $allStores;
            }
        }

        // Distribute to stores if multiples
        if (count($brochures) >= 2) {
            $allBrochure =
            $storeNumbersAndBrochures = [];
            foreach ($brochures as $brochure) {
                if (!preg_match('#([^K]*)' . $KW . '(?<city>[^\.]*)\.pdf#', $brochure, $match)) {
                    throw new Exception('Something is wrong with PDF name on our FTP: ' . $brochure);
                }

                if(!empty($match['city'])) {
                   $storeNumbersAndBrochures[str_replace('_', '', $match['city'])] = $brochure;
                   continue;
                }

                $allBrochure = $brochure;
            }
            // Make sure "all" is last
            $storeNumbersAndBrochures['all'] = $allBrochure;

            $brochures = $storeNumbersAndBrochures;
        }

        $storeNumbersUsed = [];
        foreach ($brochures as $distribution => $brochure) {
            $validFrom = null;
            $validTo = null;
            $city = null;

            // find relative excel data
            if (empty($excelBrochureData)){
                $validFrom = date('d.m.Y', strtotime('+4 days'));
                $validTo = date('d.m.Y', strtotime('+9 days'));
                $this->_logger->warn('Warning! Setting default dates as: ' . PHP_EOL .
                    '->' . $validFrom . ' - ' . $validTo
                );
            } else {
                foreach ($excelBrochureData as $excelData) {
                    if (preg_match('#' . $excelData[1] . '#', $brochure)) {
                        $city = $excelData[0];

                        if(!preg_match_all('#(?<dates>\d{2}\.\d{2}\.\d{4})#', $excelData[2], $dateMatch)) {
                            throw new Exception('Date format are somewhat wrong');
                        }

                        $validFrom = $dateMatch['dates'][0];
                        $validTo = $dateMatch['dates'][1];
                    }
                }
            }

            // add store number and remove the used store from "all" brochure
            $storeNumbers = [];
            if (count($brochures) >= 2) {
                if ($distribution == 'GFH' || $distribution == 'Gräfenhainichen') {
                    foreach ($cStores->getElements()  as $store) {
                        if ($store->getCity() == 'Gräfenhainichen') {
                            $storeNumbers[] = $store->getStoreNumber();
                            $storeNumbersUsed[] = $storeNumbers;
                        }
                    }
                } elseif ($distribution == 'all') {
                    foreach ($cStores->getElements()  as $store) {
                        if (!array_key_exists($store->getStoreNumber, $storeNumbersUsed)) {
                            $storeNumbers[] = $store->getStoreNumber();
                        }
                    }
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($brochure)
                ->setTitle('Wreesmann: Wochenangebote')
                ->setStart($validFrom)
                ->setEnd($validTo)
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' -2 days')) . ' 19:00:00')
                ->setVisibleEnd($eBrochure->getEnd() . ' 18:00:00')
                ->setVariety('leaflet')
                ->setBrochureNumber($KW . '_' . $sTimes->getWeeksYear($week) . '_' . $distribution)
                ->setStoreNumber(implode(',', $storeNumbers))
            ;

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
