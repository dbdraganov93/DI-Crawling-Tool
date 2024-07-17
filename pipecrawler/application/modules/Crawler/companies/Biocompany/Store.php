<?php

/**
 * Storecrawler für Bio Company(ID: 368)
 */
class Crawler_Company_Biocompany_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://uberall.com/';
        $storeListUrl = $baseUrl . 'api/storefinders/4w3OLJTTT66unD30WlbJhuit7Hd45w/locations/all';
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

        $sPage->open($storeListUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->response->locations as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->country)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';
            foreach ($singleJStore->openingHours as $singleDay) {
                if (property_exists($singleDay, 'closed') && $singleDay->closed) {
                    continue;
                }

                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $aWeekdays[$singleDay->dayOfWeek] . ' ' . $singleDay->from1 . '-' . $singleDay->to1;
            }

            $eStore->setCity($singleJStore->city)
                ->setStoreNumber($singleJStore->id)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setStoreHoursNotes($singleJStore->openingHoursNotes)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStreetAndStreetNumber($singleJStore->streetAndNumber)
                ->setZipcode($singleJStore->zip)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}