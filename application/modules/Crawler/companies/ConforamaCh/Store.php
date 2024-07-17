<?php

/**
 * Store Crawler für Conforama (ID: 72136)
 */
class Crawler_Company_ConforamaCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.conforama.ch/occ/v2/conforama/stores?fields=stores(name%2CdisplayName%2CformattedDistance%2CopeningHours(weekDayOpeningList(FULL)%2CspecialDayOpeningList(FULL))%2CgeoPoint(latitude%2Clongitude)%2Caddress(line1%2Cline2%2Ctown%2Cregion(FULL)%2CpostalCode%2Cphone%2Ccountry%2Cemail)%2C%20features)%2Cpagination(DEFAULT)%2Csorts(DEFAULT)&query=&pageSize=-1&lang=de&curr=CHF';

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($baseUrl);
        $jsonResponse = $sPage->getPage()->getResponseAsJson();

        if (empty($jsonResponse) || isset($jsonResponse->errors)) {
            throw new Exception($companyId . ': Was not possible to get json response or it contains errors');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jsonResponse->stores as $store) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setCity(ucfirst(strtolower($store->address->town)))
                ->setZipcode($store->address->postalCode)
                ->setStreetAndStreetNumber($this->removeUnwantedString($store->address->line1), 'FR')
                ->setLatitude((string) $store->geoPoint->latitude)
                ->setLongitude((string) $store->geoPoint->longitude)
                ->setWebsite('https://www.conforama.ch/de/store-finder/country/CH/' . $store->displayName)
                ->setPhoneNormalized(isset($store->address->phone) ? $store->address->phone : '')
                ->setStoreHours($this->generateStoreHours($store->openingHours->weekDayOpeningList))
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function generateStoreHours($storeHours) : string
    {
        $result = [];
        foreach ($storeHours as $storeHour) {
            if(isset($storeHour->openingTime->formattedHour)) {
                $day = substr($storeHour->weekDay, 0, -1);
                $result[] = $day . ' ' .  $storeHour->openingTime->formattedHour . '-' .
                    $storeHour->closingTime->formattedHour;
            }
        }

        // Remove sunday if empty
        if(array_key_exists(6, $result) && $result[6] == 'So -') {
            unset($result[6]);
        }

        return implode(', ', $result);
    }

    private function removeUnwantedString(string $street) : string
    {
        if(preg_match('#Conforama AG#', $street)) {
            return str_replace('Conforama AG', '', $street);
        } elseif (preg_match('#Conforama SA#', $street)) {
            return str_replace('Conforama SA', '', $street);
        }

        return $street;
    }
}
