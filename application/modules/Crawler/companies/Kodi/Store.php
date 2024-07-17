<?php

/**
 * Store Crawler für KODi Diskontläden (ID: 63)
 */
class Crawler_Company_Kodi_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.kodi.de/';
        $searchUrl = $baseUrl . 'service/filialfinder/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#data-googlemapsstores=\'([^>]+?)\'>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeListMatch[1]) as $singleJStore) {
            if (!preg_match('#DE#', $singleJStore->countryCode)) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeId)
                ->setStreetAndStreetNumber($singleJStore->street . ' ' . $singleJStore->streetNo)
                ->setZipcode($singleJStore->zip)
                ->setCity($singleJStore->city)
                ->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setPhoneNormalized($singleJStore->phone)
                ->setStoreHoursNormalized($singleJStore->openingHours);

            $cStores->addElement($eStore);

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
