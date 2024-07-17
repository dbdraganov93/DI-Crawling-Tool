<?php
/*
 * Article Crawler for Kaufland (ID: 80550)
 */
class Crawler_Company_KauflandBg_Article extends Crawler_Generic_Company {

    private const URL = 'https://www.kaufland.bg';
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
        $categoriesUrls = $this->getProductCategoriesUrls($this->htmParser->parseHtml($homePageContent));
        foreach ($categoriesUrls as $categoryUrl) {
            $categoryPageContent = $this->pageContent->getContent(self::URL . $categoryUrl);
            $productsData = $this->getProductData($this->htmParser->parseHtml($categoryPageContent));
            foreach ($productsData as $productData) {
                $article = $this->createArticle($productData);
                $articles->addElement($article, true, 'complex', false);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductCategoriesUrls(DOMDocument $page): ?array
    {
        $categoryLinks = [];
        foreach ($page->getElementsByTagName('a') as $links) {
            if ('m-offer-tile__link u-button--hover-children' === $links->getAttribute('class')) {
                if (!empty($links->getAttribute('href')) && !in_array($links->getAttribute('href'), $categoryLinks)) {
                    $categoryLinks[] = $links->getAttribute('href');
                }
            }
        }

        return $categoryLinks;
    }

    private function getProductData(DOMDocument $page): array
    {
        $urlService = new Marktjagd_Service_Text_Url();
        $products = [];

        foreach ($page->getElementsByTagName('h2') as $validity) {
            if ('' === $validity->getAttribute('class')) {
                preg_match_all('/(\d{2}\.\d{2}\.\d{4})/', $validity->nodeValue, $dateMatch);
                break;
            }
        }

        $validityFrom = !empty($dateMatch) ? reset($dateMatch)[0] : '';
        $validityTo = !empty($dateMatch) ? reset($dateMatch)[1] : '';

        foreach ($page->getElementsByTagName('a') as $productsData) {
            if ('m-offer-tile__link u-button--hover-children' === $productsData->getAttribute('class')) {
                $productUrl = self::URL . $productsData->getAttribute('href');

                $product = [];
                $product['validFrom'] = $validityFrom;
                $product['validTo'] = $validityTo;
                $product['url'] = $urlService->addParametersFromUrl($productUrl, $this->utms);
                preg_match('/so_id=(\d+)/', $product['url'], $id);
                $product['articleNumber'] = !empty($id) ? $id[1] : '';
                $product['image'] = $productsData->getElementsByTagName('img')->item(0)->getAttribute('data-src');

                foreach ($productsData->getElementsByTagName('h5') as $title) {
                    if ('m-offer-tile__subtitle' === $title->getAttribute('class')) {
                        $product['title'] = trim(preg_replace(['/\r?\n/', '/\s+/'], ['', ' '], $title->nodeValue));
                    }
                }

                foreach ($productsData->getElementsByTagName('h4') as $title) {
                    if ('m-offer-tile__title' === $title->getAttribute('class')) {
                        $product['title'] .= ' ' .trim(preg_replace(['/\r?\n/', '/\s+/'], ['', ' '], $title->nodeValue));
                    }
                }

                foreach ($productsData->getElementsByTagName('div') as $info) {
                    if ('a-pricetag__old-price' === $info->getAttribute('class') && !empty($info->getElementsByTagName('span')->item(0)->nodeValue)) {
                        $product['suggestedPrice'] = preg_replace('/\s+/', '', $info->getElementsByTagName('span')->item(0)->nodeValue);
                    }
                    if ('a-pricetag__price' == $info->getAttribute('class')) {
                        $product['price'] = preg_replace('/\s+/', '', $info->nodeValue);
                    }
                }

                $products[] = $product;
            }
        }

        return $products;
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($articleData['articleNumber'])
            ->setTitle($articleData['title'])
            ->setStart($articleData['validFrom'])
            ->setEnd($articleData['validTo'])
            ->setVisibleStart($article->getStart())
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);

        if (!empty($articleData['suggestedPrice'])) {
            $article->setSuggestedRetailPrice($articleData['suggestedPrice']);
        }

        return $article;
    }
}
