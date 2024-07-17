<?php

/*
 * Brochure Crawler für Kaufland (ID: 67394)
 */

class Crawler_Company_Kaufland_Brochure extends Crawler_Generic_Company
{

    protected Marktjagd_Service_Transfer_Sftp $ftp;
    protected Marktjagd_Service_Input_PhpExcel $excelService;
    protected Marktjagd_Service_Text_Times $timeService;
    protected Marktjagd_Service_Output_Pdf $pdfService;
    protected string $week = 'this';
    protected string $_weekNr;
    protected string $localPath;
    protected string $momiWeekDay = 'monday';
    protected string $momiWeekDayToCheck = 'montag';
    protected const DAYS_EARLIER = ' - 1 day';
    protected const DEFAULT_START_TIME = '19:59:59';
    protected const DEFAULT_DATE_FORMAT = 'd.m.Y';

    private const REGIO_WEEKS = [
        17,
        21,
        23,
        25,
        29,
        38,
        42,
        6
    ];

    const BIO_DEMETER_TRACKING = '';
    const DEMETER_TRACKING = 'https://ad.doubleclick.net/ddm/trackimp/N1106503.2806609OFFERISTA/B26240841.323941595;dc_trk_aid=516067835;dc_trk_cid=167222980;dc_lat=;dc_rdid=;tag_for_child_directed_treatment=;tfua=;gdpr=${GDPR};gdpr_consent=${GDPR_CONSENT_755};ltd=?;ord=%%CACHEBUSTER%%';
    private const CONFIG = [
        'hostname' => 'mft.schwarz',
        'username' => 't-marktjagdt',
        'password' => 'eAKhphU4ThZXJF2r2pJM',
        'port' => '22',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ftp = new Marktjagd_Service_Transfer_Sftp();
        $this->excelService = new Marktjagd_Service_Input_PhpExcel();
        $this->timeService = new Marktjagd_Service_Text_Times();
        $this->pdfService = new Marktjagd_Service_Output_Pdf();
    }

