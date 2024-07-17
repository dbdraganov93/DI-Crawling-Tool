<?php

/**
 * Store Crawler für McDonald's CH (ID: 72177)
 */
class Crawler_Company_McDonaldsCh_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl    = 'https://www.mcdonalds.com/';
        $radius     = 1000;
        $maxResults = 1000;
        // uses geo location from Zürich as basis
        $searchUrl  = $baseUrl . 'googleappsv2/geolocation?latitude=47.3768866&longitude=8.541694&radius=' . $radius .
            '&maxResults=' . $maxResults . '&country=ch&language=de-ch&showClosed=&hours24Text=Open%2024%20hr';
        $sPage      = new Marktjagd_Service_Input_Page(true);
        $cStores    = new Marktjagd_Collection_Api_Store();
        $sGeo       = new Marktjagd_Database_Service_GeoRegion();

        $sPage->open($searchUrl);
        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json->features as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $store = $singleStore->properties;

            $storeHours = null;
            if($store->restauranthours !== null){
                $storeHours = $store->restauranthours;
            } elseif ($store->drivethruhours !== null) {
                $storeHours = $store->drivethruhours;
            }

            $zipCode = $store->postcode;
            if(trim($zipCode) == 'CH-1853') {
                $zipCode = $this->findZipCodeManually($store->addressLine1);
            }
            if($zipCode == null) {
                $this->_logger->warn('Zip code not found for: ' . $store->name);
                $zipCode = $sGeo->findZipCodeByCity($store->addressLine3, 'CH');

                if($zipCode == null) {
                    $this->_logger->warn('Zip also not find by sGeo Service, attempting "manually"');
                    $zipCode = $this->findZipCodeManually($store->addressLine1);
                }
            }

            $eStore->setTitle($store->name)
                ->setStreetAndStreetNumber($store->addressLine1)
                ->setZipcode($zipCode)
                ->setCity($store->addressLine3)
                ->setPhoneNormalized($store->telephone)
                ->setLatitude($singleStore->geometry->coordinates[1])
                ->setLongitude($singleStore->geometry->coordinates[0])
                ->setPhoneNormalized($store->telephone)
                ->setEmail($store->storeEmail)
                ->setStoreHoursNormalized($this->generateStoreHours($storeHours))
                ->setStoreNumber($store->shortDescription)
                ->setService(implode(', ', $store->filterType))
            ;

            if($store->addressLine1 == 'Moosbachstrasse 1') {
                $eStore->setDistribution('de');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function findZipCodeManually(string $streetName): string
    {
        switch (trim($streetName)) {
            case 'Route de Moutier 115':
                return '2800';
            case 'Place de la Gare 1':
            case 'Rue de Romont 15':
                return '1700';
            case "Rue de l'Hôpital 20":
                return '2000';
            case 'Via Zorzi 36':
                return '6500';
            case 'Via Gorelle 8':
                return '6592';
            case 'Rue de Potence 2':
                return '3960';
            case 'Av. de la Gare 3':
                return '1950';
            case 'Via alla Sguancia 9':
                return '6915';
            case 'Z.A. Pré du Pont 105':
                return '1868';
            case 'Relais autoroutier du Chablais Ouest':
                return '1853';
        }
    }

    private function generateStoreHours(?stdClass $rawStoreHours): string
    {
        if($rawStoreHours === null) {
            return '';
        }

        $storeHourArray = [];
        foreach ($rawStoreHours as $day => $rawStoreHour) {
            switch ($day) {
                case 'hoursMonday':
                    $storeHourArray[] = 'Mo ' . $rawStoreHour;
                    break;
                case 'hoursTuesday':
                    $storeHourArray[] = 'Di ' . $rawStoreHour;
                    break;
                case 'hoursWednesday':
                    $storeHourArray[] = 'Mi ' . $rawStoreHour;
                    break;
                case 'hoursThursday':
                    $storeHourArray[] = 'Do ' . $rawStoreHour;
                    break;
                case 'hoursFriday':
                    $storeHourArray[] = 'Fr ' . $rawStoreHour;
                    break;
                case 'hoursSaturday':
                    $storeHourArray[] = 'Sa ' . $rawStoreHour;
                    break;
                case 'hoursSunday':
                    $storeHourArray[] = 'So ' . $rawStoreHour;
                    break;
            }
        }

        return implode(', ', $storeHourArray);
    }
}
