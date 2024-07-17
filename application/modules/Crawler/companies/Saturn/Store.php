<?php

/*
 * Store Crawler für Saturn (ID: 16)
 */

class Crawler_Company_Saturn_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        //todor: cities in array klöppeln und durchiterieren, shop id's hinzufügen damit die nicht 2x gecrawlt werden
        $jsonUrl = 'https://www.saturn.de/markt/search?q=[city],Deutschland';

        $cStores = new Marktjagd_Collection_Api_Store();
        $geo = new Marktjagd_Database_Service_GeoRegion();
        $cities = $geo->findAllZipCodes();

        $id = [];
        foreach ($cities as $city) {
            $searchUrl = $jsonUrl;
            $searchUrl = str_replace('[city]', $city, $searchUrl);

            $json = $this->curlAlternative($searchUrl);

            if (empty($json)) {
                continue;
            }


            foreach ($json as $singleStore) {
                if (in_array($singleStore->loc->customByName->{'Cookie Store ID'}, $id)) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStreetAndStreetNumber($singleStore->loc->address1)
                    ->setCity($singleStore->loc->city)
                    ->setZipcode($singleStore->loc->postalCode)
                    ->setStoreHoursNormalized($this->hours($singleStore))
                    ->setLatitude($singleStore->loc->latitude)
                    ->setLongitude($singleStore->loc->longitude)
                    ->setPhoneNormalized($singleStore->loc->phone)
                    ->setFaxNormalized($singleStore->loc->phones[1]->number)
                    ->setEmail($singleStore->loc->emails[0])
                    ->setWebsite($singleStore->loc->website->displayUrl)
                    ->setStoreNumber($singleStore->loc->customByName->{'Cookie Store ID'});

                $id[] = $singleStore->loc->customByName->{'Cookie Store ID'};


                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);

    }

    private function curlAlternative($url)
    {
        $host = 'www.saturn.de';
        $request_headers = array(
            "Accept: application/json",
            "Host:" . $host,
            "User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:78.0) Gecko/20100101 Firefox/78.0",
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);


        $sessionData = curl_exec($ch);
        curl_close($ch);
        $seasonDecode = json_decode($sessionData);
        return $seasonDecode->locations;
    }

    private function hours($json)
    {
        $hours = [];
        $dayEngDe = [
            'MONDAY'=> 'Mo',
            'TUESDAY' => 'Di',
            'WEDNESDAY' => 'Mi',
            'THURSDAY' => 'Do',
            'FRIDAY' => 'Fr',
            'SATURDAY' => 'Sa',
        ];

        foreach ($json->loc->hours->days as $singleDay) {
            $pattern = '#(\d{2})(\d{2})#';
            $hours .= $dayEngDe[$singleDay->day] . ': ' . preg_replace($pattern,'\1' . ':' . '\2', $singleDay->intervals[0]->start) . ' - ' . preg_replace($pattern,'\1' . ':' . '\2', $singleDay->intervals[0]->end) . ', ';
        }
        return $hours;
    }
}
