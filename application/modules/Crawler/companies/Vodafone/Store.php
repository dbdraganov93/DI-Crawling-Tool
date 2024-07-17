<?php

/**
 * Store Crawler für Vodafone (ID: 28895)
 */
class Crawler_Company_Vodafone_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.vodafone.de/';
        $searchUrl = $baseUrl . 'api/geoloc/locator?json=true&lat=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT .
            '&lon=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON .
            '&s=&n=&c=&p=&r=15000&m=20&pg=3';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $serviceMap = array(
            "arcor" => "Arcor",
            "callYa" => "CallYa",
            "d2Center" => "D2 Center",
            "d2Shop" => "D2 Shop",
            "businessPoint" => "Business Point",
            "servicePoint" => "Service",
            "partnerAgentur" => "Partner Agentur"
        );

        $aLinks = $sGen->generateUrl($searchUrl, 'coords', 0.2);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aLinks as $idx => $singleLink) {
            usleep(rand(100000, 1000000));
            $this->_logger->info('open link ' . $idx . ' of ' . count($aLinks));
            try {
                $sPage->open($singleLink);
            }
            catch (Zend_Http_Client_Exception $e) {
                try {
                    sleep(2);
                    $sPage->open($singleLink);
                }
                catch (Zend_Http_Client_Exception $e) {
                    $this->_logger->warn('Exception during http call, skipping zipcode');
                    continue;
                }
            }
            $jStores = $sPage->getPage()->getResponseAsJson();

            if (!$jStores->addresses) {
                $this->_logger->info($companyId . ': no content.');
                continue;
            }

            foreach ($jStores->addresses as $address) {
                if ($address->country != 'Deutschland') {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $services = array();
                foreach ($serviceMap as $serviceKey => $serviceText) {
                    if ($address->$serviceKey) {
                        $services[] = $serviceText;
                    }
                }

                $eStore->setStoreNumber((string)$address->id)
                    ->setSubtitle($address->name1)
                    ->setLongitude((string)$address->geoLocation->longitudeDeg)
                    ->setLatitude((string)$address->geoLocation->latitudeDeg)
                    ->setStreet($address->street)
                    ->setStreetNumber($address->houseNumber)
                    ->setZipcode($address->postalCode)
                    ->setCity($address->city)
                    ->setFaxNormalized($address->faxNumber)
                    ->setPhoneNormalized($address->phoneNumber)
                    ->setEmail($address->name2)
                    ->setService(implode(',', $services))
                    ->setStoreHoursNormalized($address->openingHoursMoFr . ',' . $address->openingHoursSa);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
