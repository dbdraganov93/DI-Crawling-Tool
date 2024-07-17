<?php

/* 
 * Store Crawler für ROFU (ID: 28773)
 */

class Crawler_Company_Rofu_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://uberall.com/';
        $searchUrl = $baseUrl . 'api/storefinders/Yj00RdSbv7nOPIFWz62WrRUuPzoiGM/locations/all?fieldMask=id';
        $sPage = new Marktjagd_Service_Input_Page();

        $aWeekDays = [
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So'
        ];

        $sPage->open($searchUrl);
        $jStoreIds = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStoreIds->response->locations as $singleJStoreId) {
            $storeDetailUrl = $baseUrl . 'api/storefinders/Yj00RdSbv7nOPIFWz62WrRUuPzoiGM/locations/' . $singleJStoreId->id . '?full=true';

            $sPage->open($storeDetailUrl);
            $jStore = $sPage->getPage()->getResponseAsJson()->response;

            $strTimes = '';
            foreach ($jStore->openingHours as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $aWeekDays[$singleDay->dayOfWeek] . ' ' . $singleDay->from1 . '-' . $singleDay->to1;
                if (property_exists($singleDay, 'from2')) {
                    $strTimes .= ',' . $aWeekDays[$singleDay->dayOfWeek] . ' ' . $singleDay->from2 . '-' . $singleDay->to2;
                }
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setSection(implode(', ', $jStore->brands))
                ->setCity($jStore->city)
                ->setText($jStore->descriptionLong)
                ->setStoreNumber($jStore->id)
                ->setLatitude($jStore->lat)
                ->setLongitude($jStore->lng)
                ->setPayment(implode(', ', $jStore->paymentOptions))
                ->setPhoneNormalized($jStore->phone)
                ->setService(implode(', ', $jStore->services))
                ->setStreetAndStreetNumber($jStore->streetAndNumber)
                ->setWebsite($jStore->website)
                ->setZipcode($jStore->zip)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}