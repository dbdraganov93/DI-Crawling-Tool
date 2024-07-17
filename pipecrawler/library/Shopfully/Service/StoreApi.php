<?php

use Shopfully\Mapper\Shopfully_Service_StoreMapper;

class Shopfully_Service_StoreApi
{
    protected Shopfully_Service_ApiClient $shopfullyApiClient;
    protected Shopfully_Mapper_StoreMapper $storeMapper;

    public function __construct(string $lang)
    {
        $this->shopfullyApiClient = new Shopfully_Service_ApiClient($lang);
        $this->storeMapper = new Shopfully_Mapper_StoreMapper();
    }

    public function getStoresByCompanyId(int $companyId): ?array
    {
        $stores = [];
        $page = 1;
        do {
            $storeData = $this->shopfullyApiClient->getAllStoresByCompanyId($companyId, $page);
            if ($storeData) {
                foreach ($storeData as $store) {
                    $stores[] = $this->storeMapper->toEntity($store['Store']);
                }
            }
            $page++;
        } while ($storeData);

        return $stores;
    }

    public function getStoreById(int $storeId): ?Shopfully_Entity_Store
    {
        $store = null;
        $storeData = $this->shopfullyApiClient->getStoresById($storeId);
        if ($storeData) {
            foreach ($storeData as $storeFa) {
                $store = $this->storeMapper->toEntity($storeFa['Store']);
                break;
            }
        }

        return $store;
    }

    public function getStoresIdsByBrochureId(int $brochureId): ?array
    {
        $stores = [];
        $page = 1;
        do {
            $storeData = $this->shopfullyApiClient->getStoresByBrochureId($brochureId, $page);
            if ($storeData) {
                foreach ($storeData as $store) {
                    $stores[] = $store['Store']['id'];
                }
            }
            $page++;
        } while ($storeData);

        return $stores;
    }

    public function getStoresByBrochureId(int $brochureId): ?array
    {
        $stores = [];
        $page = 1;
        do {
            $storeData = $this->shopfullyApiClient->getStoresByBrochureId($brochureId, $page);
            if ($storeData) {
                foreach ($storeData as $store) {
                    $stores[] = $this->storeMapper->toEntity($store['Store']);
                }
            }
            $page++;
        } while ($storeData);

        return $stores;
    }
}
