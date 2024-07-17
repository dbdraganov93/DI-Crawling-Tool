<?php
/**
 * Store Crawler für Nah & Frisch AT (ID: 72708)
 */

class Crawler_Company_NahUndFrischAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.nahundfrisch.at/';
        $searchUrl = $baseUrl . 'marktadmin/ajax/merchants/by_filters';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aParams = [
            'distance' => '50'
        ];

        $aGeo = $sDbGeo->findZipCodesByNetSize(40, TRUE, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aGeo as $singleGeoData) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);
            $aParams = array_merge($aParams, [
                    'lat' => $singleGeoData['lat'],
                    'lng' => $singleGeoData['lng']
                ]
            );

            $this->_logger->info($companyId . ': opening ' . $searchUrl . ' with parameter ' . implode('-', $aParams));
            $sPage->open($searchUrl, $aParams);
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!$jStores->success) {
                $this->_logger->info($companyId . ': no stores found for ' . implode('-', $aParams));
                continue;
            }

            foreach ($jStores->merchants as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->id)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setZipcode($singleJStore->postal)
                    ->setCity($singleJStore->city)
                    ->setPhoneNormalized($singleJStore->tel)
                    ->setEmail($singleJStore->email)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude);

                if (strlen($singleJStore->slug)) {
                    $eStore->setWebsite($baseUrl . 'de/kaufmann/' . $singleJStore->slug);

                    $oPage = $sPage->getPage();
                    $oPage->setMethod('GET');
                    $sPage->setPage($oPage);

                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();

                    $pattern = '#ffnungszeiten(.+?)<\/div>\s*<\/div>\s*<\/div#';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                    }

                    $pattern = '#<i[^>]*fax[^>]*>\s*<\/i>\s*<a[^>]*>([^<]+?)<#';
                    if (preg_match($pattern, $page, $faxMatch)) {
                        $eStore->setFaxNormalized($faxMatch[1]);
                    }
                }

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}