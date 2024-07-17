<?php

/**
 * Sotre Crawler für Mc Donald's (ID: 28989)
 */
class Crawler_Company_McDonalds_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mcdonalds.de/';
        $searchUrl = $baseUrl . 'json/feeds/restaurants.json';
        $sPage = new Marktjagd_Service_Input_Page();

        $aServices = array(
            'mcDrive' => 'McDrive',
            'wlan' => 'WLAN',
            'coupons' => 'Gutscheine',
            'toggo' => 'Kindergeburstage',
            'mcCafe' => 'McCafé'
        );
        
        
        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->restaurants as $singleJStore) {
            $strTimes = '';
            if ($singleJStore->open24h) {
                $strTimes = 'Mo-So 00:00-24:00';
            } else {
                foreach ($singleJStore->hoursOfOperation as $day => $time) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $day . ' ' . $time;
                }
            }
            
            $strServices = '';
            foreach ($aServices as $singleService => $serviceName) {
                if ($singleJStore->{$singleService}) {
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }
                    $strServices .= $serviceName;
                }
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->id)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postalCode)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setWebsite($baseUrl . 'restaurant/' . $singleJStore->seoURL)
                    ->setStoreHoursNormalized($strTimes)
                    ->setService($strServices);
            
            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
