<?php

/*
 * Store Crawler für JYSK CH (ID: 72181)
 */

class Crawler_Company_JyskCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        #exec("curl 'https://www.jysk.ch/store/locator/search?address=&latitude=&longitude='   -H 'x-requested-with: XMLHttpRequest'   -H 'Referer: https://www.jysk.ch/store-locator-standalone'", $returnValue, $returnVar);
        #$jStores = json_decode($returnValue[0]);

        $cStores = new Marktjagd_Collection_Api_Store();

        $curl = curl_init();
        for($storeNumber = 6101; $storeNumber < 6199; $storeNumber++) {

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://jysk.ch/de/services/store/get/' . $storeNumber,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'accept: */*',
                    'accept-language: de,en-US;q=0.9,en;q=0.8'
                ),
            ));
            $singleJStore = json_decode(curl_exec($curl));
            if(!$singleJStore)
                continue;

            $openingHours = '';
            $dayNames = [
                '0' => 'So ',
                '1' => 'Mo ',
                '2' => 'Di ',
                '3' => 'Mi ',
                '4' => 'Do ',
                '5' => 'Fr ',
                '6' => 'Sa ',
            ];
            foreach($singleJStore->opening as $day) {
                if($day->starthours == '0' && $day->endhours == '0')
                    continue;

                if(strlen($openingHours > 0))
                    $openingHours .= ', ';

                $openingHours .= $dayNames[$day->day] . $day->format_time;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->shop_id)
                ->setStreet($singleJStore->street)
                ->setStreetNumber($singleJStore->street)
                ->setZipcode($singleJStore->zipcode)
                ->setCity($singleJStore->city)
                ->setPhone($singleJStore->tel)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lon)
                ->setStoreHoursNormalized($openingHours);

            $cStores->addElement($eStore);
        }
        return $this->getResponse($cStores);
    }
}
