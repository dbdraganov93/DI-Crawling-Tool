<?php

/*
 * Store Crawler für Hervis (ID: 71919)
 */

class Crawler_Company_Hervis_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hervis.de/';
        $searchUrl = $baseUrl . 'store/store-finder/position-ajax?'
            . 'centerLat=48.2&centerLng=11.5&northEastLat=55.2&northEastLng=15.2&southWestLat=47.2&southWestLng=5.8';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jStores = $sPage->getPage()->getResponseAsJson();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->results as $singleJStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $aStreet = preg_split('#\s*,\s*#', $singleJStore->address->line1);

            $eStore->setLatitude($singleJStore->latitude)
                ->setLongitude($singleJStore->longitude)
                ->setZipcodeAndCity($singleJStore->address->line2)
                ->setStreetAndStreetNumber($aStreet[0])
                ->setStoreHoursNormalized($singleJStore->openingTimes);

            if (count($aStreet) == 2) {
                $eStore->setStreetAndStreetNumber($aStreet[1])
                    ->setSubtitle($aStreet[0]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
