<?php

/**
 * Store Crawler für BankCard (ID: 69742)
 */
class Crawler_Company_BankCard_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.vr.de/';
        $searchUrl = $baseUrl . 'bin/webCenter/filialsuche?service=banks&zip_code='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&teilnahme_vrde=true&api_radius=100&api_limit=500';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 25);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aLinks as $singleLink) {
            try {
                $sPage->open($singleLink);

                $jStores = json_decode(preg_replace(array('#callback\(#', '#\)#'), array('', ''), $sPage->getPage()->getResponseBody()), true);
                foreach ($jStores as $singleJStore) {
                    $eStore = new Marktjagd_Entity_Api_Store();
                    $strTimes = '';
                    for ($i = 1; $i <= 8; $i++) {
                        if (!strlen($singleJStore['opening_time_' . $i])) {
                            break;
                        }
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleJStore['opening_time_' . $i];
                    }

                    $eStore->setStoreNumber($singleJStore['id'])
                            ->setTitle($singleJStore['name'])
                            ->setPhoneNormalized($singleJStore['phone_area'] . $singleJStore['phone_number'])
                            ->setFaxNormalized($singleJStore['fax_area'] . $singleJStore['fax_number'])
                            ->setEmail($singleJStore['email'])
                            ->setWebsite($singleJStore['detail_page_url'])
                            ->setStoreHoursNormalized($strTimes)
                            ->setCity($singleJStore['city'])
                            ->setZipcode($singleJStore['zip_code'])
                            ->setStreetAndStreetNumber('street', $singleJStore['street'])
                            ->setLongitude($singleJStore['longitude'])
                            ->setLatitude($singleJStore['latitude']);

                    if ($singleJStore['free_parking']) {
                        $eStore->setParking('vorhanden');
                    }
                    if ($singleJStore['wheelchair_accessible']) {
                        $eStore->setBarrierFree(true);
                    }

                    $cStores->addElement($eStore);
                }
            } catch (Exception $e) {
                $this->_logger->info($companyId . ': ' . $e);
                continue;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
