<?php

/*
 * Store Crawler für Chevrolet (ID: 71452)
 */

class Crawler_Company_Chevrolet_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.chevrolet.de/';
        $searchUrl = $baseUrl . 'dealer-locator-ws-json/servlet/DE/de/meta/?query='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '&queryType=city&limit=10000';
        $storesUrl = $baseUrl . 'dealer-locator-ws-json/servlet/DE/de/detail/?';
        $sPage = new Marktjagd_Service_Input_Page(true);
        $sAddress = new Marktjagd_Service_Text_Address();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zip', 50);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $this->_logger->info('opening ' . $singleUrl);
            $sPage->open($singleUrl);
            $storeNumbers = $sPage->getPage()->getResponseAsJson();

            $aStoreIds = array();
            foreach ($storeNumbers->partners as $singlePartner) {
                $aStoreIds[] = 'ids=' . $singlePartner->id;
            }

            $strIds = implode('&', $aStoreIds);

            $jStores = array('1');
            $count = 0;
            while (count($jStores) == 1) {
                usleep(5000000);
                $this->_logger->info('opening ' . $storesUrl . $strIds);
                $sPage->open($storesUrl . $strIds);
                $jStores = $sPage->getPage()->getResponseAsJson();
                if ($count++ == 20) {
                    throw new Exception($companyId . ': unable to get store details page.');
                }
            }

            foreach ($jStores as $singleJStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                foreach ($singleJStore->communication as $singleCommData) {
                    if (preg_match('#tel#', $singleCommData->type) && preg_match('#fax#', $singleCommData->subType)) {
                        $eStore->setFax($sAddress->normalizePhoneNumber($singleCommData->data->areaCode . $singleCommData->data->subscriberNumber));
                        continue;
                    }
                    if (preg_match('#tel#', $singleCommData->type) && preg_match('#voice#', $singleCommData->subType)) {
                        $eStore->setPhone($sAddress->normalizePhoneNumber($singleCommData->data->areaCode . $singleCommData->data->subscriberNumber));
                        continue;
                    }
                    if (preg_match('#email#', $singleCommData->type)) {
                        $eStore->setEmail($singleCommData->data->address);
                        continue;
                    }
                }

                $eStore->setStoreNumber($singleJStore->id)
                        ->setSubtitle($singleJStore->identification[0]->data->familyNames)
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->deliveryAddresses[0]->data->street)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->deliveryAddresses[0]->data->street)))
                        ->setCity($singleJStore->deliveryAddresses[0]->data->locality)
                        ->setZipcode($singleJStore->deliveryAddresses[0]->data->postalCode)
                        ->setLongitude($singleJStore->geographical[0]->data->longitude)
                        ->setLatitude($singleJStore->geographical[0]->data->latitude);

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
