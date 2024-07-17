<?php

class Crawler_Company_Poland_Store  extends Crawler_Generic_Company
{
    private const DEFAULT_LANG = 'pl_pl';
    public function crawl($companyId)
    {
        $shopfullyCompanyId = Crawler_Company_Poland_Brochure::POLAND_COMPANY_MAP[$companyId] ?: null;

        if ($shopfullyCompanyId === null) {
            return false;
        }

        $stores = new Marktjagd_Collection_Api_Store();

        // Is mendatory to pass the language to the API
        $api = new Shopfully_Service_StoreApi(self::DEFAULT_LANG);
        // We need to pass the shopfully retailer_id to the API to get the stores
        $storesDate = $api->getStoresByCompanyId($shopfullyCompanyId);

        foreach ($storesDate as $store) {
            $store = $this->createStore($store);
            $stores->addElement($store, TRUE);
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
}
