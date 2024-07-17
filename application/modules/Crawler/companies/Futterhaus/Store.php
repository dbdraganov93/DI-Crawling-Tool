<?php

/**
 * Storecrawler für Futterhaus (ID: 22196)
 */
class Crawler_Company_Futterhaus_Store extends Crawler_Generic_Company
{
    private const BASE_URL = 'https://www.futterhaus.de';

    private Marktjagd_Service_Input_GoogleSpreadsheetRead $googleSpreadsheet;

    private int $companyId;
    private array $campaignData;

    public function __construct()
    {
        parent::__construct();
        $this->googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $this->campaignData = $this->googleSpreadsheet->getCustomerData('futterhaus');
    }

    public function crawl($companyId)
    {
        $this->companyId = $companyId;

        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $currentStores = $api->findStoresByCompany($this->companyId)->getElements();

        $now = strtotime('now');
        if (!empty($this->campaignData) && $now >= strtotime($this->campaignData['start']) && $now <= strtotime($this->campaignData['end'])) {
            $storesData = $this->getCampaignStoreData($currentStores);
        } else {
            $storesData = $this->getDefaultStoreData($currentStores);
        }

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storesData as $storeData) {
            $store = $this->createStore($storeData);
            $stores->addElement($store);
        }

        return $this->getResponse($stores);
    }

    private function getCampaignStoreData(array $currentStores): array
    {
        $storeExceptionsData = $this->googleSpreadsheet->getFormattedInfos($this->campaignData['excludedStoresSpreadsheet'], 'A1', 'B', $this->campaignData['spreadsheetTab']);

        $skipStoreNumbers = [];
        foreach ($storeExceptionsData as $storeException) {
            if (!empty($storeException['store_number'])) {
                $skipStoreNumbers[] = $storeException['store_number'];
            }
        }

        $storesData = [];
        foreach ($currentStores as $storeNumber => $store) {
            $website = $this->campaignData['websiteBaseUrl'] . $storeNumber;
            if(in_array($storeNumber, $skipStoreNumbers)) {
                $website = NULL;
            }

            $storesData[] = [
                'title' => $store->getTitle(),
                'latitude' => $store->getLatitude(),
                'longitude' => $store->getLongitude(),
                'streetAndStreetNumber' => $store->getStreet() . ' ' . $store->getStreetNumber(),
                'zipcode' => $store->getZipcode(),
                'city' => $store->getCity(),
                'phoneNormalized' => $store->getPhone(),
                'website' => $website,
                'storeNumber' => $store->getStoreNumber(),
                'storeHoursNormalized' => $store->getStoreHours()
            ];
        }

        return $storesData;
    }

    private function getDefaultStoreData(array $currentStores): array
    {
        $websiteStoresData = $this->getWebsiteStoresData();

        foreach ($websiteStoresData as $storeNumber => $websiteStore) {
            if (!empty($currentStores[$storeNumber])) {
                // in case of slight difference in the store address we use the one in the BT
                $store = $currentStores[$storeNumber];
                $websiteStoresData[$storeNumber]['streetAndStreetNumber'] = $store->getStreet() . ' ' . $store->getStreetNumber();
            }
        }

        return $websiteStoresData;
    }

    private function getWebsiteStoresData(): array
    {
        $pageService = new Marktjagd_Service_Input_Page();
        $pageService->open(self::BASE_URL . '/service/marktsuche/');
        $page = $pageService->getPage()->getResponseBody();

        $pattern = '#var\s*locationsFinder\s*=\s*\[([^]]+?)]#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($this->companyId . ': store list not found.');
        }

        $pattern = '#\{([^}]+?)}#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
            throw new Exception($this->companyId . ': stores in list not found.');
        }

        $websiteStoresData = [];
        foreach ($storeMatches[1] as $storeMatch) {
            $pattern = '#([^:,\s]+?)\s*:\s*\'?([^,]+?)\'?,#';
            if (!preg_match_all($pattern, $storeMatch, $attributeMatches)) {
                $this->_logger->err($this->companyId . ': store attributes not found.');
                continue;
            }

            $attributes = array_combine($attributeMatches[1], $attributeMatches[2]);
            if (strlen($attributes['filPlz']) < 5) {
                continue;
            }

            $storeNumber = trim($attributes['fillUid'], '"');
            $websiteStoresData[$storeNumber] = [
                'title' => 'DAS FUTTERHAUS - ' . preg_replace('#.+<br[^>]*>\s*([^<]+?)\s*</p>.+#', '$1', $attributes['name']),
                'latitude' => $attributes['lat'],
                'longitude' => $attributes['lng'],
                'streetAndStreetNumber' => $attributes['filStrasse'],
                'zipcode' => $attributes['filPlz'],
                'city' => $attributes['filOrt'],
                'phoneNormalized' => $attributes['filTelephone'],
                'website' => self::BASE_URL . $attributes['filPageLink'],
                'storeNumber' => $storeNumber,
                'storeHoursNormalized' => 'Mo-Fr ' . $attributes['filHoursWeek'] . ', Sa ' . $attributes['filHoursSa']
            ];
        }

        return $websiteStoresData;
    }

    private function createStore(array $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();

        return $store->setTitle($storeData['title'])
            ->setLatitude($storeData['latitude'])
            ->setLongitude($storeData['longitude'])
            ->setStreetAndStreetNumber($storeData['streetAndStreetNumber'])
            ->setZipcode($storeData['zipcode'])
            ->setCity($storeData['city'])
            ->setPhoneNormalized($storeData['phoneNormalized'])
            ->setWebsite($storeData['website'])
            ->setStoreNumber($storeData['storeNumber'])
            ->setStoreHoursNormalized($storeData['storeHoursNormalized']);
    }
}
