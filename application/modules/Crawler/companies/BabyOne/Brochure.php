<?php

require APPLICATION_PATH . '/modules/Crawler/companies/BabyOne/DynBrochure.php';

/**
 * Brochure Crawler für BabyOne (ID: 28698)
 */
class Crawler_Company_BabyOne_Brochure extends Crawler_Generic_Company
{
    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private const GOOGLE_SHEET_ID = '1gtNocU-e2-i1uBNu0CMuyTnbXbyydMoqLPszFNfc_R0';
    private const TWO_DIGIT_FORMAT_YEAR_PATTERN = '#(\d){2}.(\d){2}.(\d){2}$#';
    private const ARTICLE_FILE_PATTERN = '#artikel.*.xlsx$#i';
    private const DYNAMIC_FLYER_PATTERN = '#dynamisch#i';
    private const BABY_ONE_PATTERN = '#BabyOne#';

    public function __construct()
    {
        parent::__construct();
        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $brochures = new Marktjagd_Collection_Api_Brochure();
        $dynBrochure = new Crawler_Company_BabyOne_DynBrochure();

        $brochuresData = $this->getBrochuresFromPlan();

        # no new brochures -> we're done here
        if (empty($brochuresData)) {

            $this->_logger->info('no brochures need to be imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

            return $this->_response;
        }
        $localFolder = $this->ftp->connect($companyId, TRUE);
        $this->ftp->changedir('Flyer DE');

        $downloadFiles = $this->downloadFilesFromFtp($brochuresData, $localFolder);
        $activeBrochures = $api->findActiveBrochuresByCompany($companyId);

        foreach($brochuresData as $pdfFile => $brochureData) {
            $brochureData = $this->handleClickouts($brochureData, $companyId);

            if (empty($brochureData['url'])) {
                if ($this->searchForBrochure($brochureData['brochureNr'], $activeBrochures)) {
                    $this->_logger->info('Brochure ' . $brochureData['brochureNr'] . ' Already Imported.');
                    continue;
                }
                $this->_logger->warn('Missing Brochure On The FTP Server: ' . $brochureData['PDF file']);
                continue;
            }

            # if it is a dynamic flyer, we create it
            if (preg_match(self::DYNAMIC_FLYER_PATTERN,$brochureData['brochureNr'])) {
                $brochureData['url'] = $dynBrochure->buildDynBrochure($companyId, $downloadFiles, $brochureData['PDF file']);
                unset($dynBrochure);
            }
            if (!preg_match(self::BABY_ONE_PATTERN, $brochureData['title'])) {
                $brochureData['title'] = 'BabyOne: ' . $brochureData['title'];
            }
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);

            # move the PDF file after the creation of the brochure element
            $this->ftp->move('./' . $pdfFile, './0_Archiv/' . $pdfFile);

            if ($downloadFiles)
                $this->ftp->move('./' . $downloadFiles, './0_Archiv/' . $downloadFiles);
        }
        $this->ftp->close();

        if (empty($brochures->getElements())) {

            $this->_logger->info('No Brochures Have Been Imported');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

            return $this->_response;
        }
        return $this->getResponse($brochures, $companyId);
    }

    private function downloadFilesFromFtp(array $brochuresData, string $localFolder): string
    {
        try {
            foreach ($this->ftp->listFiles('.', '#\.#') as $singleFile) {
                if (isset($brochuresData[$singleFile])) {

                    $this->_logger->info('Found Brochure To Import - ' . $singleFile);
                    $brochuresData[$singleFile]['url'] = $this->ftp->downloadFtpToDir($singleFile, $localFolder);

                } elseif (preg_match(self::ARTICLE_FILE_PATTERN, $singleFile)) {

                    $this->ftp->downloadFtpToDir($singleFile, $localFolder);
                    $remoteArticleFile = $singleFile;
                } else {
                    $this->_logger->warn('found file that is not in the marketing plan: ' . $singleFile);
                }
            }
        } catch (Exception $e) {
            $this->_logger->err('Error while downloading files from FTP: ' . $e->getMessage());
        }

        return $remoteArticleFile;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setTitle($brochureData['title'])
            ->setBrochureNumber(substr($brochureData['brochureNr'],0, 32))
            ->setUrl($brochureData['url'])
            ->setVariety('leaflet')
            ->setVisibleStart(date('d.m.Y', strtotime($brochureData['start'])))
            ->setStart(date('d.m.Y', strtotime($brochureData['start'])))
            ->setEnd(date('d.m.Y H:i:s', strtotime($brochureData['end'])));

        return $brochure;
    }

