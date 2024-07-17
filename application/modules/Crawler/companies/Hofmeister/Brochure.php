<?php

/**
 * Brochure Hofmeister (ID: 69717) || MöbelBorst (ID: 80108)
 */
class Crawler_Company_Hofmeister_Brochure extends Crawler_Generic_Company
{

    private const MODE = 0;
    private const FILIALE = 1;
    private const DATEINAME = 2;
    private const LAUFZEIT = 3;
    private const MAPPED_COLUMNS = [self::MODE => 'Mode', self::FILIALE => 'Filiale', self::DATEINAME => 'Dateiname', self::LAUFZEIT => 'Laufzeit'];

    /**
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        // things to change before run, we need to check if we want to import to Hofmeister or MoebelBorst (80108) -> Ehingen
        // It is still not fully automated ->
        // Normally we need to create one for Hofmeister and one for MöbelBost
        $brochureTitle = 'Hofmeister: Onlineprospekt';
        if ($companyId == '80108') {
            $brochureTitle = 'Möbel Borst: Onlineprospekt';
        }

        $week = 'next';
        $weekNo = date('W', strtotime($week . ' week'));
        $weekKW = 'KW' . $weekNo;
        $currentYear = date('y');

        $localPath = $sFtp->connect('69717', true);

        // get association excel files
        foreach ($sFtp->listFiles() as $singleFile) {
            // each Excel tab (city) contains
            if (preg_match('#plz21\.xlsx$#', $singleFile)) {
                $excelZipFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                continue;
            }
            // to look for the order of the tabs
            if (preg_match('#excel_tab_order\.xlsx$#', $singleFile)) {
                $excelTabOrder = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->changedir($weekKW);

        // get main file
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#' . $weekKW . '-' . $currentYear . '\.xls$#', $singleFile)) {
                $referenceExcel = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        if (!isset($referenceExcel)) {
            throw new \Exception('No reference file was found on the FTP. Example: "woche_KW11-22.xls"');
        }
        $referenceExcelData = $this->validateReferenceExcel($sExcel->readFile($referenceExcel)->getElement(0)->getData(), $weekNo);
        $this->_logger->info('The crawler found ' . count($referenceExcelData) . ' different brochures on the reference Excel');

        // get excel with Tab reference
        $tabOrderArray = $sExcel->readFile($excelTabOrder, true)->getElement(0)->getData();

        // creates the brochure for each entry that contains "auto" mode on the Excel reference file
        foreach ($referenceExcelData as $row => $brochureReferenceData) {
            $this->_logger->info('Trying to create clickouts for brochure: ' . $brochureReferenceData[1]);
            if (trim($brochureReferenceData[self::MODE]) !== 'auto') {
                continue;
            }

            // resolve brochure name
            $brochureName = trim($brochureReferenceData[self::DATEINAME]);

            // resolve dates
            $datePattern = "#(?<startDate>(?<startDay>\d{2})\.(?<startMonth>\d{2})\.\d{2,4})\s?-\s?(?<endDate>(?<endDay>\d{2})\.(?<endMonth>\d{2})\.\d{2,4})#";
            if (!preg_match($datePattern, $brochureReferenceData[self::LAUFZEIT], $dateMatch)) {
                throw new \Exception(
                    ' -> The crawler was not able to match dates: ' . $brochureReferenceData[self::LAUFZEIT] .
                    ' -> on excel row: ' . ($row + 3)
                );
            }

            // resolve cities and zips to distribute
            $storeNumbers = [];
            $citiesToDistribute = explode(',', $brochureReferenceData[self::FILIALE]);
            foreach ($citiesToDistribute as $cityToDistribute) {
                $cleanCityToDistribute = trim($cityToDistribute);

                // get Store Number
                /** @var Marktjagd_Entity_Api_Store $apiStore */
                foreach ($sApi->findStoresByCompany($companyId)->getElements() as $apiStore) {
                    if (preg_match('#' . $cleanCityToDistribute . '#', $apiStore->getCity())) {
                        $storeNumbers[] = $apiStore->getStoreNumber();
                    }
                }

