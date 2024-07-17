<?php
/*
* Store Crawler for Jysk Sk
*/
class Crawler_Company_JyskSk_Store extends Crawler_Generic_Company {

    private const HOMEPAGE_URL = 'https://jysk.sk/predajne';
    private const STORE_URL = 'https://jysk.sk/services/store/get/%s';
    private Marktjagd_Service_Input_UrlReader $pageContent;
    private Marktjagd_Service_Input_HtmlParser $htmParser;

    public function __construct()
    {
        parent::__construct();
        $this->pageContent = new Marktjagd_Service_Input_UrlReader();
        $this->htmParser = new Marktjagd_Service_Input_HtmlParser();
    }

    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();
        $storesPageContent = $this->pageContent->getContent(self::HOMEPAGE_URL);
        $storesData = $this->getStoresData($this->htmParser->parseHtml($storesPageContent));

        foreach ($storesData as $storeData) {
            $storeInfo = json_decode($this->pageContent->getContent(sprintf(self::STORE_URL, $storeData->id)));
            $store = $this->createStore($storeInfo);
            $stores->addElement($store);
        }

        return $this->getResponse($stores, $companyId);
    }

    private function getStoresData(DOMDocument $page): array
    {
        $stores = [];
        foreach ($page->getElementsByTagName('div') as $productsData) {
            if ('block-region-content' === $productsData->getAttribute('class')) {
                foreach ($productsData->getElementsByTagName('div') as $image) {
                    if ('StoresLocatorLayout' === $image->getAttribute('data-jysk-react-component')) {
                        $stores = json_decode($image->getAttribute('data-jysk-react-properties'));
                        break;
                    }
                }
            }
        }

        return !empty($stores) ? $stores->storesCoordinates : $stores;
    }

    private function createStore(object $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData->shop_id)
            ->setTitle($storeData->name)
            ->setStreet($storeData->street)
            ->setStreetNumber($storeData->house)
            ->setZipcode($storeData->zipcode)
            ->setCity($storeData->city)
            ->setLatitude($storeData->lat)
            ->setLongitude($storeData->lon)
            ->setPhone($storeData->tel);

        return $store;
    }
}
