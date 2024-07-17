<?php

/**
 * Store Crawler für Konsum Dresden (ID: 264)
 */
class Crawler_Company_KonsumDresden_Store extends Crawler_Generic_Company {

    private const STORE_API = 'https://konsum-dev.de/content-website/graphql';

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $cities = $this->getStoresData();
        foreach ($cities as $city) {
            foreach ($city->markets->nodes as $storeData) {
                $storeData->marketInfos->workingHours = $this->workingHours($storeData->marketInfos->workingHours);
                $store = $this->addStore($storeData);
                $cStores->addElement($store);
            }
        }
        return $this->getResponse($cStores, $companyId);
    }

    private function getStoresData(): array
    {
        $data = [
            "operationName" => "getAllMarketsQuery",
            "query"=> "query getAllMarketsQuery {\n  cityDistricts(first: 999) {\n    nodes {\n      name\n      markets {\n        nodes {\n          id\n          title\n          slug\n          featuredImage {\n            node {\n              sourceUrl\n            }\n          }\n          marketInfos {\n            address {\n              city\n              number\n              postcode\n              street\n              coordinates {\n                lat\n                long\n              }\n            }\n            phone\n            nextStop\n            numberOfParkingSpaces\n            workingHours {\n              workingRoutine\n              mondaysToSaturdays {\n                from\n                to\n              }\n              weekdays {\n                to\n                from\n              }\n              saturdays {\n                to\n                from\n              }\n            }\n            workingHoursService {\n              workingHours {\n                workingRoutine\n                weekdays {\n                  to\n                  from\n                }\n                saturdays {\n                  to\n                  from\n                }\n                mondaysToSaturdays {\n                  to\n                  from\n                }\n              }\n              serviceDescription\n            }\n            photoGallery {\n              sourceUrl(size: LARGE)\n            }\n            awardText {\n              text\n              icon {\n                sourceUrl(size: LARGE)\n                mediaItemUrl\n              }\n            }\n            infoText\n            mapImg {\n              sourceUrl(size: LARGE)\n            }\n            workingHoursSpecialDays {\n              dateSpecialDay\n              hoursSpecialDay {\n                from\n                to\n                closeEntireDay\n              }\n            }\n            temporaryClosing {\n              closingHint\n              isClosed\n            }\n          }\n          cityDistricts {\n            nodes {\n              id\n              slug\n              name\n            }\n            nodes {\n              name\n              slug\n            }\n          }\n          locationSpecificServices {\n            nodes {\n              id\n              slug\n              name\n              sslIcons {\n                icon {\n                  sourceUrl(size: LARGE)\n                  title\n                }\n              }\n            }\n          }\n        }\n      }\n    }\n  }\n}"
        ];


        $options = [
            'http' => [
                'method'  => 'POST',
                'content' => json_encode($data),
                'header'=>  "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n",
            ]
        ];

        $context  = stream_context_create( $options );
        $result = file_get_contents( self::STORE_API, false, $context );
        $response = json_decode($result);

        return $response->data->cityDistricts->nodes ?: [];
    }

    private function addStore(object $store): Marktjagd_Entity_Api_Store
    {
        $storeEntity = new Marktjagd_Entity_Api_Store();
        $storeEntity->setTitle($store->title)
            ->setStoreNumber($store->id)
            ->setLatitude($store->marketInfos->address->coordinates->lat)
            ->setLongitude($store->marketInfos->address->coordinates->long)
            ->setStreet($store->marketInfos->address->street)
            ->setStreetNumber($store->marketInfos->address->number)
            ->setZipcode($store->marketInfos->address->postcode)
            ->setCity($store->marketInfos->address->city)
            ->setStoreHours($store->marketInfos->workingHours);

        return $storeEntity;
    }

    private function workingHours(object $workingHours): string
    {
        $hours = [];
        if ($workingHours->weekdays->{'from'}) {
            $hours['Mo'] = "Mo {$workingHours->weekdays->{'from'}}-{$workingHours->weekdays->{'to'}}";
            $hours['Di'] = "Di {$workingHours->weekdays->{'from'}}-{$workingHours->weekdays->{'to'}}";
            $hours['Mi'] = "Mi {$workingHours->weekdays->{'from'}}-{$workingHours->weekdays->{'to'}}";
            $hours['Do'] = "Do {$workingHours->weekdays->{'from'}}-{$workingHours->weekdays->{'to'}}";
            $hours['Fr'] = "Fr {$workingHours->weekdays->{'from'}}-{$workingHours->weekdays->{'to'}}";
        }

        if (empty($hours) && $workingHours->mondaysToSaturdays->{'from'}) {
            $hours['Mo'] = "Mo {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
            $hours['Di'] = "Di {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
            $hours['Mi'] = "Mi {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
            $hours['Do'] = "Do {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
            $hours['Fr'] = "Fr {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
            $hours['So'] = "So {$workingHours->mondaysToSaturdays->{'from'}}-{$workingHours->mondaysToSaturdays->{'to'}}";
        }

        if ($workingHours->saturdays->{'from'}) {
            $hours['So'] = "So {$workingHours->saturdays->{'from'}}-{$workingHours->saturdays->{'to'}}";
        }

        return implode(',', $hours);
    }
}
