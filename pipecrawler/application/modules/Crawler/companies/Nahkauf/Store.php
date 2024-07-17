<?php

/* 
 * Store Crawler für nahkauf (ID: 22)
 */

class Crawler_Company_Nahkauf_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nahkauf.de/';
        $searchUrl = $baseUrl . '.rest/nk/markets/list';
        $sPage = new Marktjagd_Service_Input_Page();

        $aWeekday = array(
            'mondayOpen' => 'Mo',
            'tuesdayOpen' => 'Di',
            'wednesdayOpen' => 'Mi',
            'thursdayOpen' => 'Do',
            'fridayOpen' => 'Fr',
            'saturdayOpen' => 'Sa',
            'sundayOpen' => 'So'
        );

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($jStore->id)
                ->setStreet($jStore->street)
                ->setStreetNumber($jStore->houseNumber)
                ->setZipcode($jStore->zipCode)
                ->setCity($jStore->city)
                ->setLatitude($jStore->latitude)
                ->setLongitude($jStore->longitude)
                ->setWebsite($baseUrl . preg_replace('#^\/#', '', $jStore->link));

            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="selected-shop__opening"[^>]*>(.+?)<\/div#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $pattern = '#href="tel:([^"]+?)"[^>]*selected-shop__call-button#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
