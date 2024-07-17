<?php
/**
 * Storecrawler für SportXX (ID: 72169)
 */
class Crawler_Company_SportXX_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page(true);

        $baseUrl = 'https://www.sportxx.ch';
        $searchUrl = '/de/cp/storefinder';

        $page = $sPage->getDomElsFromUrlByClass($baseUrl . $searchUrl, 'storelist--link u-reset');

        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($page as $singleStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();

            $storeUrl = $singleStore->getAttribute('href');
            $sPage->open($baseUrl . $storeUrl);
            $infos = $sPage->getDomElFromUrlByID($baseUrl . $storeUrl, 'state-body');

            preg_match('#window.__INITIAL_STATE__ = (.*[^\n])#', $infos->nodeValue, $info);
            $info = substr($info[1], 0, -1);

            $validJson = preg_replace('#undefined#', 'null', $info);
            $json = json_decode($validJson);
//            var_dump($json->store->details->openingHours);die;


            $eStore->setStreetAndStreetNumber($json->store->details->location->address)
                ->setZipcode($json->store->details->location->zip)
                ->setCity($json->store->details->location->city)
                ->setLatitude($json->store->details->location->latitude)
                ->setLongitude($json->store->details->location->longitute)
                ->setStoreNumber($json->store->details->storeId)
                ->setTitle($json->store->details->name)
                ->setPhone($json->store->details->phone)
                ->setStoreHoursNormalized($this->getStoreHours($json->store->details->openingHours))
                ->setWebsite($baseUrl . $storeUrl);


            $cStore->addElement($eStore);
        }
        return $this->getResponse($cStore,$companyId);
    }

    public function getStoreHours(array $unfiltered): string
    {
        $storeArray = [];
        $days = [
            'Montag' => 'Mo',
            'Dienstag' => 'Di',
            'Mittwoch' => 'Mi',
            'Donnerstag' => 'Do',
            'Freitag' => 'Fr',
            'Samstag' => 'Sa',
            'Sonntag' => 'So'
        ];

        $unfiltered = json_decode(json_encode($unfiltered), true);

        foreach ($unfiltered as $day)
        {
            $storeArray[$days[$day['day']]] = $day['openingTime'];

        }
        return implode(', ', array_map(
            function ($v, $k) { return sprintf("%s: %s", $k, $v); },
            $storeArray,
            array_keys($storeArray)
        ));
    }
}