    private function searchForBrochure(int $brochureNumber, array $activeBrochures): ?int
    {
        foreach ($activeBrochures as $key => $val) {
            if ($val['brochureNumber'] === $brochureNumber) {
                return $key;
            }
        }
        return null;
    }

    private function getBrochuresFromPlan(): array
    {
        $googleSheetService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        # read all planned brochures from GoogleSheet
        $brochuresPlans = $googleSheetService->getFormattedInfos(self::GOOGLE_SHEET_ID, 'A1', 'I', 'geplant');

        foreach ($brochuresPlans as $brochurePlan) {
            # handle year in 2-digit format
            if (preg_match(self::TWO_DIGIT_FORMAT_YEAR_PATTERN, $brochurePlan['Startdatum'])) {
                $singleRow['Startdatum'] = substr($brochurePlan['Startdatum'], 0, 6) . '20' . substr($brochurePlan['Startdatum'], 6, 2);
            }
            if (preg_match(self::TWO_DIGIT_FORMAT_YEAR_PATTERN, $brochurePlan['Enddatum'])) {
                $brochurePlan['Enddatum'] = substr($brochurePlan['Enddatum'], 0, 6) . '20' . substr($brochurePlan['Enddatum'], 6, 2);
            }
            if (!empty($brochurePlan['PDF Datei']) && 'ja' == $brochurePlan['DE'] && strtotime($brochurePlan['Enddatum'] . ' 23:59:59') >= time()) {

                $brochuresData[trim($brochurePlan['PDF Datei'])] = [
                    'PDF file' => trim($brochurePlan['PDF Datei']),
                    'start' => $brochurePlan['Startdatum'],
                    'end' => $brochurePlan['Enddatum'] . ' 23:59:59',
                    'title' => $brochurePlan['Anzeigename'],
                    'brochureNr' => $brochurePlan['Kampagne'],
                    'tracking' => $brochurePlan['Link DE']
                ];
            }
        }

        return $brochuresData;
    }

    private function handleClickouts(array $brochureData, int $companyId): array
    {
        $pdf = new Marktjagd_Service_Output_Pdf();

        # if there are clickout links as annotations, we exchange them with real links
        $brochureData['url'] = $pdf->exchange($brochureData['url']);
        $annotations = $pdf->getAnnotationInfos($brochureData['url']);
        $utmParameter = parse_url($brochureData['tracking'], PHP_URL_QUERY) ?? '';
        $trackingData = [];

        # set additional link on first page, if there is one configured
        if (FALSE !==  strpos($brochureData['tracking'], 'https') ) {
            $trackingData[] = [
                'page' => 0,
                'link' => $brochureData['tracking'],
                'startX' => '340',
                'endX' => '390',
                'startY' => '740',
                'endY' => '790'
            ];

        }
        foreach ($annotations as $annotation) {

            if ('Link' != $annotation->subtype || NULL == $annotation->url) {
                unset($annotation);
                continue;
            }
            $trackingData[] = [
                'page' => $annotation->page,
                'link' => $annotation->url . '?' . $utmParameter,
                'startX' => $annotation->rectangle->startX,
                'endX' => $annotation->rectangle->endX,
                'startY' => $annotation->rectangle->startY,
                'endY' => $annotation->rectangle->endY
            ];
        }

        $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $companyId . '.json';
        file_put_contents($coordFileName, json_encode($trackingData));
        $brochureData['url'] = $pdf->setAnnotations($brochureData['url'], $coordFileName);
        $this->_logger->info('added clickout link to ' . $brochureData['url']);

        return $brochureData;
    }
}
