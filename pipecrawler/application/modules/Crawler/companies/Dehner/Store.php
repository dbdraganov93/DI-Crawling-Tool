<?php

/*
 * Store Crawler für Dehner Gartencenter (ID: 355 and 73079)
 */

class Crawler_Company_Dehner_Store extends Crawler_Generic_Company
{
    private const COMPANY_DATA_MAP = [
        '355' => [
            'searchUrl' => 'https://www.dehner.de/markt/',
            'website' => 'https://www.dehner.de/garten-sale/',
            'countryCode' => 'de',
        ],
        '73079' => [
            'searchUrl' => 'https://www.dehner.at/markt/',
            'website' => 'https://www.dehner.at/garten-sale/',
            'countryCode' => 'at',
        ],
    ];

    public function crawl($companyId)
    {
        $companyData = self::COMPANY_DATA_MAP[$companyId];
        $pageService = new Marktjagd_Service_Input_Page();

        $pageService->open($companyData['searchUrl']);
        $page = $pageService->getPage()->getResponseBody();
        $pattern = '#\.initializeShopFinder\(\s*([^]]+?]),#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $storeList = json_decode($storeListMatch[1]);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storeList as $jsonStoreData) {
            if (!preg_match('#' . $companyData['countryCode'] . '#', $jsonStoreData->countryCode)) {
                continue;
            }

            $store = $this->createStore($jsonStoreData, $companyData);
            $stores->addElement($store);
        }

        return $this->getResponse($stores, $companyId);
    }

    private function createStore(object $jsonStoreData, array $companyData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();

        return $store->setStreetAndStreetNumber($jsonStoreData->street)
            ->setZipcode($jsonStoreData->zip)
            ->setCity($jsonStoreData->city)
            ->setLatitude($jsonStoreData->latitude)
            ->setLongitude($jsonStoreData->longitude)
            ->setStoreHoursNormalized($jsonStoreData->hours)
            ->setPhoneNormalized($jsonStoreData->phone)
            ->setWebsite($companyData['website']);
    }

}
