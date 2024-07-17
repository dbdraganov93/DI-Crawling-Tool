<?php

/*
 * Store Crawler für Shoe4you (ID: 68742)
 */

class Crawler_Company_Shoe4You_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sCollUp = new Marktjagd_Service_Compare_Collection_Store();
        $jsonPage = 'https://www.shoe4you.de/wp-json/wpgmza/v1/markers/base64eJyrVkrLzClJLVKyUqqOUcpNLIjPTIlRsopRMoxR0gEJFGeUgsSKgYLRsbVKtQCV7hBN';

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
