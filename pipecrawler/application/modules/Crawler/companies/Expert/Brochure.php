<?php

/*
 * Brochure Crawler für Expert (ID: 87)
 */

class Crawler_Company_Expert_Brochure extends Crawler_Generic_Company
{
    private const SHEET_NAME = 'Expert';
    private const CUSTOMER_SPREEDSHEET_ID = '1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA';
    private const COORDS_DOWNLOAD_URL = 'https://expert.publishing.one/%s/werbung/xml/book.xml';
    private const PAGE_COORDS_DOWNLOAD_URL = 'https://expert.publishing.one/%s/werbung/xml/page_%s.xml';
    private $kwNr;
    private $localPath;
    private $brochurePath;
    private $transfer;
    private $previewFile;
    private $stores = [];
    private $week = 'this';
    private $previewBrochure = false;
    private array $specialCampaignData;

    public function __construct()
    {
        $this->transfer = new Marktjagd_Service_Transfer_Http();

        if (date('N') == 5 || date('N') == 1) {
            $this->previewBrochure = TRUE;
            if (date('N') == 5) {
                $this->week = 'next';
            }
        }

        $spreadsheetService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->specialCampaignData = $spreadsheetService->getFormattedInfos(self::CUSTOMER_SPREEDSHEET_ID, 'A1', 'D', self::SHEET_NAME);

        parent::__construct();
    }

    /**
     * @param $companyId
     * @return Crawler_Generic_Response|void
     * @throws Exception
     *
     * This is used in conjunction with the normal Expert Brochure.
     * This only to get clickouts from their web and overwrite brochures.
     */
    public function crawl($companyId)
    {
        $times = new Marktjagd_Service_Text_Times();
        $this->kwNr = $times->getWeekNr($this->week);

        // Comment only because we can revert it.
        $files = $this->downloadFile($companyId);
//        $filteredExcelData = $this->getStoresData($companyId, $files);
        $filteredExcelData = $this->getStoreDataForSpecialCampaign();

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($filteredExcelData as $storeNumber => $zipcodeData) {
            $brochureData = $this->getBrochureData($storeNumber, $zipcodeData);
            if (empty($brochureData)) {
                continue;
            }
            $brochure = $this->createBrochure($brochureData);
            $brochures->addElement($brochure);
            if ($this->previewBrochure) {
                $this->previewFile = $brochure->getUrl();
            }
        }

        return $this->getResponse($brochures);
    }

    private function downloadFile(int $companyId): string
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->_logger->info('Getting FTP data');
        $this->localPath = $ftp->connect($companyId, TRUE);

        $distributionExcelFilePath = '';
        foreach ($ftp->listFiles() as $file) {
            if (preg_match('#expert-PLZ\s*Liste-final-Stand\s*112023\.xlsx#', $file)) {
                $distributionExcelFilePath = $ftp->downloadFtpToDir($file, $this->localPath);
            } elseif ($this->previewBrochure && preg_match('#\.pdf#', $file)) {
                $this->previewFile = $ftp->downloadFtpToDir($file, $this->localPath);
            }
        }

        $ftp->close();

