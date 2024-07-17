<?php

/**
 * Article Crawler für Media Markt AT (ID: 73214)
 */

class Crawler_Company_MediaMarktAt_DiscoverHelpers
{
    private const PRODUCT_FEED = 'https://transport.productsup.io/c356556d9ccea18d6c6e/channel/321962/at-mm.wogibtswas.csv';
    private const ARTICLE_NUMBER_PATTERN = 'DC_KW_%s_%s';
    private const BROCHURE_NUMBER_PATTERN = 'MM-Discover_KW_%s';

    public function getCampaignData(): array
    {
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        return $googleSpreadsheet->getCustomerData('mediaMarktAt', 'A1', 'Z', TRUE);
    }

    public function downloadCampaignFiles(int $companyId, array $campaignData): array
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $http = new Marktjagd_Service_Transfer_Http();

        $localPath = $ftp->connect($companyId, true);

        $articleListFile = $ftp->downloadFtpToDir($campaignData['articleList'], $localPath);
        if (empty($articleListFile)) {
            throw new Exception("Company ID: {$companyId}: Article list {$campaignData['articleList']} not found on our FTP");
        }

        $coverPagePdf = $ftp->downloadFtpToDir($campaignData['brochureName'], $localPath);
        if (empty($coverPagePdf)) {
            throw new Exception("Company ID: {$companyId}: Cover page {$campaignData['brochureName']} not found on our FTP");
        }

        $productFeed = $http->getRemoteFile(self::PRODUCT_FEED, $localPath);
        if (empty($productFeed)) {
            throw new Exception("Company ID: {$companyId}: Couldn't download the product feed file: " . self::PRODUCT_FEED);
        }

        $ftp->close();

        return [
            'articleListFile' => $articleListFile,
            'coverPagePdf' => $coverPagePdf,
            'productFeed' => $productFeed,
        ];
    }

    public function getArticlesData(array $campaignData, string $productFeedFile, string $articleListFile): array
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();
        $urlService = new Marktjagd_Service_Text_Url();

        $articlesData = [];
        $week = $this->getWeekNr($campaignData['validityStart']);

        $articleList = $spreadsheetService->readFile($articleListFile, TRUE)->getElement(0)->getData();
        $productNumbers = [];
        $appendix = '';
        if ($campaignData['duplicate']) {
            $appendix = '_dup';
        }
        foreach ($articleList as $articleDetails) {
            if (!$articleDetails['Artikel-Nr.']) {
                continue;
            }

            $productNumbers[] = $articleDetails['Artikel-Nr.'];
        }

        $feedData = $spreadsheetService->readFile($productFeedFile, TRUE, ',')->getElement(0)->getData();
        foreach ($feedData as $article) {
            if (!in_array($article['article_number'], $productNumbers)) {
                continue;
            }

            $additionalProperties = '';
            if ($article['energy_label'] !== 'n/a' && $article['energy_label_new'] !== 'n/a') {
                $additionalProperties = json_encode(
                    ['energyLabel' => $article['energy_label'], 'energyLabelType' => $article['energy_label_new']]
                );
            }

            $url = $urlService->removeParameters($article['url']);
            $url = $urlService->addParametersFromUrl($url, $campaignData['utmParams']);

            $articleData = [
                'number' => sprintf(self::ARTICLE_NUMBER_PATTERN, $week, $article['article_number']) . $appendix,
                'title' => $article['title'],
                'text' => $article['text'],
                'image' => $article['image'],
                'price' => str_replace(' EUR', '', $article['price']),
                'url' => $url,
                'ean' => $article['EAN'],
                'manufacturer' => $article['Manufacturer'],
                'suggested_retail_price' => str_replace(' EUR', '', $article['suggested_retail_price']),
                'start' => $campaignData['validityStart'],
                'end' => $campaignData['validityEnd'],
                'visible_start' => $campaignData['validityStart'],
                'category' => trim(substr($article['category'], 0, strpos($article['category'], '>', 0))),
                'additional_properties' => $additionalProperties,
            ];

            $articlesData[$articleData['number']] = $articleData;
        }

        return $articlesData;
    }

    private function getWeekNr(string $date): string
    {
        return date('W', strtotime($date));
    }

    public function getBrochureNumber(array $campaignData): string
    {
        $week = $this->getWeekNr($campaignData['validityStart']);

        return sprintf(self::BROCHURE_NUMBER_PATTERN, $week);
    }
}
