<?php

/* 
 * Brochure Crawler für Penny (ID: 122)
 */

class Crawler_Company_Penny_Brochure extends Crawler_Generic_Company
{
    private const TRACKING_URL = 'https://track.adform.net/adfserve/?bn=61040737;1x1inv=1;srctype=3;gdpr=${gdpr};gdpr_consent=${gdpr_consent_50};ord=%%CACHEBUSTER%%';
    private const REGEX_ALL_STORES_XLS = '#20210825_markt_liste_Penny.xlsx#';
    private Marktjagd_Service_Input_PhpSpreadsheet $sPss;

    public function crawl($companyId)
    {
        $sTime = new Marktjagd_Service_Text_Times();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $this->sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $week   = 'next';
        $weekNr = $sTime->getWeekNr($week);
        $ftpFolderName = "PENNY_Flippingbooks-KW$weekNr-" . date('Y');

        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);

        $sFtp->connect($companyId);

        $allStoresLocalXlsFile = '';
        foreach($sFtp->listFiles() as $fileName) {
            // Get xls file with all store PLZs and StoreNumbers
            if(preg_match(self::REGEX_ALL_STORES_XLS, $fileName, $xlsFile)) {
                $allStoresLocalXlsFile = $sFtp->downloadFtpToDir($fileName, $localFolder);
            }
        }

        $isWeekDirValid = $sFtp->changedir($ftpFolderName);
        if(!$isWeekDirValid) {
            throw new Exception('No Penny directory found for this week: KW' . $weekNr . '! Change $week var');
        }

        $brochuresFileNames = [];
        foreach($sFtp->listFiles() as $fileName) {
            if(preg_match('#Penny_Aussteuerung_dhZ_PLZ-Zuordnung_20211110_komplett.csv$#', $fileName, $xlsFile)) {
                $localNewXlsFile = $sFtp->downloadFtpToDir($fileName, $localFolder);
            }

            if(preg_match('#.pdf$#', $fileName)) {
                $this->_logger->info('FTP -> Downloading PDF: ' . $fileName);
                // Download PDFs
                $sFtp->downloadFtpToDir($fileName, $localFolder);
                $brochuresFileNames[] = $fileName;
            }
        }

        $this->_logger->info(
            'Done! '. PHP_EOL . 'PDFs Saved at local path: ' . $localFolder . ' | Closing FTP conn...'
        );
        $sFtp->close();

        if (empty($brochuresFileNames)) {
            throw new Exception('No Brochures found! folder: ' . $ftpFolderName);
        } elseif (empty($localNewXlsFile)) {
            throw new Exception('No Excel reference file found! folder: ' . $ftpFolderName);
        }

        $storeBrochureZipAllocation = $this->getStoreBrochureZipAllocations($localNewXlsFile, $brochuresFileNames);
        $cBrochures = $this->createBrochures($storeBrochureZipAllocation, $allStoresLocalXlsFile, $localFolder);

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getStoreBrochureZipAllocations(string $localNewXlsFile, array $brochuresFileNames): array
    {
        $xlsContent          = $this->sPss->readFile($localNewXlsFile, true)->getElements();

        $storeBrochureZipAllocation = [];
        foreach ($xlsContent[0]->getData() as $storeListEntry) {
            // store region distribution and sub distribution
            $storeDist = $storeListEntry['Regions-WK'];
            if(preg_match(
                '#(?<regionPart1>\d{2}\w{1})-(?<regionPart2>\d{1})$#',
                $storeDist,
                $regionMatch
            )) {
                $storeDist = $regionMatch['regionPart1'] . '-0' . $regionMatch['regionPart2'];
            }

            $storeSubDist = $storeDist . '-' . $storeListEntry['PY WERKEKREIS 2'];

            $brochureToAssign = '';
            foreach ($brochuresFileNames as $brochureName) {
                // assign brochures to store alternatively leaving out the sub region $storeListEntry['WKR_40']
                if (preg_match('#' . $storeSubDist . '#', $brochureName)){
                    $brochureToAssign = $brochureName;
                    break;
                } elseif (preg_match('#' . $storeDist . '.pdf$#', $brochureName)) {
                    $this->_logger->info(
                        $storeDist . ' is added alternatively to ' . $brochureName
                    );
                    $brochureToAssign = $brochureName;
                    break;
                }
            }

            if (empty($brochureToAssign)) {
                $this->_logger->alert(
                    'No brochure found for store: ' . $storeListEntry['MA_STR'] .
                    ' Region: ' . $storeListEntry['Regions-WK']
                );
                continue;
            }

            if (array_key_exists($brochureToAssign, $storeBrochureZipAllocation)){
                array_push($storeBrochureZipAllocation[$brochureToAssign], $storeListEntry['PLZ5']);
            } else {
                $storeBrochureZipAllocation[$brochureToAssign] = [$storeListEntry['PLZ5']];
            }
        }

        return $storeBrochureZipAllocation;
    }

    private function createBrochures(array $storeBrochureZipAllocation, string $allStoresLocalXlsFile, string $localFolder): Marktjagd_Collection_Api_Brochure
    {
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $allStoresXlsContent = $this->sPss->readFile($allStoresLocalXlsFile, true)->getElements()[0]->getData();
        foreach ($storeBrochureZipAllocation as $brochure => $storeZips) {
            $brochureStoreNumbers = [];
            foreach ($storeZips as $zip) {
                foreach ($allStoresXlsContent as $xlsStoreContent) {
                    if($xlsStoreContent['PLZ'] == $zip) {
                        $brochureStoreNumbers[] = $xlsStoreContent['WAWI_MA_NR'];
                    }
                }
            }

            $strText = $sPdf->extractText($localFolder . $brochure);

            $pattern = '#(?<startDate>\d{1,2}.\d{1,2}.)\s*bis\s*Sa,\s*(?<endDate>\d{1,2}.\d{1,2}.)#';
            if (preg_match($pattern, $strText, $dateMatch)) {
                $this->_logger->info('Dates found on brochure: ' . $brochure);
            } else {
                $this->_logger->info('The crawler was not able to get dates on brochure: ' . $brochure);
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $startDate = $dateMatch['startDate'] . date('Y');
            $endDate   = $dateMatch['endDate'] . date('Y');

            // build visible_start
            if(!preg_match('#(?<day>\d{1,2})\.(?<month>\d{1,2})\.(?<year>\d{4})#', $startDate, $startMatch)){
                $this->_logger->alert('Regex problem on Date - Could not resolve Visible_Date: ' .  $startDate);
                continue;
            }
            $visibleStartDay = (int) $startMatch['day'] -1;

            $eBrochure->setUrl($localFolder . $brochure)
                ->setTitle('Penny: Wochenangebote')
                ->setVisibleStart($visibleStartDay . '.' . $startMatch['month'] . '.' . $startMatch['year'])
                ->setStart($startDate)
                ->setEnd($endDate)
                ->setVisibleEnd($endDate)
                ->setVariety('leaflet')
                ->setBrochureNumber(substr($brochure, 0, -4))
                ->setStoreNumber(implode(',', $brochureStoreNumbers))
                ->setZipCode(!empty($storeZips) ? '' : implode(',', $storeZips))
                ->setTrackingBug(self::TRACKING_URL);
            ;

            $cBrochures->addElement($eBrochure);
        }

        return $cBrochures;
    }
}
