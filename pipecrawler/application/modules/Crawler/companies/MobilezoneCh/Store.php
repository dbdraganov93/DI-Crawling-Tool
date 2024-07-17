<?php

/* 
 * Store Crawler für mobilezone CH (ID: 72168)
 */

class Crawler_Company_MobilezoneCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.mobilezone.ch/';
        $searchUrl = $baseUrl . 'data/de/handy-shop-reparatur/overview';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->data->data as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';
            foreach ($singleJStore->openingHours as $singleDay) {
                foreach ($singleDay->hours as $singleTimeFrame) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay->dayOfWeek . ' ' . $singleTimeFrame->openAt . '-' . $singleTimeFrame->closeAt;
                }
            }

            foreach ($singleJStore->contacts as $singleContact) {
                if (preg_match('#email#i', $singleContact->type)) {
                    $eStore->setEmail($singleContact->locator);
                } elseif (preg_match('#phone#i', $singleContact->type)) {
                    $eStore->setPhoneNormalized($singleContact->locator);
                }
            }

            $singleJStore->address->street = preg_replace('#[\"\n\r]#', '', $singleJStore->address->street);

            $singleJStore->address->state = $singleJStore->address->storeNumber == '8060' ? 'de' : $singleJStore->address->state;

            $eStore->setStoreNumber($singleJStore->storeNumber)
                ->setCity($singleJStore->address->city)
                ->setLatitude($singleJStore->address->latitude)
                ->setLongitude($singleJStore->address->longitude)
                ->setDistribution($singleJStore->address->state)
                ->setStreetAndStreetNumber($singleJStore->address->street, 'CH')
                ->setZipcode($singleJStore->address->zipCode)
                ->setWebsite($singleJStore->urls[0]->url)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}