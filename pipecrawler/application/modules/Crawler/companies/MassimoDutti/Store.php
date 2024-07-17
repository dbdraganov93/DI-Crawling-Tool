<?php

class Crawler_Company_MassimoDutti_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $generateUrls = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'https://www.massimodutti.com/webapp/wcs/stores/servlet/'
                . 'StoreLocatorResultPage?catalogId=30220004&langId=-3&'
                . 'orderShippingPage=0&storeId=34009454'
                . '&latitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                . '&longitude=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON;
        $aRequestLinks = $generateUrls->generateUrl($baseUrl);

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();

        foreach ($aRequestLinks as $sRequestLink) {
            if (!$sPage->open($sRequestLink)) {
                $logger->log('unable to get requested page for company with id ' . $companyId, Zend_Log::ERR);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();
            if (!strlen(preg_match('#\[\s*(.+?)\s*\]#', $page))) {
                continue;
            }
            $jStores = json_decode($page);
            foreach ($jStores as $jStore) {
                if ($jStore->country != 'DE') {
                    continue;
                }
                $eStore = new Marktjagd_Entity_Api_Store();

                $jStore->address = ucwords(strtolower(preg_replace('#\, #', ' ', $jStore->address)));

                $eStore->setStoreNumber($jStore->physicalStoreId)
                        ->setLatitude($jStore->latitude)
                        ->setLongitude($jStore->longitude)
                        ->setCity(ucwords(strtolower($jStore->city)))
                        ->setZipcode($jStore->postalCode)
                        ->setStreet($mjAddress->extractAddressPart('street', $jStore->address))
                        ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $jStore->address))
                        ->setPhone($mjAddress->normalizePhoneNumber($jStore->phone1));
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
