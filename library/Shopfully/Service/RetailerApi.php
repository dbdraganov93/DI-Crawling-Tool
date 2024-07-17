<?php

class Shopfully_Service_RetailerApi
{
    private Shopfully_Service_ApiClient $shopfullyApiClient;
    private Shopfully_Mapper_RetailerMapper $retailerMapper;

    public function __construct(string $lang)
    {
        $this->shopfullyApiClient = new Shopfully_Service_ApiClient($lang);
        $this->retailerMapper = new Shopfully_Mapper_RetailerMapper();
    }

    /**
     * With this method we get the retailer data from the Shopfully API for specific retailer by ID.
     */
    public function getRetailerById(int $retailerId): ?Shopfully_Entity_Retailer
    {
        $retailerData = $this->shopfullyApiClient->getRetailerById($retailerId);
        if (null == $retailerData) {
            return NULL;
        }

        return $this->getRetailerEntity($retailerData);
    }

    /**
     * With this method we get the retailer data from the Shopfully API for specific retailer by name.
     */
    public function getRetailerByName(string $retailerName): ?Shopfully_Entity_Retailer
    {
        $retailerData = $this->shopfullyApiClient->getRetailerByName($retailerName);
        if (null === $retailerData) {
            return null;
        }

        return $this->getRetailerEntity($retailerData);
    }

    private function getRetailerEntity(array $retailerData): Shopfully_Entity_Retailer
    {
        $retailerData = reset($retailerData)['Retailer'];

        return $this->retailerMapper->toEntity($retailerData);
    }
}
