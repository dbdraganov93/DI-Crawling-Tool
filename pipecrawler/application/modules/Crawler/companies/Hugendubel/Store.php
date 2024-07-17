<?php

/**
 * Store Crawler für Hugendubel (ID: 29082)
 */
class Crawler_Company_Hugendubel_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();
        $url = 'http://www.hugendubel.de/ws/affiliate/getbranchesaround?lat=51.0491316&lon=13.78742109999996&dist=1000';
        $sPage->open($url);
        $json = $sPage->getPage()->getResponseAsJson();
        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($json->result as $jStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setEmail($jStore->EMailAddress);
            $eStore->setCity($jStore->city);
            $eStore->setStreetNumber($jStore->houseNumber);
            $eStore->setLatitude($jStore->latitude);
            $eStore->setLongitude($jStore->longitude);
            $eStore->setSubtitle($jStore->name);
            $eStore->setStoreHoursNormalized(strip_tags($jStore->openingHours1));
            $eStore->setStreet($jStore->street);
            $eStore->setPhoneNormalized($jStore->telefax);
            $eStore->setFaxNormalized($jStore->telephone);
            $eStore->setZipcode($jStore->zipcode);

            if (strlen($jStore->specialOpeningHours)) {
                $eStore->setStoreHoursNotes($jStore->specialOpeningHours);
            }

            $cStore->addElement($eStore);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        return $this->_response->generateResponseByFileName($fileName);
    }

}