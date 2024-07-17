<?php

/**
 * Storecrawler für Apotheken (ID: 71678)
 *
 * Class Crawler_Company_Apotheken_Store
 */
class Crawler_Company_Apotheken_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://suche.apotheken.de/';
        $searchUrl = $baseUrl . 'search?around=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '%2C' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&radius=1000&orderBy=distanceAsc';
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.1);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $ch = curl_init($singleUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['authorization: Bearer uKs1pxszpo7IGpkOXwH1HFDSyEs1Fqmr']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $result = curl_exec($ch);
            curl_close($ch);

            $jResult = json_decode($result);
            if (preg_match('#No\s*pharmacies\s*found\s*for\s*given\s*query#', $jResult->message)) {
                continue;
            }

            foreach ($jResult as $singleJStore) {
                $strTimes = '';
                if (array_key_exists(0, $singleJStore->openingTimes)) {
                    foreach ($singleJStore->openingTimes[0] as $day => $times) {
                        foreach ($times->openingtimes as $singleTimeFrame) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $day . ' ' . $singleTimeFrame->opening . '-' . $singleTimeFrame->closing;
                        }
                    }
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setTitle($singleJStore->name)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zip)
                    ->setEmail($singleJStore->email)
                    ->setPhoneNormalized($singleJStore->telephone)
                    ->setWebsite($singleJStore->website)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreHoursNormalized($strTimes);

                if ($cStores->addElement($eStore)) {
                    $this->_logger->info($companyId . ': store added. ' . count($cStores->getElements()) . ' stores in collection.');

                }
            }
//            sleep(5);
        }

        return $this->getResponse($cStores);
    }
}
