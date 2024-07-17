<?php

/**
 * Store Crawler für Douglas (ID: 73184)
 */

class Crawler_Company_DouglasAt_Store extends Crawler_Generic_Company
{
    private const WEBSITE_URL = 'https://www.douglas.at';
    private const STORE_API_URL = 'https://www.douglas.at/api/v2/stores?fields=FULL&pageSize=1000&sort=asc';

    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();
        $storesData = $this->getStoreData();

        foreach ($storesData as $storeData) {
            $stores->addElement($this->createStore($storeData));
        }

        return $this->getResponse($stores);
    }

    private function getStoreData(): array
    {
        $ch = curl_init(self::STORE_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response);

        return $data->stores ?? [];
    }

    private function createStore($storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();
        $store->setStoreNumber($storeData->name)
            ->setTitle($storeData->displayName)
            ->setWebsite(self::WEBSITE_URL . $storeData->url)
            ->setCity($storeData->address->town)
            ->setStreet($storeData->address->line1)
            ->setStreetNumber($storeData->address->line2)
            ->setZipcode($storeData->address->postalCode)
            ->setPhone($storeData->address->phone)
            ->setFax($storeData->address->fax)
            ->setLatitude($storeData->geoPoint->latitude)
            ->setLongitude($storeData->geoPoint->longitude);

        $openHours = [];
        foreach ($storeData->openingHours->weekDayOpeningList as $openingHour) {
            if (!empty($openingHour->openingTime->formattedHour) && !empty($openingHour->closingTime->formattedHour)) {
                $openHours[] = sprintf('%s %s-%s', str_replace('.', '', $openingHour->weekDay), $openingHour->openingTime->formattedHour, $openingHour->closingTime->formattedHour);
            }
        }

        if ($openHours) {
            $store->setStoreHoursNormalized(implode(', ', $openHours));
        }

        return $store;
    }
}
