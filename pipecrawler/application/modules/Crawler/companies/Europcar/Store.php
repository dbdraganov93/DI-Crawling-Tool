<?php

/*
 * Store Crawler für Europcar (ID: 22306)
 */

class Crawler_Company_Europcar_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://applications.europcar.com/';
        $searchUrl = $baseUrl . 'stationfinder/stationfinder?query=getAllCountriesStations&lg=de_DE';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $aWeekdays = array(
            '1' => 'Mo',
            '2' => 'Di',
            '3' => 'Mi',
            '4' => 'Do',
            '5' => 'Fr',
            '6' => 'Sa',
            '7' => 'So',
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

        $aData = curl_exec($ch);

        curl_close($ch);

        $jStores = json_decode($aData);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->allCoutriesStations->DE as $singleGerStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $strTimes = '';
            foreach ($singleGerStore->details->openhours as $singleWeekdayKey => $singleWeekdayValue) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $aWeekdays[$singleWeekdayKey] . ' ' . $singleWeekdayValue->normalHours[0];
            }


            $eStore->setStoreNumber($singleGerStore->code)
                    ->setStreetAndStreetNumber(ucwords(strtolower($singleGerStore->details->street)))
                    ->setCity(ucwords(strtolower($singleGerStore->details->city)))
                    ->setZipcode($singleGerStore->details->postcode)
                    ->setLatitude($singleGerStore->details->latitude)
                    ->setLongitude($singleGerStore->details->longitude)
                    ->setPhoneNormalized($singleGerStore->details->phone)
                    ->setFaxNormalized($singleGerStore->details->fax)
                    ->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
