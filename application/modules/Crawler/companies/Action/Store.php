<?php

/**
 * Store Crawler für Action (ID: 71353)
 */
class Crawler_Company_Action_Store extends Crawler_Generic_Company
{
    private const STORE_FEED_XML = 'https://files.channable.com/YAgszrTIgZxWio_3DKdXQQ==.xml';

    public function crawl($companyId)
    {
        $storesData = self::readStoresXML(self::STORE_FEED_XML);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storesData as $storeData) {
            $store = self::createStore($storeData);
            $stores->addElement($store);
        }

        return $this->getResponse($stores, $companyId);
    }

    public static function readStoresXML(string $xml): array
    {
        $pageService = new Marktjagd_Service_Input_Page(TRUE);

        $pageService->open($xml);
        $page = $pageService->getPage()->getResponseBody();

        $xmlContent = simplexml_load_string($page);

        $storesData = [];
        foreach ($xmlContent->item as $item) {

            if (!empty($item->new_store_tag) && 'Not open yet' === (string) $item->new_store_tag) {
                continue;
            }

            $storesData[] = [
                'storeNumber' => (string) $item->branch_number,
                'city' => (string) $item->address_city,
                'zipcode' => (string) $item->address_postal_code,
                'street' => (string) $item->address_street,
                'streetNumber' => $item->address_house_number . $item->address_house_number_addition,
                'longitude' => (float) $item->address_geo_location_longitude,
                'latitude' => (float) $item->address_geo_location_latitude,
                'website' => (string) $item->link,
                'storeHours' => self::getStoreHours($item->opening_hours)
            ];
        }

        return $storesData;
    }

    private static function getStoreHours(object $storeHoursArray): string
    {
        $storeHourString = '';
        foreach ($storeHoursArray as $storeHours)
        {
            $day = date('D', strtotime((string) $storeHours->date));
            $storeHourString .= $day . ' ' . $storeHours->local_opening_times_from . '-' . $storeHours->local_opening_times_till . ', ';
        }

        return $storeHourString;
    }

    public static function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData['storeNumber'])
            ->setCity($storeData['city'])
            ->setZipcode($storeData['zipcode'])
            ->setStreet($storeData['street'])
            ->setStreetNumber($storeData['streetNumber'])
            ->setLongitude($storeData['longitude'])
            ->setLatitude($storeData['latitude'])
            ->setWebsite($storeData['website'])
            ->setStoreHoursNormalized($storeData['storeHours']);

        return $store;
    }
}
