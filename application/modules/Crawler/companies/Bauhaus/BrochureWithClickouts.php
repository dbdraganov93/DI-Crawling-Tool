<?php
/**
 * Brochure Crawler for Bauhaus (ID: 577)
 */
class Crawler_Company_Bauhaus_BrochureWithClickouts extends Crawler_Generic_Company
{

    private const REGEX_CLICKOUTS = '#window\.staticSettings\s*=\s*({.*?});#';
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private Marktjagd_Service_Text_Url $urlService;
    private array $campaignData;
    private string $localPath;

    public function __construct()
    {
        parent::__construct();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->urlService = new Marktjagd_Service_Text_Url();
        $spreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->campaignData = $spreadsheet->getCustomerData('bauhausClickouts');
    }

    public function crawl($companyId)
    {
        $brochures = new Marktjagd_Collection_Api_Brochure();

        $clickoutLinks = $this->getClickoutLinks();
        $clickouts = $this->getClickouts($clickoutLinks);

        $pdf = $this->downloadFile($companyId);
        $pdfWithLinks = $this->addClickouts($clickouts, $pdf);
        $brochureData = $this->getBrochureData($pdfWithLinks);
        $brochure = $this->createBrochure($brochureData);
        $brochures->addElement($brochure);

        return $this->getResponse($brochures, $companyId);
    }

    /**
     * add clickouts to the PDF file.
     */
    private function addClickouts(array $clickouts, string $pdf): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $annotationInfos = $pdfService->getAnnotationInfos($pdf);
        $annotationInfo = reset($annotationInfos);
        $annotations = [];

        foreach ($clickouts as $annotation) {
            $url = $this->urlService->removeParameters($annotation->url);
            $url = $this->urlService->addParameters($url, ['cid' => $this->campaignData['cid']]);

            $startX = $annotation->x * $annotationInfo->width;
            $startY = $annotationInfo->height - $annotation->y * $annotationInfo->height;

            $annotations[] = [
                'width' => $annotationInfo->width,
                'height' => $annotationInfo->height,
                'page' => $annotation->pageIndex,
                'startX' => $startX,
                'startY' => $startY,
                'endX' => $startX + 5,
                'endY' => $startY + 5,
                'maxX' => $annotationInfo->maxX,
                'maxY' => $annotationInfo->maxY,
                "link" => $url
            ];
        }

        $pdfService->cleanAnnotations($pdf);
        $jsonFile = $this->localPath . 'clickouts.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($annotations));
        fclose($fh);

        # add the JSON elements to the pdf template and return the file path
        return $pdfService->setAnnotations($pdf, $jsonFile);
    }

    /**
     * download the PDF file from the FTP server.
     */
    private function downloadFile(int $companyId): string
    {
        $this->localPath = $this->ftp->connect($companyId, TRUE);
        $pdf = $this->ftp->downloadFtpToDir($this->campaignData['pdfName'], $this->localPath);
        $this->ftp->close();
        if (false === $pdf) {
            throw new Exception("unable to download PDF file for campaign: {$this->campaignData['brochureName']} SKIPPING CAMPAIGN");
        }

        return $pdf;
    }

    /**
     * get all clickout links from the brochure page.
     */
    private function getClickoutLinks(): ?array
    {
        $matches = [];
        $chunkUrls = [];
        $html = file_get_contents($this->campaignData['brochureUrl']);

        if (preg_match(self::REGEX_CLICKOUTS, $html, $matches)) {
            $staticSettingsJSON = $matches[1];
            $staticSettingsData = json_decode($staticSettingsJSON, true);
            $chunkUrls = $staticSettingsData['enrichments']['chunkUrls'] ?: [];
        }

        return $chunkUrls;
    }

    /**
     * get all clickouts from several json links.
     */
    private function getClickouts(array $clickoutLinks): ?array
    {
        $clickouts = [];
        foreach ($clickoutLinks as $clickoutLink) {
            $clickout = json_decode(file_get_contents($clickoutLink));

            if (empty($clickout->enrichments)) {
                continue;
            }

            $clickouts = array_merge($clickouts, $clickout->enrichments);
        }

        return $clickouts;
    }

    private function getBrochureData(string $pdf): array
    {
        $brochureData = [];
        $brochureData['url'] = $pdf;
        $brochureData['title'] = $this->campaignData['title'];
        $brochureData['brochureNumber'] = $this->campaignData['brochureNumber'];
        $brochureData['validFrom'] = $this->campaignData['validFrom'];
        $brochureData['validTo'] = $this->campaignData['validTo'];

        return $brochureData;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setUrl($brochureData['url'])
            ->setBrochureNumber($brochureData['brochureNumber'])
            ->setTitle($brochureData['title'])
            ->setStart($brochureData['validFrom'])
            ->setEnd($brochureData['validTo'])
            ->setVisibleStart($brochure->getStart())
            ->setVariety('leaflet');

        return $brochure;
    }
}
