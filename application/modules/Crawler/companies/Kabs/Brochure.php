<?php

/**
 * Brochure crawler for Kabs (ID: 71161)
 */
class Crawler_Company_Kabs_Brochure extends Crawler_Generic_Company
{
    private const ZIPCODES_FILE_ID = '1RE66g7Jc20VL7J6_YOHStli6mpRAwah9QAhh71SQ6s4';
    private const BASE_URL = 'https://www.kabs.de';
    private const BROCHURE_HISTORY_ID = '1bczJvwz_hd0PiD4CgtURwI7uQ_dL69ao8kOCRCBRFPU';

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSpreadsheetRead;
    private array $brochureHistory;
    private array $rawHistoryData;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $this->googleSpreadsheetRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->brochureHistory = $this->googleSpreadsheetRead->getFormattedInfos(self::BROCHURE_HISTORY_ID, 'A1', 'D');
        $this->rawHistoryData = $this->getRawHistoryData();
    }

    public function crawl($companyId)
    {
        $googleSpreadsheetWrite = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
        $this->companyId = $companyId;

        $brochures = new Marktjagd_Collection_Api_Brochure();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $ftpFiles = $this->getFtpFiles();

        if (empty($ftpFiles['campaignDataFiles'])) {
            throw new Exception('Company ID: ' . $companyId . ': No campaign data file found');
        }

        foreach ($ftpFiles['campaignDataFiles'] as $campaignDataFile) {
            $campaignData = $spreadsheetService->readFile($campaignDataFile, true)->getElement(0)->getData();

            foreach ($campaignData as $campaign) {
                $brochureName = $campaign['brochure'];
                if (isset($ftpFiles['brochures'][$brochureName])) {
                    $this->_logger->info('Company ID: ' . $companyId . ': processing brochure: ' . $brochureName);

                    $brochureData = $this->getBrochureData($campaign, $ftpFiles['brochures'][$brochureName]);
                    if (empty($brochureData)) {
                        continue;
                    }

                    $brochure = $this->createBrochure($brochureData);
                    $brochures->addElement($brochure);
                }
            }
        }

        $googleSpreadsheetWrite->writeGoogleSpreadsheet($this->rawHistoryData, self::BROCHURE_HISTORY_ID, FALSE, 'A2');

        return $this->getResponse($brochures);
    }

    private function getRawHistoryData(): array
    {
        $rawHistoryData = [];
        foreach($this->brochureHistory as $historyData) {
            if (strtotime('now') < strtotime($historyData['end'])) {
                $rawHistoryData[] = array_values($historyData);
            }
        }

        return $rawHistoryData;
    }

    private function getFtpFiles(): array
    {
        $this->ftp->connect($this->companyId);

        $localPath = $this->ftp->connect($this->companyId, true);

        $ftpFiles = [];
        foreach ($this->ftp->listFiles() as $folder) {
            if ('archive' === $folder) {
                continue;
            }

            if (preg_match('#\.\w{3,4}$#', $folder, $matches)) {
                if (preg_match('#(.*\.xlsx?)$#', $folder, $matches)) {
                    $ftpFiles['campaignDataFiles'][] = $this->ftp->downloadFtpToDir($folder, $localPath);
                }

                continue;
            }

            foreach ($this->ftp->listFiles($folder) as $innerFolder) {
                if (preg_match('#\.pdf$#', $innerFolder)) {
                    if (!preg_match('#Clickouts#i', $innerFolder)) {
                        $ftpFiles['brochures'][$folder] = $this->ftp->downloadFtpToDir($innerFolder, $localPath);
                    }
                }
            }
        }

        $this->ftp->close();

        return $ftpFiles;
    }

    private function getBrochureData(array $campaign, string $brochureFile): array
    {
        $visibility = $this->parseDatePeriod($campaign['visibility']);
        $validity = $this->parseDatePeriod($campaign['validity']);

        $stores = $this->getStores($campaign['stores']);
        $zipcodes = $this->getZipcodes($stores);

        $historyEntry = [];
        foreach ($this->brochureHistory as $history) {
            if ($history['brochure'] === $campaign['brochure'] && $history['start'] === $validity['start'] && $history['end'] === $validity['end']) {
                $historyEntry = $history;
            }
        }

        if (!empty($historyEntry) && 'TRUE' === $historyEntry['haveClickouts']) {
            return [];
        }

        $brochureUrl = '';
        $haveClickouts = 'FALSE';
        if (strtotime('now') >= strtotime($visibility['start']) && strtotime('now') < strtotime($validity['end'])) {
            // the clickouts are available only after the brochure is live
            try {
                $brochureUrl = $this->addClickoutsFromAPI($campaign, $brochureFile);
                $haveClickouts = 'TRUE';
            }
            catch (Exception $e) {
                $this->_logger->err('Company ID: ' . $this->companyId . ': can\'t get clickout data with message: ' . $e->getMessage());
            }
        }
        if (empty($brochureUrl)) {
            $brochureUrl = $this->ftp->generatePublicFtpUrl($brochureFile);
        }

        if (empty($historyEntry) && strtotime('now') < strtotime($validity['end'])) {
            $this->rawHistoryData[] = [
                $campaign['brochure'],
                $validity['start'],
                $validity['end'],
                $haveClickouts,
            ];
        }

        return [
            'url' => $brochureUrl,
            'title' => 'Kabs: ' . preg_replace('#_#', ' ', $campaign['brochure']),
            'number' => preg_replace('#\.#', '', $validity['start']) . '_' . $campaign['brochure'],
            'start' => $validity['start'],
            'end' => $validity['end'],
            'visibilityStart' => $visibility['start'],
            'visibilityEnd' => $visibility['end'],
            'stores' => $stores,
            'zipcodes' => $zipcodes,
        ];
    }

    private function parseDatePeriod(string $datePeriod): array
    {
        $dateNormalizer = new Marktjagd_Service_DateNormalization_Date();

        $dates = explode('-', $datePeriod);
        $dates = array_map('trim', $dates);

        return [
            'start' => $dateNormalizer->normalize($dates[0]),
            'end' => $dateNormalizer->normalize($dates[1]),
        ];
    }

    private function getStores(string $stores): string
    {
        $storeIds = [];
        if (preg_match('#all#', $stores)) {
            $api = new Marktjagd_Service_Input_MarktjagdApi();
            $stores = $api->findAllStoresForCompany($this->companyId);
            foreach ($stores as $store) {
                $storeIds[] = $store['number'];
            }
        }
        else {
            $storeIds = array_map('trim', explode(',', $stores));
        }

        return implode(',', $storeIds);
    }

    private function getZipcodes(string $stores): string
    {
        $zipcodesData = $this->googleSpreadsheetRead->getFormattedInfos(self::ZIPCODES_FILE_ID, 'A12', 'C', 'Zipcodes');

        $zipcodesPerStore = [];
        foreach ($zipcodesData as $zipcodeData) {
            $zipcodesPerStore[$zipcodeData['Filialnr.']][] = str_pad($zipcodeData['PLZ'], 5 ,'0', STR_PAD_LEFT);
        }

        $zipcodes = [];
        $storeIds = explode(',', $stores);
        foreach ($storeIds as $storeId) {
            if (isset($zipcodesPerStore[$storeId])) {
                $zipcodes = array_merge($zipcodes, $zipcodesPerStore[$storeId]);
            }
        }

        return implode(',', $zipcodes);
    }

    private function addClickoutsFromAPI(array $brochureDetails, string $pdf): string
    {
        $pageService = new Marktjagd_Service_Input_Page();
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $pdfInfos = $pdfService->getAnnotationInfos($pdf);

        if (preg_match('#(/prospekte/.*)$#', $brochureDetails['URL'], $urlMatch)) {
            // open the leaflet page once so whe api call can work, otherwise they will return "Not Found"
            $pageService->open(self::BASE_URL . $urlMatch[1]);
            $leafletPage = $pageService->getPage()->getResponseBody();
            sleep(5);

            $url = self::BASE_URL . '/api/page-config?url=' . $urlMatch[1];

            $pageService->open($url);
            $leafletConfigPage = $pageService->getPage()->getResponseAsJson();

            $leafletId = '';
            if ($leafletConfigPage && is_array($leafletConfigPage)) {
                foreach ($leafletConfigPage as $leafletData) {
                    if (isset($leafletData->config->leaflet_id)) {
                        $leafletId = $leafletData->config->leaflet_id;
                        break;
                    }
                }
            }
            else {
                $leafletConfigPage = $pageService->getPage()->getResponseBody();

                if (preg_match('#"type":"leaflet",(?:"\w+":"?\w+"?,)+"config":\{"module_id":"[^"]+","leaflet_id":"([^"]+)#', $leafletConfigPage, $leafletIdMatch)) {
                    $leafletId = $leafletIdMatch[1];
                }
            }

            if ($leafletId) {
                $leafletUrl = self::BASE_URL . '/api/leaflet/' . $leafletId;
                $pageService->open($leafletUrl);

                $response = $pageService->getPage()->getResponseBody();
                $result = preg_replace('#},"products":.*#', '}}', $response);
                $leaflet = json_decode($result);

                $clickouts = [];
                if ($leaflet) {
                    foreach ($leaflet->response->pages as $pageNumber => $page) {
                        if (!isset($pdfInfos[$pageNumber])) {
                            $this->_logger->warn('Company ID: ' . $this->companyId . ': Skipping clickout, because there is no page "' . $pageNumber . '"!');
                            continue;
                        }

                        foreach ($page->elements as $element) {
                            $url = '';
                            if ('url' === $element->type) {
                                $url = $element->target;
                            }
                            else if ('campaign' === $element->type) {
                                foreach ($leaflet->response->page_targets->campaign as $campaign) {
                                    if ($campaign->id === $element->target) {
                                        $url = $campaign->url;
                                        if (!preg_match('#^https?://#', $url)) {
                                            $url = self::BASE_URL . $url;
                                        }
                                        break;
                                    }
                                }
                            }
                            else if ('service' === $element->type) {
                                foreach ($leaflet->response->page_targets->service as $service) {
                                    if ($service->id === $element->target) {
                                        $url = $service->url;
                                        if (!preg_match('#^https?://#', $url)) {
                                            $url = self::BASE_URL . $url;
                                        }
                                        break;
                                    }
                                }
                            }
                            else if ('product' === $element->type) {
                                $url = self::BASE_URL . '/p/' . $element->target;
                            }

                            if ($url) {
                                if (!empty($brochureDetails['UTM Parameter'])) {
                                    $url = preg_replace('#([^?]+).*#', '$1' . $brochureDetails['UTM Parameter'], $url);
                                }

                                $clickouts[] = [
                                    'page' => $pageNumber,
                                    'height' => $pdfInfos[$pageNumber]->height,
                                    'width' => $pdfInfos[$pageNumber]->width,
                                    'startX' => $pdfInfos[$pageNumber]->width * $element->x,
                                    'endX' => $pdfInfos[$pageNumber]->width * $element->x + 10,
                                    'startY' => $pdfInfos[$pageNumber]->height - ($pdfInfos[$pageNumber]->height * $element->y),
                                    'endY' => $pdfInfos[$pageNumber]->height - ($pdfInfos[$pageNumber]->height * $element->y) - 10,
                                    'link' => $url,
                                ];
                            }
                        }
                    }
                }

                if ($clickouts) {
                    $coordFileName = dirname($pdf) . '/coordinates_' . $brochureDetails['brochure'] . '.json';
                    $fh = fopen($coordFileName, 'w+');
                    fwrite($fh, json_encode($clickouts));
                    fclose($fh);

                    return $pdfService->setAnnotations($pdf, $coordFileName);
                }
            }
        }

        return $pdf;
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setUrl($brochureData['url'])
            ->setTitle($brochureData['title'])
            ->setBrochureNumber($brochureData['number'])
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['visibilityStart'])
            ->setVisibleEnd($brochureData['visibilityEnd'])
            ->setStoreNumber($brochureData['stores'])
            ->setZipCode($brochureData['zipcodes'])
            ->setVariety('leaflet');
    }
}
