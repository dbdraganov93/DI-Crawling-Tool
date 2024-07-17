<?php

/**
 * Store Crawler für MeaApotheke (ID: 71112)
 */
class Crawler_Company_MeaApotheke_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.meineapotheke.de/';
        $searchUrl = $baseUrl . 'aposuche/search?query=01309&rad=1000';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage->open($searchUrl);

        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json[0]->data as $jsonStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreet($jsonStore->street);
            $eStore->setStreetNumber($jsonStore->houseNumber);
            $eStore->setZipcode($jsonStore->zipCode);
            $eStore->setCity($jsonStore->city);
            $eStore->setTitle($jsonStore->nameApo);
            $eStore->setPhoneNormalized($jsonStore->adrTel);
            $eStore->setFaxNormalized($jsonStore->adrFax);
            $eStore->setLatitude($jsonStore->latitude);
            $eStore->setLongitude($jsonStore->longitude);

            $sOpening = 'Mo ' . $jsonStore->montag . ', '
                . 'Di ' . $jsonStore->dienstag . ', '
                . 'Mi ' . $jsonStore->mittwoch . ', '
                . 'Do ' . $jsonStore->donnerstag . ', '
                . 'Fr ' . $jsonStore->freitag . ', '
                . 'Sa ' . $jsonStore->samstag;

            $eStore->setStoreHoursNormalized($sOpening);

            if ($jsonStore->logo) {
                $eStore->setLogo($jsonStore->logo);
            }

            $eStore->setWebsite($jsonStore->homepage);
            $eStore->setText($jsonStore->anrede . ' ' . $jsonStore->nameVor . ' ' . $jsonStore->nameNach);
            $eStore->setEmail($jsonStore->email);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}