<?php


class Crawler_Company_Steiskal_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $baseUrl = 'https://www.baecker-steiskal.de/wp-admin/admin-ajax.php';
        $jsonStoreList = $baseUrl .'?action=store_search&lat=54.323293&lng=10.122765&max_results=5&search_radius=10&autoload=1';

        $page = $sPage->getPage()->setIgnoreRobots(TRUE);
        $sPage->open($jsonStoreList);
        $page = $sPage->getPage()->getResponseBody();
        $aStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $store) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($store->address)
                ->setTitle($store->store)
                ->setStreetAndStreetNumber($store->address)
                ->setZipcode($store->zip)
                ->setCity($store->city)
                ->setLatitude($store->lat)
                ->setLongitude($store->lng)
                ->setWebsite($store->url);

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores, $companyId);

    }
}