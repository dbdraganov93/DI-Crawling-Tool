<?php

/**
 * Store Crawler für Action (ID: 73604)
 */
class Crawler_Company_ActionAt_Store extends Crawler_Generic_Company
{
    private const STORE_FEED_XML = 'https://files.channable.com/Gzq4R6rUl5bWv_hh-UjjTA==.xml';

    public function crawl($companyId)
    {
        $storesData = Crawler_Company_Action_Store::readStoresXML(self::STORE_FEED_XML);

        $stores = new Marktjagd_Collection_Api_Store();
        foreach ($storesData as $storeData) {
            $store = Crawler_Company_Action_Store::createStore($storeData);
            $stores->addElement($store);
        }

        return $this->getResponse($stores, $companyId);
    }
}

