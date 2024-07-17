<?php
/*
 * Article Crawler for Como (ID: 80730)
 */
class Crawler_Company_ComoBg_Article extends Crawler_Generic_Company {

    private const URL = 'https://como.bg/broshurabg?limit=all';
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
        $homePageContent = $this->pageContent->getContent(self::URL);
        $productsUrls = $this->getProductUrls($this->htmParser->parseHtml($homePageContent));

        foreach ($productsUrls as $productUrl) {
            $productPageContent = $this->pageContent->getContent($productUrl);
            $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent), $productUrl);
            $article = $this->createArticle($productData);
            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $productsLinks = [];
        foreach ($page->getElementById('products-list')->getElementsByTagName('a') as $links) {
            if ('product-image ajax-loading-stripes' === $links->getAttribute('class')) {
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
        foreach ($page->getElementsByTagName('div') as $productsData) {
            if ('product-page row' === $productsData->getAttribute('class')) {
                $product['title'] = $productsData->getElementsByTagName('h1')->item(0)->nodeValue;
                $product['url'] = $urlService->addParametersFromUrl($url, $this->utms);

                foreach ($productsData->getElementsByTagName('a') as $image) {
                    if ('product-thumbnail-link active' === $image->getAttribute('class')) {
                        $product['image'] = $image->getAttribute('data-image');
                        break;
                    }
                }

                foreach ($productsData->getElementsByTagName('div') as $descriptionSection) {
                    if ('description' === $descriptionSection->getAttribute('class')) {
                        foreach ($descriptionSection->getElementsByTagName('div') as $description) {
                            if ('std' === $description->getAttribute('class')) {
                                $product['description'] = preg_replace(['/\r?\n/', '/\s+/'], ['', ' '], strip_tags($description->nodeValue));
                            }
                        }
                    }

                    if ('shadow-price-box product-shop-container' == $descriptionSection->getAttribute('class')) {
                        foreach ($descriptionSection->getElementsByTagName('span') as $price) {
                            if ('price' === $price->getAttribute('class')) {
                                $product['price'] = str_replace([' ', 'лв.'], '', $price->nodeValue);
                            }
                        }
                    }
                }

                foreach ($productsData->getElementsByTagName('div') as $table) {
                    if ('product-info__sku' === $table->getAttribute('class')) {
                        $product['articleNumber'] = str_replace('Кат.№', '', preg_replace('/\s+/', '', $table->nodeValue));
                    }
                }

                if (empty($product['articleNumber'])) {
                    $product['articleNumber'] = $productsData->getElementsByTagName('td')->item(1)->nodeValue;
                }

                $product['articleNumber'] = str_replace(' ', '', $product['articleNumber']);
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
            ->setVisibleStart($article->getStart())
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);

        return $article;
    }
}
