<?php

/**
 * Brochure crawler for Möbel Martin (ID: 81148)
 */
class Crawler_Company_MobelMartin_Brochure extends Crawler_Generic_Company
{
    private const REGEX_VALIDITY_FROM_PDF = '#VOM\s(\d{2})\.(\d{2})\.(\d{2,4})?\s(?:.*)BIS\s(?:ZUM\s)?(\d{2})\.(\d{2})\.(\d{2,4})?#';

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;
    private Marktjagd_Service_Output_Pdf $pdfService;
    private Marktjagd_Service_DateNormalization_Date $dateNormalization;

    private string $localPath;

    public function __construct()
    {
        parent::__construct();

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->pdfService = new Marktjagd_Service_Output_Pdf();
        $this->dateNormalization = new Marktjagd_Service_DateNormalization_Date();
    }

    public function crawl($companyId)
    {
        $this->localPath = $this->ftp->connect($companyId, TRUE);

        $brochureFiles = [];
        $csvFiles = [];
        $xlsxFile = '';
        foreach ($this->ftp->listFiles() as $singleFile) {
            if (preg_match('#(.*)\.pdf#', $singleFile, $matchedTitle)) {
                $brochureFiles[$matchedTitle[1]] = $this->ftp->downloadFtpToDir($singleFile, $this->localPath);
            }
            else if (preg_match('#(.*)\.csv#', $singleFile, $matchedTitle)) {
                $csvFiles[$matchedTitle[1]] = $this->ftp->downloadFtpToDir($singleFile, $this->localPath);
            }
            else if (preg_match('#(.*)\.xlsx#', $singleFile, $matchedTitle)) {
                $xlsxFile = $this->ftp->downloadFtpToDir($singleFile, $this->localPath);
            }
        }
        $this->ftp->close();

        $validities = [];
        if (!empty($xlsxFile)) {
            $validities = $this->getValiditiesFromFile($xlsxFile);
        }

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($csvFiles as $csvName => $csvPath) {
            if (!isset($brochureFiles[$csvName.'_BK'])) {
                $this->_logger->err($companyId . ': There is no brochure ' . $csvName.'_BK.pdf');
                continue;
            }

            $brochureUrl = $brochureFiles[$csvName.'_BK'];

            $brochureData = $validities[$csvName] ?? $this->getValidityFromPdf($brochureUrl);
            if (empty($brochureData)) {
                $this->_logger->err($companyId . ': There is no validity for ' . $csvName);
                continue;
            }

            $coordsFile = $this->getClickoutsFromCsv($csvPath, $brochureUrl);

            $brochureData['url'] = $this->pdfService->setAnnotations($brochureUrl, $coordsFile);
            $brochureData['number'] = $csvName;

            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getValiditiesFromFile(string $xlsxFile): array
    {
        $fileData = $this->spreadsheetService->readFile($xlsxFile)->getElement(0)->getData();

        $validities = [];
        foreach ($fileData as $brochureDetails) {
            if (!strlen($brochureDetails[3]) || 'Laufzeit-Anfang' === $brochureDetails[3]) {
                continue;
            }

            $validities[$brochureDetails[1]] = [
                'start' => $this->dateNormalization->normalize($brochureDetails[3]),
                'end' => $this->dateNormalization->normalize($brochureDetails[4])
            ];
        }

        return $validities;
    }

    private function getClickoutsFromCsv(string $csvPath, string $brochureUrl): string
    {
        $brochurePagesInfo = $this->pdfService->getAnnotationInfos($brochureUrl);
        $trackingData = $this->spreadsheetService->readFile($csvPath, TRUE)->getElement(0)->getData();

        $clickouts = [];
        foreach ($trackingData as $rowData) {
            $page = (int)$rowData['page'] - 1;
            $pageWidth = $brochurePagesInfo[$page]->width;
            $pageHeight = $brochurePagesInfo[$page]->height;
            $startX = $pageWidth * $rowData['left'];
            $startY = $pageHeight - ($pageHeight * $rowData['top']);
            $clickouts[] = [
                'page' => $page,
                'height' => $pageHeight,
                'width' => $pageWidth,
                'startX' => $startX,
                'endX' => $startX + ($pageWidth * $rowData['width']),
                'startY' => $startY,
                'endY' => $startY + ($pageHeight * $rowData['height']),
                'link' => $rowData['urlAttribute']
            ];
        }

        $clickoutsFileName = $this->localPath . 'coordinates_' . md5($csvPath) . '.json';
        $fh = fopen($clickoutsFileName, 'w+');
        fwrite($fh, json_encode($clickouts));
        fclose($fh);

        return $clickoutsFileName;
    }

    private function getValidityFromPdf(string $brochureUrl): array
    {
        $extractedText = $this->pdfService->extractText($brochureUrl);

        if (preg_match(self::REGEX_VALIDITY_FROM_PDF, $extractedText, $matchedValidity)) {
            return [
                'start' => $this->dateNormalization->normalize($matchedValidity[1] . '.' . $matchedValidity[2] . '.' . $matchedValidity[3]),
                'end' => $this->dateNormalization->normalize($matchedValidity[4] . '.' . $matchedValidity[5] . '.' . $matchedValidity[6])
            ];
        }

        return [];
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setUrl($data['url'])
            ->setTitle('Möbel Martin: Wochenangebote') // get the title from the previous brochures in the BT
            ->setBrochureNumber($data['number'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($brochure->getStart())
            ->setVariety('leaflet');
    }
}
