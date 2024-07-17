<?php

/*
 * Store Crawler für Trink Gut (ID: 22241)
 */

class Crawler_Company_TrinkGut_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.trinkgut.de/';
        $searchUrl = $baseUrl . 'marktsuche?location=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '&radius=20';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 10);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<script[^>]*type="application/json"[^>]*id="markets--data">([^<]+?)</script>#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': unable to get any stores: ' . $singleUrl);
                continue;
            }

            $jsonStores = json_decode($storeListMatch[1]);
            foreach ($jsonStores as $singleJStore) {
                if (!strlen($singleJStore->city)) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->id)
                        ->setStreetAndStreetNumber($singleJStore->street)
                        ->setZipcode($singleJStore->zipCode)
                        ->setCity($singleJStore->city)
                        ->setPhoneNormalized($singleJStore->phone)
                        ->setFaxNormalized($singleJStore->fax)
                        ->setStoreHoursNormalized('Mo-Fr ' . $singleJStore->openingHoursWeek . ', Sa ' . $singleJStore->openingHoursSaturday)
                        ->setLatitude($singleJStore->latitude)
                        ->setLongitude($singleJStore->longitude)
                        ->setWebsite($singleJStore->detailUrl);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
