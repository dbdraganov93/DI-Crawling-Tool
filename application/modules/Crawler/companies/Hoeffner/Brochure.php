<?php

/*
 * Brochure Crawler for Höffner (ID: 59, 156)
 */

class Crawler_Company_Hoeffner_Brochure extends Crawler_Generic_Company
{
    private const DEFAULT_VALIDITY_COL = 4;
    private const DEFAULT_VALIDITY_ROW = 4;
    private int $validityDateCol;
    private array $downloadLinks = [];
    private array $companyData = [];
    private Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;

    private const COMPANIES_DATA = [
        '59' => [
            'name' => 'Hoeffner',
            'brochureTitle' => 'Höffner: Möbelangebote',
        ],
        '156' => [
            'name' => 'Sconto',
            'brochureTitle' => 'SCONTO: Prospekt',
        ],
    ];

    private const DATE_REGEX = '#(\d{2}\.\d{2}\.\d{0,2})\s*\-\s*(\d{2}\.\d{2}\.\d{2,4})#';

    public function __construct()
    {
        parent::__construct();

        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->validityDateCol = self::DEFAULT_VALIDITY_COL;
    }

    /**
     * @throws Zend_Exception
     */
    public function crawl($companyId)
    {
        $this->companyData = self::COMPANIES_DATA[$companyId];
        $emailService = new Marktjagd_Service_Transfer_Email();

        $emails = $emailService->generateEmailCollection($companyId, $this->companyData['name']);

        if (empty($emails->getElements())) {
            return $this->getSuccessResponse();
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($emails->getElements() as $email) {
            $hasBrochures = false;

            $attachments = $this->getAttachments($email);
            if (empty($attachments)) {
                $emailService->archiveMail($email);
                continue;
            }

            // (?:=\r\n)? checks for line break in the middle of the link
            $downloadLinkRegex = '#(https://cloud\.kriegerit\.de/index\.php/s/[0-9a-zA-Z]+(?:=\r\n)?[0-9a-zA-Z]*)#';
            if (!preg_match_all($downloadLinkRegex, $email->getText(), $downloadLinkMatch)) {
                $emailService->archiveMail($email);
                continue;
            }

            $brochureLists = [];
            foreach ($downloadLinkMatch[1] as $linkMatch) {
                $linkMatch = preg_replace('#=\\r\\n#', '', $linkMatch);
                if (!in_array($linkMatch, $this->downloadLinks)) {
                    $this->downloadLinks[] = $linkMatch;

                    $foundBrochures = $this->getBrochures($linkMatch . '/download', $companyId);
                    if (empty($foundBrochures)) {
                        throw new Exception('Company ID: ' . $companyId . ': No brochures found in: ' . $linkMatch);
                    }

                    $brochureLists = array_merge($brochureLists, $foundBrochures);
                }
            }

            foreach ($brochureLists as $category => $list) {
                $categoryToCheck = $category;
                $this->validityDateCol = self::DEFAULT_VALIDITY_COL;
                if (preg_match('#Streu_(\d)#', $category, $streuungsMatch)) {
                    $categoryToCheck = preg_replace('#[^_]+_([^_]+?)_.+#', 'H_$1', array_key_first($list));
                    $this->validityDateCol = 3 + (int)$streuungsMatch[1];
                }

                $xlsContent = $this->getAttachmentData($attachments, $categoryToCheck);

                $brochureData = $this->getValidity($xlsContent);
                if (empty($brochureData)) {
                    $this->_logger->err('Regular expression could not find valid dates on the Excel reference file');
                    continue;
                }

                $storesPerBrochure = [];
                foreach ($xlsContent as $key => $tableRow) {
                    // filter data out
                    $brochureNumber = $tableRow[$this->validityDateCol];
                    if (NULL == $brochureNumber || self::DEFAULT_VALIDITY_ROW == $key || preg_match('#Streuung - Version#', $brochureNumber)) {
                        continue;
                    }

                    $storeNumber = $tableRow[1];
                    if (is_object($tableRow[1])) {
                        $storeNumber = $this->getStoreNumberFromRichText($tableRow[1]);
                    }

                    $storesPerBrochure[$brochureNumber][] = $storeNumber;
                }

                foreach ($storesPerBrochure as $brochureNumber => $storeNumbers) {
                    $brochureData['storeNumber'] = implode(',', array_unique($storeNumbers));
                    $brochureData['brochureNumber'] = $brochureNumber;
                    $brochureData['brochurePath'] = $list[$brochureNumber];

                    if (empty($brochureData['brochurePath'])) {
                        $this->_logger->err('Company ID: ' . $companyId . ': No brochure "' . $brochureNumber . '" found!');
                    } else {
                        $brochure = $this->generateBrochure($brochureData);
                        $hasBrochures = $brochures->addElement($brochure) || $hasBrochures;
                    }
                }
            }

            if ($hasBrochures) {
                $emailService->archiveMail($email);
            }
        }

        return $this->getResponse($brochures);
    }

    private function getAttachments(Marktjagd_Entity_Email $email): array
    {
        if (NULL == $email->getLocalAttachmentPath()) {
            return [];
        }

        $attachments = [];
        foreach ($email->getLocalAttachmentPath() as $name => $path) {
            if (strpos(strtolower($name), '.xlsx') !== false) {
                $attachments[$name] = $path;
            }
        }

        return $attachments;
    }

    private function getBrochures(string $downloadUrl, int $companyId): array
    {
        $archiveService = new Marktjagd_Service_Input_Archive();

        $localPath = $this->ftp->generateLocalDownloadFolder($companyId);
        $localArchive = $this->downloadZipFile($localPath, $downloadUrl);

        if ($this->isPdf($localArchive)) {
            return [$localArchive];
        }

        if (!$localArchive || !$archiveService->unzip($localArchive, $localPath . 'brochures/')) {
            $this->_logger->err($companyId . ': unable to download and extract the archive: ' . $downloadUrl);
            return [];
        }

        return $this->getBrochuresMap($localPath . 'brochures/');
    }

    private function downloadZipFile(string $localPath, string $downloadUrl): string
    {
        $localArchive = $localPath . md5($downloadUrl) . '.zip';

        $fp = fopen($localArchive, 'w');

        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        $executed = curl_exec($ch);
        $responseInfo = curl_getinfo($ch);
        curl_close($ch);

        fclose($fp);

        if (!$executed || $responseInfo['http_code'] > 200) {
            unlink($localArchive);
            return '';
        }

        if ($this->isPdf($localArchive)) {
            $localArchive = $localPath . md5($downloadUrl) . '.pdf';
            rename($localPath . md5($downloadUrl) . '.zip', $localArchive);
        }

        return $localArchive;
    }

    private function getBrochuresMap(string $folder, string $folderName = ''): array
    {
        $brochures = [];
        foreach (scandir($folder) as $singleFile) {
            if (strpos($singleFile, '.') === 0) {
                continue;
            }

            if (is_dir($folder . $singleFile)) {
                $brochures = array_merge($brochures, $this->getBrochuresMap($folder . $singleFile . '/', $singleFile));
            } elseif (preg_match('#(.*)\.pdf$#', $singleFile, $nameMatch)) {
                $brochures[$folderName][$nameMatch[1]] = $this->ftp->generatePublicFtpUrl($folder . $singleFile);
            }
        }
        return $brochures;
    }

    private function getAttachmentData(array $attachments, string $title): array
    {
        if (1 === count($attachments)) {
            return $this->spreadsheetService->readFile(reset($attachments))->getElement(0)->getData();
        }

        if ($found = preg_grep('/^' . $title . '/i', array_keys($attachments))) {
            $attachmentKey = array_pop($found);
            return $this->spreadsheetService->readFile($attachments[$attachmentKey])->getElement(0)->getData();
        }

        return [];
    }

    private function getValidity(array $spreadsheetData): array
    {
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date();

        if (!preg_match(self::DATE_REGEX, $spreadsheetData[self::DEFAULT_VALIDITY_ROW][$this->validityDateCol], $dateMatch)) {
            return [];
        }

        return [
            'start' => $dateNormalizer->normalize($dateMatch[1]),
            'end' => $dateNormalizer->normalize($dateMatch[2])
        ];
    }

    private function getStoreNumberFromRichText(object $richTextObject): string
    {
        $richTextElements = $richTextObject->getRichTextElements();

        $storeNumber = '';
        foreach (array_reverse($richTextElements) as $richText) {
            $storeNumber = trim($richText->getText());

            if (!empty($storeNumber)) {
                break;
            }
        }

        return $storeNumber;
    }

    private function generateBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setTitle($this->companyData['brochureTitle'])
            ->setBrochureNumber($data['brochureNumber'])
            ->setUrl($data['brochurePath'])
            ->setStoreNumber($data['storeNumber'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($brochure->getStart())
            ->setVariety('leaflet');

        return $brochure;
    }

    private function isPdf(string $file): bool
    {
        return 'application/pdf' === mime_content_type($file) ?? false;
    }
}
