<?php

/*
 * Store Crawler für Tedi (ID: 22289)
 */

class Crawler_Company_TediAt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tedi.com/';
        $searchUrl = $baseUrl . 'at/filialfinder';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDb = new Marktjagd_Database_Service_GeoRegion();

        $counter = 1;
        $aZipCodes = $sDb->findZipCodesByNetSize(80, FALSE, 'AT');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $singleZipCode) {
            $aParams = array(
                'area' => '100',
                'country' => '101',
                'type' => '65149',
                'location' => $singleZipCode
            );

            $this->_logger->info('open for zipcode ' . $singleZipCode . ', request ' . $counter++ . ' of ' . count($aZipCodes));
            $sPage->open($searchUrl, $aParams);
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!$jStores || !count($jStores)) {
                $this->_logger->info($companyId . ': unable to get any stores for zipcode: ' . $singleZipCode);
                continue;
            } else {
                $this->_logger->info($companyId . ': ' . count($jStores) . ' records for zipcode: ' . $singleZipCode);
            }

            foreach ($jStores as $singlejStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($singlejStore->filialfinder_filialnummer)
                    ->setStreetAndStreetNumber($singlejStore->filialfinder_street)
                    ->setCity($singlejStore->filialfinder_city)
                    ->setZipcode($singlejStore->filialfinder_plz)
                    ->setLatitude($singlejStore->latitude)
                    ->setLongitude($singlejStore->longitude);

                $openingHours = 'Mo ' . $singlejStore->filialfinder_openings_mo_from .
                    ' - ' . $singlejStore->filialfinder_openings_mo_to . ';' .
                    'Di ' . $singlejStore->filialfinder_openings_di_from .
                    ' - ' . $singlejStore->filialfinder_openings_di_to . ';' .
                    'Mi ' . $singlejStore->filialfinder_openings_mi_from .
                    ' - ' . $singlejStore->filialfinder_openings_mi_to . ';' .
                    'Do ' . $singlejStore->filialfinder_openings_do_from .
                    ' - ' . $singlejStore->filialfinder_openings_do_to . ';' .
                    'Fr ' . $singlejStore->filialfinder_openings_fr_from .
                    ' - ' . $singlejStore->filialfinder_openings_fr_to . ';' .
                    'Sa ' . $singlejStore->filialfinder_openings_sa_from .
                    ' - ' . $singlejStore->filialfinder_openings_sa_to . ';' .
                    'So ' . $singlejStore->filialfinder_openings_so_from . ';' .
                    ' - ' . $singlejStore->filialfinder_openings_so_to;

                $eStore->setStoreHoursNormalized($openingHours);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }
}
