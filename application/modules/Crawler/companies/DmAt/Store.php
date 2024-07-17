<?php
/**
 * Store Crawler für dm AT (ID: 73424)
 */

class Crawler_Company_DmAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://services.dm.de/';
        $searchUrl = $baseUrl . 'storedata/stores/bbox/49.021%2C9.530%2C46.373%2C17.161';
        $sPage = new Marktjagd_Service_Input_Page();

        $aWeekdays = [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        ];

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            if (!preg_match('#AT#', $singleJStore->localeCountry)) {
                continue;
            }

            $strTimes = '';
            foreach ($singleJStore->openingDays as $singleDay) {
                foreach ($singleDay->timeSlices as $singleTime) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $aWeekdays[$singleDay->weekDay] . ' ' . $singleTime->opening . '-' . $singleTime->closing;
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeNumber)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setCity($singleJStore->address->city)
                ->setLatitude($singleJStore->location->lat)
                ->setLongitude($singleJStore->location->lon)
                ->setStoreHoursNormalized($strTimes)
                ->setBarrierFree(1)
                ->setToilet(1);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}