<?php

/**
 * Store Crawler für Lidl (ID: 28)
 */
class Crawler_Company_Lidl_Store extends Crawler_Generic_Company {

    private const CONFIG_FILE = 'https://www.lidl.de/storesearch/assets/23.13-2/config/de-DE/config.json';

    public function crawl($companyId) {
        $urlGenerator = new Marktjagd_Service_Generator_Url();
        $pageService = new Marktjagd_Service_Input_Page();

        $requestKey = $this->getRequestKey();
        $searchUrl = 'https://spatial.virtualearth.net/REST/v1/data/ab055fcbaac04ec4bc563e65ffa07097/'
            . 'Filialdaten-SEC/Filialdaten-SEC?$select=*,__Distance&$filter=Adresstyp%20eq%201'
            . '&key=' . $requestKey
            . '&$format=json&jsonp=Microsoft_Maps_Network_QueryAPI_1&spatialFilter=nearby('
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . ',' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . ',1000)';

        $urls = $urlGenerator->generateUrl($searchUrl, 'coords', 0.3);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($urls as $i => $url) {
            $this->_logger->info('Checking URL ' . $i . ' of ' . count($urls));

            $pageService->open($url);
            $page = $pageService->getPage()->getResponseBody();

            $page = preg_replace(
                ['#^Microsoft_Maps_Network_QueryAPI_1\(#', '#\)$#'],
                ['',''],
                $page);

            $storesJson = json_decode($page);

            foreach ($storesJson->d->results as $storeData) {
                if ('DE' !== $storeData->CountryRegion) {
                    continue;
                }

                $store = $this->createStore($storeData);
                $stores->addElement($store);
            }
        }

        return $this->getResponse($stores, $companyId);
    }

    private function getRequestKey(): string
    {
        $ch = curl_init(self::CONFIG_FILE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.lidl.de/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Origin: ' . 'https://www.lidl.de/',
            'X-Requested-With: XMLHttpRequest'
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        $config = json_decode($result);

        return $config->storesearch->key;
    }

    private function createStore(object $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        return $store->setStoreNumber($storeData->EntityID)
            ->setStreetAndStreetNumber($storeData->AddressLine)
            ->setZipcode($storeData->PostalCode)
            ->setCity(ucwords(strtolower($storeData->Locality)))
            ->setLatitude($storeData->Latitude)
            ->setLongitude($storeData->Longitude)
            ->setDistribution($storeData->AR)
            ->setStoreHoursNormalized($storeData->OpeningTimes);
    }
}
