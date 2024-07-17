<?php

/**
 * Store Crawler für Runnerspoint (ID: 203)
 */
class Crawler_Company_Runnerspoint_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.runnerspoint.de/';
        $searchUrl = $baseUrl . 'INTERSHOP/web/WFS/Runnerspoint-Runnerspoint_DE-Site/'
                . 'de_DE/-/EUR/ViewStoreLocator-Search'
                . '?Latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&Longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'rect');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            
            if (!count($jStores)) {
                continue;
            }
            
            foreach ($jStores as $singleJStore) {
                if (strlen($singleJStore->phoneNumber)
                    && !preg_match('#^\+49#', $singleJStore->phoneNumber)) {
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
                                
                $eStore->setLongitude($singleJStore->longitude)
                        ->setLatitude($singleJStore->latitude)
                        ->setStoreNumber($singleJStore->storeNumber)
                        ->setWebsite($singleJStore->storeUrl)
                        ->setStoreHoursNormalized($strTimes)
                        ->setCity($singleJStore->city)
                        ->setZipcode($singleJStore->postalCode)
                        ->setStreetAndStreetNumber($singleJStore->street);
                
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
