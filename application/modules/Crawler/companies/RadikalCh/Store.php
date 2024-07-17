<?php

/* 
 * Store Crawler für Radikal Liquidationen (ID: 72160)
 */

class Crawler_Company_RadikalCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://radikal-liquidationen.ch/';
        $searchUrl = $baseUrl . 'wp-admin/admin-ajax.php?lang=de&action=store_search&lat=47.05017&lng=8.30931&max_results=1000&search_radius=500';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $storeJList = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeJList as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setStreetAndStreetNumber($singleJStore->address, 'CH')
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setStoreNumber($singleJStore->id)
                ->setPhone($singleJStore->phone)
                ->setFax($singleJStore->fax)
                ->setEmail($singleJStore->email);


            $pattern = '#<tr>(?<dayOfWeek>.*?)<\/tr>#';
            if (preg_match_all($pattern, $singleJStore->hours, $storeHoursMatch)) {
                $storeHours = '';
                foreach($storeHoursMatch['dayOfWeek'] as $weekday) {
                    if(strlen($storeHours) > 0) {
                        $storeHours .= ", ";
                    }
                    preg_match('#<td>(.*?)<\/td>#', $weekday, $day);
                    preg_match('#<time>(.*?)<\/time>#', $weekday, $hours);
                    $storeHours .= $day[1] . ' ' . $hours[1];
                }

                $eStore->setStoreHoursNormalized($storeHours);
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}