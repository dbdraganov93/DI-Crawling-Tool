<?php

/**
 * Store Crawler für Polo Motorrad (ID: 186)
 */
class Crawler_Company_Polo_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.polo-motorrad.com/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-Global-Site/de_DE/Stores-FindStores?lat='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $jStores = json_decode(file_get_contents($singleUrl));

            if (!$jStores->stores) {
                continue;
            }

            foreach ($jStores->stores as $singleJStore) {
                if (!preg_match('#DE#', $singleJStore->address->countryCode) || $singleJStore->closed) {
                    continue;
                }

                $strTimes = '';
                foreach ($singleJStore->storeDetails->storeHours->days as $singleDay => $storeHours) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $singleDay . ' ' . $storeHours;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->ID)
                    ->setImage($singleJStore->images->main[0]->absURL)
                    ->setStreetAndStreetNumber($singleJStore->address->address1)
                    ->setCity($singleJStore->address->city)
                    ->setZipcode($singleJStore->address->postalCode)
                    ->setLatitude($singleJStore->address->latitude)
                    ->setLongitude($singleJStore->address->longitude)
                    ->setPhoneNormalized($singleJStore->contactDetails->phone)
                    ->setEmail($singleJStore->contactDetails->email)
                    ->setFaxNormalized($singleJStore->contactDetails->fax)
//                    ->setText(stripcslashes(strip_tags($singleJStore->storeDetails->storeInformations)))
                    ->setStoreHoursNormalized($strTimes);

                $cStores->addElement($eStore, TRUE);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}
