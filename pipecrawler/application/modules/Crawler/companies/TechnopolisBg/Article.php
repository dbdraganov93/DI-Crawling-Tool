<?php

/**
 * Article Crawler for Technopolis BG (ID: 80603) and Praktiker BG (ID: 80710)
 */

class Crawler_Company_TechnopolisBg_Article extends Crawler_Generic_Company
{
    private const COMPANY_BASE_URL = [
        80603 => 'https://www.technopolis.bg/',
        80710 => 'https://praktiker.bg/',
    ];
    private const PAGE_URL = 'bg/PredefinedProductList/c/Promotions?currentPage=';
    private const GRID_CONTAINER = 'products-grid';
    private const MAX_PRODUCTS = 300;

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
        $productUrls = $this->getProductUrls($companyId);

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($productUrls as $productUrl) {
            $productPageContent = $this->pageContent->getContent($productUrl);
            $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent));
            if (!empty($productData)) {
                $article = $this->createArticle($productData);
                $articles->addElement($article, true, 'complex', false);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(int $companyId): ?array
    {
        $baseUrl = self::COMPANY_BASE_URL[$companyId];
        $searchUrl = $baseUrl . self::PAGE_URL;

        $productsLinks = [];
        $pageNr = 0;
        while (count($productsLinks) < self::MAX_PRODUCTS) {
            $pageContent = $this->pageContent->getContent($searchUrl . $pageNr);
            $page = $this->htmParser->parseHtml($pageContent);

            $productsUrls = [];
            foreach ($page->getElementsByTagName('div') as $div) {
                if (self::GRID_CONTAINER === $div->getAttribute('class')) {
                    foreach ($div->getElementsByTagName('a') as $a) {
                        if (!preg_match('#javascript|\.pdf$#', $a->getAttribute('href'))) {
                            $productsUrls[] = $baseUrl . $this->fixProductUrl($a->getAttribute('href'));
                        }
                    }
                }
            }

            if (empty($productsUrls)) {
                break;
            }

            $productsLinks = array_merge($productsLinks, array_unique($productsUrls));
            $pageNr++;
        }

        return $productsLinks;
    }

    private function fixProductUrl(string $url): string
    {
        if (preg_match('#/?(bg/)?(.*)$#', $url, $matches)) {
            $fixedUrl = $matches[2];
            if (empty($matches[1])) {
                $fixedUrl = 'bg/' . $fixedUrl;
            }

            return $fixedUrl;
        }

        return $url;
    }

    private function getProductData(DOMDocument $page): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $product = [];
        foreach ($page->getElementsByTagName('script') as $script) {
            if ('application/ld+json' === $script->getAttribute('type')) {
                $productData = json_decode($script->nodeValue);
                if (!empty($productData->sku)) {
                    $product['url'] = $urlService->addParametersFromUrl($productData->offers->url, $this->utms);
                    $product['articleNumber'] = $productData->sku;
                    $product['title'] = $productData->name;
                    $product['description'] = $productData->description;
                    $product['price'] = $productData->offers->price;
                    $product['image'] = $productData->image[0];
                    break;
                }
            }
        }

        return $product;
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setArticleNumber($articleData['articleNumber'])
            ->setTitle($articleData['title'])
            ->setText($articleData['description'])
            ->setVisibleStart($article->getStart())
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);
    }
}
