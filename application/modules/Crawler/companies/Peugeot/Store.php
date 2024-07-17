<?php

/*
 * Store Crawler für Peugeot (ID: 68790)
 */

class Crawler_Company_Peugeot_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.peugeot.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect(68846);
        $localPath = $sFtp->downloadFtpToCompanyDir('PSAR_Liste_NL-Offnungszeiten.xls', $companyId);
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($localPath, true);
        $aLines = $worksheet->getElement(0)->getData();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStoresAdditionalInfos = new Marktjagd_Collection_Api_Store();
        foreach ($aLines as $aLine) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreetAndStreetNumber($aLine['Adresszeile 1']);
            $eStore->setZipcode($aLine['Postleitzahl']);
            $eStore->setCity($aLine['Ort']);
            $eStore->setPhoneNormalized($aLine['Primäre Telefonnummer']);
            $eStore->setWebsite($aLine['Webseite']);
            $sOpening = 'Mo ' . $aLine["Öffnungszeiten (montags)"] . ', '
                . 'Di ' . $aLine["Öffnungszeiten (dienstags)"] . ', '
                . 'Mi ' . $aLine["Öffnungszeiten (mittwochs)"] . ', '
                . 'Do ' . $aLine["Öffnungszeiten (donnerstags)"] . ', '
                . 'Fr ' . $aLine["Öffnungszeiten (freitags)"] . ', '
                . 'Sa ' . $aLine["Öffnungszeiten (samstags)"];
            $sOpening = $sTimes->convertAmPmTo24Hours($sOpening);
            $eStore->setStoreHoursNormalized($sOpening);
            $cStoresAdditionalInfos->addElement($eStore);
        }

        $sGenerator = new Marktjagd_Service_Generator_Url();
        $searchUrl = $baseUrl
            . 'api/search-pointofsale/de/24/DE/1/1/300/50/0/0/0?departure='
            . $sGenerator::$_PLACEHOLDER_LAT . '%2C'
            . $sGenerator::$_PLACEHOLDER_LON;

        $aUrls = $sGenerator->generateUrl($searchUrl, 'coords', 0.3);

        $cStores = new Marktjagd_Collection_Api_Store();
        $aIds = array();
        foreach ($aUrls as $key => $url) {
            $this->_logger->log('Crawle Url ' . ($key+1) . ' von ' . count($aUrls), Zend_Log::INFO);
            $sPage->open($url);
            $json = $sPage->getPage()->getResponseAsJson();
            foreach ($json->listDealer as $store) {
                if (in_array($store->id, $aIds)) {
                    continue;
                }
                $aIds[] = $store->id;

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber($store->id)
                        ->setTitle($store->name)
                        ->setLatitude($store->lat)
                        ->setLongitude($store->lng)
                        ->setWebsite($store->contact->website)
                        ->setStreetAndStreetNumber($store->adress->street)
                        ->setZipcodeAndCity($store->adress->city)
                        ->setPhoneNormalized($store->contact->tel)
                        ->setFaxNormalized($store->contact->fax)
                        ->setEmail($store->contact->mail)
                        ->setWebsite($store->contact->website)
                        ->setStoreHoursNormalized($store->schedules);

                $cStores->addElement($eStore, true);
            }
        }

        $sCompare = new Marktjagd_Service_Compare_Collection_Store();
        $cStoresUpdated = $sCompare->updateStores($cStores, $cStoresAdditionalInfos);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStoresUpdated);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
