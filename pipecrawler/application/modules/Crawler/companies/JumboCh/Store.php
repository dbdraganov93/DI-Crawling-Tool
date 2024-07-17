<?php

/**
 * Store Crawler für Jumbo (CH) (ID: 72131)
 */

class Crawler_Company_JumboCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sApi->findStoresByCompany(72152);
        $baseUrl = 'https://www.jumbo.ch';
        $searchUrl = $baseUrl . '/yp/de/api/stores?center[lat]=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '&center[lon]=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON . '&sort=distance&distance=1000km';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.5, 'CH');

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();
            foreach ($jStores->stores as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $strServices = '';
                foreach ($singleJStore->services as $singleService) {
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }

                    $strServices .= $singleService->name;
                }

                $strStoreHours = '';
                foreach ($singleJStore->opening_hours as $day => $aTime) {
                    if (strlen($strStoreHours)) {
                        $strStoreHours .= ',';
                    }

                    $strStoreHours .= $day . ' ' . preg_replace('#:00$#', '', $aTime->from) . '-' . preg_replace('#:00$#', '', $aTime->to);
                }

                $eStore->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->postcode)
                    ->setService($strServices)
                    ->setWebsite($baseUrl . $singleJStore->url)
                    ->setStoreNumber($singleJStore->id)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setImage($baseUrl . $singleJStore->image_path)
                    ->setStoreHoursNormalized($strStoreHours)
                    ->setLatitude($singleJStore->location->latitude)
                    ->setLongitude($singleJStore->location->longitude)
                    ->setFaxNormalized($singleJStore->fax)
                    ->setEmail($singleJStore->email);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores);
    }
}