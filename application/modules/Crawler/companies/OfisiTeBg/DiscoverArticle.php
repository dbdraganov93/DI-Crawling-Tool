<?php

/*
 * Article Crawler for OfisiTe BG (ID: 80617)
 */

class Crawler_Company_OfisiTeBg_DiscoverArticle extends Crawler_Generic_Company
{
    private const CAMPAIGN_SPREADSHEET_ID = '1Sil1e49Z7edQA3RLF9jxaCULQIC9Rzv2ie8xa9wxXKE';
    private const ARTICLE_NUMBER_FORMAT = '%s_Disc_%s';

    private Marktjagd_Service_Input_UrlReader $pageContent;
    private Marktjagd_Service_Input_HtmlParser $htmlParser;
    private array $campaign;

    public function __construct()
    {
        parent::__construct();

        $this->pageContent = new Marktjagd_Service_Input_UrlReader();
        $this->htmlParser = new Marktjagd_Service_Input_HtmlParser();

        $this->campaign = self::getCampaignData();
    }

    public function crawl($companyId)
    {
        $articles = new Marktjagd_Collection_Api_Article();
        $pageContent = $this->pageContent->getContent($this->campaign['source_url']);
        $productUrls = $this->getProductUrls($this->htmlParser->parseHtml($pageContent));
        foreach ($productUrls as $productUrl) {
            $productPageContent = $this->pageContent->getContent($productUrl);
            if (!empty($productPageContent)) {
                $dom = $this->htmlParser->parseHtml($productPageContent);
                if (!empty($dom)) {
                    $productData = $this->getProductData($dom, $productUrl);
                    $article = $this->createArticle($productData);
                    $articles->addElement($article, true, 'complex', false);
                }
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    public static function getCampaignData(): array
    {
        $googleSpreadsheetRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $campaignData = $googleSpreadsheetRead->getFormattedInfos(self::CAMPAIGN_SPREADSHEET_ID, 'A1', 'F');

        return reset($campaignData);
    }

    private function getProductUrls(DOMDocument $page): ?array
    {
        $productLinks = [];
        foreach ($page->getElementsByTagName('a') as $link) {
            if (preg_match('#_product-image-thumb#', $link->getAttribute('class'))) {
                $url = $link->getAttribute('href');
                if (!empty($url) && preg_match('#^https?://#', $url) && !in_array($url, $productLinks)) {
                    $productLinks[] = $link->getAttribute('href');
                }
            }
        }

        return $productLinks;
    }

    private function getProductData(DOMDocument $page, string $url): array
    {
        $urlService = new Marktjagd_Service_Text_Url();

        $productData = [];
        foreach ($page->getElementsByTagName('script') as $script) {
            if (preg_match('#@context#', $script->nodeValue)) {
                $jsonData = json_decode($script->nodeValue);

                $productData['number'] = self::getArticleNumber($url, $this->campaign['start']);
                $productData['title'] = trim($jsonData->name);
                $productData['description'] = trim(preg_replace(['#&nbsp;#', '# #'], '', $jsonData->description));
                $productData['price'] = $jsonData->offers->price;
                $productData['image'] = preg_replace(['#\[#', '#]#'], ['%5B', '%5D'], $jsonData->image);
                $productData['url'] = $urlService->addParametersFromUrl($url, $this->campaign['utm_params']);
                $productData['from'] = $this->campaign['start'];
                $productData['to'] = $this->campaign['end'];

                if (isset($jsonData->offers->highPrice)) {
                    $suggestedPrice = $jsonData->offers->highPrice;
                    if ($suggestedPrice > $productData['price']) {
                        $hasDiscount = false;
                        foreach ($page->getElementsByTagName('div') as $div) {
                            if (preg_match('#_product-details-price-old price-old-js#', $div->getAttribute('class'))) {
                                $hasDiscount = true;
                            }
                        }

                        if ($hasDiscount) {
                            $productData['suggestedPrice'] = $suggestedPrice;
                        }
                    }
                }
            }
        }

        return $productData;
    }

    public static function getArticleNumber(string $productUrl, string $startDate): string
    {
        $productHash = md5($productUrl); // hashing the URL because the product id and the gtin14 are inconsistent.

        return sprintf(self::ARTICLE_NUMBER_FORMAT, $productHash, date('W_Y', strtotime($startDate)));
    }

    private function createArticle(array $articleData): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($articleData['number'])
            ->setTitle($articleData['title'])
            ->setText($articleData['description'])
            ->setStart($articleData['from'])
            ->setEnd($articleData['to'])
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
