<?php

/* 
 * Store Crawler für Jack & Jones (ID: 73745)
 */

class Crawler_Company_JackJones_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.jackjones.com/';
        $searchUrl = $baseUrl . 'de/de/stores';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<select[^>]*class="form__input-select"[^>]*name="city"[^>]*id="city"[^>]*>(.+?)<\/select>#s';
        if (!preg_match($pattern, $page, $storeCityListMatch)) {
            throw new Exception($companyId . ': unable to get store city list.');
        }

        $pattern = '#<option[^>]*value="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeCityListMatch[1], $storeCityMatches)) {
            throw new Exception($companyId . ': unable to get any store cities from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeCityMatches[1] as $singleCity) {
            $searchUrl = $baseUrl . 'on/demandware.store/Sites-BSE-DACH-Site/de_DE/'
                . 'PickupLocation-GetLocations?countryCode=DE&brandCode=jj'
                . '&postalCodeCity=' . urlencode($singleCity) . '&serviceID=SDSStores'
                . '&filterByClickNCollect=false';

            $this->_logger->info($companyId . ': opening ' . $searchUrl);
            $sPage->open($searchUrl);
            $jInfos = $sPage->getPage()->getResponseAsJson();

            if (!count($jInfos->locations)) {
                continue;
            }

            foreach ($jInfos->locations as $singleJStore) {
                if (!preg_match('#DE#', $singleJStore->country)) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreNumber($singleJStore->storeID)
                    ->setStreet($singleJStore->address1)
                    ->setStreetNumber($singleJStore->houseNumber . $singleJStore->houseNumberExtension)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setPhoneNormalized($singleJStore->phone);

                $cStores->addElement($eStore, TRUE);
            }
        }

        return $this->getResponse($cStores, $companyId, '2', false);
    }
}