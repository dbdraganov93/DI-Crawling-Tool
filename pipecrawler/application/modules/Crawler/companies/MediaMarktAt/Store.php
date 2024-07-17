<?php
/**
 * Store Crawler für Media Markt AT (ID: 73214)
 */

class Crawler_Company_MediaMarktAt_Store extends Crawler_Generic_Company
{

    private int $companyId;
    private const STORE_FEED = 'https://www.mediamarkt.at/de/store/store-finder';
    private const STORE_FEED_PATTERN = "/window.__PRELOADED_STATE__ = ({.*?});/s";
    private const STORE_FEED_HOURS = 'https://www.mediamarkt.at/de/store/graz-nord-';
    private const STORE_FEED_HOURS_PATTERN = '/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/i';

    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();

        $this->companyId = $companyId;
        $storeData = $this->getStoreData(self::STORE_FEED, self::STORE_FEED_PATTERN);

        foreach ($this->mapStoreData($storeData) as $data) {
            $stores->addElement($this->createStore($data));
        }
        return $this->getResponse($stores, $companyId);
    }

    /**
    * @throws Zend_Exception
    */
    private function getStoreData(string $storeFeed, string $pattern): string
    {
        $pageService = new Marktjagd_Service_Input_Page();

        $pageService->open($storeFeed);
        $stores = $pageService->getPage()->getResponseBody();

        if (!preg_match($pattern, $stores, $storeListMatch)) {
            throw new Exception("Unable to get store list for company with ID: {$this->companyId}");
        }
        return $storeListMatch[1];
    }

    private function storeOpeningData(string $storeHoursData): string
    {
        $formattedOpeningHours = [];

        if (!empty($storeHoursData)) {
            $openingHours = json_decode($storeHoursData, true);
            foreach ($openingHours as $hourRange) {
                if (is_array($hourRange)) {
                    foreach ($hourRange as $hours) {
                        if (isset($hours["dayOfWeek"]) && is_array($hours["dayOfWeek"])) {
                            foreach ($hours["dayOfWeek"] as $day) {
                                $formattedDay = substr($day, 0, 2);
                                $formattedTime = $hours["opens"] . "-" . $hours["closes"];
                                $formattedOpeningHours[] = $formattedDay . " " . $formattedTime;
                            }
                        }
                    }
                }
            }
        }
        return implode(', ', $formattedOpeningHours);
    }

    private function mapStoreData(string $rawStoreData): array
    {
        $processedStoreData = [];
        $finalStoreData= [];

        if (!empty($rawStoreData)) {
            $storeFeed = json_decode($rawStoreData, true);

            foreach ($storeFeed as $storeItem) {
                if (is_array($storeItem)) {
                    foreach ($storeItem as $key => $feedItem) {
                        if (false !== strpos($key, 'GraphqlStore:')) {

                            $updatedFeed = [];
                            foreach ($feedItem as $subStoreKey => $subStoreFeed) {
                                $updatedFeed[$subStoreKey] = $subStoreFeed;
                            }
                            $processedStoreData[] = $updatedFeed;
                        }
                    }
                }
            }
            foreach ($processedStoreData as $key => $storeData) {
                $finalStoreData[$key]['id'] = $storeData['id'];
                $finalStoreData[$key]['city'] = $storeData['address']['city'];
                $finalStoreData[$key]['phoneNumber'] = $storeData['phoneNumber'];
                $finalStoreData[$key]['street'] = $storeData['address']['street'];
                $finalStoreData[$key]['houseNumber'] = $storeData['address']['houseNumber'];
                $finalStoreData[$key]['zipCode'] = $storeData['address']['zipCode'];
                $finalStoreData[$key]['openingTimes'] = $storeData['openingTimes']['regular'];
                $finalStoreData[$key]['lat'] = $storeData['position']['lat'];
                $finalStoreData[$key]['lng'] = $storeData['position']['lng'];
                $finalStoreData[$key]['website'] = self::STORE_FEED_HOURS . $storeData['id'];
                $finalStoreData[$key]['openingHours'] = $this->storeOpeningData($this->getStoreData(self::STORE_FEED_HOURS . $storeData['id'], self::STORE_FEED_HOURS_PATTERN));
            }
        }
        return $finalStoreData;
    }

    private function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();

        return $store->setLatitude($storeData['lat'])
               ->setLongitude($storeData['lng'])
               ->setStoreNumber($storeData['id'])
               ->setStoreHoursNormalized($storeData['openingHours'])
               ->setStreetAndStreetNumber($storeData['street'] . ' ' . $storeData['houseNumber'])
               ->setZipcode($storeData['zipCode'])
               ->setCity($storeData['city'])
               ->setWebsite($storeData['website']);
    }
}

