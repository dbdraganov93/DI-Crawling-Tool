<?php

/**
 * Brochure Crawler für Real (ID: 15)
 */
class Crawler_Company_Real_Brochure extends Crawler_Generic_Company
{
    private const SEARCH_URL = 'http://shared.real.de/blaetterkatalog/webservice/?pid=lDH4vB&week=';
    private const AUTH_USER = 'bk-marktjagd';
    private const AUTH_PASS = ',DAZ8RaAqg8}fFNVO';
    private const DATE_FORMAT = 'Y-m-d';
    private const TRACKING_BUG = 'https://ad.doubleclick.net/ddm/trackimp/N486201.1824599MARKTJAGD.DE/B11524130.153321731;dc_trk_aid=323333377;dc_trk_cid=83018466;ord=%%CACHEBUSTER%%;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=?';
    private const TRACKING_PARAMS = 'utm_source=marktjagd&utm_medium=cpc&utm_campaign=prospect&utm_content=';
    private const REGEX_PDF = '#pdf$#';
    private const REGEX_BROCHURE_TITLE = 'Prospekt\s*Woche\s*';
    private const WEEK = 'next';

    private Marktjagd_Service_Transfer_Http $http;
    private Marktjagd_Service_Input_Page $pageService;
    private Marktjagd_Service_Text_Times $timesService;

    private string $weekNr;
    private string $weekYear;
    private string $localPath;
    private DateTime $currentDate;

    public function __construct()
    {
        parent::__construct();

        $this->http = new Marktjagd_Service_Transfer_Http();
        $this->pageService = new Marktjagd_Service_Input_Page();
        $this->currentDate = new DateTime();
        $this->timesService = new Marktjagd_Service_Text_Times();
    }

    public function crawl($companyId)
    {
        $this->localPath = $this->http->generateLocalDownloadFolder($companyId);

        $this->weekNr = $this->timesService->getWeekNr(self::WEEK);
        $this->weekYear = $this->timesService->getWeeksYear(self::WEEK);
        $previousWeekTimestamp = strtotime(  self::WEEK . ' week -1 week');
        $weeksList = [
            [
                'date' => date('W', $previousWeekTimestamp) . '-' . date('Y', $previousWeekTimestamp),
                'tracking' => self::TRACKING_PARAMS . $this->timesService->getWeeksYear() . '_kw' . date('W')
            ],
            [
                'date' => $this->weekNr . '-' . $this->weekYear,
                'tracking' => self::TRACKING_PARAMS . $this->weekYear . '_kw' . $this->weekNr
            ]
        ];

        $addedBrochures = $this->getAddedBrochuresData($companyId);

        $brochuresCollection = new Marktjagd_Collection_Api_Brochure();
        foreach ($weeksList as $singleWeek) {
            $this->pageService->open(self::SEARCH_URL . $singleWeek['date']);
            $brochuresJson = $this->pageService->getPage()->getResponseAsJson();
            foreach ($brochuresJson as $brochureType) {
                foreach ($brochureType as $brochureData) {
                    if (!preg_match('#Prospekt\s*Woche#', $brochureData->name) && $brochureData->pages < 16
                        || !preg_match(self::REGEX_PDF, $brochureData->url)
                        || in_array($this->generateHashFromObject($brochureData), $addedBrochures['hashes'])
                    ) {
                        continue;
                    }

                    $brochureFileData = $this->setAnnotations($brochureData, $singleWeek, $companyId);

                    if (empty($brochureFileData)
                        || !strlen(trim($brochureFileData['url']))
                        || !preg_match(self::REGEX_PDF, $brochureFileData['url'])
                    ) {
                        continue;
                    }

                    $newBrochureHash = $this->generateHashFromObject($brochureData);

                    if (!in_array($newBrochureHash, $addedBrochures['hashes'])) {
                        $brochure = $this->generateBrochure($brochureData, $brochureFileData, $singleWeek['date']);

                        if (isset($aAddedBrochuresData['customer_magazines'][$brochure->getTitle()])) {
                            $brochure->setBrochureNumber($aAddedBrochuresData['customer_magazines'][$brochure->getTitle()]);
                        }

                        $brochuresCollection->addElement($brochure);
                        $addedBrochures['hashes'][] = $newBrochureHash;
                    }
                }
            }
        }

        if (!count($brochuresCollection->getElements()) && $addedBrochures['weekly']) {
            $crawlerResponse = new Crawler_Generic_Response();

            $crawlerResponse->setIsImport(false)
                ->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

            return $crawlerResponse;
        }

        return $this->getResponse($brochuresCollection, $companyId);
    }

    private function getAddedBrochuresData(int $companyId): array
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $weeklyBrochures = FALSE;
        $addedBrochures = [];
        $customerMagazines = [];