    public function crawl($companyId)
    {

        $this->ftp->connect(self::CONFIG);
        $this->weekData();

        try {
            $this->localPath = $this->ftp->generateLocalDownloadFolder($companyId);
            $files = $this->getFile();

            if (!key_exists('csv', $files)) {
                throw new Exception("$companyId: no CSV-file on Kaufland FTP for KW $this->_weekNr");
            }

            if (!key_exists('pdf', $files)) {
                throw new Exception("$companyId: no pdf-files on Kaufland FTP for KW $this->_weekNr");
            }

            $amount = count($files['pdf']);
            $cnt = 0;

            $storeData = [];
            foreach ($files['csv'] as $csvFile) {
                $storeData = array_merge($this->excelService->readFile($csvFile, false, ';')->getElement(0)->getData(), $storeData);
            }

            $brochures = new Marktjagd_Collection_Api_Brochure();
            foreach ($files['pdf'] as $pdfName => $brochureData) {
                $brochureData['url'] = $this->downloadPdf($brochureData['url']);
                if (empty($brochureData['url'])) {
                    sleep(5);
                    $brochureData['url'] = $this->downloadPdf(preg_replace('#(austausch)/#', '$1/archiv/', $brochureData['url']));
                }

                if (empty($brochureData['url'])) {
                    continue;
                }
                $cnt++;

                $this->metaLog("PDF-File $cnt from $amount downloaded");

                $brochureData['stores'] = $this->getStoreNumbers($storeData, $brochureData['stores'], $pdfName);

                $trackingUrl = true === $brochureData['bioDemeter'] ? self::BIO_DEMETER_TRACKING : self::DEMETER_TRACKING;

                try {
                    $regionalData = $this->getRegionalData($brochureData);
                    if ($regionalData && in_array($this->_weekNr, self::REGIO_WEEKS)) {
                        $brochure = $this->createBrochures($regionalData, $trackingUrl);
                        $brochures->addElement($brochure);
                    }

                    $momiData = $this->getMomiBrochureData($brochureData);
                    if ($momiData) {
                        $brochure = $this->createBrochures($momiData, $trackingUrl);
                        $brochures->addElement($brochure);
                    }

                    if (preg_match('#UA3-Nat#i', $brochureData['brochureNumber'])) {
                        $brochure = $this->createBrochures($brochureData);
                        if ($brochures->addElement($brochure)) {
                            $brochureData['url'] = $brochure->getUrl();
                        }
                    }

                    $brochure = $this->createBrochures($brochureData, $trackingUrl);
                    $brochures->addElement($brochure, TRUE);

                    if (file_exists($brochureData['url'])) {
                        unlink($brochureData['url']);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        } finally {
            $this->ftp->close();
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getStoreNumbers(array $csvData, string $refStore, string $pdfName): string
    {
        $stores = [$refStore];
        $machStores = array_filter($csvData, function ($storeData) use ($pdfName) {
            return $storeData[1] == $pdfName;
        });

        foreach ($machStores as $storeData) {
            $stores[] = reset($storeData);
        }
        return preg_replace("#/#", ',', implode(',', array_unique($stores)));
    }

    private function weekData(): void
    {
        if (date('N') > 3) {
            $this->week = 'next';
        }

        $publicHolidayService = new Marktjagd_Service_Validator_PublicHoliday();
        if ($publicHolidayService->isPublicHoliday($this->getMomiStartDate())) {
            $this->momiWeekDay = 'tuesday';
            $this->momiWeekDayToCheck = 'dienstag';
        }

        $this->_weekNr = $this->timeService->getWeekNr($this->week);
    }

    private function getFile(): array
    {
        $files = [];
        $folderVerbunddatei = './Schwarz/DE/Marktportale/austausch/';
        if (count($this->ftp->listFiles($folderVerbunddatei)) < 4
            || ($this->week === 'this' && date('N') < 4)) {
            $folderVerbunddatei .= 'archiv/';
        }
        // need to add Verbunddatei logic, because they sometimes use VerbunddateiWW$KW.csv and sometimes only Verbunddatei.csv

        foreach ($this->ftp->listFiles($folderVerbunddatei) as $file) {
            if (preg_match('#Verbunddatei(\D*' . $this->_weekNr . '[^\.]*)?\.csv$#', $file)) {
                $csvLocalUrl = $this->ftp->downloadFile("$folderVerbunddatei$file", $this->localPath);
                if ($csvLocalUrl) {
                    $files['csv'][] = $csvLocalUrl;
                    $this->metaLog("$file downloaded");
                }
            }

            if (preg_match("#^VerbunddateiBioDemeter[WW$this->_weekNr]*\.csv$#", $file)) {
                $csvLocalUrl = $this->ftp->downloadFile("$folderVerbunddatei$file", $this->localPath);
                if ($csvLocalUrl) {
                    $files['csv'][] = $csvLocalUrl;
                    $this->metaLog("$file downloaded");
                }
            }

            $patterns = [
                "#([A-Z]$this->_weekNr)_(\d+)_[^.]*\.pdf$#",
                "#([A-Z]$this->_weekNr)-EL-NAT_(\d+)_[^.]*\.pdf$#i",
                "#([A-Z]$this->_weekNr)-UA3-NAT_(\d+)_[^.]*\.pdf$#i",
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $file, $weekMatch)) {
                    # Beispiel: D14_3000_2020-04-02_2020-04-08_2020-03-28_2020-04-08.pdf
                    # Definition: Länderkürzel+KW_FilialID_GültigAb_GültigBis_AnzeigeVon_AnzeigeBis.pdf
                    $nameParts = explode('_', $file);

                    $files['pdf'][$file]['bioDemeter'] = false;
                    if (preg_match("#([A-Z]$this->_weekNr)-EL-NAT#i", $nameParts[0])) {
                        $files['pdf'][$file]['bioDemeter'] = true;
                    }

                    $files['pdf'][$file]['week'] = $nameParts[0];
                    $files['pdf'][$file]['url'] = "$folderVerbunddatei$file";
                    $files['pdf'][$file]['stores'] = $nameParts[1];
                    $files['pdf'][$file]['title'] = 'Kaufland: Wochenangebote';
                    $files['pdf'][$file]['brochureNumber'] = $nameParts[0] . '_%s_' . $nameParts[1];
                    $files['pdf'][$file]['postfix'] = $files['pdf'][$file]['bioDemeter'] ? '_BIO' : '';
                    $files['pdf'][$file]['validFrom'] = date(self::DEFAULT_DATE_FORMAT, strtotime($nameParts[2]));
                    $files['pdf'][$file]['validTo'] = date(self::DEFAULT_DATE_FORMAT, strtotime($nameParts[3])) . ' ' . self::DEFAULT_START_TIME;
                    $files['pdf'][$file]['visibleFrom'] = date(self::DEFAULT_DATE_FORMAT, strtotime($nameParts[2] . self::DAYS_EARLIER)) . ' ' . self::DEFAULT_START_TIME;
                }
            }
        }

        return $files;
    }

    private
    function getMomiBrochureData(array $brochureData): ?array
    {
        $this->pdfService->splitPdf($brochureData['url']);
        $pdfInfos = json_decode($this->pdfService->extractText($brochureData['url']));
        $extraFiles = [];
        $filesToAppend = [];
        foreach (scandir($this->localPath) as $file) {
            if (preg_match('#separated_(\d+)#', $file, $pageMatch)) {
                $filesToAppend[$pageMatch[1]] = $this->localPath . $file;
            }
        }
        ksort($filesToAppend);
        foreach ($pdfInfos as $pageNo => $info) {
            if (preg_match('#ab\s*' . $this->momiWeekDayToCheck . '\s*,\s*' . date('d.m.', strtotime($this->momiWeekDay . ' ' . $this->week . ' week + 7 days')) . '#i', $info->text)) {
                foreach (scandir($this->localPath) as $file) {
                    if (!preg_match('#separated_(\d+)#', $file, $pageMatch)) {
                        continue;
                    }
                    if ((int)$pageMatch[1] > $pageNo) {
                        $extraFiles[$pageMatch[1]] = $this->localPath . $file;
                        unset($filesToAppend[$pageMatch[1]]);
                    }
                }
            }
        }

        if (!$extraFiles) {
            $this->deleteSeparatedFiles();
            return [];
        }

        $completeNewBrochure = array_merge($extraFiles, $filesToAppend);
        $extraFiles = array_values($completeNewBrochure);

        $momiData = $brochureData;
        $momiData['url'] = $this->pdfService->merge($extraFiles, $this->localPath);
        $momiData['title'] = 'Kaufland: Mo-Mi Der Wochenstart';
        $momiData['validFrom'] = $this->getMomiStartDate();
        $momiData['visibleFrom'] = date('d.m.Y H:i:s', strtotime($momiData['validFrom'] . ' - 6 hours -' . ((date('N', strtotime($momiData['validFrom'])) - 1) * 24) . ' hours'));
        $momiData['postfix'] .= '_MoMi';

        $this->deleteSeparatedFiles();

        return $momiData;
    }

    private
    function downloadPdf(string $file): ?string
    {
        $pdfLocalUrl = $this->ftp->downloadFile($file, $this->localPath);
        if (!$pdfLocalUrl) {
            sleep(5);
            $pdfLocalUrl = $this->ftp->downloadFile($file, $this->localPath);
            if (!$pdfLocalUrl) {
                return null;
            }
        }
        return $pdfLocalUrl;
    }

    private
    function createBrochures(array $brochureData, string $trackingUrl = ''): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        return $brochure->setUrl($brochureData['url'])
            ->setTitle($brochureData['title'])
            ->setStoreNumber($brochureData['stores'])
            ->setVariety('leaflet')
            ->setStart($brochureData['validFrom'])
            ->setEnd($brochureData['validTo'])
            ->setVisibleStart($brochureData['visibleFrom'])
            ->setVisibleEnd($brochureData['validTo'])
            ->setBrochureNumber(sprintf($brochureData['brochureNumber'], $brochureData['validFrom']) . $brochureData['postfix'])
            ->setTrackingBug($trackingUrl);
    }

    private
    function deleteSeparatedFiles(): void
    {
        foreach (scandir($this->localPath) as $file) {
            if (preg_match('#separated#', $file)) {
                unlink($this->localPath . $file);
            }
        }
    }

    private
    function getMomiStartDate(): string
    {
        return date(self::DEFAULT_DATE_FORMAT, strtotime($this->momiWeekDay . ' ' . $this->week . ' week + 7 days'));
    }

    private
    function getRegionalData(array $brochureData): ?array
    {
        $this->pdfService->splitPdf($brochureData['url']);
        $pdfInfos = json_decode($this->pdfService->extractText($brochureData['url']));
        $extraFiles = [];
        $filesToAppend = [];
        foreach (scandir($this->localPath) as $file) {
            if (preg_match('#separated_(\d+)#', $file, $pageMatch)) {
                $filesToAppend[$pageMatch[1]] = $this->localPath . $file;
            }
        }
        ksort($filesToAppend);
        foreach ($pdfInfos as $pageNo => $info) {
            if (preg_match('#_REGP#i', $info->text)) {
                $extraFiles[$pageNo + 1] = $filesToAppend[$pageNo + 1];
                unset($filesToAppend[$pageNo + 1]);
            }
        }

        if (!$extraFiles) {
            $this->deleteSeparatedFiles();
            return [];
        }

        $completeNewBrochure = array_merge($extraFiles, $filesToAppend);
        $extraFiles = array_values($completeNewBrochure);

        $regionalData = $brochureData;
        $regionalData['url'] = $this->pdfService->merge($extraFiles, $this->localPath);
        $regionalData['title'] = 'Kaufland: Regio-Wochen';
        $regionalData['postfix'] .= '_ReWo';

        $this->deleteSeparatedFiles();

        return $regionalData;
    }

    private
    function getDuplicateBrochureData(array $brochureData, string $newUrl, string $prefix = ''): array
    {
        $brochureData['brochureNumber'] = $prefix . $brochureData['brochureNumber'];
        $brochureData['url'] = $newUrl;

        return $brochureData;
    }
}
