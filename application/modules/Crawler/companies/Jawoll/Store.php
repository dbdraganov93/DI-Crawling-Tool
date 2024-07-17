<?php

/* 
 * Store Crawler für Jawoll (ID: 29087)
 */

class Crawler_Company_Jawoll_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://cdn.contentful.com/';
        $searchUrl = $baseUrl . 'spaces/3rvj9sgg0vmv/environments/master/entries?content_type=store';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer 91b2e7ca82d4bb72acd67560493af291c2784f221bcd09b5f28b90b8127edb2d']);
        $page = curl_exec($ch);
        curl_close($ch);

        $jStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->items as $singleJStore) {
            $strTimes = '';
            foreach ($singleJStore->fields->openingHours->content as $singleStoreHoursInfo) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $singleStoreHoursInfo->content[0]->value;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setLongitude($singleJStore->fields->storeAddress->lon)
                ->setLatitude($singleJStore->fields->storeAddress->lat)
                ->setStreetAndStreetNumber($singleJStore->fields->street)
                ->setZipcode($singleJStore->fields->zipcode)
                ->setCity($singleJStore->fields->city)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}