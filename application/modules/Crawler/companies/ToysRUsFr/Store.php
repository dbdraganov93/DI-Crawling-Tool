<?php

/**
 * Store Crawler für Toys"R"Us FR (ID: 72364)
 */
class Crawler_Company_ToysRUsFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.toysrus.fr/';
        $searchUrl = $baseUrl . 'isvc/trufr-sl/search?substore=tru&locale=fr_FR'
            . '&storeTag=TRU_BRU&storeTag=TRU_WITH_ORCHESTRA&storeTag=TRU_EXPRESS&height=655&width=727'
            . '&radius=999&latitude=47.3237985&longitude=5.03861459999996';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = preg_replace('#\\\'#', '\\\'', $sPage->getPage()->getResponseBody());

        $jStores = json_decode($page);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->RESULTS as $jsonResult) {
            $jsonStore = $jsonResult->store;

            if (!preg_match('#\s*(\d{5})$#', $jsonStore->postalCode, $zipcodeMatch)
                || (preg_match('#\s*(\d{5,})$#', $jsonStore->postalCode, $zipcodeMatch) && strlen($zipcodeMatch[1]) != 5)) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($jsonStore->id)
                ->setStreetAndStreetNumber($jsonStore->address1, 'fr')
                ->setZipcode($jsonStore->postalCode)
                ->setCity(ucwords(strtolower($jsonStore->city)))
                ->setLatitude($jsonStore->latitude)
                ->setLongitude($jsonStore->longitude)
                ->setPhoneNormalized($jsonStore->phoneNumber)
                ->setFaxNormalized($jsonStore->faxNumber);

            if ($jsonStore->address2) {
                $eStore->setSubtitle($jsonStore->address2);
            }

            if ($jsonStore->emailAddress) {
                $eStore->setEmail($jsonStore->emailAddress);
            }

            $sOpening = 'Mo ' . $jsonStore->hours->openingTimeMon . '-' . $jsonStore->hours->closingTimeMon
                . 'Di ' . $jsonStore->hours->openingTimeTue . '-' . $jsonStore->hours->closingTimeTue
                . 'Mi ' . $jsonStore->hours->openingTimeWed . '-' . $jsonStore->hours->closingTimeWed
                . 'Do ' . $jsonStore->hours->openingTimeThu . '-' . $jsonStore->hours->closingTimeThu
                . 'Fr ' . $jsonStore->hours->openingTimeFri . '-' . $jsonStore->hours->closingTimeFri
                . 'Sa ' . $jsonStore->hours->openingTimeSat . '-' . $jsonStore->hours->closingTimeSat;

            $eStore->setStoreHoursNormalized($sOpening);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}