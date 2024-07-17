<?php

/**
 * Store Crawler for Electronic Partner AT (ID: 72750)
 */

class Crawler_Company_EpAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.ep.at/';
        $searchUrl = $baseUrl . 'store-finder';

        $this->_logger->info($companyId . ': opening ' . $searchUrl);
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSe);
        $page = curl_exec($ch);
        curl_close($ch);

        $pattern = '#<script[^>]*>\s*window\.storeFinderComponent\s*=\s*(.+?)\s*;\s*<\/script>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            $strTimes = '';
            foreach ($singleJStore->openingHours as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                if (strlen($singleDay->startPauseTime) && strlen($singleDay->endPauseTime)) {
                    $strTimes .= $singleDay->day . ' ' . $singleDay->openingTime . '-' . $singleDay->startPauseTime;
                    $strTimes .= ',' . $singleDay->day . ' ' . $singleDay->endPauseTime . '-' . $singleDay->closingTime;
                    continue;
                }

                $strTimes .= $singleDay->day . ' ' . $singleDay->openingTime . '-' . $singleDay->closingTime;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->code)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->long)
                ->setTitle($singleJStore->name)
                ->setWebsite($baseUrl . preg_replace('#\/#', '', $singleJStore->url))
                ->setStreetAndStreetNumber($singleJStore->address->street)
                ->setZipcode($singleJStore->address->zip)
                ->setCity($singleJStore->address->town)
                ->setPhoneNormalized($singleJStore->address->phone)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}