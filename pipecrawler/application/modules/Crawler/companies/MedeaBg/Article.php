
<?php
/*
 * Article Crawler for Medea (ID: 80640)
 */
class Crawler_Company_MedeaBg_Article extends Crawler_Generic_Company
{
    private const MAX_PAGE = 4;
    private const URL = 'https://aptekamedea.bg/promocii?limit=50&p=';
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
                $productPageContent = $this->pageContent->getContent($productUrl);
                $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent), $productUrl);
                $article = $this->createArticle($productData);
                $articles->addElement($article, true, 'complex', false);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $productsLinks = [];

        foreach ($page->getElementsByTagName('a') as $links) {
            if ('productName' === $links->getAttribute('class')) {
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

        foreach ($page->getElementsByTagName('meta') as $meta) {
            if ('name' === $meta->getAttribute('itemprop')) {
                $product['title'] = $meta->getAttribute('content');
            }

            if ('description' === $meta->getAttribute('itemprop')) {
                $product['description'] = $meta->getAttribute('content');
            }
        }

        foreach ($page->getElementsByTagName('input') as $input) {
            if ('product' === $input->getAttribute('name')) {
                $product['articleNumber'] = $input->getAttribute('value');
                break;
            }
        }

        foreach ($page->getElementsByTagName('img') as $image) {
            if ('sp-image' === $image->getAttribute('class')) {
                $product['image'] = $image->getAttribute('src');
                break;
            }
        }

        $product['price'] = str_replace([' ', 'лв.'], '', $page->getElementById('product-price-' . $product['articleNumber'])->nodeValue);
        $product['suggestedPrice'] = str_replace([' ', 'лв.'], '', $page->getElementById('old-price-' . $product['articleNumber'])->nodeValue);

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
