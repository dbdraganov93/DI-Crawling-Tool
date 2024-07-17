<?php

/**
 * Discover Article Crawler für Douglas (ID: 326)
 */

class Crawler_Company_Douglas_DiscoverArticle extends Crawler_Generic_Company
{
    public const PRODUCT_FEED = 'https://get.cpexp.de/zaGV6Kes6Blvf2P6DgoWjjDiuZg9EmpwJ4p8NJIv9EW2AL28pJmTSoAtd6YDq4ii4pn-fs-YakTBhp-tc84Hmg~~/de-display_offeristaprospectingde.csv';

    public function crawl($companyId)
    {
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $articles = new Marktjagd_Collection_Api_Article();

        $campaignData = $googleSpreadsheet->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', 'A1', 'F', 'douglasGer')[0];

        $articlesFeed = self::getArticlesFeed($companyId);
        if (empty($articlesFeed)) {
            return $this->getSuccessResponse();
        }

        foreach ($articlesFeed as $articleData) {
            if (!preg_match('#in\s*stock#', $articleData['availability'])) {
                continue;
            }

            $article = $this->createArticle($articleData, $campaignData);

            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles);
    }

    public static function getArticlesFeed(int $companyId): array
    {
        $http = new Marktjagd_Service_Transfer_Http();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localFolder = $http->generateLocalDownloadFolder($companyId);
        $articlesFile = $http->getRemoteFile(self::PRODUCT_FEED, $localFolder);

        return $spreadsheetService->readFile($articlesFile, TRUE)->getElement(0)->getData();
    }

    private function createArticle(array $articleData, array $campaignData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        $article->setArticleNumber($campaignData['brochureNumber'] . '_' . $articleData['article_number'])
            ->setTitle($articleData['title'])
            ->setText($articleData['text'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image'])
            ->setPrice($this->formatPrice($articleData['price']))
            ->setStart($campaignData['validityStart'])
            ->setEnd($campaignData['validityEnd'])
            ->setVisibleStart($article->getStart());

        $additionalProperties = [];

        if ('true' === $articleData['isSale']) {
            $additionalProperties['priceLabel'] = 'SALE';
        }
        if (preg_match('#(?<price>.*)/(?<unit>[^)]*)#', $articleData['product_base_price'], $matches)) {
            $additionalProperties['unitPrice'] = [
                'value' => trim(preg_replace('#,#', '.', $matches['price'])),
                'unit' => trim($matches['unit'])
            ];
        }

        if (!empty($additionalProperties)) {
            $article->setAdditionalProperties(json_encode($additionalProperties));
        }

        return $article;
    }

    private function formatPrice(float $price): string
    {
        return sprintf('%.2f', $price);
    }

}