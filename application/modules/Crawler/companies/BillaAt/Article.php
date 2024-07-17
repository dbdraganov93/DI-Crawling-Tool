<?php

/**
 * Article crawler for Billa AT (ID: 73282)
 */
class Crawler_Company_BillaAt_Article extends Crawler_Generic_Company
{
    private const MAX_ARTICLES = 300;
    private const PRODUCT_FEEDS_URL = 'https://shop.billa.at/api/products/feed';

    public function crawl($companyId)
    {
        $articlesData = $this->getArticlesData();
        $articles = new Marktjagd_Collection_Api_Article();

        foreach ($articlesData as $articleData) {
            $article = $this->createArticle($articleData);
            $articles->addElement($article);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getArticlesData(): array
    {
        $articlesFeedData = [];
        $xmlContent = file_get_contents(self::PRODUCT_FEEDS_URL);
        if (false === $xmlContent) {
            throw new Exception('Could not get XML content from ' . self::PRODUCT_FEEDS_URL);
        }

        $xmlContent = str_replace('g:', '', $xmlContent);
        $xml = simplexml_load_string($xmlContent);
        $articleCount = 0;
        foreach ($xml->channel->item as $item) {
            $articleCount++;
            $articlesFeedData[] = $item;
            if (self::MAX_ARTICLES === $articleCount) {
                break;
            }
        }

        return $articlesFeedData;
    }

    private function createArticle(object $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        return $article->setArticleNumber($articleData->id)
                ->setTitle($articleData->title)
                ->setText(strip_tags($articleData->description))
                ->setUrl($articleData->link)
                ->setManufacturer($articleData->brand)
                ->setEan($articleData->gtin)
                ->setImage($articleData->image_link)
                ->setPrice(substr($articleData->price, 0 , -4));
    }
}
