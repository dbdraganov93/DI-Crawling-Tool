<?php
/**
 * Store crawler für Aldi Nord(ID: 30)
 */

class Crawler_Company_Aldi_StoreNord extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://locator.uberall.com/';
        $searchUrl = $baseUrl . 'api/storefinders/ALDINORDDE_UimhY3MWJaxhjK9QdZo3Qa4chq1MAu/' .
            'locations?v=20230110&language=de&radius=1000000000&lat=45&lng=10&max=5000';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->response->locations as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->country)) {
                continue;
            }

            $daysOfWeek = [
                1 => "Montag",
                2 => "Dienstag",
                3 => "Mittwoch",
                4 => "Donnerstag",
                5 => "Freitag",
                6 => "Samstag",
                7 => "Sonntag"
            ];

            $formattedOpeningHours = [];
            foreach ($singleJStore->openingHours as $openingHour) {
                if (isset($openingHour->closed) && $openingHour->closed) {
                    $formattedOpeningHours[] = $daysOfWeek[$openingHour->dayOfWeek] . ": Closed";
                } else {
                    $formattedOpeningHours[] = $daysOfWeek[$openingHour->dayOfWeek] . ": " . $openingHour->from1 . " - " . $openingHour->to1;
                }
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->identifier)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setTitle($singleJStore->name)
                ->setCity($singleJStore->city)
                ->setStreetAndStreetNumber($singleJStore->streetAndNumber)
                ->setZipcode($singleJStore->zip)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStoreHoursNormalized(implode(',', $formattedOpeningHours));

            $cStores->addElement($eStore);

        }

        return $this->getResponse($cStores);
    }
}