                // get zips
                foreach ($tabOrderArray as $tabOrder) {
                    if (preg_match('#' . $cleanCityToDistribute . '#', $tabOrder['city'])) {
                        $zipsResult .= $this->parseExcelAsArray($tabOrder['tab_order'], $excelZipFile, $sExcel);
                    }
                }
            }

            if (empty($storeNumbers)) {
                $this->_logger->alert('Skipping brochure ' . $brochureName . ' -> no StoreNumberFound');
                // Skips the if no stores found (To differentiate btw companies)
                continue;
            }

            // get folder with all XML coordinates
            foreach ($sFtp->listFiles() as $folderItemsList) {

                if (!isset(pathinfo($folderItemsList)['extension']) && preg_match('#' . $brochureName . '#', $folderItemsList)) {
                    continue;
                } if (!isset($folderItemsList)) {
                    throw new \Exception('The folder containing all the brochure files could not be found!');
                }

            }
            $sFtp->changedir($folderItemsList);
            $sFtp->changedir('maps');

            // get xml pages
            $xmlFiles = [];
            foreach ($sFtp->listFiles() as $singleXML) {
                if (!preg_match('#\.xml$#', $singleXML)) {
                    continue;
                }
                $xmlFiles[] = $sFtp->downloadFtpToDir($singleXML, $localPath);
            }

            // get dimensions from catalog.xml
            $sFtp->changedir('../xml');
            foreach ($sFtp->listFiles() as $catalogXMLFile) {
                if (!preg_match('#catalog.xml#', $catalogXMLFile)) {
                    continue;
                }
                $catalogXML = $sFtp->downloadFtpToDir($catalogXMLFile, $localPath);
            }

            // get the complete.pdf since the original sent is doubled page
            $sFtp->changedir('../pdf');
            foreach ($sFtp->listFiles() as $file) {
                if (!preg_match('#complete.pdf#', $file)) {
                    continue;
                }
                $completePdf = $sFtp->downloadFtpToDir($file, $localPath);
            }

            // Clickouts start
            $coordFiles = $this->buildClickoutJson($xmlFiles, $localPath, $catalogXML, $brochureName);

            $this->_logger->info('Adding annotations to get linked pdf. Working on brochure: ' . $brochureName);
            $sPdf->setAnnotations($completePdf, $coordFiles);


            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setStoreNumber(implode(', ', $storeNumbers))
                ->setUrl($localPath . 'complete_linked.pdf')
                ->setVariety('leaflet')
                ->setStart($this->normalizeDates($dateMatch['startDate'], $currentYear))
                ->setVisibleStart($eBrochure->getStart())
                ->setEnd($this->normalizeDates($dateMatch['endDate'], $currentYear))
                ->setTitle($brochureTitle)
                ->setBrochureNumber($brochureName . $weekKW . '_linked')
                ->setZipCode($zipsResult);

            $cBrochures->addElement($eBrochure);

            $this->_logger->info('-> going back to the main FTP folder');
            $sFtp->changedir('..');
            $sFtp->changedir('..');
        }

        $sFtp->close();

        return $this->getResponse($cBrochures, $companyId);
    }

    private function buildClickoutJson($xmlFiles, $localPath, $catalogXML, $brochureName)
    {
        $coordinates = [];

        $xmlString = file_get_contents($catalogXML);
        $pattern = '#<detaillevel[^>]*name="large"[^>]*width="([^"]+?)"[^>]*height="([^"]+?)"#';
        if (preg_match($pattern, $xmlString, $dimensionMatch)) {
            $pageWidth = $dimensionMatch[1];
            $pageHeight = $dimensionMatch[2];
        }

        foreach ($xmlFiles as $xmlFile) {
            if (!preg_match('#bk_(?<page>\d+)#', $xmlFile, $pageNumber)) {
                continue;
            }

            $xmlString = file_get_contents($xmlFile);
            $xmlData = new SimpleXMLElement($xmlString);

            foreach ($xmlData->area as $singleLink) {
                $coords = explode(',', (string)$singleLink->attributes()->coords);
                $link = (string)$singleLink->attributes()->id;

                $this->_logger->info('Adding annotation to ' . $link);

                $endX = min($coords[2], 1653);
                $endY = max($pageHeight - $coords[3], 0);
                $coordinates[] = [
                    # for pdfbox page nr is 0-based
                    'page' => (int)$pageNumber['page'] - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $coords[0] + 45.0,
                    'endX' => $endX + 45.0,
                    'startY' => $pageHeight - $coords[1] + 55.0,
                    'endY' => $endY + 55.0,
                    'link' => $link
                ];
            }
        }
        $coordFileName = $localPath . 'coordinates_' . $brochureName . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($coordinates));
        fclose($fh);

        return $coordFileName;
    }

    private function parseExcelAsArray(int $excelTab, $zipExcelFile, Marktjagd_Service_Input_PhpExcel $sExcel): string
    {
        $aData = $sExcel->readFile($zipExcelFile)->getElement($excelTab)->getData();

        return $this->getZipsFromExcel($aData);
    }

    private function getZipsFromExcel(array $excelData): string
    {
        $zips = [];
        foreach ($excelData as $data) {
            if (empty($data[0]) || $data[0] == 'PLZ') {
                continue;
            }

            $zips[] = (string)$data[0];
        }

        return implode(',', $zips);
    }

    /**
     * @throws Exception
     */
    private function validateReferenceExcel(array $excelData, string $weekNo): array
    {
        $cleanRows = [];
        foreach ($excelData as $key => $dataRow) {
            // validate Excel "title" week KW and then "columns" with mapped consts
            if ($key == 0 && !preg_match('#KW ' . $weekNo . '#', $dataRow[2])) {
                throw new \Exception(' -> The week number: ' . $weekNo . ' does not match with Excel title: ' . $dataRow[2]);
            } elseif ($key == 1) {
                $cleanRow = array_slice($dataRow, 0, count(self::MAPPED_COLUMNS));
                if (empty(array_diff($cleanRow, self::MAPPED_COLUMNS))) {
                    $this->_logger->info('The Excel columns match with the mapped by the crawler');
                    continue;
                }

                throw new \Exception(
                    'The Excel columns does not match the mapped consts on this crawler. They changed the reference excel? -> ' .
                    implode(', ', self::MAPPED_COLUMNS)
                );
            }

            // clean empty fields
            if (empty($dataRow[self::MODE]) || empty($dataRow[self::FILIALE]) || empty($dataRow[self::DATEINAME]) || empty($dataRow[self::LAUFZEIT])) {
                $this->_logger->info('Skipping row number: ' . $key);
                continue;
            }

            $cleanRows[] = $dataRow;
        }

        return $cleanRows;
    }

    private function normalizeDates(string $date, $year): string
    {
        if (preg_match('#(?<day>\d{2})\.(?<month>\d{2})\.(?<year>\d{2})#', $date, $dateMatch)) {
            $date = $dateMatch['day'] . '.' . $dateMatch['month'] . '.20' . $year;
        }

        return $date;
    }
}
