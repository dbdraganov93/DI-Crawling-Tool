<?php

/*
 * Store Crawler für Schuhpark (ID: 29143)
 */

class Crawler_Company_Schuhpark_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $url = 'www.schuhpark.de/StoreLocator/search/';
        $page = $this->getUrlResponse($url);

        $pattern = "#<div\sid=\"store-\d{1,3}\".+?auswählen#";
        if (!preg_match_all($pattern, $page, $matches)) {
            $this->_logger->err('Unable to match stores on overview page');
            throw new Exception('No stores machted');
        }

        $storeDetailPattern = "#<div class=\"store-details\">(.+?)<\/div#";
        $storeHoursPattern = "#ffnungszeiten(.+?)<\/div#";

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($matches[0] as $match) {
            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match($storeDetailPattern, $match, $storeDetails)) {
                $storeDetailsSplitted = preg_split('#<br#', $storeDetails[1]);
                $eStore->setStreetAndStreetNumber($storeDetailsSplitted[0]);
                $eStore->setZipcodeAndCity($storeDetailsSplitted[1]);
                $eStore->setPhoneNormalized(preg_replace('#<.+?>#', '', $storeDetailsSplitted[4]));
            } else {
                throw new Exception('Unable to match store details (' . $storeDetailPattern . ') for store: ' . $match);
            }

            if (preg_match($storeHoursPattern, $match, $storeHours)) {
                $eStore->setStoreHoursNormalized(preg_replace('#<.+?>#', '', $storeHours[1]));
            } else {
                throw new Exception('Unable to match store hours (' . $storeHoursPattern . ') for store: ' . $match);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);

    }

    private function getUrlResponse($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(

            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS =>'lat=52.3758916&lng=9.732010400000002&distance=5000&input=hannover&catFilter=&byname=',
            CURLOPT_HTTPHEADER => array(
                "Cookie: store-locator-consent=true;",
                "Host: www.schuhpark.de"
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;

    }




}
