<?php
/**
 * Store Crawler für Mr. Bricolage FR (ID: 73518)
 */

class Crawler_Company_MrBricolageFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://bridge-api.leadformance.com/';
        $searchUrl = $baseUrl . 'locations/full-text-search';
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeo->findZipCodesByNetSize(25);

        $aInfos = array(
            'options' => array(
                'country' => 'fr',
                'language' => 'fr'
            )
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aInfos['term'] = $singleZipcode;

            $ch = curl_init($searchUrl);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($aInfos));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'authorization: Hbaafd8fb16710001f28b1_4cbf38d8-386c-4418-8603-c3d7c2b65018',
                'Content-Type: application/json',
                'X-Api-Key: 58cbaafd8fb16710001f28b1_4cbf38d8-386c-4418-8603-c3d7c2b65018',
                'Accept: application/vnd.bridge+json; version=1}'
            ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

            $result = curl_exec($ch);
            curl_close($ch);

            $jStores = json_decode($result);

            foreach ($jStores->rows as $singleJStore) {
                if (!preg_match('#^active#i', $singleJStore->status)) {
                    continue;
                }

                $strTimes = '';
                foreach ($singleJStore->openingHours as $singleDay => $aStoreHours) {
                    foreach ($aStoreHours->periods as $singlePeriods) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }

                        $strTimes .= $singleDay . ' ' . $singlePeriods->openTime . '-' . $singlePeriods->closeTime;
                    }
                }

                $strServices = '';
                foreach ($singleJStore->offerRanges[0]->offerRange->offers as $singleService) {
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }

                    $strServices .= $singleService->name;
                }

                $aStreet = preg_split('#\s*\n\s*#', $singleJStore->localisation->address1);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->_id)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setWebsite($singleJStore->url)
                    ->setCity($singleJStore->localisation->city)
                    ->setZipcode($singleJStore->localisation->postalCode)
                    ->setLatitude($singleJStore->localisation->latitude)
                    ->setLongitude($singleJStore->localisation->longitude)
                    ->setStreetAndStreetNumber(end($aStreet), 'fr')
                    ->setStoreHoursNormalized($strTimes)
                    ->setService($strServices);

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}