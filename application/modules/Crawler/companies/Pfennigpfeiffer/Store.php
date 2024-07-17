<?php

/* 
 * Store Crawler für Pfennigpfeiffer (ID: 41)
 */

class Crawler_Company_Pfennigpfeiffer_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pfennigpfeiffer.de/';
        $detailUrl = $baseUrl . 'maerkte/';
        $ajaxUrl = 'http://www.pfennigpfeiffer.de/ustorelocator/location/search/?addr=';
        $storeUrl = 'https://www.pfennigpfeiffer.de/filialfinder/?zip=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $sText = new Marktjagd_Service_Text_TextFormat();
        $cStores = new Marktjagd_Collection_Api_Store();

        $distributionGroups = array(
            'Berlin-Brandenburg-Mecklenb.–Vorp.' => array('BE', 'BB', 'MV'),
            'Niedersachsen-Hessen-Rheinland-Pfalz' => array('NI', 'HE', 'RP'),
            'Sachsen-Thüringen-Sachsen-Anhalt' => array('SN', 'TH', 'ST'),
            'Bayern' => array('BY'),
            'Niedersachsen-Hessen' => array('NI', 'HE')
        );

        $sPage->open($detailUrl);
        $page = $sPage->getPage()->getResponseBody();

        $zipPattern = '#zip=(?<zips>\d{5})#';
        if(!preg_match_all($zipPattern, $page, $zipMatches)) {
            throw new Exception(
                $companyId . ' -> Was not possible to get any zip Stores from: ' . $detailUrl
            );
        }

        $oPage = $sPage->getPage();
        $oPage->setAlwaysConvertCharset(false);
        $oPage->setAlwaysHtmlDecode(false);
        $oPage->setAlwaysStripNewLines(false);
        $oPage->setAlwaysStripWhiteSpace(false);
        $oPage->setAlwaysStripComments(false);
        $sPage->setPage($oPage);


        foreach ($zipMatches['zips'] as $zip) {
            $this->_logger->info('Opening URL: ' . $ajaxUrl . $zip);
            $sPage->open($ajaxUrl . $zip);
            $page = $sPage->getPage()->getResponseBody();
            $pattern = '#<marker ([^>]+)>#';
            if (!preg_match_all($pattern, $page, $sMatches)) {
                $this->_logger->log('unable to get stores from region: ' . $ajaxUrl . $zip, Zend_Log::INFO);
                continue;
            }

            foreach ($sMatches[1] as $storeText) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setWebsite($storeUrl . $zip)
                    ->setZipcode($zip)
                ;

                $pattern = '#([a-z_]+)="([^"]*)"#';
                if (!preg_match_all($pattern, $storeText, $aMatches)) {
                    $this->_logger->log('unable to get attributes form store-text "' . $storeText, Zend_Log::ERR);
                    continue;
                }

                for ($a = 0; $a < count($aMatches[0]); $a++) {
                    $key = trim($aMatches[1][$a]);
                    $value = $sText->htmlDecode(trim($aMatches[2][$a]));

                    switch ($key) {
                        case 'location_id':
                            $eStore->setStoreNumber($value);
                            break;
                        case 'latitude':
                            $eStore->setLatitude($value);
                            break;
                        case 'longitude':
                            $eStore->setLongitude($value);
                            break;
                        case 'fax':
                            $eStore->setFaxNormalized($value);
                            break;
                        case 'phone':
                            $eStore->setPhoneNormalized($value);
                            break;
                        case 'city':
                            $this->_logger->info('Adding ' . $value . ' to $cStores');
                            $eStore->setCity($value);
                            break;
                        case 'street':
                            $eStore->setStreetAndStreetNumber($this->cleanStreetField($value, $eStore));
                            break;
                        case 'store_photo':
                            if (strlen($value)) {
                                $eStore->setImage('http://www.pfennigpfeiffer.de/media/' . $value);
                            }
                            break;
                        case 'notes':
                            $storeHours = $value;
                            $storeHours = preg_replace('#<[^>]*ul[^>]*>#', '', $storeHours);
                            $storeHours = preg_replace('#<[^>]*span[^>]*>#', '', $storeHours);
                            $storeHours = preg_replace('#<\/li><li>#', ',', $storeHours);
                            $storeHours = preg_replace('#<[^>]*li[^>]*>#', '', $storeHours);
                            $eStore->setStoreHoursNormalized($storeHours);
                            break;
                    }
                    $distAr = array();
                    foreach ($distributionGroups as $disName => $disVals) {
                        if (in_array($sGeo->findShortRegionByZipCode($eStore->getZipcode()), $disVals)) {
                            $distAr[] = $disName;
                        }
                    }

                    $eStore->setDistribution(implode(',', $distAr));
                    $cStores->addElement($eStore);
                }
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    private function cleanStreetField(string $streetName, Marktjagd_Entity_Api_Store $eStore): string
    {
        if(preg_match('#VORERST GESCHLOSSEN#', $streetName)) {
            $eStore->setText('vorerst geschlossen');

            return str_replace('VORERST GESCHLOSSEN', '', $streetName);
        }

        if(preg_match('#TEL#', $streetName)) {
            return substr($streetName, 0, -19);
        }

        return $streetName;
    }
}
