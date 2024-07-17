<?php
/*
 * Article Crawler for Enikom M (ID: 80737)
 */
class Crawler_Company_EnikomMBg_Article extends Crawler_Generic_Company {

    private const MAX_PAGE = 3;
    private const URL = 'https://www.enikom-m.com/bg/catalog/promobroshura-mebeli-15_294.html?page=';

    private Marktjagd_Service_Input_UrlReader $pageContent;
    private Marktjagd_Service_Input_HtmlParser $htmParser;
    private string $utms;

    public function __construct()
    {
        parent::__construct();
        $this->pageContent = new Marktjagd_Service_Input_UrlReader();
        $this->htmParser = new Marktjagd_Service_Input_HtmlParser();

        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $this->utms = $googleSpreadsheet->getBgArticleCrawlerUTMs();
    }

    public function crawl($companyId)
    {
        $articles = new Marktjagd_Collection_Api_Article();

        for ($page = 1; $page <= self::MAX_PAGE; $page++) {

            $homePageContent = $this->pageContent->getContent(self::URL . $page);
            $productsUrls = $this->getProductUrls($this->htmParser->parseHtml($homePageContent));

            foreach ($productsUrls as $productUrl) {
                try {
                    $productPageContent = $this->pageContent->getContent($productUrl);
                    if ($productPageContent) {
                        $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent), $productUrl);
                        $article = $this->createArticle($productData);
                        $articles->addElement($article, true, 'complex', false);
                    }
                } catch (Exception $e) {
                    $this->_logger->info('Cannot crawl product with url: ' . $productUrl . ' because of: ' . $e->getMessage());
                    continue;
                }
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $productsLinks = [];

        foreach ($page->getElementById('productList')->getElementsByTagName('a') as $links) {
            if ('url' === $links->getAttribute('itemprop')) {
                if (!empty($links->getAttribute('href')) && !in_array($links->getAttribute('href'), $productsLinks)) {
                    $productsLinks[] = $links->getAttribute('href');
                }
            }
        }

        return $productsLinks;
    }

    private function getProductData(DOMDocument $page, string $url): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $product = [];
        $product['url'] = $urlService->addParametersFromUrl($url, $this->utms);
        $content = $page->getElementById('content');

        foreach ($content->getElementsByTagName('a') as $images) {
            if ('fancybox' === $images->getAttribute('class')) {
                $product['image'] = $images->getAttribute('href');
                break;
            }
        }

        $product['title'] = $content->getElementsByTagName('h1')->item(0)->nodeValue ;

        foreach ($content->getElementsByTagName('span') as $span) {
            if ('price-retail' === $span->getAttribute('class')) {
                $product['suggestedPrice'] = str_replace([' ', 'лв.'], '', $span->nodeValue);;
            }

            if ('price-sale' === $span->getAttribute('class')) {
                $product['price'] = str_replace([' ', 'лв.'], '', $span->nodeValue);
            }

            if ('number' === $span->getAttribute('class')) {
                $product['articleNumber'] = $span->nodeValue;
            }
        }

        foreach ($content->getElementsByTagName('div') as $div) {
            if ('row description' === $div->getAttribute('class')) {
                $product['description'] = strip_tags($div->getElementsByTagName('p')->item(0)->nodeValue);
                break;
            }
        }

        return $product;
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($articleData['articleNumber'])
            ->setTitle($articleData['title'])
            ->setText($articleData['description'])
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);

        if (!empty($articleData['suggestedPrice'])) {
            $article->setSuggestedRetailPrice($articleData['suggestedPrice']);
        }

        return $article;
    }
}
