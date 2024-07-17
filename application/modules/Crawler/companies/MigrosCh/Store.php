<?php

/*
 * Store Crawler für Migros CH (ID: 72162)
 */

class Crawler_Company_MigrosCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://web-api.migros.ch/';
        $searchUrl = $baseUrl . 'widgets/stores?key=loh7Diephiengaiv&aggregation_options[empty_buckets]=true' .
            '&filters[markets][0][0]=super&verbosity=detail&offset=0&limit=2000';
        $sPage = new Marktjagd_Service_Input_Page();

        $migrosDistributions = array(
            'gmnf' => '72393',
            'gmbs' => '72162',
            'gmvd' => '72373',
            'gmge' => '72394',
            'gmaa' => '72396',
            'gmvs' => '72397',
            'gmlu' => '72398',
            'gmzh' => '72399',
            'gmti' => '72400',
            'gmos' => '72401'
        );

        $aWeekDays =
            [
                1 => 'Mo',
                2 => 'Di',
                3 => 'Mi',
                4 => 'Do',
                5 => 'Fr',
                6 => 'Sa',
                7 => 'So'
            ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Origin: https://filialen.migros.ch'));
        $result = curl_exec($ch);

        $jStores = json_decode($result);
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->stores as $singleJStore) {
            if (strlen($singleJStore->location->zip) != 4
                || !preg_match('#CH#', $singleJStore->location->country)
                || $migrosDistributions[$singleJStore->cooperative->id] != $companyId) {
                continue;
            }
            $strTimes = '';
            foreach ($singleJStore->markets[0]->opening_hours[0]->opening_hours as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }

                $strTimes .= $aWeekDays[$singleDay->day_of_week] . ' ' . $singleDay->time_open1 . '-' . $singleDay->time_close1;
                if (!is_null($singleDay->time_open2)) {
                    $strTimes .= ',' . $aWeekDays[$singleDay->day_of_week] . ' ' . $singleDay->time_open2 . '-' . $singleDay->time_close2;
                }
            }

            $detailUrl = '';
            foreach ($singleJStore->markets as $singleMarket) {
                if (preg_match('#supermarkt#', $singleMarket->localized_slugs->de)) {
                    $detailUrl = 'https://filialen.migros.ch/de/' . $singleMarket->localized_slugs->de;
                    break;
                }
            }
            if (!strlen($detailUrl)) {
                $detailUrl = 'https://filialen.migros.ch/de/' . $singleJStore->markets[0]->localized_slugs->de;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->id)
                ->setZipcode($singleJStore->location->zip)
                ->setCity($singleJStore->location->city)
                ->setStreetAndStreetNumber($singleJStore->location->address, 'CH')
                ->setLatitude($singleJStore->location->geo->lat)
                ->setLongitude($singleJStore->location->geo->lon)
                ->setPhoneNormalized($singleJStore->phone)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($strTimes)
                ->setWebsite($detailUrl);

            $cStores->addElement($eStore);

        }
        return $this->getResponse($cStores, $companyId);
    }

}
