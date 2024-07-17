<?php

/*
 * Store Crawler für K und K Schuhe (ID: 29191)
 */

class Crawler_Company_Kienast_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $jsonPage = 'https://www.k-und-k-schuhcenter.de/wp-json/wpgmza/v1/markers/base64eJyrVkrLzClJLVKyUqqOUcpNLIjPTIlRsopRMrSMUdIBiRRnlIIEi4Gi0bG1SrUAo7IQhg==';

        $json = $this->getUrlResponseAsJSON($jsonPage);
        $resultJson = json_decode($json);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($resultJson as $stores) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $addressSplit = preg_split('#,#', $stores->address);
            $addressSplit = array_reverse($addressSplit);


            $eStore->setStreetAndStreetNumber($addressSplit[1])
                ->setZipcodeAndCity($addressSplit[0])
                ->setLatitude($stores->lat)
                ->setLongitude($stores->lng)
                ->setStoreHoursNormalized($stores->description)
                ->setStoreNumber($stores->id);


            $cStores->addElement($eStore);
        }


        return $this->getResponse($cStores, $companyId);

    }

    private function getUrlResponseAsJSON($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $headers = array();
        $headers[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);


        return $result;
    }

}
