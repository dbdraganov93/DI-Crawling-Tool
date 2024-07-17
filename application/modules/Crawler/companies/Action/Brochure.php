<?php
/**
 * Brochure Crawler für Action DE, AT and ActionFr (ID: 71353, 73604, 80314)
 */

class Crawler_Company_Action_Brochure extends Crawler_Generic_Company
{
    private const DATE_FORMAT = 'd.m.Y';
    private const COMPANY_LOCALE_MAP = [
        71353 => 'de',
        73604 => 'at',
    ];
    private const UTM_PARAMS = [
        'de' => '?utm_source=offerista&utm_medium=folder&utm_campaign=Promo-dynamic-folder_awareness_display_offerista_de&utm_content=dynamic_folder',
        'at' => '?utm_source=wogibtwas&utm_medium=folder&utm_campaign=Promo-dynamic-folder_awareness_display_wogibtwas_at&utm_content=dynamic_folder',
        'de-presents' => '?utm_source=offerista&utm_medium=folder&utm_campaign=presents-niche-Q4-2023_consideration_folder_offerista_de&utm_content=presents_static_folder',
        'at-presents' => '?utm_source=wogibtwas&utm_medium=folder&utm_campaign=presents-niche-Q4-2023_consideration_folder_wogibtwas_at&utm_content=presents_static_folder',
    ];

    private string $week;
    private string $weekNr;
    private string $year;
    private string $locale;

    public function __construct()
    {
        parent::__construct();

        $timesService = new Marktjagd_Service_Text_Times();
        $this->week = ($timesService->isDateAhead(date(self::DATE_FORMAT, strtotime("this week tuesday")))) ? 'last' : 'this';
        $this->weekNr = $timesService->getWeekNr($this->week);
        $this->year = $timesService->getWeeksYear($this->week);
    }

    public function crawl($companyId)
    {
        $this->locale = self::COMPANY_LOCALE_MAP[$companyId];

        $brochures = new Marktjagd_Collection_Api_Brochure();

        $brochureData = $this->getBrochureData();
        if ($brochureData) {
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

//        $presentsBrochureData = $this->getBrochureData('presents');
//        if ($presentsBrochureData) {
//            $presentsBrochure = $this->createBrochure($presentsBrochureData);
//            $brochures->addElement($presentsBrochure);
//        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getBrochureData(string $type = ''): array
    {
        $url = $this->getBrochureUrl($type);

        $url = $this->addTracking($url, $type);
        if (!$url) {
            return [];
        }

        $start = date(self::DATE_FORMAT, strtotime("{$this->week} week wednesday"));
        $numberSuffix = ($type) ? '_' . $type : '';
        return [
            'start' => $start,
            'end' => date(self::DATE_FORMAT, strtotime("{$start} +6 days")) . " 23:59:59",
            'url' => $url,
            'number' => "ACTION_{$this->year}_KW{$this->weekNr}{$numberSuffix}"
        ];
    }

    private function getBrochureUrl(string $type = ''): string
    {
        $baseUrl = 'https://pdfs.wepublish.digital/action-' . $this->locale;
        $week = '-week-' . (int)$this->weekNr . '-' . $this->year;
        $pdfString = 'action-' . $this->locale . $week;
        if ($type) {
            $baseUrl .= '-' . $type;
            $pdfString = 'action-' . $type . '-' . $this->locale . '-adp' . $week;
        }

        return "{$baseUrl}/{$pdfString}.pdf";
    }

    private function addTracking(string $url, string $type = ''): string
    {
        $http = new Marktjagd_Service_Transfer_Http();
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $tmpFilePath = APPLICATION_PATH . '/../public/files/tmp/';
        $localBrochure = $http->getRemoteFile($url, $tmpFilePath);
        if (!$localBrochure) {
            $this->_logger->err('Could not download brochure: ' . $url);
            return '';
        }

        # add our tracking to the existing clickout links;
        $annotationInfos = $pdfService->getAnnotationInfos($localBrochure);
        $annotations = [];
        foreach($annotationInfos as $annotation) {
            if(!$annotation->subtype == 'Link')
                continue;

            $utmParams = self::UTM_PARAMS[$this->locale];
            if ($type && isset(self::UTM_PARAMS[$this->locale . '-' . $type])) {
                $utmParams = self::UTM_PARAMS[$this->locale . '-' . $type];
            }
            $annotation->url .= strpos($annotation->url, '?') === FALSE? $utmParams : str_replace('?', '&', $utmParams);
            $annotations[] = [
                'width' => $annotation->width,
                'height' => $annotation->height,
                'page' => $annotation->page,
                'startX' => $annotation->rectangle->startX,
                'startY' => $annotation->rectangle->startY,
                'endX' => $annotation->rectangle->endX,
                'endY' => $annotation->rectangle->endY,
                'maxX' => $annotation->maxX,
                'maxY' => $annotation->maxY,
                "link" => $annotation->url
            ];
        }

        $pdfService->cleanAnnotations($localBrochure);
        $jsonFile = $tmpFilePath .'clickouts.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($annotations));
        fclose($fh);

        # add the JSON elements to the pdf template and return the file path
        return $pdfService->setAnnotations($localBrochure, $jsonFile);
    }

    private function createBrochure(array $data): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle('Action: Kleine Preise, große Freude')
            ->setUrl($data['url'])
            ->setBrochureNumber($data['number'])
            ->setStart($data['start'])
            ->setEnd($data['end'])
            ->setVisibleStart($data['start'])
            ->setVariety('leaflet');
    }
}
