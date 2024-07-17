<?php

/**
 * Store Crawler für Hertz (ID: 28599)
 */
class Crawler_Company_Hertz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://api.hertz.com/';
        $searchUrl = $baseUrl . 'rest/geography/city/country/DE?dialect=deDE';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aServices = array(
            'afterHoursPickup' => 'Anmietung des Fahrzeuges außerhalb der Stationsöffnungszeiten ist möglich',
            'afterHoursDropp' => 'Rückgabe des Fahrzeuges außerhalb der Stationsöffnungszeiten ist möglich',
            'gold_customer' => 'Gold Schalter',
            'child_seats' => 'Kindersitze, Babysitze',
            'hand_control' => 'Handbediengeräte',
            'trainLoc' => 'Bahnhofsstation',
            'heavy_trucks' => 'Transporter oder LKW Station',
            'wifi' => 'WiFi'
        );

        $sPage->open($searchUrl);
        $jCities = $sPage->getPage()->getResponseAsJson();

        foreach ($jCities->data->model as $singleJCity) {
            $sPage->open($baseUrl . 'rest/location/country/DE/city/' . urlencode($singleJCity->name) . '?dialect=deDE');
            $jStoresCity = $sPage->getPage()->getResponseAsJson();
            foreach ($jStoresCity->data->locations as $singleJStoresCity) {
                $aStoreNumbers[] = $singleJStoresCity->extendedOAGCode;
            }
        }
        array_unique($aStoreNumbers);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreNumbers as $singleStoreNumber) {
            $storeUrl = $baseUrl . 'rest/location/oag/' . $singleStoreNumber . '?dialect=deDE';
            $sPage->open($storeUrl);
            $jStore = $sPage->getPage()->getResponseAsJson()->data;
            
            $strServices = '';
            foreach ($aServices as $serviceKey => $serviceValue) {
                if ($jStore->{$serviceKey}) {
                    if (strlen($strServices)) {
                        $strServices .= ', ';
                    }
                    $strServices .= $serviceValue;
                }
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStoreNumber)
                    ->setCity($jStore->city)
                    ->setLatitude($jStore->latitude)
                    ->setLongitude($jStore->longitude)
                    ->setFax($sAddress->normalizePhoneNumber($jStore->fax))
                    ->setStoreHours($sTimes->generateMjOpenings($jStore->hours))
                    ->setPhone($sAddress->normalizePhoneNumber($jStore->phoneNumber))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $jStore->streetAddressLine1)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $jStore->streetAddressLine1)))
                    ->setZipcode(trim($jStore->zip))
                    ->setService($strServices);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
