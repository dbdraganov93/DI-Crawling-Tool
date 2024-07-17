<?php

/* 
 * Prospekt Crawler für XXXLutz, Mömax und Möbelix AT (ID: 73436, 72787, 73091)
 */

class Crawler_Company_XxxLutzAt_Brochure extends Crawler_Generic_Company
{
    private const BASE_URL = 'https://digitalesflugblatt.premedia.at/api/';
    private const GOOGLE_SHEET_ID = '10eUT_KaB6weNz5-zG1SdJ5KkiwsIVQ3iDFKG1BMNUGI';
    private const CLICKOUT_URLS = [
        '73436' => 'https://www.xxxlutz.at/',
        '72787' => 'https://www.moemax.at/',
        '73091' => 'https://www.moebelix.at/'
    ];
    private const BROCHURE_NAME_FILTER = [
        '73436' => 'LAT|CAT',
        '72787' => 'VAT',
        '73091' => 'MAT'
    ];
    private const COMPANY_NAMES = [
        '73436' => 'XXXLutz',
        '72787' => 'mömax',
        '73091' => 'Möbelix'
    ];

    private string $bearerToken = '';
    private int $companyId;
    private Marktjagd_Service_Text_Times $timesService;
    private array $savedNumbers = [];

    public function __construct()
    {
        parent::__construct();

        $this->timesService = new Marktjagd_Service_Text_Times();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;

        $this->bearerToken = $this->getBearerToken();
        $publications = $this->sendRequest(self::BASE_URL . 'Publications');

        $googleSpreadsheetService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $spreadsheetData = $googleSpreadsheetService->getFormattedInfos(self::GOOGLE_SHEET_ID, 'A1', 'E', self::COMPANY_NAMES[$companyId]);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($publications as $brochureDetails) {

            # filter out empty brochures and apply the company-specific filter string
            if (empty($brochureDetails->pdfURI)
                || empty($brochureDetails->name)
                || !preg_match('#' . self::BROCHURE_NAME_FILTER[$companyId] . '#i', $brochureDetails->name)
            ) {
                continue;
            }

            $brochureDetails->validTo = $this->normalizeDate($brochureDetails->validTo);
            $brochureDetails->validFrom = $this->normalizeDate($brochureDetails->validFrom);

            $brochureData = $this->getBrochureData($brochureDetails, $spreadsheetData);
            if (empty($brochureData)) {
                continue;
            }

            $this->_logger->info("Found brochure '{$brochureData['number']}'");


            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getBearerToken(): string
    {
        $response = $this->sendRequest('https://navis.premedia.at/SecurityTokenService/connect/token',  [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=password&username=DigitalesFlugblattApiOfferista&password=ApQ5yRfqg5-%2BR%40HL&scope=assetModule&client_id=DigitalesFlugblattClient&client_secret=KC~8H-%5BMgTQ%40D)K&acr_values=tenant%3Aofferista',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        return $response->access_token;
    }

    /**
     * @return mixed
     */
    private function sendRequest(string $url, array $options = [])
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->bearerToken
            ),
        ));

