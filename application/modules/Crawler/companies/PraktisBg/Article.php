<?php
/*
 * Article Crawler for Praktis (ID: 80588)
 */
class Crawler_Company_PraktisBg_Article extends Crawler_Generic_Company {

    private const URL = 'https://praktis.bg/?new=1';
    private const MAX_TRIES = 3;

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
        $homePageContent = $this->getPageContent(self::URL);
        $productUrls = $this->getProductUrls($this->htmParser->parseHtml($homePageContent));
        if (empty($productUrls)) {
            $this->_logger->err('Company ID: ' . $companyId . ': No product URLs found in "' . self::URL . '"', Zend_Log::ERR);
        }

        foreach ($productUrls as $productUrl) {
            $productPageContent = $this->getPageContent($productUrl);
            if (empty($productPageContent)) {
                $this->_logger->err('Company ID: ' . $companyId . ': No product page content found for "' . $productUrl . '"', Zend_Log::ERR);
                continue;
            }

            $productPage = $this->htmParser->parseHtml($productPageContent);
            $productData = $this->getProductData($productPage, $productUrl);
            $article = $this->createArticle($productData);
            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function isInvalidUrl(string $url): bool
    {
        return preg_match('#\#$#', $url)
            || preg_match('#/wishlist/#', $url)
            || preg_match('#\{\{#', $url)
            || preg_match('#\.pdf$#', $url);
    }

    private function getPageContent(string $url): string
    {
        $pageContent = '';
        $tries = 0;

        do {
            try {
                $pageContent = $this->pageContent->getContent($url);
            } catch (Exception $e) {
                Zend_Debug::dump($e->getMessage());
            }

            $tries++;
        }
        while (empty($pageContent) && $tries < self::MAX_TRIES);

        return $pageContent;
    }

    private function getProductUrls(DOMDocument $page): array
    {
        $urlList = [];
        foreach ($page->getElementsByTagName('div') as $containers) {
            if (preg_match('#prodSlider#', $containers->getAttribute('class'))) {
                foreach ($containers->getElementsByTagName('a') as $links) {
                    $link = $links->getAttribute('href');
                    if (!in_array($link, $urlList) && !$this->isInvalidUrl($link)) {
                        $urlList[] = $link;
                    }
                }
            }
        }

        return $urlList;
    }

    private function getProductData(DOMDocument $page, string $url): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $number = '';
        $skuContainer = $page->getElementById('inner-sku');
        if ($skuContainer) {
            $number = $skuContainer->nodeValue;
        }
        if (empty($number)) {
            $this->_logger->err('No product number found for "' . $url . '"', Zend_Log::ERR);
            return [];
        }

        $title = '';
        $titleContainer = $page->getElementsByTagName('h1');
        if ($titleContainer) {
            $title = $titleContainer->item(0)->nodeValue;
        }

        $image = '';
        $imageContainer = $page->getElementById('gallerySlider');
        if ($imageContainer) {
            $image = $imageContainer->getElementsByTagName('img')[0]->getAttribute('src');
        }

        $description = '';
        $descriptionContainer = $page->getElementById('description-tab-content');
        if ($descriptionContainer) {
            $description = $descriptionContainer->getElementsByTagName('p')->item(1)->nodeValue;
        }

        $price = '';
        foreach ($page->getElementsByTagName('meta') as $meta) {
            if ('product:price:amount' === $meta->getAttribute('property')) {
                $price = sprintf('%.2f', $meta->getAttribute('content'));
            }
        }

        return [
            'number' => $number,
            'title' => $title,
            'url' => $urlService->addParametersFromUrl($url, $this->utms),
            'image' => $image,
            'description' => $description,
            'price' => $price,
        ];
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();

        return $article->setArticleNumber($articleData['number'])
            ->setTitle($articleData['title'])
            ->setText($articleData['description'])
            ->setPrice($articleData['price'])
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);
    }
}