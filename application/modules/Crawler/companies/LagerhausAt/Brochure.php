<?php
ini_set('memory_limit', '1024M');

/**
 * Brochure Crawler für Lagerhaus AT (ID: 73029)
 */
class Crawler_Company_LagerhausAt_Brochure extends Crawler_Generic_Company
{
    private const EMAIL_LABEL_NAME = 'LagerhausAt';
    private const SEARCH_STRINGS_PATH = __DIR__ . "/../../../../../library/Marktjagd/Service/Python/SearchStringsInPdf.py";

    protected array $stores;
    protected array $zipcodes;
    protected string $localPath;
    protected Marktjagd_Service_Transfer_Email $emailService;
    protected Marktjagd_Service_Output_Pdf $pdfService;
    protected Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    protected Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;

    public function __construct()
    {
        parent::__construct();
        $this->emailService = new Marktjagd_Service_Transfer_Email();
        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->pdfService = new Marktjagd_Service_Output_Pdf();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;
        $this->localPath = $this->ftp->connect($companyId, TRUE);

        $pdfs = $this->downloadPdfs();

        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $this->stores = $api->findAllStoresForCompany($companyId);
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $emails = $this->emailService->generateEmailCollection($companyId, self::EMAIL_LABEL_NAME);
        foreach ($emails->getElements() as $eEmail) {
            $archiveMail = false;
            $validity = $this->getValidity($eEmail);
            $weekNr = $this->getCalendarWeek($validity['startDate']);
            $this->readZipcode($eEmail);
            $fileWithClickouts = $this->getLinksFile($eEmail);
            if ('' == $fileWithClickouts) {
                $this->_logger->err('Company ID: ' . $companyId . ': No file with clickouts found in "' . $eEmail->getSubject() . '"', Zend_Log::ERR);
                continue;
            }
            $clickoutFile = $this->buildSearchClickoutFile($fileWithClickouts);

            foreach ($pdfs as $name => $pdf) {
                $brochureData = $validity;
                $scriptPath = self::SEARCH_STRINGS_PATH;
                exec("python3 $scriptPath $pdf $clickoutFile", $output);

                $clickoutFiles = end($output);

                if (file_exists($clickoutFiles)) {
                    $links = $this->spreadsheetService->readFile($clickoutFiles, TRUE, ',')->getElement(0)->getData();
                    $brochureData['pdf'] = $this->addClickout($pdf, $links);
                } else {
                    $brochureData['pdf'] = $pdf;
                }

                $brochureData['zipcodes'] = $this->zipcodes[$name] ?: [];
//                $brochureData['storeIds'] = $this->getPdfStoreIds($name);
                $brochureData['brochureNumber'] = mb_substr('KW' . $weekNr . '_' . preg_replace(['/[0-9]+/', '/\.pdf/'], '', $name), 0, 32);

                $brochure = $this->createBrochure($brochureData);
                if ($brochures->addElement($brochure)) {
                    $archiveMail = true;
                }
            }

            if ($archiveMail) {
                $this->emailService->archiveMail($eEmail);
            }
        }

        return $this->getResponse($brochures, $companyId);
    }

    /**
     * @throws Exception
     */
    private function getValidity(Marktjagd_Entity_Email $email): array
    {
        $pattern = '#([0-9]{2}\.[0-9]{2}\.)\s*\-\s*([0-9]{2}\.[0-9]{2}\.[0-9]{2,4})#';
        if (!preg_match($pattern, $email->getSubject(), $validityMatch)) {
            throw new Exception('Unable to get brochure validity');
        }

        return [
            'startDate' => $validityMatch[1] . date('Y'),
            'endDate' => $validityMatch[2],
        ];
    }

    private function downloadPdfs(): array
    {
        $pdfs = [];
        foreach ($this->ftp->listFiles() as $file) {
            if (preg_match('#\.pdf?#i', $file)) {
                $pdfs[preg_replace('#\s+#', '_', $file)] = $this->ftp->downloadFtpToDir($file, $this->localPath);
            }
        }

        return $pdfs;
    }

    private function readZipcode(Marktjagd_Entity_Email $email): void
    {
        foreach ($email->getLocalAttachmentPath() as $file) {
            if (preg_match('#\.xlsx?#i', $file)) {
                $zipcodeData = $this->spreadsheetService->readFile($file, true)->getElement(0)->getData();

                $pdfNameColumn = $this->getPDFNameColumn($zipcodeData);
                if (empty($pdfNameColumn)) {
                    $this->_logger->err('Company ID: ' . $this->companyId . ': No PDF name column found in "' . $file . '"', Zend_Log::ERR);
                    continue;
                }

                foreach ($zipcodeData as $zipcode) {
                    $this->zipcodes[preg_replace('#\s+#', '_', $zipcode[$pdfNameColumn])][] = $zipcode['PLZ'];
                }
            }
        }
    }

    private function getPDFNameColumn(array $zipcodeData): string
    {
        if (isset($zipcodeData['Mutation'])) {
            return 'Mutation';
        } elseif (isset($zipcodeData['Layout'])) {
            return 'Layout';
        }

        foreach (reset($zipcodeData) as $key => $value) {
            if (preg_match('/\.pdf$/', $value)) {
                return $key;
            }
        }

        return '';
    }

    private function getLinksFile(Marktjagd_Entity_Email $email): ?string
    {
        $linksFile = '';
        foreach ($email->getLocalAttachmentPath() as $file) {
            if (preg_match('#\.csv#i', $file)) {
                $linksFile = $file;
                break;
            }
        }

        return $linksFile;
    }

    private function getPdfStoreIds(string $pdfName): array
    {
        $pdfStoreIds = [];
        if (empty($this->zipcodes[$pdfName]) && empty($this->zipcodes[str_replace('.pdf', '', $pdfName)])) {
            return [];
        }

        $pdfZipcode = $this->zipcodes[$pdfName] ?: $this->zipcodes[str_replace('.pdf', '', $pdfName)];
        foreach ($pdfZipcode as $zipcode) {
            $stores = array_filter($this->stores, function ($store) use ($zipcode) {
                return $zipcode == $store['zipcode'];
            });

            foreach ($stores as $store) {
                $pdfStoreIds[] = $store['number'];
            }
        }

        return $pdfStoreIds;
    }

    public function addClickout(string $pdf, array $links): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();
        $pdfInfos = $pdfService->getAnnotationInfos($pdf);

        $height = $pdfInfos[0]->height;
        $width = $pdfInfos[0]->width;

        foreach ($links as $link) {
            $aCoordsToLink[] = [
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

        $coordFileName = $this->localPath . 'coordinatesLagerhausAt.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $pdfService->setAnnotations($pdf, $coordFileName);
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

        fputcsv($clickoutFile, ['A Nummer', 'Link final'], ';');
        foreach ($links as $link) {
            // Skip empty lines
            if (empty($link[11]) || 'Link' == $link[11]) {
                continue;
            }
            $newRow = [$link[5], $link[11]];
            fputcsv($clickoutFile, $newRow, ';');
        }

        fclose($clickoutFile);

        return $fileName;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setTitle('Wochen Angebote')
            ->setUrl($brochureData['pdf'])
            ->setVariety('leaflet')
            ->setStart($brochureData['startDate'])
            ->setEnd($brochureData['endDate'])
            ->setVisibleStart($brochureData['startDate'])
            ->setBrochureNumber(preg_replace('#\s+#', '_', $brochureData['brochureNumber']))
            ->setZipCode(implode(',', $brochureData['zipcodes']));

        return $brochure;
    }
}
