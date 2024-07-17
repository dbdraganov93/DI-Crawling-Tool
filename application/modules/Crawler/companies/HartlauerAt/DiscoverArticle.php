<?php

/*
 * Discover Article Crawler for Hartlauer AT (ID: 73468)
 */

class Crawler_Company_HartlauerAt_DiscoverArticle extends Crawler_Generic_Company
{
    private array $campaignData;
    private Crawler_Company_HartlauerAt_DiscoverHelpers $helpers;

    public function __construct()
    {
        parent::__construct();

        $this->helpers = new Crawler_Company_HartlauerAt_DiscoverHelpers();
        $this->campaignData = $this->helpers->getCampaignData();
    }

    public function crawl($companyId)
    {
        if (empty($this->campaignData)) {
            return $this->setResponseIfNoImport($companyId);
        }

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($this->campaignData as $campaign) {
            $feedData = $this->helpers->getProductFeedData($campaign['productFeed'], $companyId);
            foreach ($feedData as $productData) {
                $articleData = $this->getArticleData($productData, $campaign);
                $article = $this->createArticle($articleData);
                $articles->addElement($article, true, 'complex', false);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getArticleData(array $feedData, array $campaignData): array
    {
        $articleData = [
            'articleNumber' => $this->helpers->getArticleNumber($feedData['articleNumber'], $campaignData),
            'title' => $feedData['title'],
            'url' => $feedData['url'],
            'price' => $this->formatPrice($feedData['price']),
            'suggestedRetailPrice' => $this->formatPrice($feedData['suggestedRetailPrice']),
            'start' => $campaignData['start'],
            'end' => $campaignData['end'],
            'image1' => $feedData['image1'],
            'additional' => '',
        ];

        if (!empty($feedData['priceLabel'])) {
            $articleData['additional'] = json_encode(['priceLabel' => trim($feedData['priceLabel'])]);
        }

        return $articleData;
    }

    private function formatPrice(string $price): string
    {
        return str_replace(',', '.', $price);
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setArticleNumber($articleData['articleNumber'])
            ->setTitle($articleData['title'])
            ->setUrl($articleData['url'])
            ->setPrice($articleData['price'])
            ->setSuggestedRetailPrice($articleData['suggestedRetailPrice'])
            ->setStart($articleData['start'])
            ->setEnd($articleData['end'])
            ->setImage($articleData['image1'])
            ->setAdditionalProperties($articleData['additional']);
    }
}