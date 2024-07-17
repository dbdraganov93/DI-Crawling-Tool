<?php

/**
 * Brochure crawler for Hellweg DE, AT and BayWa (ID: 28323, 72463, 69602)
 */
class Crawler_Company_Hellweg_Brochure extends Crawler_Generic_Company
{
    private const WEEK = 'next';
    private const FILE_NAME_COLUMN_NAME = 'version';
    private const STORE_NUMBER_COLUMN_NAME = 'marktnummer';
    private const DATE_FORMAT = 'd.m.Y';
    private const REGEX_VALIDITY_PATTERN = '#%s\s*Gültig\s+vom\s+(\d+)\.?\s+(\w+)?\.?\s?bis\s+(\d*)\.*\s+(\w*)\.?\s+(\d{4})#';
    private const FTP_FOLDER = '28323';
    private const COMPANY_DATA = [
        '28323' => [
            'short' => 'HWD',
            'regexFileName' => '#_D_([^\.]+)|_D\.[^\.]+$#',
            'archiveNameRegexPattern' => '#KW\s*%s(?:(?!GCA).)*\.zip$#',
            'title' => 'Hellweg',
            'startingDay' => 'monday',
            'daysBeforeEnd' => 5,
            'locale' => 'de',
        ],
        '69602' => [
            'short' => 'BGM',
            'regexFileName' => '#_B_([^\.]+)|_B\.[^\.]+$#',
            'archiveNameRegexPattern' => '#KW\s*%s(?:(?!GCA).)*\.zip$#',
            'title' => 'BayWa',
            'startingDay' => 'monday',
            'daysBeforeEnd' => 5,
            'locale' => 'de',
        ],
        '72463' => [
            'short' => 'HWAT',
            'regexFileName' => '#_A_([^\.]+)|_A\.[^\.]+$#',
            'archiveNameRegexPattern' => '#KW\s*%s(?:(?!GCA).)*\.zip$#',
            'title' => 'Hellweg At',
            'startingDay' => 'thursday',
            'daysBeforeEnd' => 13,
            'locale' => 'at',
        ],
        '72127' => [
            'short' => 'GCA',
            'regexFileName' => '#^GCA[^\.]+.pdf$#',
            'archiveNameRegexPattern' => '#KW\s*%s\s*GCA[^.]*\.zip$#',
            'title' => 'Gartencenter Augsburg',
            'startingDay' => 'wednesday',
            'daysBeforeEnd' => 3,
            'locale' => '',
        ]
    ];
    private const MONTHS_MAP = [
        'Januar' => 1,
        'Jänner' => 1,
        'Februar' => 2,
        'März' => 3,
        'April' => 4,
        'Mai' => 5,
        'Juni' => 6,
        'Juli' => 7,
        'August' => 8,
        'September' => 9,
        'Oktober' => 10,
        'November' => 11,
        'Dezember' => 12
    ];
    private const SKIP_STORE_ASSIGNMENTS = [
        72463,
        72127
    ];

    private string $year;
    private string $weekNr;
    private string $localPath;
    private string $zipName;
    private int $companyId;
    private Marktjagd_Service_Input_PhpSpreadsheet $spreadSheet;

    public function __construct()
    {
        parent::__construct();

        $this->spreadSheet = new Marktjagd_Service_Input_PhpSpreadsheet();
    }

    public function crawl($companyId)
    {
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $this->companyId = $companyId;
        $this->weekNr = date('W', strtotime(self::WEEK . ' week'));
        $this->zipName = sprintf(self::COMPANY_DATA[$this->companyId]['archiveNameRegexPattern'], $this->weekNr);
        $this->year = date('Y', strtotime(self::WEEK . ' week'));

        $downloadedFiles = $this->downloadFiles();
        $extractedFiles = $this->extractFiles($downloadedFiles['zipFile']);
        $postalCodeNumbers = $this->getPostalCodes($downloadedFiles['postalCodeFile']);

        $storeAssignmentsData = [];
        if (!in_array($this->companyId, self::SKIP_STORE_ASSIGNMENTS)) {
            $storeAssignmentsData = $this->getStoreAssignmentsData($extractedFiles['storeAssignmentsFile']);
            if (empty($storeAssignmentsData)) {
                throw new Exception('Company ID: ' . $companyId . ': No store assignments found!');
            }
        }

        foreach ($extractedFiles['brochures'] as $brochureName => $brochurePath) {

            $hasClickouts = !empty($extractedFiles['clickoutFiles'][$brochureName]);
            if ($hasClickouts) {
                $clickOutData = $this->spreadSheet->readFile($extractedFiles['clickoutFiles'][$brochureName], TRUE)->getElement(0)->getData();
            }
            $storesToAssign = $this->getStoresToAssignTo($brochureName, $storeAssignmentsData);

            $validity = $this->getValidity($brochurePath);

            foreach ($storesToAssign as $storeNumber) {

                $brochureData = [
                    'url' => $hasClickouts ? $this->linkBrochure($brochurePath, $clickOutData) : $this->copyBrochure($brochurePath, $storeNumber),
                    'storeNumber' => $storeNumber,
                    'brochureNumber' => 'KW' . $this->weekNr . '_' . $this->year . '_' . $storeNumber,
                    'postalCodeNumbers' => $postalCodeNumbers[$storeNumber],
                    'start' => $validity['start'],
                    'end' => $validity['end'],
                ];

                $brochure = $this->createBrochure($brochureData);
                $brochures->addElement($brochure, FALSE);
            }
        }

        return $this->getResponse($brochures);
    }

