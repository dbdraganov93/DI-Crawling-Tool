<?php

/**
 * Store Crawler für SIDESTEP (ID: 67667)
 */
class Crawler_Company_Sidestep_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.sidestep-shoes.de/';
        $searchUrl = $baseUrl . 'INTERSHOP/web/WFS/Sidestep-Sidestep_DE-Site/de_DE/'
                . '-/EUR/ViewStoreLocator-Search?Latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&Longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;

        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'rect');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $singleJStore) {
                if (!preg_match('#^(\+49)#', $singleJStore->phoneNumber)) {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $strTimes = '';
                foreach ($singleJStore->openingHours as $singleDay) {
                    if (preg_match('#00:00.*00:00#', $singleDay->hours)) {
                        continue;
                    }
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay->day . ' ' . $singleDay->hours;
                }
                
                $eStore->setStoreNumber($singleJStore->storeNumber)
                        ->setPhoneNormalized($singleJStore->phoneNumber)
                        ->setWebsite($singleJStore->storeUrl)
                        ->setStoreHoursNormalized($strTimes)
                        ->setCity(preg_replace('#Cologne#', 'Köln', $singleJStore->city))
                        ->setZipcode($singleJStore->postalCode)
                        ->setStreetAndStreetNumber($singleJStore->street)
                        ->setLatitude($singleJStore->latitude)
                        ->setLongitude($singleJStore->longitude);
                
                $cStores->addElement($eStore, TRUE);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
