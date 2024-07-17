<?php

use Marktjagd_Service_Pinterest_Config as PinterestConfig;

/**
 * Pinterest Pin Extension crawler for Poco (ID: 197)
 */
class Crawler_Company_Poco_PinExtension extends Crawler_Generic_Company
{
    private const COMPANY_NAME = 'POCO';

    private Marktjagd_Service_Pinterest_PinExtension $pinExtension;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->pinExtension = new Marktjagd_Service_Pinterest_PinExtension();
    }

    public function crawl($companyId)
    {
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->companyId = $companyId;

        $campaigns = $googleSpreadsheet->getPinterestData(self::COMPANY_NAME);
        foreach ($campaigns as $campaign) {
            if (empty($campaign['pinID'])) {
                $this->_logger->warn("Company ID: {$this->companyId}: No pin ID found for the campaign on line {$key}.");
                continue;
            }

            if (!$this->pinExtension->campaignIsActive($campaign['start'], $campaign['end'])) {
                continue;
            }

            $pinData = $this->getPinData($campaign);
            $pin = $this->pinExtension->createPin($this->companyId, $pinData);

            $products = $this->getProductData($campaign['articleFeed']);
            $pinItems = new Marktjagd_Collection_Pinterest_Item();
            foreach ($products as $productData) {
                $pinProduct = $this->pinExtension->createPinItem($productData, PinterestConfig::ITEM_TYPE_PRODUCT);
                $pinItems->addItem($pinProduct);
            }

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

    private function getProductData(string $articleFeed): array
    {
        $excelService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $http = new Marktjagd_Service_Transfer_Http();

        $localPath = $http->generateLocalDownloadFolder($this->companyId);
        $localArticleFile = $http->getRemoteFile($articleFeed, $localPath);
        $feedData = $excelService->readFile($localArticleFile, TRUE)->getElement(0)->getData();

        $productData = [];
        foreach ($feedData as $articleData) {
            $productData[] = [
                'id' => $articleData['articleNumber'],
                'title' => $articleData['title'],
                'text' => $articleData['text'],
                'price' => $articleData['price'],
                'url' => $articleData['url'],
                'category' => $articleData['category'],
                'imageUrl' => $articleData['image1']
            ];
        }

        return $productData;
    }
}
