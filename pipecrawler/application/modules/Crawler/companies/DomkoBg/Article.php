<?php

/**
 * Article Crawler for DomkoBg (ID: 80558)
 */

class Crawler_Company_DomkoBg_Article extends Crawler_Generic_Company
{
    private const URL = 'https://www.domko.com/namaleniya';

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
        $pageContent = $this->pageContent->getContent(self::URL);
        $productsUrls = $this->getProductUrls($this->htmParser->parseHtml($pageContent));

        $articles = new Marktjagd_Collection_Api_Article();
        foreach ($productsUrls as $productUrl) {
            $productPageContent = $this->pageContent->getContent($productUrl);
            $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent), $productUrl);
            if (!empty($productData)) {
                $article = $this->createArticle($productData);
                $articles->addElement($article, true, 'complex', false);
            }

        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $productsLinks = [];
        foreach ($page->getElementsByTagName('script') as $script) {
            if (preg_match('#slider_[^"]+"#', $script->nodeValue)) {
                $scriptBody = preg_replace(['#\s#', '#\\\#'], '', $script->nodeValue);
                if (preg_match_all('#url":"([^"]+)#', $scriptBody, $itemMatches)) {
                    $productsLinks = array_merge($productsLinks, $itemMatches[1]);
                }
            }
        }

        return $productsLinks;
    }

    private function getProductData(DOMDocument $page, string $url): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $product = [
            'url' => $urlService->addParametersFromUrl($url, $this->utms),
        ];

        if ($page->getElementsByTagName('h1')) {
            $product['title'] = trim($page->getElementsByTagName('h1')->item(0)->nodeValue);
        }

        $productId = '';
        foreach ($page->getElementsByTagName('input') as $input) {
            if ('product' === $input->getAttribute('name')) {
                $productId = $input->getAttribute('value');
                break;
            }
        }

        if (empty($productId)) {
            $this->_logger->err('Product ID not found: ' . $url);
            return [];
        }

        if ($priceContainer = $page->getElementById('product-price-'.$productId)) {
            $product['price'] = $this->getPriceFromElement($priceContainer);
        }

        $oldPrice = '';
        if ($oldPriceContainer = $page->getElementById('old-price-'.$productId)) {
            $oldPrice = $this->getPriceFromElement($oldPriceContainer);
        }

        if (!empty($oldPrice)) {
            $product['suggestedPrice'] = $oldPrice;
        }

        foreach ($page->getElementsByTagName('div') as $productsData) {
            if ('product attribute sku' === $productsData->getAttribute('class')) {
                foreach ($productsData->getElementsByTagName('div') as $innerDiv) {
                    if ('value' === $innerDiv->getAttribute('class')) {
                        $product['articleNumber'] = $innerDiv->nodeValue;
                        break;
                    }
                }
            }

            if ('product attribute description' === $productsData->getAttribute('class')) {
                foreach ($productsData->getElementsByTagName('div') as $innerDiv) {
                    if ('value' === $innerDiv->getAttribute('class')) {
                        $product['description'] = $innerDiv->nodeValue;
                        break;
                    }
                }
            }
        }

        foreach ($page->getElementsByTagName('img') as $image) {
            if ('gallery-placeholder__image' === $image->getAttribute('class')) {
                $product['image'] = $image->getAttribute('src');
                break;
            }
        }

        return $product;
    }

    private function getPriceFromElement(DOMElement $element): string
    {
        $price = '';
        foreach ($element->getElementsByTagName('span') as $price) {
            if ('price' === $price->getAttribute('class')) {
                $price = str_replace([' ', 'лв.'], '', $price->nodeValue);
                break;
            }
        }

        return $price;
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

        if (isset($articleData['suggestedPrice'])) {
            $article->setSuggestedRetailPrice($articleData['suggestedPrice']);
        }

        return $article;
    }
}
