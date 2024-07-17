<?php

/**
 * Discover Crawler für IKEA BG (ID: 80534)
 */

class Crawler_Company_IkeaBg_DiscoverArticle extends Crawler_Generic_Company
{
    private const HOST = 'www.ikea.bg';
    private const BASE_URL = 'https://' . self::HOST .  '/';

    private Crawler_Company_IkeaBg_DiscoverHelpers $helpers;

    public function __construct()
    {
        parent::__construct();

        $this->helpers = new Crawler_Company_IkeaBg_DiscoverHelpers();
    }

    public function crawl($companyId)
    {
        $offers = $this->helpers->getActiveOffersData($companyId);

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($offers as $offer) {
            $products = $this->helpers->getProducts($offer);
            if (empty($products)) {
                $this->_logger->err('Company ID: ' . $companyId . ': No products found on "' . $offer['source_url'] . '"!');
                continue;
            }

            foreach ($products as $product) {
                $article = $this->createArticle($product, $offer);
                $articles->addElement($article, true, 'complex', false);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function createArticle(array $productData, array $offerData): Marktjagd_Entity_Api_Article
    {
        $urlService = new Marktjagd_Service_Text_Url();
        $article = new Marktjagd_Entity_Api_Article();

        if (false === strpos($productData['url'], self::HOST)) {
            $productData['url'] = $urlService->changeBaseUrl($productData['url'], self::BASE_URL);
        }

        $articleNumber = $this->helpers->createArticleNumber($offerData['number'], $productData['id']);

        return $article->setArticleNumber($articleNumber)
            ->setTitle($productData['name'])
            ->setPrice($productData['price'])
            ->setSuggestedRetailPrice($productData['originalPrice'])
            ->setImage($productData['image'])
            ->setUrl($urlService->addParametersFromUrl($productData['url'], $offerData['utm_params']))
            ->setStart($offerData['start'])
            ->setEnd($offerData['end'])
            ->setVisibleStart($article->getStart());
    }
}
