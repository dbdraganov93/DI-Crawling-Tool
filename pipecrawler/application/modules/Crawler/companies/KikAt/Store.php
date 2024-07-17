<?php

/**
 * Store Crawler für KiK AT (ID: 73747)
 */

class Crawler_Company_KikAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {


        $baseUrl = 'https://www.kik.de/';
        $searchUrl = $baseUrl . 'storefinder/results.json?searchlocation=1010&lat='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT . '&long='
            . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&country=AT';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $singleUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $jStores = json_decode(curl_exec($curl));
            curl_close($curl);

            if ($jStores->stores[0]->success == false) {
                continue;
            }

            foreach ($jStores->stores[0]->results as $key => $singleJStore) {
                if (!preg_match('#\d+#', $key)) {
                    continue;
                }
                if ($singleJStore->country != 5) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $strSections = '';
                if (isset($singleJStore->collections) && $singleJStore->collections) {
                    foreach ($singleJStore->collections as $singleSection) {
                        if (strlen($strSections)) {
                            $strSections .= ', ';
                        }
                        $strSections .= $singleSection->name;
                    }
                }

                $eStore->setStoreNumber($singleJStore->filiale)
                    ->setStreetAndStreetNumber($singleJStore->address)
                    ->setZipcode($singleJStore->zip)
                    ->setCity($singleJStore->city)
                    ->setStoreHoursNormalized(preg_replace('#\*#', ',', $singleJStore->opening_times))
                    ->setLongitude($singleJStore->longitude)
                    ->setLatitude($singleJStore->latitude)
                    ->setSection($strSections);

                if (strlen($eStore->getZipcode()) == 4) {
                    $cStores->addElement($eStore, TRUE);
                }
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}