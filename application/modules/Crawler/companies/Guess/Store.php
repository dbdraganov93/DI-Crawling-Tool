<?php

/*
 * Store Crawler für Guess (ID: 71854)
 */

class Crawler_Company_Guess_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.guess.eu/';
        $searchUrl = $baseUrl . 'de/StoreLocator/GetStoreLocations';
        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array('StoreConceptType' => 'All');

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 6; $i < 15; $i += 0.2) {
            for ($j = 47.5; $j < 55; $j += 0.2) {
                $aParams['Longitude'] = $i;
                $aParams['Latitude'] = $j;

                $sPage->open($searchUrl, $aParams);
                $jStores = $sPage->getPage()->getResponseAsJson()->stores;

                foreach ($jStores as $singleJStore) {
                    if (!preg_match('#DE#', $singleJStore->CountryCode)) {
                        continue;
                    }

                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setStoreNumber($singleJStore->StoreNumber)
                            ->setStreetAndStreetNumber($singleJStore->AddressLine1)
                            ->setCity($singleJStore->City)
                            ->setZipcode($singleJStore->PostalCode)
                            ->setPhoneNormalized($singleJStore->PhoneNumber)
                            ->setStoreHoursNormalized($singleJStore->TimeOfOperation)
                            ->setLatitude($singleJStore->Latitude)
                            ->setLongitude($singleJStore->Longitude);

                    $cStores->addElement($eStore);
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
