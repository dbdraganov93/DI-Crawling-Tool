<?php

/**
 * Brochure-Crawler für Getränkeland (ID: 29134)
 */
class Crawler_Company_Getraenkeland_Brochure extends Crawler_Generic_Company
{
    protected const DATE_FORMAT = 'd.m.Y';
    private const REFERENCE_FILE_NAME = 'Offerista_Standorte_Getraenkeland_Region_12012024.xls';
    private const BROCHURE_R261 = 'r261';
    private const BROCHURE_R262 = 'r262';
    private const WEEK = 'next';

    protected string $weekNr;
    protected Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private array $storeDistributions;
    private array $currentStoreList;

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        if (0 === date('W') % 2) {
            $this->_logger->info('Crawler will run only on odd weeks');
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);

            return $this->_response;
        }

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $timesService = new Marktjagd_Service_Text_Times();
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $this->weekNr = $timesService->getWeekNr(self::WEEK);

        $this->currentStoreList = $api->findAllStoresForCompany($companyId);
        $localFiles = $this->getFtpFiles($companyId);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($localFiles['brochures'] as $fileName) {
            if (preg_match('#(\/[^/]*\.pdf)$#', $fileName, $nameMatch)) {
                $brochureData = $this->getBrochureData($fileName, $nameMatch[1]);
                $brochure = $this->createBrochure($brochureData);

                $brochures->addElement($brochure);
            }
        }

        return $this->getResponse($brochures, $companyId);
    }

    /**
     * @throws Exception
     */
    protected function getFtpFiles(int $companyId): array
    {
        $localFiles = [
            'referenceFile' => '',
            'brochures' => [],
        ];
        $this->ftp->connect($companyId);
        $localDirectory = $this->ftp->generateLocalDownloadFolder($companyId);
        $ftpFolder = '';

        foreach ($this->ftp->listFiles() as $ftpFile) {
            if (preg_match('#Getränkeland_KW' . $this->weekNr . '#', $ftpFile)) {
                $ftpFolder = $ftpFile;
            }

            if (preg_match('#' . self::REFERENCE_FILE_NAME . '#', $ftpFile)) {
                $localFiles['referenceFile'] = $this->ftp->downloadFtpToDir($ftpFile, $localDirectory);
            }
        }

        if (empty($ftpFolder) || !$ftpFolderFiles = $this->ftp->listFiles($ftpFolder)) {
            throw new Exception($companyId . ': no brochures available.');
        }

        $pattern = '#(.+?pdf)$#';
        foreach ($ftpFolderFiles as $filePath) {
            if (preg_match($pattern, $filePath)) {
                $localFiles['brochures'][] = $this->ftp->downloadFtpToDir($filePath, $localDirectory);
            }
        }

        $this->ftp->close();

        return $localFiles;
    }

    /**
     * @throws Exception
     */
    private function getStoreDistributions(string $referenceFilePath): array
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $storesData = $spreadsheetService->readFile($referenceFilePath, true)->getElement(0)->getData();
        $storesDataWithoutHeadline = $spreadsheetService->readFile($referenceFilePath)->getElement(0)->getData();
        $brochureR261 = reset($storesDataWithoutHeadline)[35];
        $brochureR262 = reset($storesDataWithoutHeadline)[39];

        if (!preg_match('#' . self::BROCHURE_R261 . '#', $brochureR261) ||
            !preg_match('#' . self::BROCHURE_R262 . '#', $brochureR262)
        ) {
            throw new Exception('Brochures r261 and r262 distributions were not found on Excel sheet: ' . self::REFERENCE_FILE_NAME);
        }

        $distributionZipcodes = [
            self::BROCHURE_R261 => [],
            self::BROCHURE_R262 => []
        ];
        foreach ($storesData as $storeData) {
            if (null === $storeData['street']) {
                continue;
            }

            if (preg_match('#' . $storeData['region'] . '#', $brochureR261)) {
                $distributionZipcodes[self::BROCHURE_R261][] = $storeData['zipcode'];
            } elseif (preg_match('#' . $storeData['region'] . '#', $brochureR262)) {
                $distributionZipcodes[self::BROCHURE_R262][] = $storeData['zipcode'];
            }
        }

        $storeDistributions = [
            self::BROCHURE_R261 => [],
            self::BROCHURE_R262 => []
        ];
        foreach ($this->currentStoreList as $store) {
            foreach (array_keys($storeDistributions) as $brochureRegion) {
                if (in_array((int)$store['zipcode'], $distributionZipcodes[$brochureRegion])) {
                    $storeDistributions[$brochureRegion][] = $store['number'];
                }
            }
        }

        return $storeDistributions;
    }

    protected function getBrochureData(string $brochureFilePath, string $brochureFileName): array
    {
        $stringList = preg_split('#[\_|\,]#', pathinfo($brochureFileName, PATHINFO_FILENAME));

        foreach ($stringList as $string) {
            if (preg_match('#^(r?\d{3})$#', $string)) {
                $salesRegions[] = $string;
                continue;
            }
            if (preg_match('#(\d{4})-(\d{8})#', $string, $dateMatch)) {
                $dates = $dateMatch;
            }
        }
        $brochureTitle = 'Getränkeangebote';

        $brochureNumber = reset($stringList);

        $start = $this->formatDate($dates[1], 'short');
        $end = $this->formatDate($dates[2]);
        if (empty($start)) {
            $start = date(self::DATE_FORMAT, str_pad(strtotime(self::WEEK . ' week'), '2', '0', STR_PAD_LEFT));
            $end = date(self::DATE_FORMAT, str_pad(strtotime(self::WEEK . ' week + 5 days'), '2', '0', STR_PAD_LEFT));
        }
        $visibleStart = date(self::DATE_FORMAT, strtotime($start . '- 1 day'));

        $pattern = '#mittwochskracher\_?([^/]+?)_([0-9]{2})([0-9]{2})([0-9]{4})\.pdf#i';
        if (preg_match($pattern, $brochureFileName, $articleNameMatch)) {
            $start = $articleNameMatch[2] . '.' . $articleNameMatch[3] . '.' . $articleNameMatch[4];
            $end = $articleNameMatch[2] . '.' . $articleNameMatch[3] . '.' . $articleNameMatch[4];
            $visibleStart = date(self::DATE_FORMAT, strtotime($start . '-6 days'));
        }

        $brochureUrl = $this->ftp->generatePublicFtpUrl($brochureFilePath);

        return [
            'url' => preg_replace('#.*?(/files.+)#', 'https://di-gui.marktjagd.de$1', $brochureUrl),
            'number' => $brochureNumber . '_KW' . $this->weekNr,
            'title' => $brochureTitle,
            'salesRegions' => implode(',', $salesRegions),
            'validityFrom' => $start,
            'validityTo' => $end,
            'visibilityFrom' => $visibleStart,
        ];
    }

    protected function formatDate(string $date, $length = 'normal'): string
    {
        if ('short' === $length) {
            return preg_replace('#([0-9]{2})([0-9]{2})#', '$1.$2.' . date('Y', strtotime(self::WEEK . ' week')), $date);
        }
        return preg_replace('#([0-9]{2})([0-9]{2})([0-9]{4})#', '$1.$2.$3', $date);
    }

    protected function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        $brochure->setUrl($brochureData['url'])
            ->setBrochureNumber($brochureData['number'])
            ->setTitle($brochureData['title'])
            ->setStart($brochureData['validityFrom'])
            ->setEnd($brochureData['validityTo'])
            ->setVisibleStart($brochureData['visibilityFrom'])
            ->setVariety('leaflet')
            ->setDistribution($brochureData['salesRegions']);

        $dateStart = new DateTime($brochure->getStart());
        $dateEnd = new DateTime($brochure->getEnd());

        if (date_diff($dateStart, $dateEnd)->format('%a') <= 5) {
            $brochure->setVisibleStart($brochure->getVisibleStart() . ' 15:00')
                ->setVisibleEnd($brochure->getEnd() . ' 15:00');
        }
        if (preg_match('#r\d{3}#', $brochure->getBrochureNumber())) {
            $aStoreNumbers = [];
            foreach ($this->currentStoreList as $singleStore) {
                if (!preg_match('#(769|791)#', $singleStore['number'])) {
                    $aStoreNumbers[] = $singleStore['number'];
                }
            }
            $brochure->setDistribution(NULL)
                ->setStoreNumber(implode(',', $aStoreNumbers));
        }

        return $brochure;
    }
}