        foreach ($api->findActiveBrochuresByCompany($companyId) as $brochureId => $brochureData) {
            if (preg_match('#' . self::REGEX_BROCHURE_TITLE . $this->weekNr . '#', $brochureData['title'])) {
                $weeklyBrochures = TRUE;
            }
            if (!preg_match('#' . self::REGEX_BROCHURE_TITLE . '#', $brochureData['title'])) {
                $customerMagazines[$brochureData['title']] = $brochureData['brochureNumber'];

                $storeList = $api->findStoresWithActiveBrochures($brochureId, $companyId);
                $storeNumbers = [];
                foreach ($storeList as $store) {
                    $storeNumbers[] = $store['number'];
                }

                $addedBrochures[] = $this->generateHash([
                    'store_numbers' => $storeNumbers,
                    'title' => $brochureData['title'],
                    'start' => $brochureData['validFrom'],
                    'end' => $brochureData['validTo'],
                ]);
            }
        }

        return [
            'weekly' => $weeklyBrochures,
            'hashes' => $addedBrochures,
            'customer_magazines' => $customerMagazines,
        ];
    }

    private function setAnnotations(object $singleBrochure, array $data, int $companyId): array
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $localFilePath = $this->http->getRemoteFile(
            preg_replace(
                ['#(http:\/\/)#', '#_KW(\d)_#'],
                ['$1' . self::AUTH_USER . ':' . self::AUTH_PASS . '@', '_KW0$1_'],
                $singleBrochure->url
            )
            , $this->localPath);

        if (!$localFilePath) {
            return [];
        }

        $this->pageService->open($singleBrochure->pageflipdataService);
        $coordsJSON = $this->pageService->getPage()->getResponseAsJson();

        $pageDimensionData = $pdfService->getAnnotationInfos($localFilePath);

        $pageFlipData = $coordsJSON->Pageflipdata[0];

        if ($coordsJSON->Pages) {
            $coordsToLink = [];

            foreach ($coordsJSON->Pages as $coordPage => $coordData) {
                if (!$pageDimensionData[$coordPage - 1]) {
                    continue;
                }

                foreach ($coordData->links as $coordLink) {
                    if (!strlen($coordPage) || preg_match('#_empty_#', $coordPage)) {
                        continue;
                    }
                    $xMovement = 0;
                    if (($coordPage % 2 == 1)
                        && $pageDimensionData[$coordPage - 1]->maxX > $pageDimensionData[$coordPage - 1]->width) {
                        $xMovement = (float)$pageFlipData->width;
                    }

                    $trackingStart = (preg_match('#\?#', $coordLink->url)) ? '&' : '?';

                    $coordsToLink[] = array(
                        'page' => (int)$coordPage - 1,
                        'height' => (float)$pageFlipData->height,
                        'width' => (float)$pageFlipData->width,
                        'startX' => (float)($coordLink->left + $xMovement),
                        'endX' => (float)($coordLink->left + 50.0 + $xMovement),
                        'startY' => (float)$pageFlipData->height - ((float)$coordLink->top),
                        'endY' => (float)$pageFlipData->height - ((float)$coordLink->top + 50.0),
                        'link' => $coordLink->url . $trackingStart . $data['tracking']
                    );

                }
            }

            $coordFileName = $this->localPath . 'coordinates_' . $companyId . '_' . $data['date'] . $pageFlipData->wbk . '.json';

            $fh = fopen($coordFileName, 'w+');
            fwrite($fh, json_encode($coordsToLink));
            fclose($fh);

            $localFilePath = $pdfService->setAnnotations($localFilePath, $coordFileName);
        }

        return [
            'url' => $localFilePath,
            'hash' => md5($pageFlipData->wbk . $pageFlipData->type)
        ];
    }

    protected function generateHash(array $data): string
    {
        sort($data['store_numbers']);

        return md5(
            implode(',', $data['store_numbers'])
            . $data['title']
            . strtotime($data['start'])
            . strtotime($data['end'])
        );
    }

    protected function generateHashFromObject(object $brochure): string
    {
        $storeNumbers = preg_split('#\s*;\s*#', $brochure->bkz);

        return $this->generateHash([
            'store_numbers' => $storeNumbers,
            'title' => $brochure->name,
            'start' => $brochure->validFromDate,
            'end' => $brochure->validUntilDate,
        ]);
    }

    private function generateBrochure(object $brochureData, array $brochureFileData, string $dateString): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setBrochureNumber('KW' . $dateString . '_' . substr($brochureFileData['hash'], 0, 14))
            ->setTitle($brochureData->name)
            ->setUrl($brochureFileData['url'])
            ->setStoreNumber(preg_replace('#;#', ',', $brochureData->bkz))
            ->setStart($brochureData->validFromDate)
            ->setEnd($brochureData->validUntilDate)
            ->setVisibleStart($brochureData->displayFromDate)
            ->setVariety('leaflet')
            ->setTrackingBug(self::TRACKING_BUG);

        // Prospekte gelten teilweise bis 2099 und werden hier als Dauersortiment eingestellt
        // wenn Gültigkeit länger als 1 Jahr
        $endDate = new DateTime($brochure->getEnd());
        if (($this->currentDate->diff($endDate)->y) > 1) {
            $brochure->setStart(NULL)
                ->setEnd(NULL);
        }

        if (!preg_match('#' . self::REGEX_BROCHURE_TITLE . '#', $brochure->getTitle())) {
            $brochure->setVariety('customer_magazine');
        }

        return $brochure;
    }

}
