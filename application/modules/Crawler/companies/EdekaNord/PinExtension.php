<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

/**
 * Pinterest Pin Extension crawler for Edeka Nord (ID: 69470)
 */
class Crawler_Company_EdekaNord_PinExtension extends Crawler_Generic_Company
{
    private const COMPANY_NAME = 'EDEKA';
    private const COMPANY_DATA_TAB = 'EDEKA_NST';

    private Marktjagd_Service_Pinterest_PinExtension $pinExtension;
    private Marktjagd_Service_Input_PhpSpreadsheet $spreadsheetService;
    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSpreadsheet;
    private Marktjagd_Service_Transfer_Http $http;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->pinExtension = new Marktjagd_Service_Pinterest_PinExtension();
        $this->spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $this->googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->http = new Marktjagd_Service_Transfer_Http();
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;

        $campaigns = $this->googleSpreadsheet->getPinterestData(self::COMPANY_DATA_TAB);
        foreach ($campaigns as $key => $campaign) {
            if (empty($campaign['pinID'])) {
                $this->_logger->warn("Company ID: {$this->companyId}: No pin ID found for the campaign on line {$key}.");
                continue;
            }

            if (!$this->pinExtension->campaignIsActive($campaign['start'], $campaign['end'])) {
                continue;
            }

            $pinData = $this->getPinData($campaign);
            $pin = $this->pinExtension->createPin($this->companyId, $pinData);
            $pin->setCategoryOrder($campaign['categories']);

            $pinItems = $this->getPinItems($campaign);

            try {
                $this->pinExtension->generateAndUploadJSON($pin, $pinItems);
            } catch (Exception $e) {
                $this->_logger->err("Company ID: {$this->companyId}: {$e->getMessage()}");
            }
        }

        return $this->setResponseIfNoImport($companyId);
    }

    private function getPinData(array $campaign): array
    {
        return [
            'companyName' => self::COMPANY_NAME,
            'cover' => $campaign['coverPage'],
            'coverClickout' => $campaign['coverPageClickoutURL'],
            'ctaText' => $campaign['ctaText'],
            'ctaUrl' => $campaign['ctaURL'],
            'pinNumber' => $campaign['pinID']
        ];
    }

    private function getPinItems(array $campaign): Marktjagd_Collection_Pinterest_Item
    {
        $products = $this->getProductData($campaign['articleFeed']);
        $pinItems = new Marktjagd_Collection_Pinterest_Item();
        foreach ($products as $productData) {
            $pinProduct = $this->pinExtension->createPinItem($productData, PinterestConfig::ITEM_TYPE_PRODUCT);
            $pinItems->addItem($pinProduct);
        }

        $images = $this->getImageData($campaign['imagesFeed']);
        foreach ($images as $imageData) {
            $pinImage = $this->pinExtension->createPinItem($imageData, PinterestConfig::ITEM_TYPE_IMAGE);
            $pinItems->addItem($pinImage);
        }

        return $pinItems;
    }

    private function getProductData(string $articleFeed): array
    {
        $localPath = $this->http->generateLocalDownloadFolder($this->companyId);
        $localArticleFile = $this->http->getRemoteFile($articleFeed, $localPath);
        $feedData = $this->spreadsheetService->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        $productData = [];
        foreach ($feedData as $articleData) {
            if (empty($articleData['articleNumber']) || empty($articleData['category'])) {
                continue;
            }

            $productData[] = [
                'id' => $articleData['articleNumber'],
                'title' => $articleData['title'] ?? '',
                'text' => $articleData['text'] ?? '',
                'price' => $articleData['price'] ?? '',
                'url' => $articleData['url'] ?? '',
                'category' => $articleData['category'],
                'imageUrl' => $articleData['image1'] ?? ''
            ];
        }

        return $productData;
    }

    private function getImageData(string $googleSpreadsheetUrl): array
    {
        if (!preg_match('#spreadsheets/d/([^/]+)#', $googleSpreadsheetUrl, $spreadsheetIdMatch)) {
            $this->_logger->err("Company ID: {$this->companyId}: Invalid Google Spreadsheet URL: {$googleSpreadsheetUrl}");
            return [];
        }

        $feedData = $this->googleSpreadsheet->getFormattedInfos($spreadsheetIdMatch[1], 'A1', 'E', 'imagesFeed');

        $imageData = [];
        foreach ($feedData as $image) {
            $imageData[] = [
                'id' => $image['id'],
                'title' => $image['title'],
                'src' => $image['AWS LINK (needs to be public)'],
                'url' => $image['URL'],
                'category' => $image['Category name']
            ];
        }

        return $imageData;
    }
}
