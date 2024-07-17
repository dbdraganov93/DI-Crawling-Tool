<?php

/**
 * Prospektcrawler für toom Baumarkt (ID: 123)
 */
class Crawler_Company_Toom_Brochure extends Crawler_Generic_Company
{
    private const CALENDAR_WEEK = 'next';
    private const DATE_REGEX = '#(\d{2})\.(\d{2})\.(\d{2})#';
    private const LIMITED_STORE_LIST_SHEET_ID = '17A-M_OT4aSghkX3XPILffu4klpxOwf77tAaSrxuArTQ';
    private $calendarWeekNumber;
    private $filesToArchive = [];
    private $year;
    private $limitedStoreList = [];

    public function __construct()
    {
        parent::__construct();
        $time = new Marktjagd_Service_Text_Times();
        $this->year =  $time->getWeeksYear(self::CALENDAR_WEEK);
        $this->calendarWeekNumber =  $time->getWeekNr(self::CALENDAR_WEEK);
        $this->readLimitedStoreList();
    }

    public function crawl($companyId)
    {
        $files = $this->downloadFiles($companyId);
        $brochuresData = $this->getBrochuresData($files);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochuresData as $brochureData) {
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
        }

        if (0 != count($brochures->getElements())) {
            $this->archiveFiles($companyId);
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function downloadFiles(int $companyId): ?array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $ftp->connect($companyId);
        $localPath = $ftp->generateLocalDownloadFolder($companyId);

        $downloadedFiles = [];
        $pdfPattern = '/KW' . $this->calendarWeekNumber . '_(.*?).pdf/';
        foreach ($ftp->listFiles() as $file) {
            if (preg_match('#((?!KW).+(\d{2})(\d{2})(\d{4})-(\d{2})(\d{2})(\d{4})\.pdf)#', $file)) {
                $this->filesToArchive[] = $file;
                $downloadedFiles['additionalPage'] = $ftp->downloadFtpToDir($file, $localPath);
            } elseif (preg_match('#xlsx?$#i', $file)) {
                $this->filesToArchive[] = $file;
                $downloadedFiles['data'] = $ftp->downloadFtpToDir($file, $localPath);
            } elseif (preg_match($pdfPattern, $file, $versionMatch)) {
                $this->filesToArchive[] = $file;
                $vers = $versionMatch[1];
                $downloadedFiles['pdf'][$vers]['url'] = $ftp->downloadFtpToDir($file, $localPath);
            }
        }
        $ftp->close();

        return $downloadedFiles;
    }

    private function archiveFiles($companyId): void
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $ftp->connect($companyId);
        $ftp->mkdir('!archive/KW' . $this->calendarWeekNumber . '_' . $this->year);
        foreach ($this->filesToArchive as $file) {
            if (preg_match('/KW\s?' . $this->calendarWeekNumber . '_(.*?)/', $file)) {
                $ftp->move($file, '!archive/KW' . $this->calendarWeekNumber . '_' . $this->year . '/' . $file);
            }
        }
        $ftp->close();
    }

    private function getBrochuresData(array $files): ?array
    {
        $brochures = [];
        $pdf = new Marktjagd_Service_Output_Pdf();
        $spreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sheetId = $this->getSpreadsheetTab($files['data'], '#'.$this->calendarWeekNumber.'_ONLINE#i');
        if ('' === $sheetId) {
            return [];
        }

        $brochuresData = $spreadsheet->readFile($files['data'], true)->getElement($sheetId)->getData();
        foreach ($brochuresData as $brochureData) {
            if (!$brochureData['KST-4'] && !$brochureData['VERSION']) {
                break;
            }

            if (!$this->storeIsLimited($brochureData['KST-4'])) {
                continue;
            }

            $aFromToCol = preg_split('#-#', $brochureData['GÜLTIGKEIT']);
            $version = strtoupper(trim($brochureData['VERSION'], '_'));

            if (empty($files['pdf'][$version])) {
                $this->_logger->warn('brochure: ' . $version . ' not in assignment file: ' . $files['data']);
                continue;
            }

            $pdfUrl = $files['pdf'][$version]['url'];
            $brochureNumber = $this->getStoreGroup($brochureData['KST-4']) . "_$version-$this->calendarWeekNumber" . $this->year;

            $brochures[$brochureNumber]['brochureNumber'] = $brochureNumber;
            $brochures[$brochureNumber]['stores'][] = $brochureData['KST-4'];
            $brochures[$brochureNumber]['start'] = preg_replace(self::DATE_REGEX, '$1.$2.20$3', $aFromToCol[0]);
            $brochures[$brochureNumber]['end'] = preg_replace(self::DATE_REGEX, '$1.$2.20$3', $aFromToCol[1]);
            if (empty($brochures[$brochureNumber]['url'])) {
                $brochureCopy = preg_replace('#\.pdf#', '_' . $brochureNumber . '.pdf', $pdfUrl);
                copy($pdfUrl, $brochureCopy);
                $brochures[$brochureNumber]['url'] = strlen($files['additionalPage']) ? $pdf->insert($brochureCopy, $files['additionalPage'], 1) : $brochureCopy;
            }
        }

        return $brochures;
    }

    private function getSpreadsheetTab(string $file, string $tabPatern): ?int
    {
        $sheetId = '';
        $spreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $dataTables = $spreadsheet->readFile($file)->getElements();
        foreach ($dataTables as $elementId => $table) {
            if (!preg_match($tabPatern, $table->getTitle())) {
                continue;
            }
            $sheetId = $elementId;
            break;
        }

        return $sheetId;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setBrochureNumber($brochureData['brochureNumber'])
            ->setUrl($brochureData['url'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochure->getStart())
            ->setStoreNumber(implode(',', $brochureData['stores']))
            ->setTitle('toom: Wochenangebote')
            ->setVariety('leaflet');

        return $brochure;
    }

    private function readLimitedStoreList(): void
    {
        $spreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->limitedStoreList =  $spreadsheet->getFormattedInfos(self::LIMITED_STORE_LIST_SHEET_ID, 'A1', 'H');
    }

    private function storeIsLimited(string $storeNumber): bool
    {
        return !empty($this->findStoreFromLimitedStoreList($storeNumber)) ? true : false;
    }

    private function getStoreGroup(string $storeNumber): ?string
    {
        $limitedStore = $this->findStoreFromLimitedStoreList($storeNumber);

        return !empty($limitedStore) ? reset($limitedStore)['Gruppe'] : '';
    }

    private function findStoreFromLimitedStoreList(string $storeNumber): ?array
    {
        $limitedStore = array_filter($this->limitedStoreList, function ($store) use ($storeNumber) {
            return $store['KST'] == $storeNumber;
        });

        return $limitedStore;
    }
}
