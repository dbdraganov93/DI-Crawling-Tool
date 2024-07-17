<?php

/**
 * Store Crawler für Primark (ID: 67698)
 */

class Crawler_Company_Primark_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $perPage = 50;
        $baseUrl = 'https://stores.primark.com/';
        $searchUrl = $baseUrl . 'search?qp=Germany&country=DE&per='.$perPage;
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $result = $this->getUrlResponseAsJSON($searchUrl);
        
        if (!$result) {
            throw new Exception($companyId . ': cannot find any store information');
        }

        $resultArray = json_decode($result);        
        $arrayStores = $resultArray->response->entities; 

        if (is_array($arrayStores) && !empty($arrayStores)) {
            $countStores = $resultArray->response->count;

            if ($countStores > $perPage) {
                $countSlots = floor($countStores / $perPage);

                for ($i=1; $i <= $countSlots; $i++) {
                    $offset = $perPage * $i;
                    $searchUrlOffset = $baseUrl . 'search?qp=Germany&country=DE&per='.$perPage.'&offset='.$offset;
                    $resultNext = $this->getUrlResponseAsJSON($searchUrlOffset);
                    $resultArrayNext = json_decode($resultNext);    
                    $arrayStoresNext = $resultArrayNext->response->entities;

                    if (is_array($arrayStoresNext) && !empty($arrayStoresNext)) {
                        $arrayStores = array_merge($arrayStores, $arrayStoresNext);
                    }
                }
            }
            $cStores = new Marktjagd_Collection_Api_Store();

            foreach ($arrayStores as $store) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setWebsite($store->profile->websiteUrl);
                $eStore->setCity($store->profile->address->city);
                $eStore->setZipcode($store->profile->address->postalCode);
                $eStore->setLatitude($store->profile->yextDisplayCoordinate->lat);
                $eStore->setLongitude($store->profile->yextDisplayCoordinate->long);
                $eStore->setStreet($sAddress->extractAddressPart('street', $store->profile->address->line1));
                $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $store->profile->address->line1));

                if (isset($store->profile->mainPhone->display)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($store->profile->mainPhone->display));
                }
                if (isset($store->profile->c_pagesServices) && !empty($store->profile->c_pagesServices)) {
                    $eStore->setService(implode(', ', $store->profile->c_pagesServices));
                }
                if (isset($store->profile->c_parkingInformation) && !empty($store->profile->c_parkingInformation)) {
                    $eStore->setParking('vorhanden');
                }
                if (isset($store->profile->paymentOptions) && !empty($store->profile->paymentOptions)) {
                    $eStore->setPayment(implode(', ', $store->profile->paymentOptions));
                }
                if (isset($store->profile->c_pagesStorePhoto->image->url)) {
                    $eStore->setImage('https:' . $store->profile->c_pagesStorePhoto->image->url);
                }

                $strTimes = '';

                if (isset($store->profile->hours->normalHours) && !empty($store->profile->hours->normalHours)) {
                    $daysTimes = $store->profile->hours->normalHours;

                    foreach ($daysTimes as $times) {
                        if ($times->isClosed == 1) {
                            continue;
                        }

                        if (strlen($strTimes)) {
                            $strTimes .= ', ';
                        }

                        else {
                            $dayTiming = $times->intervals[0]->start. ' - ' .$times->intervals[0]->end;
                        }
                        
                        $strTimes .= $this->getGermanDayReplacement($times->day) .'  '. $dayTiming; 
                    }
                }
                $eStore->setStoreHours($strTimes);                
                $eStore->setStoreNumber($eStore->getHash());

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * Function to get JSON data 
     */
    private function getUrlResponseAsJSON($url, $errorCodes = '200')
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
        
        if (!preg_match('#(' . $errorCodes . ')#', $info['http_code'])) {
            return NULL;
        }

        return $result;
    }

    /**
     * Function to get Germany day 
     */
    private function getGermanDayReplacement($day)
    {
        $array = [
            'MONDAY'    => 'Mo',
            'TUESDAY'   => 'Di',
            'WEDNESDAY' => 'Mi',
            'THURSDAY'  => 'Do',
            'FRIDAY'    => 'Fr',
            'SATURDAY'  => 'Sa',
            'SUNDAY'    => 'So'
        ];
        $result = isset($array[$day]) ? $array[$day] : null;
        return $result;
    }

}
