<?php

/**
 * Brochure Crawler for Lidl CZ (ID: 81451)
 *
 * LidlCz/Brochure 81451
 */
class Crawler_Company_LidlCz_Brochure extends Crawler_Generic_Company
{
    const SPREADSHEET_ID = '1gO6kdE2TDfCBhzZYIDUqQNIe75Niyvgw0X0QetnXjUY';

    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleReader;
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftpService;
    private Marktjagd_Service_Output_Pdf $pdfService;
    private string $localPath;
    private ?string $startDate = null;
    private ?string $endDate = null;

    public function __construct()
    {
        parent::__construct();
        $this->googleReader = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->ftpService = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->pdfService = new Marktjagd_Service_Output_Pdf();
    }

    /**
     * @param $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $this->ftpService->connect($companyId);
        $this->localPath = $this->ftpService->generateLocalDownloadFolder($companyId);
        $leafletsMapping = $this->getLeafletsMapping($companyId);

        $brochuresCollection = new Marktjagd_Collection_Api_Brochure();
        $brochuresData = $this->googleReader->getFormattedInfos(self::SPREADSHEET_ID, 'A1', 'G');

        foreach ($brochuresData as $brochureRow) {
            $brochurePdf = $leafletsMapping[$brochureRow['Leaflet Name in the PDF']];
            copy($brochurePdf, $brochureRow['Number']. '.pdf');

            $brochure = $this->createBrochure(
                $brochureRow,
                $this->addFullpageClickout($brochurePdf, $brochureRow['Clickout Link'])
            );
            $brochuresCollection->addElement($brochure);
        }

        return $this->getResponse($brochuresCollection, $companyId);
    }

    private function getStartDate(string $period): string
    {
        if (null === $this->startDate) {
            $this->setValidityDates($period);
        }

        return $this->startDate;
    }

    private function getEndDate(string $period): string
    {
        if (null === $this->endDate) {
            $this->setValidityDates($period);
        }

        return $this->endDate;
    }

    private function setValidityDates(string $period): void
    {
        $dateNormalizationService = new Marktjagd_Service_DateNormalization_Date();

        $periodParts = explode('-', $period);
        $this->startDate = $dateNormalizationService->normalize($periodParts[0]);
        $this->endDate = $dateNormalizationService->normalize($periodParts[1]);
    }

    private function addFullpageClickout(string $pdfFile, string $link): string
    {
        $pages = $this->pdfService->getAnnotationInfos($pdfFile);

        $links = [];
        foreach ($pages as $page) {
            $links[] = [
                'width' => $page->width,
                'height' => $page->height,
                'page' => $page->page,
                'startX' => 0,
                'startY' => 0,
                'endX' => $page->width,
                'endY' => $page->height,
                'maxX' => $page->maxX,
                'maxY' => $page->maxY,
                "link" => $link,
            ];
        }

        $jsonFile = APPLICATION_PATH . '/../public/files/template_' . date('dmYHim') . '.json';
        file_put_contents($jsonFile, json_encode($links));

        return  $this->pdfService->setAnnotations($pdfFile, $jsonFile);
    }

    private function getLeafletsMapping(int $companyId): array
    {
        $leafletsMapping = [];
        $pattern = '#^/\d+/.*\d{1,2}_\d{1,2}_\d{1,2}_\d{1,2}_(CT|NF|PO)_.*.pdf$#';
        foreach ($this->ftpService->listFiles('/' . $companyId) as $fileName) {
            if (preg_match($pattern, $fileName, $matches)) {
                $brochurePdf = preg_replace('#^/\d+/#', '', $fileName);
                $leafletsMapping[$matches[1]] = $this->ftpService->downloadFtpToDir($brochurePdf, $this->localPath);
            }
        }

        return $leafletsMapping;
    }

    private function createBrochure($brochureRow, string $pathToPdfWithClickouts): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setTitle($brochureRow['Title'])
            ->setUrl($pathToPdfWithClickouts)
            ->setBrochureNumber($brochureRow['Number'])
            ->setStart($this->getStartDate($brochureRow['Period']))
            ->setEnd($this->getEndDate($brochureRow['Period']))
            ->setVisibleStart($this->getStartDate($brochureRow['Period']))
            ->setStoreNumber($brochureRow['Store Number']);

        return $brochure;
    }
}
