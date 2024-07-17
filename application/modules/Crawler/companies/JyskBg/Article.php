<?php
/**
 * Article crawler for Jysk BG ID: 80648
 */
class Crawler_Company_JyskBg_Article extends Crawler_Generic_Company
{
    private const NAMESPACE = 'http://base.google.com/ns/1.0';
    private const PRODUCT_STATUS = 'In Stock';
    private const REGEX_PRICE = '/\d+(\.\d+)?/';
    private const PRODUCT_FEED = 'https://feeds.datafeedwatch.com/65176/4f6f9d4eef735aafeefb5cc8d65ecef7e8dbbb82.xml';

    public function crawl($companyId)
    {
        $articles = new Marktjagd_Collection_Api_Article();
        $productsFeed = simplexml_load_file(self::PRODUCT_FEED);

        if (!$productsFeed) {
            throw new Exception('Company ID: ' . $companyId . ': Failed to load XML');
        }

        foreach ($productsFeed->channel->item as $item) {
            $productData = $this->normalizeProductData($item);

            // Skip products that are not in stock
            if (self::PRODUCT_STATUS != $productData->availability) {
                continue;
            }

            $articles->addElement($this->createArticle($productData), TRUE);
        }

        return $this->getResponse($articles);
    }

    private function createArticle(object $data): Marktjagd_Entity_Api_Article
    {
        $article = new Marktjagd_Entity_Api_Article();
        $article->setArticleNumber($data->id)
            ->setTitle($data->title)
            ->setText($data->description)
            ->setUrl($data->link)
            ->setImage($data->image_link);

        $article->setPrice(!empty($data->sale_price) ? $data->sale_price : $data->price);
        if ($data->sale_price) {
            $article->setSuggestedRetailPrice($data->price);
        }

        return $article;
    }

    private function normalizeProductData(object $item): object
    {
        $item = $item->children(self::NAMESPACE);
        $item->price = $this->formatPrice($item->price);

        if ('' != $item->sale_price) {
            $item->sale_price = $this->formatPrice($item->sale_price);
        }

        return $item;
    }

    private function formatPrice(string $price): string
    {
        $formattedPrice = '';
        if (preg_match(self::REGEX_PRICE, $price, $matches)) {
            $formattedPrice =  number_format((float) reset($matches), 2, '.', '');
        } else {
            $this->_logger->err('Price not found: ' . $price);
        }

        return $formattedPrice;
    }
}
