<?php

/**
 * Store Crawler für Hornbach AT (ID: 72718)
 */
class Crawler_Company_HornbachAt_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hornbach.at/';
        $searchUrl = $baseUrl . 'mvc/market/markets/all';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        if (!count($jStores)) {
            throw new Exception('Company ID- ' . $companyId . ': Unable to get json response for store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleStore->zipCode)
                ->setLatitude($singleStore->latitude)
                ->setLongitude($singleStore->longitude)
                ->setStoreNumber($singleStore->marketCode)
                ->setCity($singleStore->city)
                ->setPhoneNormalized($singleStore->phone)
                ->setStreet($singleStore->streetName)
                ->setStreetNumber($singleStore->streetNumber);

            $storeHours = '';
            foreach ($singleStore->workingTimes as $singleDay) {
                if (!preg_match('#^[A-Z]#', $singleDay->day)
                    || preg_match('#00:00\s*-\s*00:00#', $singleDay->workingHours)) {
                    continue;
                }
                if (strlen($storeHours)) {
                    $storeHours .= ',';
                }
                $storeHours .= $singleDay->day . ' ' . $singleDay->workingHours;
            }

            $eStore->setStoreHoursNormalized($storeHours);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
