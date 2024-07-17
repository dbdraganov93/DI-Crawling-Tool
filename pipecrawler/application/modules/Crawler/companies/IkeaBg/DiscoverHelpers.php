<?php

/**
 * Discover Helpers for IKEA BG (ID: 80534)
 */

class Crawler_Company_IkeaBg_DiscoverHelpers
{
    private const SPREADSHEET_ID = '1GicORRaAKJNzA1goHUFFBnCbhM_JH5pfwH9Gv13hnc4';
    private const PRODUCT_LIMIT = 200;

    private Marktjagd_Service_Text_Url $urlService;

    public function __construct()
    {
        $this->urlService = new Marktjagd_Service_Text_Url();
    }

    public function getActiveOffersData(int $companyId): array
    {
        $googleSheetsService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $offersData = $googleSheetsService->getFormattedInfos(self::SPREADSHEET_ID, 'A1', 'G');

        $activeOffers = [];
        foreach ($offersData as $offer) {
            if (empty($offer['source_url'])) {
                continue;
            }

            $offer['source_url'] = $this->urlService->removeParameters($offer['source_url']);
            $offer['number'] = $this->getBrochureNumber($offer);
            $activeOffers[] = $offer;
        }

        if (empty($activeOffers)) {
            throw new Exception('Company ID: ' . $companyId . ': No active offer found!');
        }

        return $activeOffers;
    }

    private function getBrochureNumber(array $offerData): string
    {
        $weekYear = date('W_Y', strtotime($offerData['start']));

        return $offerData['brochure_number_prefix'] . $weekYear;
    }

    public function createArticleNumber(string $brochureNumber, string $productId): string
    {
        return $brochureNumber . '_' . $productId;
    }

    private function isIkeaFamilyURL(string $url): bool
    {
        return false !== strpos($url, '/ikea-family/');
    }

    public function getProducts(array $offer, int $pageNumber = 1, array $foundProducts = []): array
    {
        $pageUrl = $this->urlService->addParameters($offer['source_url'], ['pg' => $pageNumber]);
        $pageProducts = $this->getProductsFromPage($pageUrl);
        if (empty($pageProducts)) {
            return $foundProducts;
        }

        $products = array_merge($foundProducts, $pageProducts);
        if (self::PRODUCT_LIMIT <= count($products)) {
            return array_slice($products, 0, self::PRODUCT_LIMIT);
        }

        return $this->getProducts($offer, $pageNumber + 1, $products);
    }

    private function getProductsFromPage(string $pageUrl): array
    {
        $pageContent = new Marktjagd_Service_Input_UrlReader();
        $htmParser = new Marktjagd_Service_Input_HtmlParser();

        $productsPageContent = $pageContent->getContent($pageUrl);
        if (empty($productsPageContent)) {
            return [];
        }

        $isIkeaFamily = $this->isIkeaFamilyURL($pageUrl);

        $page = $htmParser->parseHtml($productsPageContent);

        $products = [];
        foreach ($page->getElementsByTagName('div') as $div) {
            if (false !== strpos($div->getAttribute('class'), 'productTile')) {
                $productData = $this->getProductData($div, $isIkeaFamily);
                if (!empty($productData)) {
                    $products[] = $productData;
                }
            }
        }

        return $products;
    }

    private function getProductData(DOMElement $div, bool $isIkeaFamily): array
    {
        $productDataJSON = json_decode($div->getAttribute('data-product-data'), true);
        if (empty($productDataJSON)) {
            return [];
        }

        $productData = reset($productDataJSON);

        $productUrl = '';
        $brand = '';
        foreach($div->getElementsByTagName('a') as $links) {
            if ('at-product-tile__url' === $links->getAttribute('class')) {
                $productUrl = $links->getAttribute('href');
                $brand = trim($links->nodeValue);

                break;
            }
        }

        $image = '';
        foreach($div->getElementsByTagName('img') as $img) {
            if (false !== strpos($img->getAttribute('class'), 'image')) {
                $image = $img->getAttribute('src');
            }
        }

        $price = $productData['productPriceAmount'];
        $originalPrice = $productData['productOriginalPriceAmount'];
        if ($isIkeaFamily) {
            foreach ($div->getElementsByTagName('div') as $priceDiv) {
                if (false !== strpos($priceDiv->getAttribute('class'), 'price-module__price')) {
                    $priceData = json_decode($priceDiv->getAttribute('data-price-container'));
                    $price = $this->formatPrice($priceData->PriceDisplay);
                }
                if (false !== strpos($priceDiv->getAttribute('class'), 'price-module__notes')) {
                    $priceData = json_decode($priceDiv->getAttribute('data-price-container'));
                    $originalPrice = $this->formatPrice($priceData->PriceDisplay);
                }
            }
        }

        return [
            'id' => $productData['productRetailerPartNo'],
            'name' => $brand . ' ' . $productData['productTitle'],
            'price' => $price,
            'originalPrice' => $originalPrice,
            'category' => $productData['productCategory'],
            'url' => $productUrl,
            'image' => $image,
        ];
    }

    private function formatPrice(string $price): string
    {
        return str_replace([',', ' лв'], ['.', ''], $price);
    }
}
