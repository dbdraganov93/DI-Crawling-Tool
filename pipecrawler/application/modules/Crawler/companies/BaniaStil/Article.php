<?php
/**
 * Artikel Crawler for Bania Stil BG (ID: 80536)
 */
class Crawler_Company_BaniaStil_Article extends Crawler_Generic_Company
{
    private const URL = 'https://baniastil.com/';
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
        $productsLinks = $this->getProductUrls($this->htmParser->parseHtml($homePageContent));
        foreach ($productsLinks as $productsLink) {
            $productPageContent = $this->pageContent->getContent($productsLink);
            $productData = $this->getProductData($this->htmParser->parseHtml($productPageContent));
            $article = $this->createArticle($productData);
            $articles->addElement($article, true, 'complex', false);
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $products = [];
        foreach ($page->getElementsByTagName('div') as $tab) {
            if ('products-swiper' === $tab->getAttribute('class')) {
                foreach ($tab->getElementsByTagName('a') as $paddingsContainer) {
                    if (!empty($paddingsContainer->getAttribute('href')) && substr($paddingsContainer->getAttribute('href'), 0, 8) == 'https://' && !in_array($paddingsContainer->getAttribute('href'), $products)) {
                        $products[] = $paddingsContainer->getAttribute('href');
                    }
                }
                break;
            }
        }

        return $products;
    }

    private function getProductData(DOMDocument $page): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $product = [];
        foreach ($page->getElementsByTagName('div') as $tab) {
            if ('price detail-info-entry' === $tab->getAttribute('class')) {
                foreach ($tab->getElementsByTagName('div') as $price) {
                    if ('prev' === $price->getAttribute('class')) {
                        $product['suggestedPrice'] = $price->nodeValue;
                    } elseif ($price->getAttribute('class') === 'current') {
                        $product['price'] = $price->nodeValue;
                    }
                }

                foreach ($tab->getElementsByTagName('meta') as $info) {
                    if ('name' === $info->getAttribute('itemprop')) {
                        $product['title'] = $info->getAttribute('content');
                    } elseif ('productID' === $info->getAttribute('itemprop')) {
                        $product['articleNumber'] = $info->getAttribute('content');
                    } elseif ('image' === $info->getAttribute('itemprop')) {
                        $product['image'] = $info->getAttribute('content');
                    } elseif ('url' === $info->getAttribute('itemprop')) {
                        $product['url'] = $urlService->addParametersFromUrl($info->getAttribute('content'), $this->utms);
                    }

                }
            }
        }

        foreach ($page->getElementsByTagName('span') as $spam) {
            if ('description' === $spam->getAttribute('itemprop') && !empty($spam->getElementsByTagName('p')->item(0)->nodeValue)) {
                $product['description'] = $spam->getElementsByTagName('p')->item(0)->nodeValue;
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
            ->setPrice(preg_replace(['#\s*лв.#', '#^\.#'], ['', '0.'], $articleData['price']))
            ->setSuggestedRetailPrice(preg_replace(['#\s*лв.#', '#^\.#'], ['', '0.'], $articleData['suggestedPrice']))
            ->setUrl($articleData['url'])
            ->setImage($articleData['image']);

        return $article;
    }
}
