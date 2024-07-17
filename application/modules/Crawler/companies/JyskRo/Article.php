<?php

/**
 * Article Crawler für Jysk (ID: 80489)
 */
class Crawler_Company_JyskRo_Article  extends Crawler_Generic_Company
{
    private const UTM = 'utm_source=ofertolino&utm_medium=pdf&utm_campaign=ofertolino_products';
    private const ARTICLE_FEED = 'https://feeds.datafeedwatch.com/65435/96e41e05dd9353bf90a49c5664fbcc51e904d363.xml';

    public function crawl($companyId) {
        $articles = new Marktjagd_Collection_Api_Article();
        $articlesData = $this->getArticlesFeed();
        foreach ($articlesData->channel->item as $articleData) {
            $article = $this->createArticle($articleData);
            $articles->addElement($article);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getArticlesFeed(): SimpleXMLElement
    {
        $inputService = new Marktjagd_Service_Input_Page();
        $inputService->open(self::ARTICLE_FEED);
        $xmlString = preg_replace('/&/', '&amp;', $inputService->getPage()->getResponseBody());
        $xmlString = preg_replace('/g:/', '', $xmlString);
        return simplexml_load_string($xmlString);
    }

    private function createArticle(object $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setTitle($articleData->title)
            ->setArticleNumber($articleData->id)
            ->setPrice(floatval($articleData->sale_price) ?: floatval($articleData->price))
            ->setText($articleData->description)
            ->setImage($articleData->image_link)
            ->setUrl($articleData->link . (false !== strpos($articleData->link, "?") ? '&' : '?') . self::UTM);
        if (!empty(floatval($articleData->sale_price))) {
            $article->setSuggestedRetailPrice(floatval($articleData->price));
        }

        return $article;
    }
}
