<?php
/**
 * Article crawler for matraci and onesleep ID's: 90169 and 90171
 */
class Crawler_Company_MatraciBg_Article extends Crawler_Generic_Company
{
    private const ITEM_PATTERN = '/items:\s*\[{(.*?)\]\s*\}/s';
    private const MAX_PAGES = 10;
    private const WEBSITES = [
        '90169' => [
            'website' => 'https://matraci.bg/collections/promotsii?page=%s',
            'productUrl' => 'https://matraci.bg/collections/promotsii/products/milano?variant=%s',
        ],
        '90171' => [
            'website' => 'https://ro.onesleep.com/collections/promotii?page=%s',
            'productUrl' => 'https://ro.onesleep.com/collections/promotii/products/protectie-de-saltea-impermiabila?variant=%s',
        ],
    ];

    private array $companyData = [];

    public function __construct()
    {
        parent::__construct();

        $this->pageContent = new Marktjagd_Service_Input_UrlReader();
        $this->htmlParser = new Marktjagd_Service_Input_HtmlParser();
    }
    public function crawl($companyId)
    {
        $this->companyData = self::WEBSITES[$companyId];
        $articles = new Marktjagd_Collection_Api_Article();
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $pageContent = $this->pageContent->getContent(sprintf($this->companyData['website'], $page));
            $products = $this->getProducts($this->htmlParser->parseHtml($pageContent));

            if (empty($products)) {
                $this->_logger->info(sprintf('No products found on page %s', $page));
                continue;
            }

            foreach ($products as $product) {
                $article = $this->createArticle($product);
                $articles->addElement($article, TRUE);
            }
        }

        return $this->getResponse($articles, $companyId);
    }

    private function getProducts(DOMDocument $page): ?array
    {
        $items = [];
        foreach ($page->getElementsByTagName('script') as $script) {
            if ('module' == $script->getAttribute('type')) {
                if (preg_match(self::ITEM_PATTERN, $script->nodeValue, $matches)) {
                    // Attempt to fix the extracted string into valid JSON
                    $jsonString = '[{' . $matches[1] . ']';
                    $preparedJson = preg_replace('/(\w+):/i', '"$1":', $jsonString);
                    $preparedJson = str_replace(":'", ':"', $preparedJson);
                    $preparedJson = str_replace("',", '",', $preparedJson);
                    $preparedJson = str_replace("'}", '"}', $preparedJson);
                    $preparedJson = str_replace("'", "\'", $preparedJson); // Escape single quotes
                    $preparedJson = rtrim($preparedJson, ",]"); // Remove trailing comma before closing bracket
                    $preparedJson .= ']'; // Ensure proper JSON array closure
                    $items = json_decode($preparedJson);

                    if (JSON_ERROR_NONE !== json_last_error()) {
                        $this->_logger->err(sprintf('Error decoding JSON: %s', json_last_error_msg()));
                    } else {
                        break;
                    }
                }
            }
        }

        return $items;
    }

    private function createArticle(object $data): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($data->productId)
            ->setTitle($data->name)
            ->setUrl(sprintf($this->companyData['productUrl'], $data->variantId))
            ->setImage('https:' . $data->image)
            ->setPrice($data->price)
            ->setPriceIsVariable(true)
            ->setNational(TRUE);

        return $article;
    }
}
