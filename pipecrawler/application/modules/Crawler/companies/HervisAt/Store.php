<?php

/*
 * Store Crawler für Hervis AT (ID: 72287)
 */

class Crawler_Company_HervisAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hervis.at/';
        $searchUrl = $baseUrl . 'store/store-finder/position-ajax?'
            . 'centerLat=47.7&centerLng=13.8&northEastLat=49.1&northEastLng=17.2&southWestLat=46.3&southWestLng=9.5';
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
                ->setStoreHoursNormalized(preg_replace('#\s*und\s*#', ',', $singleJStore->openingTimes));

            if (preg_match('#^([^\s]+)\s+[^<]+?und#', $singleJStore->openingTimes, $weekDayMatch)) {
                $strTime = preg_replace('#\s*und\s*#', $weekDayMatch[1] . ' ', $singleJStore->openingTimes);
                $eStore->setStoreHoursNormalized($strTime);
            }

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
