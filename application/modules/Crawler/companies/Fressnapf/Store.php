<?php

/**
 * Store Crawler für Fressnapf (ID: 346)
 */
class Crawler_Company_Fressnapf_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.os.fressnapf.com/';
        $searchUrl = $baseUrl . 'rest/v2/fressnapfDE/stores?query=99089&sort=asc&currentPage=0&radius=2500000&fields=FULL';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i <= $jStores->pagination->totalPages; $i++) {
            $searchUrl = $baseUrl . 'rest/v2/fressnapfDE/stores?query=99089&sort=asc&currentPage=' . $i . '&radius=2500000&fields=FULL';

            $sPage->open($searchUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores->stores as $singleJStore) {
                if (!preg_match('#DE#', $singleJStore->address->country->isocode)
                    || !$singleJStore->active) {
                    continue;
                }

                $strTimes = '';
                foreach ($singleJStore->openingHours->weekDayOpeningList as $singleDay) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }

                    $strTimes .= $singleDay->weekDay . ' ' . $singleDay->openingTime->formattedHour
                        . '-' . $singleDay->closingTime->formattedHour;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->storeNumber)
                    ->setStreetAndStreetNumber($singleJStore->address->line1)
                    ->setPhoneNormalized($singleJStore->address->phone)
                    ->setZipcode($singleJStore->address->postalCode)
                    ->setCity($singleJStore->address->town)
                    ->setLatitude($singleJStore->geoPoint->latitude)
                    ->setLongitude($singleJStore->geoPoint->longitude)
                    ->setStoreHoursNormalized($strTimes)
                    ->setWebsite('https://www.fressnapf.de/stores/' . $singleJStore->partForUrlGen);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

}
