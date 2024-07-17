<?php

/* 
 * Store Crawler für Avia (ID: 67199)
 */

class Crawler_Company_Avia_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.avia.de/';
        $searchUrl = $baseUrl . '?eID=segooglemapsAjaxHandler&param[func]=getAllLocationData';
        $cStores = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage()->setAlwaysHtmlDecode(false);
        $sPage->setPage($oPage);
        $sPage->open($searchUrl);
        $aStores = $sPage->getPage()->getResponseAsJson();

        foreach ($aStores as $singleShop) {
            $eStore = new Marktjagd_Entity_Api_Store();

            if ($singleShop->facilityData->addressData->country != 'Deutschland') {
                continue;
            }

            $eStore->setStoreNumber($singleShop->facilityData->facilityID);
            $eStore->setSubtitle($singleShop->facilityData->facilityTitle);
            $eStore->setCity($singleShop->facilityData->addressData->city);
            $eStore->setZipcode($singleShop->facilityData->addressData->zip);
            $eStore->setStreetAndStreetNumber($singleShop->facilityData->addressData->streetAddress);
            $eStore->setEmail($singleShop->facilityData->contactData->emailAddress);
            $eStore->setPhoneNormalized($singleShop->facilityData->contactData->phoneNumber);
            $eStore->setFaxNormalized($singleShop->facilityData->contactData->faxNumber);
            $eStore->setWebsite($singleShop->facilityData->contactData->websiteUrl);
            $eStore->setLatitude($singleShop->facilityData->geoData->lat);
            $eStore->setLongitude($singleShop->facilityData->geoData->lng);
            $times = $this->_readData($singleShop->facilityData->optionalData->openingHours);

            // 00:00-00:00 in 00:00-24:00 ändern
            $times = preg_replace('#\-[0]{2}\:#','-24:',$times);
            if ($singleShop->facilityData->facilityID == '3187') {
                $eStore->setStoreHoursNotes($times);
            }
            else {
                $eStore->setStoreHoursNormalized($times);
            }

            $eStore->setPayment($this->_readData($singleShop->facilityData->optionalData->paymentMethods));

            // Komma zur Abtrennung von Tank- und Bezahlkarten
            if (strlen($singleShop->facilityData->optionalData->fuelCards[0])
                && strlen($singleShop->facilityData->optionalData->paymentMethods[0])
            ) {
                $eStore->setPayment($eStore->getPayment() . ', ');
            }

            $eStore->setPayment($eStore->getPayment() . $this->_readData($singleShop->facilityData->optionalData->fuelCards));

            if (strlen($singleShop->facilityData->optionalData->services[0])) {
                $eStore->setText(
                    'Unser Service für Sie:<br>'
                        . $this->_readData($singleShop->facilityData->optionalData->services));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param array $readArray
     * @return string
     */
    protected function _readData($readArray) {
        $sReturn = '';
        $amount = 0;
        foreach ($readArray as $sTime) {
            if (strlen(trim($sTime))) {
                $sReturn .= $sTime;
                if ($amount < count($readArray) - 1) {
                    $sReturn .= ', ';
                }
            }
            $amount++;
        }
        return $sReturn;
    }
}