<?php

/**
 * Brochure Crawler für Edeka Südbayern (ID: 72089 - 72090, 72301, 82395)
 */
class Crawler_Company_EdekaSuedbayern_Brochure extends Crawler_Generic_Company
{
    private string $week = 'next';
    private const DEFAULT_COMPANY = 72089;
    private const DATE_FORMAT = 'd.m.Y';
    private const NEWSLETTER_PAGE = 'EDEKA Südbayern Newsletter Page.pdf';

    protected string $title;

    private Marktjagd_Service_Input_MarktjagdApi $api;

    public function __construct()
    {
        parent::__construct();

        $this->api = new Marktjagd_Service_Input_MarktjagdApi();
        if (date('N') < 4) {
            $this->week = 'this';
        }
    }


    public function crawl($companyId)
    {
        $duplicateBrochureDLC = [72089, 72090, 72301, 82395];
        $extraNewsletterPage = [72089, 72301, 72090];

        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $timeService = new Marktjagd_Service_Text_Times();
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $this->title = $this->api->findCompanyByCompanyId($companyId)["title"];
        $weekNo = $timeService->getweekNr($this->week);
        $year = $timeService->getweeksYear($this->week);

        $distributions = $this->getStoreDistributions($companyId);

        $localFolder = $ftp->generateLocalDownloadFolder($companyId);
        $ftp->connect(self::DEFAULT_COMPANY);

        if (in_array($companyId, $extraNewsletterPage)) {
            $newsletterPage = '';
            foreach ($ftp->listFiles('.', '#\.pdf#') as $newsletter) {
                if (self::NEWSLETTER_PAGE === $newsletter) {
                    $newsletterPage = $ftp->downloadFtpToDir($newsletter, $localFolder);
                    break;
                }
            }
        }

        $ftp->changedir('./KW' . $weekNo . '_' . $year);
        foreach ($ftp->listFiles('.', '#\.pdf#') as $brochurePdf) {
            $pattern = '#KW(\d{2})_([0-9]{6,7})_([0-9]{6})_SUEDBAYERN_(.+?)(_NEU)?\.pdf#i';
            if (!preg_match($pattern, $brochurePdf, $brochureDetailsMatch)) {
                $this->_logger->err($companyId . ': invalid name scheme: ' . $brochurePdf);
                continue;
            }
            $distributionToSet = '';

            foreach ($distributions as $distribution) {
                if (preg_match('#' . $distribution . '$#i', $brochureDetailsMatch[4])) {
                    $distributionToSet = $distribution;
                    break;
                }
            }

            if (!strlen($distributionToSet) && !preg_match('#inserat#i', $brochureDetailsMatch[4])) {
                continue;
            }

            $brochurePath = $ftp->downloadFtpToDir($brochurePdf, $localFolder);
            if (self::DEFAULT_COMPANY == $companyId) {
                $brochurePath = $pdfService->implementSurvey($brochurePath, 3);
            }

            $duplicateUrl = $brochurePath;
            if (in_array($companyId, $extraNewsletterPage) && 2 < $pdfService->getPageCount($brochurePath) && strlen($newsletterPage)) {
                $brochurePath = $pdfService->insert($brochurePath, $newsletterPage, 1);
            }

            $brochureData = $this->getValidity($brochureDetailsMatch);
            $brochureData['number'] = $this->getBrochureNumber($brochureDetailsMatch);
            $brochureData['distribution'] = $distributionToSet;
            $brochureData['url'] = $brochurePath;

            $aCoordsToLink = [];
            if ($companyId == 72089 || $companyId == 72090) {
                foreach ($pdfService->getAnnotationInfos($brochurePath) as $annotation) {
                    $aCoordsToLink[] = [
                        # for pdfbox page nr is 0-based
                        'page' => $annotation->page,
                        'height' => $annotation->height,
                        'width' => $annotation->width,
                        'startX' => $annotation->width - 100,
                        'endX' => $annotation->width - 75,
                        'startY' => 50,
                        'endY' => 75,
                        'link' => 'https://app.adjust.com/199sn1wk'
                    ];
                }


                $coordFileName = $localFolder . 'coordinates_' . $companyId . '.json';
                $fh = fopen($coordFileName, 'w+');
                fwrite($fh, json_encode($aCoordsToLink));
                fclose($fh);
                $brochureData['url'] = $pdfService->setAnnotations($brochurePath, $coordFileName);
            }

            $brochure = $this->addBrochure($brochureData);
            $brochures->addElement($brochure);

            if (in_array($companyId, $duplicateBrochureDLC)) {
                $brochureData['url'] = ($duplicateUrl === $brochurePath) ? $brochure->getUrl() : $duplicateUrl;
                $brochures->addElement($this->addBrochure($brochureData, 'DLC_'));
            }

        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getStoreDistributions(int $companyId): array
    {
        $stores = $this->api->findStoresByCompany($companyId)->getElements();
        $distributions = [];
        foreach ($stores as $store) {
            $storeDistribution = preg_replace('#\s*,?\s*WhatsApp\s*,?\s*#', '', $store->getDistribution());
            if (!in_array($storeDistribution, $distributions)) {
                $distributions[] = $storeDistribution;
            }
        }

        return $distributions;
    }

    private function getValidity(array $brochureDetailsMatch): array
    {
        $dateNormalization = new Marktjagd_Service_DateNormalization_Date();

        $start = $dateNormalization->normalize($brochureDetailsMatch[2], 'dmY');
        $end = $dateNormalization->normalize($brochureDetailsMatch[3], 'dmY');

        if (strtotime($start) == strtotime('25.12.' . date('Y', strtotime('this year')))
            || strtotime($start) == strtotime('26.12.' . date('Y', strtotime('this year')))) {
            $start = '27.12.' . date('Y', strtotime('this year'));
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getBrochureNumber(array $brochureDetailsMatch): string
    {
        return $brochureDetailsMatch[4] . '_' . $brochureDetailsMatch[1] . '_' . $brochureDetailsMatch[2];
    }

    protected function addBrochure(array $brochureData, string $prefix = ''): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setUrl($brochureData['url'])
            ->setStart(date(self::DATE_FORMAT, strtotime($brochureData['start'] . '+1 day')))
            ->setEnd($brochureData['end'])
            ->setDistribution($brochureData['distribution'])
            ->setTitle($this->title . ': Wochenangebote')
            ->setVisibleStart($brochureData['start'])
            ->setVariety('leaflet')
            ->setBrochureNumber($prefix . $brochureData['number']);

        if (preg_match('#inserat#i', $brochureData['number'])) {
            $brochure->setTitle($this->title . ': Zeitungsinserat')
                ->setDistribution(NULL);
        }

        return $brochure;
    }
}