        return $distributionExcelFilePath;
    }

    private function getStoresData(int $companyId, string $distributionExcelFilePath): ?array
    {
        $spreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $storesData = $spreadsheet->readFile($distributionExcelFilePath, true)->getElement(0)->getData();
        $filteredExcelData = [];
        foreach ($storesData as $storeData) {
            if (!$storeData['Standort ID']) {
                continue;
            }

            $filteredExcelData[$storeData['Standort ID']][] = str_pad($storeData['PLZ'], 5, '0', STR_PAD_LEFT);;
            $storeNumbers = $api->findStoreNumbersByPostcode($storeData['PLZ'],$companyId);
            if (!empty($storeNumbers)) {
                if (empty($this->stores[$storeData['Standort ID']])) {
                    $this->stores[$storeData['Standort ID']] = $storeNumbers;
                } else {
                    $this->stores[$storeData['Standort ID']] = array_merge($this->stores[$storeData['Standort ID']], $storeNumbers);
                }
            }
        }

        return $filteredExcelData;
    }

    private function getStoreDataForSpecialCampaign(): array
    {
        $filteredExcelData = [];

        foreach ($this->specialCampaignData as $specialCampaign) {
            $filteredExcelData[$specialCampaign['standortId']][] = $specialCampaign['plz'];
            $this->stores[$specialCampaign['standortId']] = $specialCampaign['storeNumber'];
        }

        return $filteredExcelData;
    }

    private function getCoordsFromXml(string $xmlPath, string $webId): string
    {
        $xmlDataObject = simplexml_load_file($xmlPath);
        if (!$xmlDataObject) {
            return '';
        }
        $this->brochurePath = 'https://expert.publishing.one/' . $webId . '/werbung/' . (string)$xmlDataObject->menus->downloads->submenu->entry->attributes()->url[0];

        $pdfSize = ['width' => 0, 'height' => 0];
        foreach ($xmlDataObject->resolutions->resolution as $resolution) {
            if ((string)$resolution->attributes()->path != 'preview/small/') {
                continue;
            }

            $pdfSize['width'] = (string)$resolution->attributes()->width;
            $pdfSize['height'] = (string)$resolution->attributes()->height;
        }

        $pages = $xmlDataObject->pages->page;
        $brochurePageNumbers = reset($xmlDataObject->pages->attributes()->pagecounter);

        // if there are no pages in the xml, download them from the separate xml files.
        // this is when the brochure are with a lot clickouts and the xml is split into multiple files.
        if (0 == count($pages)) {
            $pages = [];
            for ($pageIndex = 1; $pageIndex <= $brochurePageNumbers; $pageIndex++) {
                $xmlPagePath = $this->transfer->getRemoteFile(
                    sprintf(self::PAGE_COORDS_DOWNLOAD_URL, $webId, $pageIndex),
                    $this->localPath
                );

                $xmlPageObject = simplexml_load_file($xmlPagePath);
                $pages[] = $xmlPageObject->page;
            }
        }

        $aCoordsToLink = [];
        foreach ($pages as $page) {
            $pageNumber = $page['pagenumber'] ?: $page->attributes()->pagenumber;
            foreach ($page->hotspots->hotspot as $clickout) {
                $aCoords = explode(',', (string)$clickout->attributes()->coords);

                $aCoordsToLink[] = [
                    # for pdfbox page nr is 0-based
                    'page' => (int)$pageNumber - 1,
                    'height' => $pdfSize['height'],
                    'width' => $pdfSize['width'],
                    'startX' => $aCoords[0],
                    'endX' => $aCoords[2] + $aCoords[0],
                    'startY' => $pdfSize['height'] - $aCoords[1], // inverted Y position
                    'endY' => $pdfSize['height'] - $aCoords[1] - $aCoords[3],
                    'link' => 'https://www.expert.de/suche?q=' . $clickout->attributes()->params . '&branch_id=' . $webId
                ];
            }
        }

        $coordFileName = $this->localPath . 'coordinates_87.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        return $coordFileName;
    }

    private function getBrochureData(string $storeNumber, array $zipcode): ?array
    {
        $brochureData = [];
        if (!$this->previewBrochure) {
            $xmlPath = $this->transfer->getRemoteFile(
                sprintf(self::COORDS_DOWNLOAD_URL, $storeNumber),
                $this->localPath
            );
            $coords = $this->getCoordsFromXml($xmlPath, $storeNumber);

            $localBrochurePath = $this->transfer->getRemoteFile($this->brochurePath, $this->localPath);
            if (!$coords) {
                return [];
            }

            $pdf = new Marktjagd_Service_Output_Pdf();
            $brochureData['url'] = $pdf->setAnnotations($localBrochurePath, $coords);
        } else {
            $brochureData['url'] = $this->previewFile;
        }

        $brochureData['brochureNumber'] = $this->kwNr . '_' . date('Y', strtotime($this->week . ' week wednesday')) . '_' . $storeNumber;
        $brochureData['zipcode'] = implode(',', $zipcode);
        $brochureData['storeNumber'] = $this->stores[$storeNumber];
        $brochureData['start'] = date('d.m.Y', strtotime($this->week . ' week wednesday'));
        $brochureData['end'] = date('d.m.Y', strtotime($brochureData['start'] . '+ 6 days'));

        return $brochureData;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();
        $brochure->setUrl($brochureData['url'])
            ->setTitle('Expert: Wochenangebote')
            ->setStoreNumber($brochureData['storeNumber'])
            ->setBrochureNumber($brochureData['brochureNumber'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochure->getStart())
            ->setZipCode($brochureData['zipcode']);

        return $brochure;
    }
}
