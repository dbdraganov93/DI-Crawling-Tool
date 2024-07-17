<?php
/**
 * Store Crawler für Joué Club FR (ID: )
 */

class Crawler_Company_JoueClubFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://api.woosmap.com/';
        $searchUrl = $baseUrl . 'stores/nearby?key=joueclub-woos&lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&lng=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&max_distance=50000&stores_by_page=300&limit=300&page=';
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2, 'fr');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            for ($i = 1; $i <=2; $i++) {
                $ch = curl_init($singleUrl . $i);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('origin: https://www.joueclub.fr'));
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

                    $eStore->setCity(ucwords(strtolower($singleJStore->properties->address->city)))
                        ->setStreetAndStreetNumber($singleJStore->properties->address->lines[0], 'fr')
                        ->setZipcode($singleJStore->properties->address->zipcode)
                        ->setPhoneNormalized($singleJStore->properties->contact->phone)
                        ->setEmail($singleJStore->properties->contact->email)
                        ->setStoreHoursNormalized($strTimes);

                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}