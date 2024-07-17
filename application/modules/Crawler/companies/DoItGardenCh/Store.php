<?php

/*
 * Store Crawler für Do It+Garden CH (ID: 72162)
 */

class Crawler_Company_DoItGardenCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.doitgarden.ch';
        $searchUrl = $baseUrl . '/jsapi/v1/de/stores?location=';
        $sPage = new Marktjagd_Service_Input_Page();

        $aWeekDays =
            [
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
            if (strlen($singleJStore->location->zip) != 4
                || !preg_match('#CH#', $singleJStore->location->country)) {
                continue;
            }
            $detailUrl = $baseUrl . $singleJStore->url;
            $detailApiUrl = 'https://www.doitgarden.ch/jsapi/v1/de/stores/store/0098001';# $baseUrl . '/jsapi/v1/de/stores/store/' . $singleJStore->storeId;

            $sPage->open($detailApiUrl);
            $singleJStoreDetails = $sPage->getPage()->getResponseAsJson();


            $strTimes = '';
            foreach ($singleJStoreDetails->openingHours as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                if(isset($singleDay->opens) and isset($singleDay->closes))
                    $strTimes .= $aWeekDays[$singleDay->dayOfWeek] . ' ' . $singleDay->opens . '-' . $singleDay->closes;
                else
                    $strTimes .= $aWeekDays[$singleDay->dayOfWeek] . ' geschlossen';
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setSubtitle($singleJStore->name)
                #->setText(isset($singleJStore->text)? str_replace(';',',',$singleJStore->text) : '')
                ->setZipcode($singleJStore->location->zip)
                ->setCity($singleJStore->location->city)
                ->setStreetAndStreetNumber($singleJStore->location->address, 'CH')
                ->setLatitude($singleJStore->location->latitude)
                ->setLongitude($singleJStore->location->longitude)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStoreHoursNormalized($strTimes)
                ->setWebsite($detailUrl);

            $cStores->addElement($eStore);

        }
        return $this->getResponse($cStores, $companyId);
    }

}
