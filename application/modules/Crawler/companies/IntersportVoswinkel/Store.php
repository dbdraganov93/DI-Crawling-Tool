<?php

/**
 * Storecrawler für Intersport Voswinkel (ID: 71994)
 *
 * Class Crawler_Company_IntersportVoswinkel_Store
 */
class Crawler_Company_IntersportVoswinkel_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $jsonUrl = 'https://haendler.intersport.de/plugin/Storelocator/stores-service/all';
        $baseUrl = "https://www.intersport-voswinkel.de/";

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($jsonUrl);
        $json = $sPage->getPage()->getResponseAsJson();
        $storeArray = $this->getStores($baseUrl, $sPage);
        if (empty($storeArray)) {
            $this->_logger->err("Keine Stores gefunden");
        }

        foreach ($storeArray as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $infoArray = $this->getStoreInfos($baseUrl . $singleStore, $sPage);
            $latLng = $this->getLatLng($json, $infoArray['zip']);
            $eStore->setStreetAndStreetNumber($infoArray['street'])
                ->setZipcode($infoArray['zip'])
                ->setCity($infoArray['city'])
                ->setStoreHoursNormalized($infoArray['hours'])
                ->setPhone($infoArray['telephone'])
                ->setLatitude($latLng['lat'])
                ->setLongitude($latLng['lng'])
                ->setEmail($infoArray['email']);
            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function getStores(string $url, Marktjagd_Service_Input_Page $sPage): array
    {
        $sStore = $sPage->getDomElsFromUrlByClass($url . '#', 'hidden');
        $storeArray = [];
        foreach ($sStore as $storeLink) {
            if (empty($storeLink->getAttribute('href'))) {
                continue;
            }
            array_push($storeArray, $storeLink->getAttribute('href'));
        }
        return $storeArray;
    }

    private function getStoreInfos(string $url, Marktjagd_Service_Input_Page $sPage): array
    {
        $infoArray = [];
        $infoArray["zip"] = $sPage->getDomElsFromUrl($url, "postalCode", "itemprop")[0]->textContent;
        $infoArray["city"] = $sPage->getDomElsFromUrl($url, "addressLocality", "itemprop")[0]->textContent;
        $infoArray["telephone"] = $sPage->getDomElsFromUrl($url, "telephone", "itemprop")[0]->textContent;
        $infoArray["email"] = $sPage->getDomElsFromUrl( $url, "email", "itemprop")[0]->textContent;
        $infoArray["street"] = $sPage->getDomElsFromUrl( $url, "streetAddress", "itemprop")[0]->textContent;
        $infoArray["hours"] = $sPage->getDomElsFromUrlByClass( $url, "dl-horizontal store-opening-info")[0]->textContent;

        return $infoArray;
    }

    private function getLatLng(array $json, string $zipCode): array
    {
        $latLng = [];
        foreach ($json as $jStore) {
            if (strtolower($jStore->n) != "intersport voswinkel" or $jStore->zip != $zipCode) {
                continue;
            }
            $latLng['lat'] = $jStore->lat;
            $latLng['lng'] = $jStore->lng;
        }
        return $latLng;
    }
}