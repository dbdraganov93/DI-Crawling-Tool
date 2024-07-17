<?php

/**
 * Store Crawler für Hyundai (ID: 68836)
 */
class Crawler_Company_Hyundai_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://dealer-search.hyundai.de/';
        $searchUrl = $baseUrl . 'api/data/dealers/lat/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
            . '/lng/' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'coords', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson();

            foreach ($jStores as $singleJStore) {
                $strService = '';
                foreach ($singleJStore->attributes as $singleService) {
                    if (strlen($strService)) {
                        $strService .= ', ';
                    }

                    $strService .= $singleService->name;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singleJStore->id)
                    ->setSubtitle($singleJStore->name)
                    ->setPhoneNormalized($singleJStore->phone)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zipCode)
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setWebsite($singleJStore->url)
                    ->setService($strService);

                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}