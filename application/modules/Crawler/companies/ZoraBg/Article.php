<?php
/*
 * Article Crawler for Zora BG (ID: 80564)
 */
class Crawler_Company_ZoraBg_Article extends Crawler_Generic_Company
{
    private const URL = 'https://zora.bg/app/feed/broshura';
    private const UTM_PARAMS = '?utm_source=broshura.bg&utm_medium=product_feed';
    private  DateTime $currentDate;

    public function crawl($companyId)
    {
        $this->currentDate = new DateTime();
        $articles = new Marktjagd_Collection_Api_Article();

        $productsFeed = $this->getProductsFeed();

        foreach ($productsFeed as $productData) {
            $article = $this->createArticle($productData);
            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductsFeed(): SimpleXMLElement
    {
        $pageService = new Marktjagd_Service_Input_Page(true);
        $pageService->open(self::URL);

        return new SimpleXMLElement($pageService->getPage()->getResponseBody());
    }

    private function createArticle(object $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber(sprintf('%s-%s',$articleData->id, $this->currentDate->format('m-y')))
            ->setTitle(htmlspecialchars((string)$articleData->title))
            ->setText(strip_tags((string)$articleData->description))
            ->setPrice((string)$articleData->price)
            ->setUrl((string)($articleData->url) . self::UTM_PARAMS)
            ->setImage(reset($articleData->images->image));

        if (!empty($articleData->original_price)) {
            $article->setSuggestedRetailPrice($articleData->original_price);
        }

        return $article;
    }
}
