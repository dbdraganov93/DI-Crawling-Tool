<?php
/**
 * Store Crawler for Lidl Hr (ID: 81606)
 */
class Crawler_Company_LidlHr_Store extends Crawler_Generic_Company {

    private const COUNTRY_CODE = 'HR';
    private const URL = 'https://spatial.virtualearth.net/REST/v1/data/d82c19ca83104facab354f376bf4312b/Filialdaten-HR/Filialdaten-HR?$select=*,__Distance&$filter=Adresstyp%20eq%201&key=Aohos3hmFq5KyxKggYjGt1SgOZig5XPla8MxaCT5ChvGkWcekHhTQWM_VOq3xpKf&$format=json&jsonp=Microsoft_Maps_Network_QueryAPI_92&spatialFilter=nearby([[LAT]],[[LON]],195.75381599999997)';
    private Marktjagd_Service_Input_Page $pageService;

    public function __construct()
    {
        parent::__construct();
        $this->pageService = new Marktjagd_Service_Input_Page();
    }

    public function crawl($companyId)
    {
        $this->configurePageTimeout();
        $stores = new Marktjagd_Collection_Api_Store();

        $urls = $this->generateSearchUrls();

        foreach ($urls as $url) {
            $this->_logger->info('open ' . $url);
            $this->pageService->open($url);

            $storesResult = $this->getStoresResult($this->pageService->getPage()->getResponseBody());

            foreach ($storesResult->d->results as $storeData) {
                // Check if the store is in Croatia
                if (self::COUNTRY_CODE != $storeData->CountryRegion) {
                    continue;
                }

                $store = $this->createStores($storeData);
                $stores->addElement($store);
            }
        }

        return $this->getResponse($stores, $companyId);
    }

    private function generateSearchUrls(): array
    {
        $urlGenerator = new Marktjagd_Service_Generator_Url();
        return $urlGenerator->generateUrl(self::URL, 'coords', 0.3, 'HR');
    }

    private function getStoresResult($pageData): object
    {
        $pageData = preg_replace('#^Microsoft_Maps_Network_QueryAPI_92\(#', '', $pageData);
        $pageData = preg_replace('#\)$#', '', $pageData);

        return json_decode($pageData);
    }

    private function configurePageTimeout(int $timeout = 120):void
    {
        $page = $this->pageService->getPage();
        $page->setTimeout($timeout);
        $this->pageService->setPage($page);
    }

    private function createStores($storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData->EntityID)
            ->setStreetAndStreetNumber($storeData->AddressLine, 'HR')
            ->setZipcode($storeData->PostalCode)
            ->setCity($storeData->Locality)
            ->setLatitude($storeData->Latitude)
            ->setLongitude($storeData->Longitude);

        return $store;
    }
}
