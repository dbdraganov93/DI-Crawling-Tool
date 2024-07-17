<?php

/*
 * Brochure Crawler for Kaufland RO (ID: 80347)
 *
 * KauflandRo/Brochure 80347
 */

class Crawler_Company_KauflandRo_Brochure extends Crawler_Generic_Company
{
    private const WEEK = 'next';
    private const STORE_MAP_FILE = '1lKHM6TmyHHUAh1q4tcNtVPbCY3j2ldqxkML2EkvLWew';
    private const BROCHURE_TITLE = 'Catalog Kaufland până în data de %s';
    private const BASE_CHANNEL = 'Ofertolino';
    private const DATE_FORMAT = 'd.m.Y';
    protected Marktjagd_Service_Input_GoogleSpreadsheetRead $googleReader;
    protected Marktjagd_Service_Output_Pdf $pdf;
    protected Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    protected Marktjagd_Service_Text_Times $times;
    protected string $localPath;
    private array $links;
    private array $channels;

    public function __construct()
    {
        parent::__construct();
        $this->googleReader = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->pdf = new Marktjagd_Service_Output_Pdf();
        $this->times = new Marktjagd_Service_Text_Times();
        $this->kw = $this->_weekNr = $this->times->getWeekNr(self::WEEK);
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->links = [];
        $this->channels = [];
    }

    public function crawl($companyId)
    {
        $this->localPath = $this->ftp->connect($companyId);
        $stores = $this->getStores();

        $this->readLinksSheet();

        $brochures = new Marktjagd_Collection_Api_Brochure();
        $brochuresData = $this->getBrochuresData($stores);
        if (!$brochuresData) {
            $message = "No pdf-files on Google Drive for KW $this->kw";
            $this->_logger->err($message);
            throw new Exception($message);
        }

        foreach ($brochuresData as $brochureData) {
            $brochure = $this->addBrochures($brochureData);
            $brochures->addElement($brochure, TRUE);
        }
        return $this->getResponse($brochures, $companyId);
    }

    protected function getStores(): array
    {
        $stores = [];
        $storesData =  $this->googleReader->getFormattedInfos(self::STORE_MAP_FILE, 'A1', 'C', 'stores');
        $storeNumber = '';
        foreach ($storesData as $storeData) {
            if (!empty($storeData['brochure'])) {
                $storeNumber = $storeData['brochure'];
            }
            $stores[$storeNumber][] = $storeData['stores'];
        }

        return $stores;
    }

    protected function readLinksSheet(): void
    {
        $spreadsheetLinks = $this->googleReader->getFormattedInfos(self::STORE_MAP_FILE, 'A1', 'C', 'links');
        foreach ($spreadsheetLinks as $link) {
            $this->channels[] = $link['Channel'];
            $this->links[$link['Channel']] = $link;
        }
    }

    private function getBrochuresData(array $stores): ?array
    {
        $allBrochuresData = [];
        foreach ($this->ftp->listFiles() as $fileName) {
            if (!preg_match("#RO$this->kw[^\.]*\.pdf$#", $fileName)) {
                continue;
            }

            $brochurePdf = $this->ftp->downloadFtpToDir($fileName, $this->localPath);
            $nameParts = explode('_', $fileName);
            $storeNumber = $nameParts[1];

            if (empty($stores[$storeNumber])) {
                $this->_logger->notice("No stores found for brochure $fileName");
                continue;
            }

            foreach ($this->channels as $channel) {
                $brochureData = $this->buildBrochureData($channel, $brochurePdf, $nameParts, $stores[$storeNumber]);
                $allBrochuresData[] = $brochureData;
            }
        }

        return $allBrochuresData;
    }

    private function addPagesLink(string $pdfTemplate, string $link): string
    {
        $infos = $this->pdf->getAnnotationInfos($pdfTemplate);

        $newPdfLinks = [];
        foreach ($infos as $info) {
            $newPdfLinks[] = [
                'width' => $info->width,
                'height' => $info->height,
                'page' => $info->page,
                'startX' => 0,
                'startY' => 0,
                'endX' => $info->width,
                'endY' => $info->height,
                'maxX' => $info->maxX,
                'maxY' => $info->maxY,
                "link" => $link,
            ];
        }

        $jsonFile = APPLICATION_PATH . '/../public/files/template_' . date('dmYHim') . '.json';
        file_put_contents($jsonFile, json_encode($newPdfLinks));

        return  $this->pdf->setAnnotations($pdfTemplate, $jsonFile);
    }

    private function addBrochures(array $brochure): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        return $eBrochure->setUrl($brochure['url'])
            ->setTitle($brochure['title'])
            ->setStoreNumber($brochure['store'])
            ->setVariety('leaflet')
            ->setStart($brochure['validFrom'])
            ->setEnd($brochure['validTo'])
            ->setVisibleStart($brochure['visibleFrom'])
            ->setVisibleEnd($brochure['validTo'])
            ->setBrochureNumber($brochure['brochureNumber'])
            ->setTrackingBug($brochure['trackingUrl']);
    }

    private function buildBrochureData(string $channel, string $file, array $nameParts, array $store): array
    {
        $storeNumber = $nameParts[1];

        $brochureFile = $this->getBrochureFile($file, $channel, $storeNumber);

        $validFrom = date(self::DATE_FORMAT, strtotime($nameParts[2]));

        $brochureNumber = sprintf('%s_%s_%s', $this->_weekNr, $validFrom, $storeNumber);
        if (self::BASE_CHANNEL !== $channel) {
            $brochureNumber = $channel . '_' . $brochureNumber;
        }

        return [
            'url' => $this->addPagesLink($brochureFile, $this->links[$channel]['pageLink']),
            'store' => implode(',', $store),
            'title' => sprintf(self::BROCHURE_TITLE, date(self::DATE_FORMAT, strtotime($nameParts[3]))),
            'validFrom' => $validFrom,
            'validTo' => date(self::DATE_FORMAT, strtotime($nameParts[3] . '- 1 day')) . ' 23:59:59',
            'visibleFrom' => date(self::DATE_FORMAT, strtotime($nameParts[2] . '- 1 day')),
            'brochureNumber' => $brochureNumber,
            'trackingUrl' => $this->links[$channel]['trackingPixel'],
        ];
    }

    private function getBrochureFile(string $file, string $channel, $storeNumber): string
    {
        if (self::BASE_CHANNEL === $channel) {
            return $file;
        }

        $copiedFile = sprintf('%s%s%s.pdf',$this->localPath, $channel, $storeNumber);
        copy($file, $copiedFile);

        return $copiedFile;
    }
}
