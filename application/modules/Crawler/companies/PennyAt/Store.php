<?php
/**
 * Store Crawler für Penny AT (ID: 72742)
 */

class Crawler_Company_PennyAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.penny.at/';
        $searchUrl = $baseUrl . 'api/stores';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'correlationid: 71e8b804-2063-4a54-bd87-c94215810a6a',
            'Referer: https://www.penny.at/filialsuche']);

        $result = curl_exec($ch);
        curl_close($ch);
        $jStores = json_decode($result);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#AUT#', $singleJStore->country)
                || !$singleJStore->open) {
                continue;
            }

            $strTimes = '';
            foreach ($singleJStore->openingTimes as $days) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $days->dayOfWeek . ' ' . $days->time;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setZipcode($singleJStore->zip)
                ->setStreetAndStreetNumber($singleJStore->street)
                ->setPhoneNormalized($singleJStore->phone)
                ->setLongitude($singleJStore->coordinate->x)
                ->setLatitude($singleJStore->coordinate->y)
                ->setCity($singleJStore->city)
                ->setStoreHoursNormalized($strTimes)
                ->setDistribution($singleJStore->province);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}