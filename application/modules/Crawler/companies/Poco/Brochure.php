<?php

/**
 * Prospekt Crawler für Poco (ID: 197)
 */
class Crawler_Company_Poco_Brochure extends Crawler_Generic_Company
{
    private const WEEK = 'next';

    private Marktjagd_Service_Transfer_FtpMarktjagd $sFtp;

    private string $localPath;
    private string $weekNr;
    private string $weekYear;
    private array $brochuresData;

    public function crawl($companyId)
    {
        $sTime = new Marktjagd_Service_Text_Times();
        $this->sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $this->weekNr = $sTime->getWeekNr(self::WEEK);
        $this->weekYear = $sTime->getWeeksYear(self::WEEK);

        $localFiles = $this->downloadFilesFromFtp($companyId);

        $this->brochuresData = $this->getBrochuresFromFiles($localFiles['zip'], $companyId);

        $aStoreNumbers = $this->getStoresData($localFiles['xls']);

        $this->setStores($aStoreNumbers);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($this->brochuresData as $data) {
            $eBrochure = $this->generateBrochure($data);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function downloadFilesFromFtp(int $companyId): array
    {
        $this->sFtp->connect($companyId);
        $this->localPath = $this->sFtp->generateLocalDownloadFolder($companyId);
        $folder = $this->weekYear . 'KW' . $this->weekNr;

        $files = [
            'zip' => [],
            'xls' => []
        ];
        foreach ($this->sFtp->listFiles($folder) as $singleFile) {
            if (preg_match('#(.*)\.zip#', $singleFile, $matchedName)) {
                $files['zip'][$matchedName[1]] = $this->sFtp->downloadFtpToDir($singleFile, $this->localPath);
            } elseif (preg_match('#(.*)\.xls#', $singleFile, $matchedName)) {
                $files['xls'][$matchedName[1]] = $this->sFtp->downloadFtpToDir($singleFile, $this->localPath);
            }
        }
        $this->sFtp->close();

        if (empty($files['zip'])) {
            throw new Exception($companyId . ': no archives found in ' . $folder);
        }
        if (empty($files['xls'])) {
            throw new Exception($companyId . ': no xls-file found in ' . $folder);
        }

        return $files;
    }

    private function getBrochuresFromFiles(array $zipFiles, int $companyId): array
    {
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        foreach ($zipFiles as $localZip) {
            if (!$sArchive->unzip($localZip, $this->localPath)) {
                throw new Exception("$companyId: missing local file: $localZip");
            }
            unlink($localZip);

            $dirInZip = pathinfo($localZip)['filename'];
            if (is_dir($this->localPath . $dirInZip)) {
                foreach (array_diff(scandir($this->localPath . $dirInZip), ['.', '..']) as $item) {
                    rename($this->localPath . $dirInZip . '/' . $item, $this->localPath . $item);
                }
            }
        }

        $brochureData = [];
        foreach (scandir($this->localPath . 'catalogs') as $singleCatalog) {
            $folderNamePattern = '#(' . $this->weekYear . 'KW' . $this->weekNr . '_[^.]+)(?:_\w)$#';
            if (!is_dir($this->localPath . 'catalogs/' . $singleCatalog) || !preg_match($folderNamePattern, $singleCatalog, $storeDataMatch)) {
                continue;
            }

            $aCoordFile = $this->buildClickoutFile($singleCatalog, $companyId);

            $pathToBrochurePdf = $this->localPath . 'catalogs/' . $singleCatalog . '/pdf/complete.pdf';
            $linkedBrochure = $sPdf->setAnnotations($pathToBrochurePdf, $aCoordFile);

            $brochureNumber = preg_replace('#_b$#', '', $singleCatalog);
            $brochureData[$brochureNumber] = [
                'number' => $brochureNumber,
                'url' => $this->sFtp->generatePublicFtpUrl($linkedBrochure)
            ];
        }

        return $brochureData;
    }

    private function buildClickoutFile(string $catalogName, int $companyId): string
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $this->_logger->info($companyId . ': building clickouts json for ' . $catalogName);

        $catalogDir = $this->localPath . 'catalogs/' . $catalogName . '/';
        $dimensionFilePath = $catalogDir . 'xml/catalog.xml';
        $linkFilesFolder = $catalogDir . 'maps';
        $xmlString = file_get_contents($dimensionFilePath);

        $pattern = '#<detaillevel[^>]*name="large"[^>]*width="([^"]+?)"[^>]*height="([^"]+?)"#';
        if (preg_match($pattern, $xmlString, $dimensionMatch)) {
            $pageWidth = $dimensionMatch[1];
            $pageHeight = $dimensionMatch[2];
        }


        $aCoordsToLink = array();
        foreach (scandir($linkFilesFolder) as $singleLinkFile) {
            if (!preg_match('#bk_(\d+)\.xml#', $singleLinkFile, $siteMatch)) {
                continue;
            }

            // Skips a xml if a brochure has more .xml files than .pdf pages
            $pdfInfos = $sPdf->getAnnotationInfos($catalogDir . 'pdf/complete.pdf');
            if (count($pdfInfos) < $siteMatch[1]) {
                $this->_logger->info(
                    'No page ' . $siteMatch[1] . '- skipping xml file: ' . $singleLinkFile . 'in brochure ' . $catalogName
                );
                break;
            }

            $xmlString = file_get_contents($linkFilesFolder . '/' . $singleLinkFile);
            $xmlData = new SimpleXMLElement($xmlString);

            foreach ($xmlData->area as $singleLink) {
                $aCoords = preg_split('#\s*,\s*#', (string)$singleLink->attributes()->coords);
                $idString = (string)$singleLink->attributes()->id;

                $paddedCalendarWeek = str_pad($this->weekNr, 2, '0', STR_PAD_LEFT);
                $params = array(
                    //'em_src' => 'print',
                    'utm_medium' => 'brochure',
                    'utm_source' => 'Offerista',
                    'utm_campaign' => "Prospekt%20KW{$paddedCalendarWeek}-{$this->weekYear}");
                $pattern = '#(.+?\/(\d{5,}))(\/.+)?#';
                if (!preg_match($pattern, $idString, $parameterMatch)) {
                    //$params['em_cmp'] = "Offerista/{$weekYear}/KW{$paddedCalendarWeek}";
                    $parts = parse_url($idString);
                    parse_str($parts['query'], $query);

                    // Searches for the "search parameter"  "" and do a second regex after the product id
                    if (isset($query['s'])) {
                        $params['utm_content'] = $query['s'];
                        //$params['em_cmp'] = $params['em_cmp'] . '/' . $query['query'];
                    } else if (preg_match(
                        '#(.+?(?<productId>\d{5,}))(\/.+)?#',
                        $idString,
                        $productNumberMatch
                    )) {
                        $params['utm_content'] = $productNumberMatch['productId'];
                    }

                    // If they have a link like www.poco.de! remove the '!'
                    if (preg_match('#^(.*[^!])(!$)#', $idString)) {
                        $this->_logger->warn('The link ' . $idString . ' had its character "!" removed');
                        $idString = substr($idString, 0, -1);
                    }

                    $baseURL = $idString;
                } else {
                    $productId = $parameterMatch[2];
                    //$productSEOString = substr($parameterMatch[3], 1);
                    //$params['em_cmp'] = "Offerista/{$weekYear}/KW{$paddedCalendarWeek}/{$productId}_{$productSEOString}";
                    $params['utm_content'] = $productId;
                    $baseURL = $parameterMatch[0];
                }
                $clickoutURL = $this->appendQueryParams($baseURL, $params);

                $endX = min($aCoords[2], 1503);
                $endY = max($pageHeight - $aCoords[3], 0);

                $aCoordsToLink[] = array(
                    # for pdfbox page nr is 0-based
                    'page' => (int)$siteMatch[1] - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $aCoords[0] + 45.0,
                    'endX' => $endX + 45.0,
                    'startY' => $pageHeight - $aCoords[1] + 45.0,
                    'endY' => $endY + 45.0,
                    'link' => str_replace('http://', 'https://', $clickoutURL)
                );
            }
        }

        $coordFileName = $this->localPath . 'coordinates_' . $companyId . '_' . $catalogName . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $coordFileName;
    }

    /**
     * @param string $urlString
     * @param array $params
     * @return string
     */
    private function appendQueryParams($urlString, $params)
    {
        if (preg_match('#^(\d)#', $urlString)) {
            $urlString = 'https://www.poco.de/p/' . $urlString;
        } elseif (preg_match('#^poco\.de#', $urlString)) {
            $urlString = 'https://www.' . $urlString;
        }
        $aParams = [];
        foreach ($params as $key => $param) {
            $aParams[] = "$key=$param";
        }
        $separator = strpos($urlString, '?') ? '&' : '?';

        return "$urlString$separator" . implode('&', $aParams);
    }

    private function getStoresData(array $xlsFiles): array
    {
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $aStoreNumbers = [];
        foreach ($xlsFiles as $name => $xlsPath) {
            $aHeader = [];
            $storeNumbers = [];

            $storeData = $sExcel->readFile($xlsPath)->getElement(0)->getData();
            foreach ($storeData as $singleStoreData) {
                if (!strlen($singleStoreData[0])) {
                    continue;
                }
                if (!count($aHeader)) {
                    $aHeader = $singleStoreData;
                    continue;
                }
                $aData = array_combine($aHeader, $singleStoreData);

                $brochureNumber = $aData['Dateiname'];
                if (is_array($brochureNumber) || preg_match('#---#', $brochureNumber)) {
                    $this->_logger->info('No brochure for ' . $aData['Filiale'] . ' for week ' . $this->weekNr);
                    continue;
                }

                if (empty($this->brochuresData[$brochureNumber]['start'])) {
                    $this->setBrochureValidity($brochureNumber, $aData['Laufzeit']);
                }

                $storeName = is_array($aData['Filiale']) ? implode(' ', $aData['Filiale']) : $aData['Filiale'];

                $strStoreNumber = $aData['Filial-Nr.'];

                // If there is 2 or more brochures separated by '+' on same store in .xls
                $splitPattern = '#\s*\+\s*#';
                if (preg_match($splitPattern, $brochureNumber)) {
                    $fileNameParts = preg_split($splitPattern, $brochureNumber);
                    foreach ($fileNameParts as $fileNamePart) {
                        $storeNumbers[trim($fileNamePart)][] = $strStoreNumber;
                    }
                    continue;
                }

                $storeNumbers[$brochureNumber][] = $strStoreNumber;
            }

            foreach (array_keys($storeNumbers) as $brochureNumber) {
                $this->setBrochureTitle($brochureNumber, $name);
            }

            $aStoreNumbers = array_merge_recursive($aStoreNumbers, $storeNumbers);
        }

        return $aStoreNumbers;
    }

    private function setBrochureValidity(string $brochureNumber, string $validityString): void
    {
        $regexMatchValidity = '#(\d{2}\.\d{2}\.(\d{2,4}))\s*\-\s*(\d{2}\.\d{2}\.(\d{2,4}))#';
        $regexMatchYear = '#(\d{2})$#';
        $replaceYear = '20$1';
        if (preg_match($regexMatchValidity, $validityString, $validityMatch)) {
            $start = $validityMatch[1];
            $end = $validityMatch[3];
            if (2 == strlen($validityMatch[2])) {
                $start = preg_replace($regexMatchYear, $replaceYear, $validityMatch[1]);
            }
            if (2 == strlen($validityMatch[4])) {
                $end = preg_replace($regexMatchYear, $replaceYear, $validityMatch[3]);
            }

            $this->brochuresData[$brochureNumber]['start'] = $start;
            $this->brochuresData[$brochureNumber]['end'] = $end;
        }
    }

    private function setBrochureTitle(string $brochureNumber, string $xlsName): void
    {
        $brochureTitle = 'Poco: Wochenangebote';
        if (preg_match('#\wOnlineprospekt$#', $xlsName)) {
            $brochureTitle = 'Poco: Onlineprospekt';
        } elseif (preg_match('#\wGrosselektro$#', $xlsName)) {
            $brochureTitle = 'Poco: Grosselektro';
        }

        $this->brochuresData[$brochureNumber]['title'] = $brochureTitle;
    }

    private function setStores(array $storesData): void
    {
        foreach ($storesData as $brochureNumber => $stores) {
            $storesListString = implode(',', $stores);
            $storesListString = preg_replace(
                ['#stuttgart-bad cannstatt#', '#nürnberg-ingolstädter straße#', '#kiel#', '#ü#', '#ö#'],
                ['stuttgart-bad-cannstatt', 'nuernberg-ingolstaedter-strasse', 'kiel-schwentinental', 'ue', 'oe'],
                $storesListString
            );

            $this->brochuresData[$brochureNumber]['storeNumber'] = $storesListString;
        }
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setStoreNumber($data['storeNumber'])
            ->setUrl($data['url'])
            ->setVariety('leaflet')
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')) . ' 20:00')
            ->setVisibleEnd($eBrochure->getEnd() . ' 20:00')
            ->setTitle($data['title'])
            ->setBrochureNumber(substr($data['number'], 0, 32));

        return $eBrochure;
    }
}
