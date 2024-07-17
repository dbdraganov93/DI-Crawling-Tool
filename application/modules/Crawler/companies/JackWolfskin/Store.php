<?php

/* 
 * Store Crawler für Jack Wolfskin (ID: 22237)
 */

class Crawler_Company_JackWolfskin_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.jack-wolfskin.com/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-JackWolfskin_INT-Site/'
            . 'de_DE/Store-FindStores?lat='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&lng='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseBody();
            $jStores = json_decode(preg_replace('#,\s*"participatingStores":\s*\["[^\]]+\]#', '', $jStores));
            foreach ($jStores->stores as $singleJStore) {
                if (!preg_match('#^(\+49)#', $singleJStore->phone)
                    || !preg_match('#\@jack-wolfskin\.com#', $singleJStore->email)
                ) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreHoursNormalized($this->getTimeJson($singleJStore->storeHours))
                    ->setStreetAndStreetNumber( $singleJStore->address1)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setEmail($singleJStore->email)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setStoreNumber($singleJStore->id)
                    ->setSubtitle($singleJStore->address2);

                $cStores->addElement($eStore, true);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    private function getTimeJson($jsonString)
    {
        $days = array(1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa',);
        if (!preg_match_all('#"(\d)"#', $jsonString, $dayMatches)) {
            return $jsonString;
        }
        $bla = preg_replace('#([^0-9])(\d{1})([^0-9])#', '$1 0$2$3', $jsonString);
        $aWeekdays = preg_split('#\],#', $bla);
        $retTimes = '';
        $sep = ', ';
        foreach ($aWeekdays as $weekday) {
            preg_match_all('#(\d{2})#', $weekday, $hours);
            $retTimes .= $days[(int)($hours[0][0])] . ' ' . $hours[0][1] . ':' . $hours[0][2] . '-' . $hours[0][3] . ':' . $hours[0][4].$sep;
        }
        return trim($retTimes, $sep);
    }
}
