<?php
/**
 * Store Crawler für Jardiland FR (ID: 72386)
 */

class Crawler_Company_JardilandFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.woosmap.com/';
        $searchUrl = $baseUrl . 'stores/?key=jardiland-woos-staging&page=';

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 1; $i <= 2; $i++) {
            $ch = curl_init($searchUrl . $i);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('origin: https://www.jardiland.com'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $result = curl_exec($ch);
            curl_close($ch);

            $jStores = json_decode($result);

            if (!count($jStores->features)) {
                continue;
            }

            foreach ($jStores->features as $singleJStore) {
                if (!preg_match('#fr#i', $singleJStore->properties->address->country_code)) {
                    continue;
                }

                $strTimes = '';
                if (count($singleJStore->properties->weekly_opening)) {
                    foreach ($singleJStore->properties->weekly_opening as $dayNumber => $aOpeningInfos) {
                        if (!count($aOpeningInfos->hours)) {
                            continue;
                        }

                        foreach ($aOpeningInfos->hours as $singleTime) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }

                            $strTimes .= date('D', strtotime('Sunday +' . $dayNumber . ' days'))
                                . ' ' . $singleTime->start . '-' . $singleTime->end;
                        }
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setCity($singleJStore->properties->address->city)
                    ->setStreetAndStreetNumber($singleJStore->properties->address->lines[0], 'fr')
                    ->setZipcode($singleJStore->properties->address->zipcode)
                    ->setWebsite('https://www.jardiland.com/storelocator/store/view/name/' . $singleJStore->properties->contact->website)
                    ->setPhoneNormalized($singleJStore->properties->contact->phone)
                    ->setEmail($singleJStore->properties->contact->email)
                    ->setStoreHoursNormalized($strTimes)
                    ->setLatitude($singleJStore->geometry->coordinates[1])
                    ->setLongitude($singleJStore->geometry->coordinates[0]);

                $cStores->addElement($eStore);
            }

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}