<?php

class Crawler_Company_Shopfully_Store extends Crawler_Generic_Company
{
    private const DEFAULT_LANG = 'it_it';
    public const SPREADSHEET_ID = '1wLc_tqrkS8frthiycAB1Nga1UkKPswVwp66v24pyubU';
    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();

        $shopfullyDatas = $this->getBrochuresData();

        foreach ($shopfullyDatas as $shopfullyData) {

            $language = $shopfullyData['country'] ?: self::DEFAULT_LANG;
            // Is mendatory to pass the language to the API
            $api = new Shopfully_Service_StoreApi($language);
            // We need to pass the shopfully retailer_id to the API to get the stores
            $storesDate = $api->getStoresByBrochureId($shopfullyData['brochureId']);

            foreach ($storesDate as $store) {
                $store = $this->createStore($store);
                $stores->addElement($store, TRUE);
            }
        }

        return $this->getResponse($stores);
    }

    private function createStore(Shopfully_Entity_Store $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData->getId())
            ->setStreetAndStreetNumber($storeData->getAddress())
            ->setZipcode($storeData->getZip())
            ->setCity($storeData->getCity())
            ->setLatitude($storeData->getLat())
            ->setLongitude($storeData->getLng());

        return $store;
    }

    public function getBrochuresData(): array
    {
        $googleSheetsService = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $googleSheetsService->getFormattedInfos(self::SPREADSHEET_ID, 'A1', 'B', 'shopfully');
    }
}
