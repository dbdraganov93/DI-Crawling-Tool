<?php
/**
 * Store Crawler für Go Sport FR (ID: 72385)
 */

class Crawler_Company_GoSportFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://stores.go-sport.com/';
        $searchUrl = $baseUrl . 'stores/select?q=(is_active:1%20AND%20group:%22Store%20Locator%22)&rows=10000&wt=json&' .
            'fq={!geofilt}&sfield=GeoPosition&pt=49,3&d=10000&sort=geodist()%20asc&omitHeader=true&fl=';

        $aWeekdays = array(
            'mo',
            'tu',
            'we',
            'th',
            'fr',
            'sa',
            'su'
        );

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = curl_exec($ch);
        curl_close($ch);

        $jStores = json_decode($result);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->response->docs as $singleJStore) {
            if ($singleJStore->is_active != 1
                || !preg_match('#store#', $singleJStore->doc_type)
                || !preg_match('#FRA#', $singleJStore->country)) {
                continue;
            }

            $strTimes = '';
            foreach ($aWeekdays as $singleWeekday) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                if (strlen($singleJStore->{$singleWeekday . '_am_end'})
                    && strlen($singleJStore->{$singleWeekday . '_pm_start'})) {
                    $strTimes .= $singleWeekday . ' ' . $singleJStore->{$singleWeekday . '_am_start'} . '-'
                        . $singleJStore->{$singleWeekday . '_am_end'};
                    $strTimes .= $singleWeekday . ' ' . $singleJStore->{$singleWeekday . '_pm_start'} . '-'
                        . $singleJStore->{$singleWeekday . '_pm_end'};
                    continue;
                }
                $strTimes .= $singleWeekday . ' ' . $singleJStore->{$singleWeekday . '_am_start'} . '-'
                    . $singleJStore->{$singleWeekday . '_pm_end'};
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->id)
                ->setLatitude($singleJStore->lat)
                ->setLongitude($singleJStore->lng)
                ->setZipcode($singleJStore->zip)
                ->setCity(ucwords(strtolower($singleJStore->city)))
                ->setStreetAndStreetNumber($singleJStore->street1, 'fr')
                ->setPhoneNormalized($singleJStore->phone)
                ->setEmail($singleJStore->email)
                ->setStoreHoursNormalized($strTimes);

            if (strlen($singleJStore->street2)) {
                $eStore->setStreetAndStreetNumber($singleJStore->street2, 'fr');
            }

            if (count($singleJStore->service_name)) {
                $eStore->setService(implode(', ', $singleJStore->service_name));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}