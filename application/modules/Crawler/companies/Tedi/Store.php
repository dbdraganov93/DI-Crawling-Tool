<?php

/*
 * Store Crawler für Tedi (ID: 22289)
 */

class Crawler_Company_Tedi_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.tedi.com/';
        $searchUrl = $baseUrl . 'filialfinder/?no_cache=1';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDb = new Marktjagd_Database_Service_GeoRegion();
        $cStores = new Marktjagd_Collection_Api_Store();

        $counter = 1;
        $aZipCodes = $sDb->findZipCodesByNetSize(80);

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

            if (!count($jStores) > 0) {
                $this->_logger->info($companyId . ': unable to get any stores for zipcode: ' . $singleZipCode);
                continue;
            } else {
                $this->_logger->info($companyId . ': '. count($jStores) . ' records for zipcode: ' . $singleZipCode);
            }

            foreach ($jStores as $singlejStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($singlejStore->filialfinder_filialnummer);
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singlejStore->filialfinder_street)));
		$eStore->setCity($sAddress->normalizeCity($singlejStore->filialfinder_city));
                $eStore->setZipcode($singlejStore->filialfinder_plz);
                $eStore->setLatitude($singlejStore->latitude);
                $eStore->setLongitude($singlejStore->longitude);

                $openingHours   =   'Mo '   . $singlejStore->filialfinder_openings_mo_from .
                                    ' - '   . $singlejStore->filialfinder_openings_mo_to . ';' .
                                    'Di '   . $singlejStore->filialfinder_openings_di_from .
                                    ' - '   . $singlejStore->filialfinder_openings_di_to . ';' .
                                    'Mi '   . $singlejStore->filialfinder_openings_mi_from .
                                    ' - '   . $singlejStore->filialfinder_openings_mi_to . ';' .
                                    'Do '   . $singlejStore->filialfinder_openings_do_from .
                                    ' - '   . $singlejStore->filialfinder_openings_do_to .';' .
                                    'Fr '   . $singlejStore->filialfinder_openings_fr_from .
                                    ' - '   . $singlejStore->filialfinder_openings_fr_to . ';' .
                                    'Sa '   . $singlejStore->filialfinder_openings_sa_from .
                                    ' - '   . $singlejStore->filialfinder_openings_sa_to . ';' .
                                    'So '   . $singlejStore->filialfinder_openings_so_from . ';' .
                                    ' - '   . $singlejStore->filialfinder_openings_so_to;

                $eStore->setStoreHours($sTimes->generateMjOpenings($openingHours));

                $tmp = preg_replace('#\s#', '', $sAddress->extractAddressPart('streetnumber', $singlejStore->filialfinder_street));
                preg_match('#(\d+\w?)(\-\d+\w?|\/\d+\w?)?#', $tmp, $streetNumber);
                $eStore->setStreetNumber($streetNumber[1] . $streetNumber[2]);

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