        foreach ($options as $key => $value) {
            curl_setopt($curl, $key, $value);
        }

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
    }

    private function normalizeDate(string $date): string
    {
        $dateNormalization = new Marktjagd_Service_DateNormalization_Date();

        $date = preg_replace(['#T#', '#Z#'], [' ', ''], $date);

        return $dateNormalization->normalize($date, 'Y-m-d H:i:s');
    }

    private function getBrochureData(object $brochureDetails, array $spreadsheetData): array
    {
        if (!$this->timesService->isDateAhead($brochureDetails->validTo)) {
            return [];
        }

        if (preg_match('#(\w*('.self::BROCHURE_NAME_FILTER[$this->companyId].')\d{2}-\d+-\w+)#i', $brochureDetails->name, $numberSuffixMatch)) {
            if (!in_array($numberSuffixMatch[1], $this->savedNumbers)) {
            	$this->savedNumbers[] = $numberSuffixMatch[1];
            } else {
            	return [];
            }
        }

        $url = $this->getLeafletWithClickout($brochureDetails);
        if (!$url) {
            return [];
        }

        $title = $this->getBrochureTitleFromApi($brochureDetails->name);
        if (empty($title)) {
            $title = self::COMPANY_NAMES[$this->companyId] . ': aktuelle Angebote';
        }

        $stores = '';
        $zipcodes = '';
        $start = $brochureDetails->validFrom;
        $end = $brochureDetails->validTo;
        foreach ($spreadsheetData as $row) {
            if (preg_match('#' . $row['name'] . '#i', $brochureDetails->name)) {
                $stores = $row['stores'];
                $zipcodes = $row['zipcodes'];
                $start = $row['start'];
                $end = $row['end'];
                break;
            }
        }
        
        return [
            'number' => $brochureDetails->name,
            'start' => $start,
            'end' => $end,
            'visibleStart' => $start,
            'url' => $url,
            'variety' => 'leaflet',
            'storeNumber' => $stores,
            'trackingBug' => 'https://ad.doubleclick.net/ddm/activity/src=9528244;type=ext0;cat=exter00m;u94=upper-funnel;u95=%%CACHEBUSTER%%;u96=undefined;u97=' . strtolower(preg_replace('#\s+#', '-', $brochureDetails->name)) . ';u98=digitalerprospekt;u100=wogibtswas;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;npa=;ord=1',
            'title' => $title,
            'zipcodes' => $zipcodes
        ];
    }

    private function getBrochureTitleFromApi(string $BrochureName): string
    {
        $projects = $this->sendRequest(self::BASE_URL . 'Projects');
        foreach ($projects as $project) {
            if (strpos($BrochureName, $project->name)) {
                return $project->nameSuffix;

            }
        }

        return "";
    }

    /**
     * @throws Exception
     */
    private function getLeafletWithClickout(object $singleBrochure): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localUrl = $sHttp->getRemoteFile($singleBrochure->pdfURI, $sHttp->generateLocalDownloadFolder($this->companyId), date('YmdHim') . '.pdf');

        $clickouts = $this->getClickouts($singleBrochure, self::CLICKOUT_URLS[$this->companyId], $localUrl);
        if (empty($clickouts)) {
            return '';
        }

        return $sPdf->setAnnotations($localUrl, $sPdf->getJsonCoordinatesFile($clickouts));
    }

    private function getClickouts(object $singleBrochure, string $generalClickoutUrl, string $pdf): array
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $pdfInfo = $sPdf->getAnnotationInfos($pdf);

        if (NULL === $pdfInfo) {
            $this->_logger->err('Company ID: ' . $this->companyId . ': can\'t retrieve pdf information from file: ' . $pdf . ' for brochure: ' . $singleBrochure->name);
            return [];
        }

        $clickouts = [];
        $documentDetails = $this->sendRequest(self::BASE_URL . 'Documents?publikationId=' . $singleBrochure->id);
        foreach ($documentDetails as $singlePage) {
            foreach ($singlePage->artikelOptionen as $articles) {
                $isSecondPane = false;
                if (!$articles->relevantForProspect
                    || is_null($articles->verplanungsinfo->x1)
                    || is_null($articles->verplanungsinfo->x2)
                    || is_null($articles->verplanungsinfo->y1)
                    || is_null($articles->verplanungsinfo->y2)) {
                    continue;
                }

                $artNr = $articles->artNr;
                $hybrisUrl = $this->getSEOUrl($articles->mediaInformation->hybrisURL, $artNr);
                $shopUrl = $articles->mediaInformation->shopURL;
                $clickoutUrl = $shopUrl ?? $hybrisUrl;

                if (!$clickoutUrl) {
                    continue;
                }

                $articlePage = $singlePage->pageNumberLeft - 1;
                if (50 <= $articles->verplanungsinfo->x1 || 50 <= $articles->verplanungsinfo->x2) {
                    $articlePage  = $singlePage->pageNumberRight - 1;
                    $isSecondPane = true;
                }

                if (!strlen($articlePage) || !isset($pdfInfo[$articlePage]) || ($articlePage < 0)) {
                    $this->_logger->err($this->companyId . ': unable to get page for article number ' . $articles->artNr);
                    continue;
                }

                $maxX = $pdfInfo[$articlePage]->maxX;
                $maxY = $pdfInfo[$articlePage]->maxY;

                $x1 = $maxX * ($articles->verplanungsinfo->x1) / 100.0;
                $x2 = $maxX * ($articles->verplanungsinfo->x2) / 100.0;

                if ($isSecondPane) {
                    $x1 = $maxX * ($articles->verplanungsinfo->x1 - 50) / 100.0;
                    $x2 = $maxX * ($articles->verplanungsinfo->x2 - 50) / 100.0;
                }

                $y1 = $maxY * (100.0 - $articles->verplanungsinfo->y1) / 100.0;
                $y2 = $maxY * (100.0 - $articles->verplanungsinfo->y2) / 100.0;

                # now we center the clickouts to be just 1 pixel
                $x1 =  ($x1 + $x2);
                $x2 = $x1 + 1.0;
                $y1 = ($y1 + $y2) / 2;
                $y2 = $y1 + 1.0;

                $clickouts[] = [
                    'page' => $articlePage,
                    'startX' => $x1,
                    'endX' => $x2,
                    'startY' => $y1,
                    'endY' => $y2,
                    'link' => $clickoutUrl . '?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=' . $singleBrochure->name
                ];
            }
        }
        $clickouts[] = [
            'page' => 0,
            'startX' => 45,
            'startY' => 65,
            'endX' => 55,
            'endY' => 75,
            'link' => $generalClickoutUrl . '?utm_source=wogibtswas.at&utm_medium=coop&utm_campaign=' . $singleBrochure->name
        ];

        return $clickouts;
    }

    private function getSEOUrl(?string $url, ?string $artNr): ?string
    {
        if (!$url || !$artNr) {
            return NULL;
        }

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);
        $response = $sPage->getPage()->getResponseBody();
        preg_match_all('#seoUrl" : "(?<url>https://www.xxxlutz.at/p/(.)*?)"#', $response, $seoUrl);

        foreach ($seoUrl['url'] as $matchedUrl) {
            if (strpos($matchedUrl, $artNr))
                return $matchedUrl;
        }
        return NULL;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setBrochureNumber($brochureData['number'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visibleStart'])
            ->setUrl($brochureData['url'])
            ->setVariety('leaflet')
            ->setStoreNumber($brochureData['storeNumber'])
            ->setTrackingBug($brochureData['trackingBug'])
            ->setTitle($brochureData['title'])
            ->setZipCode($brochureData['zipcodes']);
    }
}