    private function downloadFiles(): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->localPath = $ftp->connect(self::FTP_FOLDER, TRUE);

        $postalCodeFile = '';
        $zipFile = '';
        foreach ($ftp->listFiles() as $ftpFile) {
            if (preg_match($this->zipName, $ftpFile)) {
                $zipFile = $ftp->downloadFtpToDir($ftpFile, $this->localPath);
            } elseif (preg_match('#PLZ-Liste_' . self::COMPANY_DATA[$this->companyId]['short'] . '.xlsx#', $ftpFile)) {
                $postalCodeFile = $ftp->downloadFtpToDir($ftpFile, $this->localPath);
            }
        }
        $ftp->close();

        if (empty($zipFile) || empty($postalCodeFile)) {
            throw new Exception('Company ID: ' . $this->companyId . ': No zip or postal code file found!');
        }

        return [
            'postalCodeFile' => $postalCodeFile,
            'zipFile' => $zipFile,
        ];
    }

    private function extractFiles(string $zipFile): array
    {
        $filesPath = $this->unzipFile($zipFile);

        $clickoutFiles = [];
        $brochures = [];
        $storeAssignmentsFile = '';

        $directory = new RecursiveDirectoryIterator($filesPath, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($recursiveIterator as $dir) {
            if (preg_match('#\.xlsx$#', $dir->getBasename())) {
                $storeAssignmentsFile = $dir->getPathname();
                continue;
            }

            if (!preg_match(self::COMPANY_DATA[$this->companyId]['regexFileName'], $dir->getBasename())) {
                continue;
            }

            if (preg_match('#(.*)\.pdf$#i', $dir->getBasename(), $distMatch)) {
                $brochures[$distMatch[1]] = $dir->getPathname();
                continue;
            }

            if (preg_match('#(.*)\.csv$#i', $dir->getBasename(), $distMatch)) {
                $clickoutFiles[$distMatch[1]] = $dir->getPathname();
            }
        }

        return [
            'storeAssignmentsFile' => $storeAssignmentsFile,
            'clickoutFiles' => $clickoutFiles,
            'brochures' => $brochures
        ];
    }

    private function unzipFile(string $file): string
    {
        $archive = new Marktjagd_Service_Input_Archive();

        if (!$archive->unzip($file, $this->localPath . 'extracted/')) {
            throw new Exception('Company ID: ' . $this->companyId . ': Unable to unzip "' . $file . '"!');
        }

        return $this->localPath . 'extracted/';
    }

    private function getPostalCodes(string $file): array
    {
        $postalCodeData = $this->spreadSheet->readFile($file)->getElement(0)->getData();
        $postalCodes = [];

        foreach ($postalCodeData as $postalCode) {
            if (!is_int($postalCode[3])) {
                continue;
            }

            $storeNumber = $postalCode[3];
            if (strlen($postalCodes[$storeNumber])) {
                $postalCodes[$storeNumber] .= ',';
            }
            $postalCodes[$storeNumber] .= $postalCode[1];
        }

        ksort($postalCodes);

        return $postalCodes;
    }

    private function getStoreAssignmentsData(string $storeAssignmentsFile): array
    {
        $storesData = $this->spreadSheet->readFile($storeAssignmentsFile)->getElement(0)->getData();

        $storeAssignmentsData = [];
        $header = [];
        foreach ($storesData as $storeData) {
            if (empty($storeData[2]) || empty($storeData[3])) {
                continue;
            }

            if (empty($header)) {
                $headerRow = array_map('strtolower', $storeData);
                if (in_array(self::STORE_NUMBER_COLUMN_NAME, $headerRow)) {
                    $header = $headerRow;
                }

                continue;
            }

            $data = array_combine($header, $storeData);

            if (!is_numeric($data[self::STORE_NUMBER_COLUMN_NAME])) {
                continue;
            }

            $storeAssignmentsData[] = $data;
        }

        return $storeAssignmentsData;
    }

    private function getStoresToAssignTo(string $brochureName, array $storeAssignments): array
    {
        $storesToAssignTo = [];
        foreach ($storeAssignments as $storeData) {
            if (isset($storeData[self::FILE_NAME_COLUMN_NAME]) && $storeData[self::FILE_NAME_COLUMN_NAME] === $brochureName) {
                $storesToAssignTo[] = $storeData[self::STORE_NUMBER_COLUMN_NAME];
            }
        }

        if (empty($storesToAssignTo)) {
            $api = new Marktjagd_Service_Input_MarktjagdApi();
            $stores = $api->findStoresByCompany($this->companyId)->getElements();

            foreach ($stores as $store) {
                $storesToAssignTo[] = $store->getStoreNumber();
            }
        }

        return $storesToAssignTo;
    }

    private function getValidity(string $brochurePath): array
    {
        try {
            return $this->getValidityFromPDF($brochurePath);
        } catch (Exception $e) {
            $this->_logger->err($e->getMessage());
        }

        $start = date(self::DATE_FORMAT, strtotime(self::WEEK . ' week ' . self::COMPANY_DATA[$this->companyId]['startingDay']));

        return [
            'start' => $start,
            'end' => date(self::DATE_FORMAT, strtotime($start . ' +' . self::COMPANY_DATA[$this->companyId]['daysBeforeEnd'] . ' days'))
        ];
    }

    private function getValidityFromPDF(string $brochurePath): array
    {
        $pdf = new Marktjagd_Service_Output_Pdf;
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date;

        $text = json_decode($pdf->extractText($brochurePath))[0]->text;

        if (!preg_match(sprintf(self::REGEX_VALIDITY_PATTERN, self::COMPANY_DATA[$this->companyId]['locale']), $text, $validityMatches)) {
            throw new Exception('Company ID: ' . $this->companyId . ': Can\'t get validity from the PDF: "' . basename($brochurePath) . '", will generate it programmatically!');
        }

        if ('' === $validityMatches[2]) {
            $validityMatches[2] = $validityMatches[4];
        }

        if (!isset(self::MONTHS_MAP[$validityMatches[2]])) {
            throw new Exception('Company ID: ' . $this->companyId . ': Invalid month "' . $validityMatches[2] . '"!');
        }
        if (!isset(self::MONTHS_MAP[$validityMatches[4]])) {
            throw new Exception('Company ID: ' . $this->companyId . ': Invalid month "' . $validityMatches[4] . '"!');
        }

        $startMonth = self::MONTHS_MAP[$validityMatches[2]];
        $endMonth = self::MONTHS_MAP[$validityMatches[4]];

        return [
            'start' => $dateNormalizer->normalize($validityMatches[1] . '.' . $startMonth . '.' . $validityMatches[5]),
            'end' => $dateNormalizer->normalize($validityMatches[3] . '.' . $endMonth . '.' . $validityMatches[5])
        ];
    }

    private function linkBrochure(string $localBrochure, array $clickoutData): string
    {
        $pdf = new Marktjagd_Service_Output_Pdf;
        $brochureDimensions = $pdf->getAnnotationInfos($localBrochure);

        $coordinates = [];
        foreach ($clickoutData as $singleClickOut) {
            $siteNumber = (int)$singleClickOut['page'] - 1;

            $coordinates[] = [
                'page' => $siteNumber,
                'height' => $brochureDimensions[$siteNumber]->height,
                'width' => $brochureDimensions[$siteNumber]->width,
                'startX' => ($brochureDimensions[$siteNumber]->width * $singleClickOut['left']) < 10 ? 10 : $brochureDimensions[$siteNumber]->width * $singleClickOut['left'],
                'endX' => $brochureDimensions[$siteNumber]->width * ($singleClickOut['left'] + $singleClickOut['width']),
                'startY' => $brochureDimensions[$siteNumber]->height - ($brochureDimensions[$siteNumber]->height * $singleClickOut['top']),
                'endY' => $brochureDimensions[$siteNumber]->height - ($brochureDimensions[$siteNumber]->height * ($singleClickOut['top'] + $singleClickOut['height'])),
                'link' => $singleClickOut['urlAttribute'],
            ];
        }
        $coordinatesFileName = $this->localPath . 'coordinates_' . $this->companyId . '_' . basename($localBrochure, '.pdf') . '.json';

        $file = fopen($coordinatesFileName, 'w+');
        fwrite($file, json_encode($coordinates));
        fclose($file);

        return $pdf->setAnnotations($localBrochure, $coordinatesFileName);
    }

    private function copyBrochure(string $brochurePath, string $storeNumber): string
    {
        $brochureCopy = preg_replace('#\.pdf#', '_' . $storeNumber . '.pdf', $brochurePath);
        copy($brochurePath, $brochureCopy);

        return $brochureCopy;
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setTitle(self::COMPANY_DATA[$this->companyId]['title'] . ': Wochenangebote')
            ->setUrl($data['url'])
            ->setBrochureNumber($data['brochureNumber'])
            ->setStoreNumber($data['storeNumber'])
            ->setZipCode($data['postalCodeNumbers'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($brochure->getStart());

        return $brochure;
    }
}
