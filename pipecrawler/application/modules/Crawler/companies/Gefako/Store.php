<?php

/**
 * Standortcrawler für Gefako (ID: 70977)
 */
class Crawler_Company_Gefako_Store extends Crawler_Generic_Company
{
    private const STORE_FEED = 'https://www.gefako.de/json/overlay:haendlersuche-result.de.json';

    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();
        $this->companyId = $companyId;

        foreach ($this->storeData() as $data) {
            $stores->addElement($this->createStore($data));
        }

        return $this->getResponse($stores, $companyId);
    }

    /**
     * @throws Exception
     */
    private function storeData(): array
    {
        $pageService = new Marktjagd_Service_Input_Page();
        $pageService->open(self::STORE_FEED);

        $storesJson = $pageService->getPage()->getResponseAsJson();

        if (empty($storesJson->locations)) {
            throw new Exception(sprintf('No stores found for Company ID %s in %s', $this->companyId, self::STORE_FEED));
        }

        return $storesJson->locations;
    }

    private function createStore(object $storeData): Marktjagd_Entity_Api_Store
    {
        $store = new Marktjagd_Entity_Api_Store();

        return $store->setLatitude($storeData->lat)
            ->setLongitude($storeData->lng)
            ->setTitle($storeData->title)
            ->setStreet($storeData->street)
            ->setPhone($storeData->phone)
            ->setStreetNumber($storeData->streetnumber)
            ->setZipcode($storeData->zip)
            ->setCity($storeData->city);
    }
}
