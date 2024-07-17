<?php

/**
 * Store Crawler für Christ: (ID: 72151)
 */
class Crawler_Company_ChristCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId, $pageParameterNumber = '', $attempts = 0, $cStores = null)
    {
        $maxAttempts         = 10;
        $pageParameter       = empty($pageParameterNumber) ? '' : 'page=' . $pageParameterNumber;
        $baseUrl             = 'https://www.christ-swiss.ch/';
        $searchUrl           = $baseUrl .
            'de/standorte/get/locations/list?' . $pageParameter .
            '&currentlyOpen=false&lat=46.818188&lng=8.227511999999999&_=1626268650435';
        $cStores             = empty($cStores) ? new Marktjagd_Collection_Api_Store() : $cStores;
        $sPage               = new Marktjagd_Service_Input_Page();

        $json = $this->createRequest($searchUrl, $sPage);
        $this->addToStoresCollection($json, $cStores, $baseUrl, $sPage);

        $numberOfResults = $json->pagination->totalNumberOfResults;
        if($numberOfResults !== 0 || count($json->objects) !== 0) {
            $attempts++;
            $pageParameterNumber++;

            $this->_logger->info('Requesting part: ' . $attempts);

            if($attempts >= $maxAttempts) {
                throw new Exception('Max HTTP requests reached! Something is wrong');
            }

            $this->crawl($companyId, $pageParameterNumber, $attempts, $cStores);
        }

        $this->_logger->info('No more stores found, done!');

        return $this->getResponse($cStores, $companyId);
    }

    private function createRequest(string $searchUrl, Marktjagd_Service_Input_Page $sPage)
    {
        $sPage->open($searchUrl);

        return $sPage->getPage()->getResponseAsJson();
    }

    private function addToStoresCollection(
        $json,
        Marktjagd_Collection_Api_Store $cStores,
        string $baseUrl,
        Marktjagd_Service_Input_Page $sPage
    ) {
        foreach ($json->objects as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $website = $baseUrl . $singleStore->link;
            $storeHours = $this->getStoreHours($singleStore->id, $sPage);

            $eStore->setStreetAndStreetNumber($singleStore->street)
                ->setZipcode($singleStore->zipcode)
                ->setCity($singleStore->city)
                ->setLatitude($singleStore->latitude)
                ->setLongitude($singleStore->longitude)
                ->setPhoneNormalized($singleStore->displayPhone)
                ->setStoreHoursNormalized($storeHours)
                ->setStoreNumber($singleStore->id)
                ->setWebsite($website)
                ->setImage(empty($singleStore->image) ? '' : $baseUrl . $singleStore->image)
            ;

            $cStores->addElement($eStore);
        }
    }

    private function getStoreHours(string $storeId, Marktjagd_Service_Input_Page $sPage): string
    {
        $api = 'http://www.coop.ch/content/vstinfov2/de/detail.getvstopeninghours.json?language=de&id=';

        $this->_logger->info('Geting opening hours: ' . $api . substr($storeId,0,4));

        $sPage->open($api . substr($storeId,0,4));
        $json = $sPage->getPage()->getResponseAsJson();

        $aTimes = array();

        foreach ($json->hours as $day) {
            if ($day->holidayNr == '') {
                $aTimes[$day->desc] = $day->desc . ' ' . $day->time;
            }

            if (count($aTimes) == 7) {
                break;
            }
        }

        return implode(', ', $aTimes);
    }

    private function createDomDocument(string $url): DOMDocument
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return $domDoc;
    }
}
