<?php

/**
 * Storecrawler für Sonderpreis Baumarkt (ID: 28831)
 */
class Crawler_Company_SonderpreisBaumarkt_Store extends Crawler_Generic_Company
{

    private const BASE_URL = 'https://markt.sonderpreis-baumarkt.de/';

    private Marktjagd_Service_Input_Page $pageService;

    public function __construct()
    {
        parent::__construct();

        $this->pageService = new Marktjagd_Service_Input_Page();
    }

    public function crawl($companyId)
    {
        $storesData = $this->crawlStorePages(self::BASE_URL);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storesData as $storeData) {
            $store = $this->createStore($storeData);
            $stores->addElement($store);
        }

        return $this->getResponse($stores, $companyId);
    }

    private function crawlStorePages(string $catalogPage): array
    {
        $storesData = [];

        $this->pageService->open($catalogPage);
        $pageContent = $this->pageService->getPage()->getResponseBody();

        if (preg_match("#__INITIAL__DATA__[^{]*([^<]+)#i", $pageContent, $pageDataMatch)) {
            $pageData = json_decode($pageDataMatch[1])->document;

            if (isset($pageData->dm_directoryChildren)) {
                foreach ($pageData->dm_directoryChildren as $directoryChild) {
                    if (isset($directoryChild->address)) {
                        $innerCatalogUrl = preg_replace(['#ä#', '#ö#', '#ü#', '#ß#'], ['a', 'o', 'u', 'ss'], $directoryChild->slug);
                    } else {
                        $innerCatalogUrl = preg_replace(['#%2F#', '#%28#', '#%29#'], ['/', '', ''], urlencode($directoryChild->slug));
                    }
                    $storesData = array_merge($storesData, $this->crawlStorePages(self::BASE_URL . $innerCatalogUrl));
                }
            } else {
                $storesData[] = $this->getStoreData($pageData);
            }
        }

        return $storesData;
    }

    private function getStoreData(object $rawStoreData): array
    {
        $strStoreHours = NULL;
        if (is_object($rawStoreData->hours)) {
            $strStoreHours = $this->getOpeningHours($rawStoreData->hours);
        }

        return [
            'number' => $rawStoreData->id,
            'city' => $rawStoreData->address->city,
            'street' => $rawStoreData->address->line1,
            'zipcode' => $rawStoreData->address->postalCode,
            'phone' => $rawStoreData->mainPhone,
            'email' => $rawStoreData->emails[0],
            'latitude' => $rawStoreData->geocodedCoordinate->latitude,
            'longitude' => $rawStoreData->geocodedCoordinate->longitude,
            'openingHours' => $strStoreHours,
        ];
    }

    private function getOpeningHours(object $hours): string
    {
        $openingHours = '';

        foreach ($hours as $day => $dayHours) {
            if (isset($dayHours->isClosed) && $dayHours->isClosed) {
                continue;
            }

            if (strlen($openingHours)) {
                $openingHours .= ',';
            }

            $openIntervals = $dayHours->openIntervals[0];
            $openingHours .= $day . ' ' . $openIntervals->start . '-' . $openIntervals->end;

        }

        return $openingHours;
    }

    private function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();

        return $store->setStoreNumber($storeData['number'])
            ->setStreetAndStreetNumber($storeData['street'])
            ->setZipcode($storeData['zipcode'])
            ->setCity($storeData['city'])
            ->setLatitude($storeData['latitude'])
            ->setLongitude($storeData['longitude'])
            ->setPhoneNormalized($storeData['phone'])
            ->setStoreHoursNormalized($storeData['openingHours']);
    }
}
