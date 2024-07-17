<?php

/*
 * Prospekt Crawler für Woolworth DE and AT (ID: 79, 90014)
 */

class Crawler_Company_Woolworth_Brochure extends Crawler_Generic_Company
{
    private const EMAIL_LABEL = 'Woolworth';
    private const GOOGLE_DRIVE_PATTERN = '#(https://drive\.google\.com/drive/folders/[^?]+(?:=\r\n)?[^?]?)\??#s';
    private const FILE_NAME_PATTERN = '#(V\d)?_?(?:\w+_)?(\d{2}\.?\d{2}\.?(?:\d{2,4})?)[-_](\d{2}\.?\d{2}\.?(?:\d{2,4})?)_(?:\w+)?_?AS_(WP\d{4})_?([^.]+)?#';
    private const DEFAULT_DATE_FORMAT = 'd.m.Y';
    private const DMY_DATE_FORMAT = 'dmY';
    private const REGEX_EMAIL_BY_COMPANY = [
        '79' => '#[\s\(]DE[\)\s]#',
        '90014' => '#[\s\(]AT[\)\s]#',
    ];

    private Marktjagd_Service_Google_Drive $googleDrive;
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private int $companyId;
    private array $driveUrls = [];

    public function __construct()
    {
        parent::__construct();

        $this->googleDrive = new Marktjagd_Service_Google_Drive;
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;
        $localPath = $this->ftp->generateLocalDownloadFolder($companyId);

        $emailService = new Marktjagd_Service_Transfer_Email(self::EMAIL_LABEL);
        $emails = $emailService->generateEmailCollection($companyId);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($emails->getElements() as $email) {
            if (!preg_match(self::REGEX_EMAIL_BY_COMPANY[$companyId], $email->getSubject())) {
                continue;
            }

            $hasErrors = false;

            if (!preg_match(self::GOOGLE_DRIVE_PATTERN, $email->getText(), $urlMatch)) {
                $this->_logger->err('Company ID: ' . $companyId . ': unable to get drive url from mail: ' . $email->getSubject());
                continue;
            }


            $googleDriveUrl = $urlMatch[1];
            if (in_array($googleDriveUrl, $this->driveUrls)) {
                $emailService->archiveMail($email);
                continue;
            }

            $this->driveUrls[] = $googleDriveUrl;

            $googlePdfFiles = $this->googleDrive->getFiles($googleDriveUrl, 'pdf');
            $pdfFiles = $this->downloadFiles($googlePdfFiles, $localPath);
            $googleXlsxFiles = $this->googleDrive->getFiles($googleDriveUrl, 'xlsx');
            $storeLists = $this->downloadFiles($googleXlsxFiles, $localPath);

            foreach ($pdfFiles as $name => $pdf) {
                try {
                    $storeListFile = $storeLists[$name] ?? '';

                    $brochureData = $this->getBrochureData($pdf, $storeListFile);
                    $brochure = $this->createBrochure($brochureData);
                    $success = $brochures->addElement($brochure);
                    $hasErrors = $hasErrors || !$success;
                } catch (\Exception $ex) {
                    $this->_logger->err($ex->getMessage());
                    continue;
                }
            }

            if (!$hasErrors) {
                $emailService->archiveMail($email);
            }
        }

        return $this->getResponse($brochures);
    }

    private function downloadFiles(array $googleDriveFiles, string $directory): array
    {
        $files = [];
        foreach ($googleDriveFiles as $googleDriveFile)
        {
            $name = preg_replace(['#\\\#', '#__#'], ['', '_'], $googleDriveFile['name']);
            $key = preg_replace('#\.[^.]+$#', '', $name);
            $files[$key] = $this->googleDrive->downloadFile($googleDriveFile['id'], $directory, $name);
        }

        return $files;
    }

    private function getBrochureData(string $pdfFile, string $storeList = ''): array
    {
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date();

        if (!preg_match(self::FILE_NAME_PATTERN, $pdfFile, $fileNameMatch)) {
            throw new Exception('Company ID (' . $this->companyId . '): Unable to parse file name: ' . $pdfFile);
        }

        if (preg_match('#\d{6,8}#', $fileNameMatch[2])) {
            $start = $dateNormalizer->normalize($fileNameMatch[2], self::DMY_DATE_FORMAT);
            $end = $dateNormalizer->normalize($fileNameMatch[3], self::DMY_DATE_FORMAT);
        }
        else if (preg_match('#\d{2}\.\d{2}\.(?:\d{2,4})?#', $fileNameMatch[2])) {
            $start = $dateNormalizer->normalize($fileNameMatch[2], self::DEFAULT_DATE_FORMAT);
            $end = $dateNormalizer->normalize($fileNameMatch[3], self::DEFAULT_DATE_FORMAT);
        }
        else {
            throw new Exception('Company ID (' . $this->companyId . '): Unable to parse dates form filename: ' . $pdfFile);
        }

        $visibleStart = date(self::DEFAULT_DATE_FORMAT, strtotime($start . ' - 1 day'));

        $numberPrefix = '';
        if (!empty($fileNameMatch[1])) {
            $numberPrefix = $fileNameMatch[1] . '_';

            if ('V2' === $fileNameMatch[1]) {
                $visibleStart = $start;
            }
        }

        $wp = $fileNameMatch[4];
        $numberSuffix = $fileNameMatch[5] ? '_' . preg_replace(['#\.#', '#\s#'], ['-', '_'], $fileNameMatch[5]) : '';

        return [
            'number' => $numberPrefix . $wp . $numberSuffix,
            'start' => $start,
            'end' => $end,
            'visibleStart' => $visibleStart,
            'stores' => $storeList ? $this->getStores($storeList) : '',
            'url' => $this->ftp->generatePublicFtpUrl($pdfFile)
        ];
    }

    private function getStores(string $storeList): string
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $storesData = $spreadsheetService->readFile($storeList)->getElement(0)->getData();

        $stores = [];
        foreach ($storesData as $store) {
            if (!empty($store['Filialen'])) {
                $stores[] = $store['Filialen'];
            }
            elseif (preg_match('#(\d+)#', $store[1])) {
                $stores[] = $store[1];
            }
        }

        return implode(',', $stores);
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle('Woolworth: Wochenangebote')
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visibleStart'])
            ->setBrochureNumber($brochureData['number'])
            ->setStoreNumber($brochureData['stores'])
            ->setVariety('leaflet');
    }
}
