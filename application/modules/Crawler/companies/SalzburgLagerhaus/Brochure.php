<?php

/**
 * Brochure Crawler for SalzburgLagerhaus (ID: 73621)
 *
 * SalzburgLagerhaus/Brochure 73621
 */
class Crawler_Company_SalzburgLagerhaus_Brochure extends Crawler_Generic_Company
{
    private const EMAIL_LABEL_NAME = 'SalzburgLagerhaus';
    private const SEARCH_STRINGS_PATH = __DIR__ . "/../../../../../library/Marktjagd/Service/Python/SearchStringsInPdf.py";

    protected Marktjagd_Service_Transfer_Email $emailService;
    protected Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;
    private string $companyId;
    private ?string $fileWithClickouts = null;
    private $localPath;

    public function __construct()
    {
        parent::__construct();
        $this->emailService = new Marktjagd_Service_Transfer_Email();
        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    /**
     * @param string $companyId
     * @return Crawler_Generic_Response
     * @throws Zend_Exception
     */
    public function crawl($companyId)
    {
        $this->companyId = $companyId;
        $this->localPath = $this->ftp->connect($companyId, TRUE);
        $brochuresCollection = new Marktjagd_Collection_Api_Brochure();

        $pdf = $this->downloadPdf();
        if (null === $pdf) {
            $message = sprintf('Company ID: %s: No PDF found', $companyId);
            $this->_logger->err($message, Zend_Log::ERR);
            throw new Exception($message);
        }

        $emailCollection = $this->emailService->generateEmailCollection($companyId, self::EMAIL_LABEL_NAME);

        foreach ($emailCollection->getElements() as $email) {
            $archiveMail = false;
            $validity = $this->getValidity($email->getSubject());
            $weekNr = $this->getCalendarWeek($validity['startDate']);

            $this->readEmailAttachments($email);
            if (null === $this->fileWithClickouts) {
                $message = sprintf(
                    'Company ID: %s: No file with clickouts found in "%s"',
                    $companyId,
                    $email->getSubject()
                );
                $this->_logger->err($message, Zend_Log::ERR);
                continue;
            }
            $clickoutFile = $this->buildSearchClickoutFile($this->fileWithClickouts);

            $brochureData = $validity;
            $scriptPath = self::SEARCH_STRINGS_PATH;
            exec("python3 $scriptPath $pdf $clickoutFile", $output);

            $clickoutFiles = end($output);

            $brochureData['pdf'] = $pdf;
            if (file_exists($clickoutFiles)) {
                $links = $this->spreadsheetService->readFile($clickoutFiles, TRUE, ',')->getElement(0)->getData();
                $brochureData['pdf'] = $this->addClickouts($pdf, $links);
            }

            $brochureData['weekNr'] = $weekNr;
            $brochureData['brochureNumber'] = $this->formatBrochureNumber($weekNr, basename($pdf));

            $brochure = $this->createBrochure($brochureData);
            if ($brochuresCollection->addElement($brochure)) {
                $archiveMail = true;
            }

            if ($archiveMail) {
                $this->emailService->archiveMail($email);
            }
        }

        return $this->getResponse($brochuresCollection, $companyId);
    }

    /**
     * @throws Exception
     */
    private function getValidity(string $emailSubject): array
    {
        $pattern = '#([0-9]{2}\.[0-9]{2}\.)-([0-9]{2}\.[0-9]{2}\.)#';
        if (!preg_match($pattern, $emailSubject, $validityMatch)) {
            throw new Exception('Unable to get brochure validity');
        }

        $dateService = new Marktjagd_Service_DateNormalization_Date();

        return [
            'startDate' => $dateService->normalize($validityMatch[1]),
            'endDate' => $dateService->normalize($validityMatch[2]),
        ];
    }

    private function downloadPdf(): ?string
    {
        foreach ($this->ftp->listFiles() as $file) {
            if (preg_match('#\.pdf?#i', $file)) {
                return $this->ftp->downloadFtpToDir($file, $this->localPath);
            }
        }

        return null;
    }

    private function readEmailAttachments(Marktjagd_Entity_Email $email): void
    {
        foreach ($email->getLocalAttachmentPath() as $file) {
            if (preg_match('#\.xlsx?#i', $file)) {
                $this->fileWithClickouts = $file;
            }
        }
    }

    public function addClickouts(string $pdf, array $links): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();
        $pdfInfos = $pdfService->getAnnotationInfos($pdf);

        $height = $pdfInfos[0]->height;
        $width = $pdfInfos[0]->width;

        foreach ($links as $link) {
            $clickouts[] = [
                'page' => $link['pageNumber'] - 1,
                'height' => $height,
                'width' => $width,
                'startX' => $link['x'],
                'endX' => $link['x'] + 10,
                'startY' => $link['y'],
                'endY' => $link['y'] + 10,
                'link' => $link['link'],
            ];
        }

        $clickoutsFileName = $this->localPath . 'coordinatesLagerhausAt.json';
        file_put_contents($clickoutsFileName, json_encode($clickouts));

        return $pdfService->setAnnotations($pdf, $clickoutsFileName);
    }

    private function getCalendarWeek(string $startDate): string
    {
        $startDate = new DateTime($startDate);
        return $startDate->format('W');
    }

    /**
     * Build search string file for python script.
     * We need to have only two columns in file: search string and Link.
     **/
    private function buildSearchClickoutFile(string $fileWithClickouts): string
    {
        $fileName = $this->localPath . 'clickouts.csv';
        $clickoutFile = fopen($fileName, "w");
        $links = $this->spreadsheetService->readFile($fileWithClickouts, false, ';')->getElement(0)->getData();

        do {
            $row = array_shift($links);
        } while ($row[1] !== 'Seite');

        fputcsv($clickoutFile, ['A Nummer', 'Link final'], ';');
        foreach ($links as $link) {
            // Skip empty lines
            if (empty($link[2]) || empty($link[4])) {
                continue;
            }

            $newRow = [$link[2], $link[4]];
            fputcsv($clickoutFile, $newRow, ';');
        }

        return $fileName;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setTitle(self::EMAIL_LABEL_NAME . ' KW ' . $brochureData['weekNr'])
            ->setUrl($brochureData['pdf'])
            ->setVariety('leaflet')
            ->setStart($brochureData['startDate'])
            ->setEnd($brochureData['endDate'])
            ->setVisibleStart($brochureData['startDate'])
            ->setBrochureNumber($brochureData['brochureNumber']);

        return $brochure;
    }

    private function formatBrochureNumber(string $weekNr, string $filename): string
    {
        // Remove numbers and '.pdf' from the name
        $cleanName = preg_replace(['/[0-9]+/', '/\.pdf/'], '', $filename);

        $brochureNumber = sprintf('KW%s_%s', $weekNr, $cleanName);
        $brochureNumber = mb_substr($brochureNumber, 0, 32);

        return preg_replace('#\s+#', '_', $brochureNumber);
    }
}
