<?php
/**
 * Brochure Crawler für Penny AT (ID: 72742)
 */

class Crawler_Company_PennyAt_Article extends Crawler_Generic_Company
{

    protected const PRODUCT_API_URL = 'https://www.penny.at/api/categories/{CATEGORY}/products?page=0&pageSize=20';
    protected const SEARCH_URL = 'https://www.penny.at/offers';

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cArticles = new Marktjagd_Collection_Api_Article();

        $sPage->open($this->buildArticleApiUrl($sPage));
        $json = json_decode($sPage->getPage()->getResponseBody());

        foreach ($json->results as $arcicle) {
            $cArticles->addElement($this->createArticle($arcicle), true, 'complex', false);
        }

        return $this->getResponse($cArticles);
    }

    private function buildArticleApiUrl(Marktjagd_Service_Input_Page $page): string
    {
        $brochureRow = $page->getDomElsFromUrlByClass(self::SEARCH_URL, 'ws-show-more-tile');
        $links = explode('/', reset($brochureRow)->getAttribute('href'));
        return str_replace('{CATEGORY}', end($links), self::PRODUCT_API_URL);
    }

    private function createArticle(object $article): Marktjagd_Entity_Api_Article
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $eArticle = new Marktjagd_Entity_Api_Article();
        $eArticle->setImage($article->images[0])
            ->setArticleNumber($article->sku)
            ->setTitle(trim($article->name))
            ->setPrice($this->formatPrice($article->price->regular->value))
            ->setStart($sTimes->getDateWithAssumedYear($article->price->validityStart, 'd.m.Y'))
            ->setEnd($sTimes->getDateWithAssumedYear($article->price->validityEnd, 'd.m.Y'));

        if ($article->price->crossed) {
            $eArticle->setSuggestedRetailPrice($this->formatPrice($article->price->crossed));
        }
        return $eArticle;
    }

    private function formatPrice(int $price): float
    {
        return $price / 100;
    }
}
