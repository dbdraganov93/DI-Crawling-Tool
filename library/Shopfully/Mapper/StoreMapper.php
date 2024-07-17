<?php

class Shopfully_Mapper_StoreMapper
{

    private Marktjagd_Service_Input_MarktjagdApi $api;
    private Marktjagd_Service_Output_OpenStreetMap $openStoreMap;

    public function __construct()
    {
        $this->api = new Marktjagd_Service_Input_MarktjagdApi();
        $this->openStoreMap = new Marktjagd_Service_Output_OpenStreetMap();
    }

    public function toEntity(array $data): Shopfully_Entity_Store
    {
        $store = new Shopfully_Entity_Store();
        $store->setId($data['id'] ?? null);
        $store->setRetailerId($data['retailer_id'] ?? '');
        $store->setGroup($data['group'] ?? '');
        $store->setProvince($data['province'] ?? '');
        $store->setCity($data['city'] ?? '');
        $store->setAddress($data['address'] ?? '');
        $store->setZip($data['zip'] ?: $this->findZip($data) ?? '');
        $store->setMoreInfo($data['more_info'] ?? '');
        $store->setDescription($data['description'] ?? '');
        $store->setLat($data['lat'] ?? '');
        $store->setLng($data['lng'] ?? '');
        $store->setViewRadius($data['view_radius'] ?? '');
        $store->setUrl($data['url'] ?? '');
        $store->setPhone($data['phone'] ?? '');
        $store->setFax($data['fax'] ?? '');

        return $store;
    }

    private function findZip(array $data): string
    {

        // Search if we have the zip code in the store data.
        $stores = $this->api->findStoreByStoreNumber($data['id']);
        foreach ($stores as $store) {
            if ($store['zipcode']) {
                return $store['zipcode'];
            }
        }

        // Find the zip code from the coordinates using the OpenStreetMap API
        $store = $this->openStoreMap->findAddressFromCoordinates($data['lat'], $data['lng']);

        return $store['postcode'];
    }
}
